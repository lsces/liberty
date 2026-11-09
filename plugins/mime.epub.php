<?php

namespace Bitweaver\Liberty;

use Bitweaver\BitBase;

/**
 * Mime handler for epubs: the text of the book into liberty_content_data for search, the package file's description as the summary, and the
 * cover image as the thumbnails - the epub counterpart of mime.pdf.php. mime_epub_read() does the reading and is also used by the mimeextepub
 * handler (fisheyemedia) for epubs that stay where they are.
 *
 * @version		$Header$
 * @package		liberty
 * @subpackage	liberty_mime_handler
 **/

/**
 * setup
 */
global $gLibertySystem;

/**
 * This is the name of the plugin - max char length is 16
 */
define( 'PLUGIN_MIME_GUID_EPUB', 'mimeepub' );

$pluginParams = [
	'verify_function'     => 'mime_default_verify',
	'store_function'      => 'mime_epub_store',
	'update_function'     => 'mime_epub_update',
	'load_function'       => 'mime_epub_load',
	'download_function'   => 'mime_default_download',
	'expunge_function'    => 'mime_default_expunge',
	'help_function'       => 'mime_epub_help',
	'title'               => 'Epubs with covers and text',
	'description'         => 'Stores epubs with their text for search and their cover image as the thumbnail.',
	'view_tpl'            => 'bitpackage:liberty/mime/default/view.tpl',
	'attachment_tpl'      => 'bitpackage:liberty/mime/default/attachment.tpl',
	'plugin_type'         => MIME_PLUGIN,
	'auto_activate'       => true,
	'mimetypes'           => [
		'#application/epub\+zip#i',
	],
];
$gLibertySystem->registerPlugin( PLUGIN_MIME_GUID_EPUB, $pluginParams );

/** Text taken from an epub for the search index stops here (characters); the largest books are a few million. */
const MIME_EPUB_MAX_TEXT = 6000000;

/**
 * Read an epub where it is, without unpacking it to disk.
 *
 * @param string $pFile path of the epub
 * @return array{text:string,summary:?string,cover:?string} the text of every XHTML file (tags, style and script dropped, whitespace collapsed),
 *         the package file's dc:description cut to a summary (null when there is none), and the cover image's bytes (null when there is none:
 *         the manifest's cover-image, else the meta cover id, else an image item called cover)
 */
function mime_epub_read( string $pFile ): array {
	$out = [ 'text' => '', 'summary' => null, 'cover' => null ];
	$zip = new \ZipArchive();
	if( !is_file( $pFile ) || $zip->open( $pFile, \ZipArchive::RDONLY ) !== true ) {
		return $out;
	}
	$prev = libxml_use_internal_errors( true );
	$opfPath = '';
	$opf = null;
	if( ( $container = $zip->getFromName( 'META-INF/container.xml' ) ) !== false && ( $xml = simplexml_load_string( $container ) ) !== false ) {
		$opfPath = (string)( $xml->rootfiles->rootfile['full-path'] ?? '' );
		if( $opfPath !== '' && ( $opfXml = $zip->getFromName( $opfPath ) ) !== false ) {
			$opf = simplexml_load_string( $opfXml );
		}
	}

	if( $opf ) {
		$opf->registerXPathNamespace( 'dc', 'http://purl.org/dc/elements/1.1/' );
		$desc = $opf->xpath( '//dc:description' );
		if( $desc && trim( (string)$desc[0] ) !== '' && function_exists( '\Bitweaver\Liberty\mime_pdf_summary' ) ) {
			$out['summary'] = mime_pdf_summary( html_entity_decode( strip_tags( (string)$desc[0] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		}
	}

	$text = '';
	for( $i = 0; $i < $zip->numFiles && strlen( $text ) < MIME_EPUB_MAX_TEXT; $i++ ) {
		if( !preg_match( '/\.(x?html?)$/i', (string)$zip->getNameIndex( $i ) ) ) {
			continue;
		}
		$html = (string)$zip->getFromIndex( $i );
		$html = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html ) ?? $html;
		$plain = html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text .= preg_replace( '/\s+/u', ' ', $plain ).' ';
	}
	$out['text'] = trim( $text );

	$cover = '';
	if( $opf ) {
		$opf->registerXPathNamespace( 'o', 'http://www.idpf.org/2007/opf' );
		$items = [];
		foreach( $opf->xpath( '//o:manifest/o:item' ) ?: [] as $item ) {
			$items[(string)$item['id']] = [ 'href' => (string)$item['href'], 'type' => (string)$item['media-type'], 'props' => (string)$item['properties'] ];
		}
		foreach( $items as $it ) {
			if( str_contains( $it['props'], 'cover-image' ) ) {
				$cover = $it['href'];
				break;
			}
		}
		if( $cover === '' && ( $meta = $opf->xpath( '//o:metadata/o:meta[@name="cover"]' ) ) && isset( $items[(string)$meta[0]['content']] ) ) {
			$cover = $items[(string)$meta[0]['content']]['href'];
		}
		if( $cover === '' ) {
			foreach( $items as $id => $it ) {
				if( str_starts_with( $it['type'], 'image/' ) && preg_match( '/cover/i', $id.' '.$it['href'] ) ) {
					$cover = $it['href'];
					break;
				}
			}
		}
	}
	if( $cover !== '' ) {
		$blob = $zip->getFromName( ( dirname( $opfPath ) !== '.' ? dirname( $opfPath ).'/' : '' ).rawurldecode( $cover ) );
		$out['cover'] = $blob !== false ? $blob : null;
	}
	libxml_use_internal_errors( $prev );
	$zip->close();
	return $out;
}

/**
 * Make the thumbnail set of an attachment from a cover image: written as a jpeg beside the file, then through the same function the pdf and image
 * plugins use, and the jpeg removed.
 *
 * @param string $pCover the cover image's bytes
 * @param string $pDir absolute folder of the attachment's file, closing slash
 * @param int $pAttachmentId
 */
function mime_epub_thumbnails( string $pCover, string $pDir, int $pAttachmentId ): bool {
	if( $pCover === '' || !extension_loaded( 'imagick' ) ) {
		return false;
	}
	$thumb = rtrim( $pDir, '/' ).'/thumb.jpg';
	try {
		$im = new \Imagick();
		$im->readImageBlob( $pCover );
		$im->setImageFormat( 'jpeg' );
		$im->setImageCompressionQuality( 85 );
		$im->setImageBackgroundColor( 'white' );
		$im = $im->mergeImageLayers( \Imagick::LAYERMETHOD_FLATTEN );
		$im->thumbnailImage( 1024, 1024, true );
		$im->writeImage( $thumb );
		$im->clear();
	} catch( \Exception $e ) {
		return false;
	}
	if( !is_file( $thumb ) || filesize( $thumb ) === 0 ) {
		return false;
	}
	liberty_generate_thumbnails( [
		'attachment_id' => $pAttachmentId,
		'dest_branch'   => rtrim( str_replace( STORAGE_PKG_PATH, '', rtrim( $pDir, '/' ) ), '/' ).'/',
		'source_file'   => $thumb,
		'type'          => 'image/jpeg',
	] );
	array_map( 'unlink', glob( rtrim( $pDir, '/' ).'/thumb*.jpg' ) ?: [] );
	return true;
}

/**
 * Store an uploaded epub: the text, summary and cover are read from the upload before the file is moved into storage, the file and its text row
 * are stored by the default handler, and the thumbnails made from the cover beside the stored file.
 *
 * @param array $pStoreRow File data needed to store details in the database - sanitised and generated in the verify function
 * @return bool true on success, false on failure - $pStoreRow['errors'] will contain reason
 */
function mime_epub_store( &$pStoreRow ) {
	$pStoreRow['attachment_plugin_guid'] = PLUGIN_MIME_GUID_EPUB;
	$book = mime_epub_read( (string)( $pStoreRow['upload']['source_file'] ?? '' ) );
	if( $book['text'] !== '' ) {
		$pStoreRow['text'] = $book['text'];
		$pStoreRow['data'] = $book['summary'] ?? ( function_exists( '\Bitweaver\Liberty\mime_pdf_summary' ) ? mime_pdf_summary( $book['text'] ) : '' );
	}
	$ret = mime_default_store( $pStoreRow );
	if( $ret && $book['cover'] !== null && !empty( $pStoreRow['upload']['dest_branch'] ) ) {
		mime_epub_thumbnails( $book['cover'], STORAGE_PKG_PATH.$pStoreRow['upload']['dest_branch'], (int)$pStoreRow['attachment_id'] );
	}
	return $ret;
}

/**
 * A replaced epub: read again, as a new one is.
 */
function mime_epub_update( &$pStoreRow, $pParams = null ) {
	$pStoreRow['attachment_plugin_guid'] = PLUGIN_MIME_GUID_EPUB;
	if( !empty( $pStoreRow['upload']['source_file'] ) ) {
		$book = mime_epub_read( (string)$pStoreRow['upload']['source_file'] );
		if( $book['text'] !== '' ) {
			$pStoreRow['text'] = $book['text'];
			$pStoreRow['data'] = $book['summary'] ?? ( function_exists( '\Bitweaver\Liberty\mime_pdf_summary' ) ? mime_pdf_summary( $book['text'] ) : '' );
		}
	}
	$ret = mime_default_update( $pStoreRow );
	if( $ret && !empty( $book['cover'] ) && !empty( $pStoreRow['upload']['dest_branch'] ) ) {
		mime_epub_thumbnails( $book['cover'], STORAGE_PKG_PATH.$pStoreRow['upload']['dest_branch'], (int)$pStoreRow['attachment_id'] );
	}
	return $ret;
}

/**
 * Load file data from the database
 */
function mime_epub_load( &$pFileHash, &$pPrefs, $pParams = null ) {
	return mime_default_load( $pFileHash, $pPrefs );
}

function mime_epub_help() {
	return '';
}

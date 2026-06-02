<?php

namespace Bitweaver\Liberty;

use Bitweaver\BitBase;

/**
 * @version		$Header$
 *
 * @author		xing  <xing@synapse.plus.com>
 *				Reworked to remove swf and add text layer management
 * @version		$Revision: 1.3 $
 * created		Thursday May 08, 2008
 * @package		liberty
 * @subpackage	liberty_mime_handler
 **/

/**
 * setup
 */
global $gLibertySystem;

/**
 *  This is the name of the plugin - max char length is 16
 * As a naming convention, the liberty mime handler definition should start with:
 * PLUGIN_MIME_GUID_
 */
define( 'PLUGIN_MIME_GUID_PDF', 'mimepdf' );

$pluginParams = [
	// Set of functions and what they are called in this paricular plugin
	// Use the GUID as your namespace
	'verify_function'     => 'mime_default_verify',
	'store_function'      => 'mime_pdf_store',
	'update_function'     => 'mime_pdf_update',
	'load_function'       => 'mime_pdf_load',
	'download_function'   => 'mime_default_download',
	'expunge_function'    => 'mime_default_expunge',
	'help_function'       => 'mime_pdf_help',
	// Brief description of what the plugin does
	'title'               => 'Browsable PDFs with thumbnails',
	'description'         => 'View PDFs in browser online and provides thumbnail images for the galleries and links.',
	// Templates to display the files
	'view_tpl'            => 'bitpackage:liberty/mime/pdf/view.tpl',
	//'attachment_tpl'      => 'bitpackage:liberty/mime/image/attachment.tpl',
	// url to page with options for this plugin
	'plugin_settings_url' => LIBERTY_PKG_URL.'admin/plugins/mime_pdf.php',
	// This should be the same for all mime plugins
	'plugin_type'         => MIME_PLUGIN,
	// Set this to true if you want the plugin active right after installation
	'auto_activate'       => true,
	// Help page on bitweaver.org
	//'help_page'           => 'LibertyMime+Image+Plugin',
	// this should pick up all raw pdf files
	'mimetypes'           => [
		'#.*/pdf#i',
	],
];
$gLibertySystem->registerPlugin( PLUGIN_MIME_GUID_PDF, $pluginParams );

/**
 * Store the data in the database
 *
 * @param array $pStoreRow File data needed to store details in the database - sanitised and generated in the verify function
 * @access public
 * @return bool true on success, false on failure - $pStoreRow[errors] will contain reason
 */
function mime_pdf_store( &$pStoreRow ) {
	global $gBitSystem;

	// this will set the correct pluign guid, even if we let default handle the store process
	$pStoreRow['attachment_plugin_guid'] = PLUGIN_MIME_GUID_PDF;
	$pStoreRow['log'] = [];

	// We process the pdf to extract the text layer to include with the save.
	if( mime_pdf_text_extract( $pStoreRow ) ) {
		$ret = mime_default_store( $pStoreRow );
	} else {
		// if it all goes tits up, we'll know why
		$pStoreRow['errors'] = $pStoreRow['log'];
		$ret = false;
	}

	if( $gBitSystem->getConfig( 'pdf_thumbnails', 'y' ) == 'y' && (!empty( $pStoreRow['source_file'] ) || !empty( $pStoreRow['upload'] )) ) {
		if( !mime_pdf_thumbnail( $pStoreRow )) {
			// if it all goes tits up, we'll know why
			$pStoreRow['errors'] = $pStoreRow['log'];
			$ret = false;
		}
	}
	return $ret;
}

/**
 * mime_pdf_update update file information in the database if there were changes.
 *
 * @param array $pStoreRow File data needed to update details in the database
 * @access public
 * @return bool true on success, false on failure - $pStoreRow[errors] will contain reason
 */
function mime_pdf_update( &$pStoreRow, $pParams = null ) {
	global $gThumbSizes, $gBitSystem;

	$ret = true;

	// this will set the correct pluign guid, even if we let default handle the store process
	$pStoreRow['attachment_plugin_guid'] = PLUGIN_MIME_GUID_PDF;

	// We process the pdf to extract the text layer to include with the save.
	if( !empty( $pStoreRow['upload'] ) ) {
		if( mime_pdf_text_extract( $pStoreRow ) ) {
			$ret = mime_default_update( $pStoreRow );
		} else {
			// if it all goes tits up, we'll know why
			$pStoreRow['errors'] = $pStoreRow['log'];
			$ret = false;
		}
	}

	if( $gBitSystem->getConfig( 'pdf_thumbnails', 'y' ) == 'y' && (!empty( $pStoreRow['source_file'] ) || !empty( $pStoreRow['upload'] )) ) {
		if( !mime_pdf_thumbnail( $pStoreRow )) {
			// if it all goes tits up, we'll know why
			$pStoreRow['errors'] = $pStoreRow['log'];
			$ret = false;
		}
	}
	return $ret;
}

/**
 * Load file data from the database
 *
 * @param array $pFileHash Contains all file information
 * @param array $pPrefs Attachment preferences taken liberty_attachment_prefs
 * @param array $pParams Parameters for loading the plugin - e.g.: might contain values such as thumbnail size from the view page
 * @access public
 * @return bool true on success, false on failure - $pStoreRow[errors] will contain reason
 */
function mime_pdf_load( &$pFileHash, &$pPrefs, $pParams = null ) {
	global $gBitSystem;
	// don't load a mime image if we don't have an image for this file
	if( $ret = mime_default_load( $pFileHash, $pPrefs )) {
		if( !empty( $ret['source_file'] )) {
			$source_path = dirname( $ret['source_file'] ).'/';
		}
	}
	return $ret;
}

/**
 * mime_pdf_text_extract Download text layer from a PDF
 *		This will be saved as ['data'] and stored in the liberty base object
 *
 * @param array $pFileHash file details.
 * @var array $pFileHash[upload] should contain a complete hash from $_FILES
 * @access public
 * @return bool true on success, false on failure
 */
function mime_pdf_text_extract( &$pFileHash ) {
	global $gBitSystem;

	if( !empty( $pFileHash['upload'] ) && BitBase::verifyId( $pFileHash['attachment_id'] )) {
		// get file paths

		$stock_command = shell_exec( 'which pdftotext' ) ?? "/usr/bin/pdftotext";
		$pdftotext = trim( $gBitSystem->getConfig( 'pdftotext_path', $stock_command ) );

		if( is_executable( $pdftotext ) ) {
//			$source = STORAGE_PKG_PATH.$pFileHash['upload']['dest_branch'].$pFileHash['upload']['name'];
			$source = $pFileHash['upload']['source_file'];
			chmod($source, 0666);
			$pdftotextcommand = $pdftotext. " \"$source\" \"$source.txt\" ";
			shell_exec( $pdftotextcommand );
			$pFileHash['data'] = file_get_contents($source.'.txt');
			@unlink( $source.'.txt' );
		} else {
			$pFileHash['log']['pdftotext'] = "PDF to Text function not installed.";
		}
	}
	return empty( $pFileHash['log'] );
}

/**
 * mime_pdf_thumbnail Build a thumbnail set from the pdf
 *
 * @param array $pFileHash file details.
 * @var array $pFileHash[upload] should contain a complete hash from $_FILES
 * @access public
 * @return bool true on success, false on failure
 */
function mime_pdf_thumbnail( $pFileHash ) {
	global $gBitSystem;
	if( !extension_loaded( 'imagick' ) || $gBitSystem->getConfig( 'pdf_thumbnails', 'y' ) != 'y' ) {
		return empty( $pFileHash['log'] );
	}

	if( !empty( $pFileHash['upload'] ) ) {
		$source = STORAGE_PKG_PATH.$pFileHash['upload']['dest_branch'];
		$source .= $gBitSystem->isFeatureActive( 'liberty_jpeg_originals' ) ? 'original.jpg' : $pFileHash['upload']['name'];
	} else {
		$source = $pFileHash['source_file'];
	}
	$dest_branch = dirname( $source );
	$thumb_file  = "$dest_branch/thumb.jpg";

	try {
		$im = new \Imagick();
		$im->setResolution( 150, 150 );
		$im->readImage( $source . '[0]' );
		$im->setImageFormat( 'jpeg' );
		$im->setImageCompressionQuality( 85 );
		$im->setImageBackgroundColor( 'white' );
		$im = $im->mergeImageLayers( \Imagick::LAYERMETHOD_FLATTEN );
		$im->thumbnailImage( 1024, 1024, true );
		$im->writeImage( $thumb_file );
		$im->clear();
	} catch ( \Exception $e ) {
		return empty( $pFileHash['log'] );
	}

	if( is_file( $thumb_file ) && filesize( $thumb_file ) > 0 ) {
		$genHash = [
			'attachment_id' => $pFileHash['attachment_id'],
			'dest_branch'   => $pFileHash['upload']['dest_branch'] ?? rtrim( str_replace( STORAGE_PKG_PATH, '', $dest_branch ), '/' ).'/',
			'source_file'   => $thumb_file,
			'type'          => 'image/jpeg',
		];
		liberty_generate_thumbnails( $genHash );
		array_map( 'unlink', glob( "$dest_branch/thumb*.jpg" ) );
	}
	return empty( $pFileHash['log'] );
}

/**
 * mime_pdf_help
 *
 * @access public
 * @return string
 */
function mime_pdf_help() {
	return '';
}
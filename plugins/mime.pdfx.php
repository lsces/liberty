<?php

namespace Bitweaver\Liberty;
use Bitweaver\BitBase;
use Bitweaver\KernelTools;

/**
 * @version		$Header: /cvsroot/bitweaver/_bit_liberty/plugins/mime.pdf.php,v 1.2 2009/04/29 14:29:24 wjames5 Exp $
 *
 * @author		xing  <xing@synapse.plus.com>
 *				Reworked to remove swf and add text layer management
 * @version		$Revision: 1.2 $
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
define( 'PLUGIN_MIME_GUID_PDFX', 'mimepdfx' );

$pluginParams = [
	// Set of functions and what they are called in this paricular plugin
	// Use the GUID as your namespace
	'verify_function'     => 'mime_default_verify',
	'store_function'      => 'mime_pdfx_store',
	'update_function'     => 'mime_pdfx_update',
	'load_function'       => 'mime_pdfx_load',
	'download_function'   => 'mime_default_download',
	'expunge_function'    => 'mime_default_expunge',
	'help_function'       => 'mime_pdfx_help',
	// Brief description of what the plugin does
	'title'               => 'Browsable PDFs with thumbnails',
	'description'         => 'View PDFs in browser online and provides thumbnail images for the galleries and links.',
	// Templates to display the files
	'view_tpl'            => 'bitpackage:liberty/mime/pdfx/view.tpl',
	//'attachment_tpl'      => 'bitpackage:liberty/mime/image/attachment.tpl',
	// url to page with options for this plugin
	'plugin_settings_url' => LIBERTY_PKG_URL.'admin/plugins/mime_pdfx.php',
	// This should be the same for all mime plugins
	'plugin_type'         => MIME_PLUGIN,
	// Set this to true if you want the plugin active right after installation
	'auto_activate'       => false,
	// Help page on bitweaver.org
	//'help_page'           => 'LibertyMime+Image+Plugin',
	// this should pick up all raw pdf files
	'mimetypes'           => [
		'#.*/pdf#i',
	],
];
$gLibertySystem->registerPlugin( PLUGIN_MIME_GUID_PDFX, $pluginParams );

/**
 * Store the data in the database
 *
 * @param array $pStoreRow File data needed to store details in the database - sanitised and generated in the verify function
 * @access public
 * @return bool true on success, false on failure - $pStoreRow[errors] will contain reason
 */
function mime_pdfx_store( &$pStoreRow ) {
	global $gBitSystem;

	// this will set the correct pluign guid, even if we let default handle the store process
	$pStoreRow['attachment_plugin_guid'] = PLUGIN_MIME_GUID_PDFX;
	$pStoreRow['log'] = [];

	// We process the pdf to extract the text layer to include with the save.
	if( mime_pdfx_text_extract( $pStoreRow ) ) {
		$ret = mime_default_update( $pStoreRow );
	} else {
		// if it all goes tits up, we'll know why
		$pStoreRow['errors'] = $pStoreRow['log'];
		$ret = false;
	}

	if( $gBitSystem->getConfig( 'pdf_thumbnails', 'y' ) == 'y' ) {
		if( !mime_pdfx_thumbnail( $pStoreRow )) {
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
function mime_pdfx_update( &$pStoreRow, $pParams = null ) {
	global $gThumbSizes, $gBitSystem;

	$ret = true;

	// this will set the correct pluign guid, even if we let default handle the store process
	$pStoreRow['attachment_plugin_guid'] = PLUGIN_MIME_GUID_PDFX;

	// We process the pdf to extract the text layer to include with the save.
	if( !empty( $pStoreRow['upload'] ) ) {
		if( mime_pdfx_text_extract( $pStoreRow ) ) {
			$ret = mime_default_update( $pStoreRow );
		} else {
			// if it all goes tits up, we'll know why
			$pStoreRow['errors'] = $pStoreRow['log'];
			$ret = false;
		}
	}

	if( $gBitSystem->getConfig( 'pdf_thumbnails', 'y' ) == 'y' ) {
		if( !mime_pdfx_thumbnail( $pStoreRow )) {
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
function mime_pdfx_load( &$pFileHash, &$pPrefs, $pParams = null ) {
	global $gBitSystem;
	// don't load a mime image if we don't have an image for this file
	if( $ret = mime_default_load( $pFileHash, $pPrefs )) {
		if( !empty( $ret['source_file'] )) {
			$source_path = dirname( $ret['source_file'] ).'/';
//				$ret['media_url'] = KernelTools::storage_path_to_url( dirname( $ret['source_url'] ).'/pdf.swf' );
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
function mime_pdfx_text_extract( $pFileHash ) {
	global $gBitSystem;
return true;
	if( !empty( $pFileHash['upload'] ) && BitBase::verifyId( $pFileHash['attachment_id'] )) {
		// get file paths

		$stock_command = shell_exec( 'which pdftotext' ) ?? "/usr/bin/pdftotext";
		$pdftotext = trim( $gBitSystem->getConfig( 'pdftotext_path', $stock_command ) );

		if( is_executable( $pdftotext ) ) {
//			$source = STORAGE_PKG_PATH.$pFileHash['upload']['dest_branch'].$pFileHash['upload']['name'];
			$source = $pFileHash['upload']['source_file'];
			$pdftotextcommand = "\$pdftotext \"$source\" - 2>&1";
			$pFileHash['data'] = shell_exec( $pdftotextcommand );
		} else {
			$pFileHash['log']['pdftotext'] = "PDF to Text function not installed.";
		}
	}
	return empty( $pFileHash['log'] );
}

/**
 * mime_pdfx_thumbnail Build a thumbnail set from the pdf
 *
 * @param array $pFileHash file details.
 * @var array $pFileHash[upload] should contain a complete hash from $_FILES
 * @access public
 * @return bool true on success, false on failure
 */
function mime_pdfx_thumbnail( $pFileHash ) {
	global $gBitSystem;
		$stock_command = shell_exec( 'which convert' ) ?? "/usr/bin/convert";
		$mwconvert  = trim( $gBitSystem->getConfig( 'mwconvert_path', $stock_command ));

		if( is_executable( $mwconvert ) && $gBitSystem->getConfig( 'pdf_thumbnails', 'y' ) == 'y' ) {
			$source    = STORAGE_PKG_PATH.$pFileHash['upload']['dest_branch'];
			if ( $gBitSystem->isFeatureActive( 'liberty_jpeg_originals' ) ) {
				$source .= 'original.jpg';
			} else {
				$source .= $pFileHash['upload']['name'];
			}
			$dest_branch = dirname( $source );

			$thumb_file  = "$dest_branch/thumb.jpg";
			$mwccommand = "$mwconvert '$source' '$thumb_file' 2>&1";

			shell_exec( $mwccommand );
			if( is_file( $thumb_file ) && filesize( $thumb_file ) > 0 ) {
			}
			else if( is_file( "$dest_branch/thumb-0.jpg" ) ) {
				$thumb_file = "$dest_branch/thumb-0.jpg";
			}
			$genHash = [
				'attachment_id'	=> $pFileHash['attachment_id'],
				'dest_branch'		=> $pFileHash['upload']['dest_branch'],
				'source_file'		=> $thumb_file,
				'type'				=> 'image/jpeg',
				'thumbnail_sizes'	=> [ 'extra-large', 'large', 'medium', 'small', 'avatar', 'icon' ],
			];
			if( liberty_generate_thumbnails( $genHash )) {
//				$genHash['source_file'] = $genHash['icon_thumb_path'];
//				if( !$panoramaFunc( $genHash )) {
//					$pStoreRow['errors']['panorama'] = $genHash['error'];
//				}
			}
			$mask = "$dest_branch/thumb*.jpg";
   			array_map( "unlink", glob( $mask ) );
		}
	return empty( $pFileHash['log'] );
}

/**
 * mime_pdf_help
 *
 * @access public
 * @return string
 */
function mime_pdfx_help() {
	return '';
}
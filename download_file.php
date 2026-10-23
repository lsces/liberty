<?php
/**
 * @version      $Header$
 *
 * @author       xing  <xing@synapse.plus.com>
 * @package      treasury
 * @copyright    2003-2006 bitweaver
 * @license      LGPL {@link http://www.gnu.org/licenses/lgpl.html}
 **/

/**
 * Setup
 */
namespace Bitweaver\Liberty;

use Bitweaver\BitBase;
use Bitweaver\KernelTools;
require_once '../kernel/includes/setup_inc.php';

// fetch the attachment details
if( @!BitBase::verifyId( $_REQUEST['attachment_id'] ?? 0 ) || !( $attachment = LibertyMime::loadAttachment( $_REQUEST['attachment_id'], $_REQUEST ))) {
	$gBitSystem->fatalError( KernelTools::tra( "The Attachment ID given is not valid" ));
}

$gBitSmarty->assign( 'attachment', $attachment );

// first we need to check the permissions of the content the attachment belongs to since they inherit them
if( $gContent = LibertyBase::getLibertyObject( $attachment['content_id'] ) ) {
	$gContent->verifyViewPermission();
	$gBitSmarty->assign( 'gContent', $gContent );

	// ?size=<name> asks for one of the generated thumbnails kept beside the original (thumbs/<name>.jpg) instead of the
	// original file itself; the name is checked against the known sizes, and the original is served when there is no such file.
	if( !empty( $_REQUEST['size'] ) && in_array( $_REQUEST['size'], [ 'icon', 'avatar', 'small', 'medium', 'large', 'extra-large' ], true ) && !empty( $attachment['source_file'] )) {
		$thumbFile = dirname( $attachment['source_file'] ).'/thumbs/'.$_REQUEST['size'].'.jpg';
		if( is_file( $thumbFile )) {
			$attachment['source_file'] = $thumbFile;
			$attachment['mime_type'] = 'image/jpeg';
			$attachment['file_name'] = pathinfo( (string)( $attachment['file_name'] ?? 'image' ), PATHINFO_FILENAME ).'-'.$_REQUEST['size'].'.jpg';
			$attachment['last_modified'] = filemtime( $thumbFile );
		}
	}

	if( $download_function = $gLibertySystem->getPluginFunction( $attachment['attachment_plugin_guid'], 'download_function', 'mime' )) {
		if( $download_function( $attachment )) {
			LibertyMime::addDownloadHit( $attachment['attachment_id'] );
			die;
		}
			if( !empty( $attachment['errors'] )) {
				$msg = '';
				foreach( $attachment['errors'] as $error ) {
					$msg .= $error.'<br />';
				}
				$gBitSystem->fatalError( KernelTools::tra( $msg ));
			} else {
				$gBitSystem->fatalError( KernelTools::tra( 'There was an undetermined problem trying to prepare the file for download.' ));
			}

	} else {
		$gBitSystem->fatalError( KernelTools::tra( "No suitable download function found." ));
	}
} else {
	$gBitSystem->fatalError( KernelTools::tra( "Object not found." ), null, null, 404 );
}

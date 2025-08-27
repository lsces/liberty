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
use Bitweaver\Liberty\LibertyMime;
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

	if( $download_function = $gLibertySystem->getPluginFunction( $attachment['attachment_plugin_guid'], 'download_function', 'mime' )) {
		if( $download_function( $attachment )) {
			LibertyMime::addDownloadHit( $attachment['attachment_id'] );
			die;
		} else {
			if( !empty( $attachment['errors'] )) {
				$msg = '';
				foreach( $attachment['errors'] as $error ) {
					$msg .= $error.'<br />';
				}
				$gBitSystem->fatalError( KernelTools::tra( $msg ));
			} else {
				$gBitSystem->fatalError( KernelTools::tra( 'There was an undetermined problem trying to prepare the file for download.' ));
			}
		}
	} else {
		$gBitSystem->fatalError( KernelTools::tra( "No suitable download function found." ));
	}
} else {
	$gBitSystem->fatalError( KernelTools::tra( "Object not found." ), null, null, 404 );
}

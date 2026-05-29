<?php
/**
 * @package liberty
 * @subpackage functions
 */

namespace Bitweaver\Liberty;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty, $gBitUser, $gContent;

if( empty( $_REQUEST['content_id'] ) || !is_numeric( $_REQUEST['content_id'] ) ) {
	$gBitSystem->fatalError( 'No content ID specified.' );
}

$gContent = LibertyContent::getLibertyObject( (int)$_REQUEST['content_id'] );

if( !$gContent || !$gContent->isValid() ) {
	$gBitSystem->fatalError( 'Content not found.' );
}

if( !empty( $_REQUEST['xref_id'] ) ) {
	$gContent->loadXref( $_REQUEST['xref_id'] );
}

if( !empty( $_REQUEST['fCancel'] ) ) {
	header( 'Location: '.$gContent->getEditUrl() );
	die;
}

if( !empty( $_REQUEST['fSaveXref'] ) ) {
	$gContent->verifyUpdatePermission();
	if( $gContent->storeXref( $_REQUEST ) ) {
		header( 'Location: '.$gContent->getEditUrl() );
		die;
	}
	$xrefInfo = $_REQUEST;
	$xrefInfo['data'] = $_REQUEST['edit'] ?? '';
} elseif( isset( $_REQUEST['expunge'] ) ) {
	$gContent->verifyExpungePermission();
	if( $gContent->stepXref( $_REQUEST ) ) {
		header( 'Location: '.$gContent->getEditUrl() );
		die;
	}
	$xrefInfo = $gContent->mInfo['xref_store']['data'] ?? [];
} else {
	$gContent->verifyUpdatePermission();
	$xrefInfo = $gContent->mInfo['xref_store']['data'] ?? [];
}

$gBitSmarty->assign( 'gContent', $gContent );
$gBitSmarty->assign( 'xrefInfo', $xrefInfo );
$gBitSmarty->assign( 'errors', $gContent->mErrors );

$xrefTemplate = $gContent->mInfo['xref_store']['data']['template'] ?? 'text';
$gBitSystem->display( $gContent->getXrefEditTemplate( $xrefTemplate ), 'Edit Detail', [ 'display_mode' => 'edit' ] );

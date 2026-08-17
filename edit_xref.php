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
	if( isset( $_REQUEST['json_field'] ) && is_array( $_REQUEST['json_field'] ) ) {
		// json-list/json-text edit forms (edit_xref_json-list_item.tpl) submit one
		// field per JSON key rather than a single 'edit' textarea — reassemble into
		// the JSON string storeXref() actually saves into 'data'. Numeric strings cast
		// back to int/float first so a human edit doesn't quietly turn a field's JSON
		// type from number to string (every form input value arrives as a string).
		$jsonFields = array_map(
			fn( $v ) => is_numeric( $v ) ? $v + 0 : $v,
			$_REQUEST['json_field']
		);
		$_REQUEST['edit'] = json_encode( $jsonFields );
	}
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

$gContent->enrichXrefDisplay( $xrefInfo );

$gBitSmarty->assign( 'gContent', $gContent );
$gBitSmarty->assign( 'xrefInfo', $xrefInfo );
$gBitSmarty->assign( 'errors', $gContent->mErrors );

$xrefTemplate = $gContent->mInfo['xref_store']['data']['template'] ?? 'text';
$gBitSystem->display( $gContent->getXrefEditTemplate( $xrefTemplate ), 'Edit Detail', [ 'display_mode' => 'edit' ] );

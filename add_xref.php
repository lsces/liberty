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

$gContent->verifyUpdatePermission();

if( !empty( $_REQUEST['fCancel'] ) ) {
	header( 'Location: '.$gContent->getDisplayUrl() );
	die;
}

if( !empty( $_REQUEST['fAddXref'] ) ) {
	if( $gContent->storeXref( $_REQUEST ) ) {
		header( 'Location: '.$gContent->getDisplayUrl() );
		die;
	}
}

$group = (int)( $_REQUEST['group'] ?? 1 );
$xrefTypeList = $gContent->getXrefTypeList( $group );

$gBitSmarty->assign( 'gContent', $gContent );
$gBitSmarty->assign( 'group', $group );
$gBitSmarty->assign( 'xrefTypeList', $xrefTypeList );
$gBitSmarty->assign( 'errors', $gContent->mErrors );

$gBitSystem->display( 'bitpackage:liberty/add_xref.tpl', 'Add Detail', [ 'display_mode' => 'edit' ] );

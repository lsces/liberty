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
	header( 'Location: '.$gContent->getEditUrl() );
	die;
}

if( !empty( $_REQUEST['fAddXref'] ) ) {
	// Generic file-lifecycle hook, same shape as edit_xref.php's replaceXrefFile() call - the
	// content class decides whether this item has a real file to save at all (only fisheye's
	// 'image' item does). Unlike replaceXrefFile() (which needs an existing xkey_ext to replace
	// at), this is for a brand-new row: the content class picks the filename itself and hands it
	// back, which becomes this row's own xkey_ext.
	if( method_exists( $gContent, 'addImageXrefFile' ) ) {
		foreach( $_FILES as $file ) {
			if( !empty( $file['tmp_name'] ) && is_uploaded_file( $file['tmp_name'] ) ) {
				if( $relativePath = $gContent->addImageXrefFile( $file['tmp_name'], $file['name'] ) ) {
					$_REQUEST['xkey_ext'] = $relativePath;
				}
				break;
			}
		}
	}
	if( $gContent->storeXref( $_REQUEST ) ) {
		header( 'Location: '.$gContent->getEditUrl() );
		die;
	}
}

$group = (int)( $_REQUEST['group'] ?? 1 );
$xrefTypeList = $gContent->getXrefTypeList( $group );

// Same group-template override the view side already has (getXrefListTemplate(), e.g.
// fisheye's view_images_group.tpl) - a group whose only item needs something this generic form
// can't do (a real file upload, for fisheye's 'image' item) gets a real template override
// instead of rendering the broken generic one.
$groupTemplate = null;
foreach( $gContent->getXrefGroupList() as $groupRow ) {
	if( (int)$groupRow['sort_order'] === $group ) {
		$groupTemplate = $groupRow['template'] ?? null;
		break;
	}
}

$gBitSmarty->assign( 'gContent', $gContent );
$gBitSmarty->assign( 'group', $group );
$gBitSmarty->assign( 'xrefTypeList', $xrefTypeList );
$gBitSmarty->assign( 'errors', $gContent->mErrors );

$gBitSystem->display( $gContent->getXrefAddTemplate( $groupTemplate ), 'Add Detail', [ 'display_mode' => 'edit' ] );

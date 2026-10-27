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
	if( isset( $_REQUEST['json_field'] ) && is_array( $_REQUEST['json_field'] ) ) {
		// A json-list/json-text item's panel: same reassembly as edit_xref.php, for a new row.
		$itemDef = $gContent->getXrefItemDefinition( (string)( $_REQUEST['item'] ?? '' ) );
		LibertyXrefType::applyJsonFields( $_REQUEST, $itemDef['item_data'] ?? null, null );
	}
	if( $gContent->storeXref( $_REQUEST ) ) {
		// Optional hook, same shape as the file hooks above: lets a content class act on a row just added (a book with no
		// thumbnail making this first image its own) without this controller knowing what that means.
		if( method_exists( $gContent, 'xrefAdded' ) ) {
			$gContent->xrefAdded( (string)( $_REQUEST['item'] ?? $_REQUEST['Array_xref_type_list']['Array.item'] ?? '' ), (string)( $_REQUEST['xkey_ext'] ?? '' ) );
		}
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

// A failed add shows the typed values again in the panel of the item they were typed for.
if( !empty( $_REQUEST['fAddXref'] ) ) {
	$posted = $_REQUEST;
	$posted['data'] = $_REQUEST['edit'] ?? '';
	$gBitSmarty->assign( 'postedRow', $posted );
}

// Dates, when the content type offers them: a new row starts now, with no end.
$gBitSmarty->assign( 'xrefDates', $gContent->xrefShowsDates() );
$gBitSmarty->assign( 'xrefInfo', array_merge(
	[ 'ignore_start_date' => 'n', 'start_date' => date( 'Y-m-d\TH:i' ), 'ignore_end_date' => 'y', 'end_date' => '' ],
	array_intersect_key( $_REQUEST, array_flip( [ 'ignore_start_date', 'start_date', 'ignore_end_date', 'end_date' ] ) )
) );

$gBitSmarty->assign( 'gContent', $gContent );
$gBitSmarty->assign( 'group', $group );
$gBitSmarty->assign( 'xrefTypeList', $xrefTypeList );
$gBitSmarty->assign( 'errors', $gContent->mErrors );

$gBitSystem->display( $gContent->getXrefAddTemplate( $groupTemplate ), 'Add Detail', [ 'display_mode' => 'edit' ] );

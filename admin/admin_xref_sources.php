<?php
/**
 * Admin page for managing liberty_xref_item entries across all packages.
 * @package liberty
 */

use Bitweaver\Liberty\LibertyXrefType;
use Bitweaver\KernelTools;

require_once '../../kernel/includes/setup_inc.php';

$gBitSystem->verifyPermission( 'p_admin' );

// Persist selected content_type_guid in session
if ( isset( $_REQUEST['content_type_guid'] ) ) {
	$_SESSION['liberty_xref_admin_guid'] = $_REQUEST['content_type_guid'];
}
$activeGuid = $_SESSION['liberty_xref_admin_guid'] ?? '';

// Add a new source
if ( !empty( $_REQUEST['fAddSource'] ) ) {
	$source   = trim( $_REQUEST['source'] ?? '' );
	$guid     = trim( $_REQUEST['new_content_type_guid'] ?? $activeGuid );
	$xrefType = trim( $_REQUEST["x_group"] ?? '' );
	$title    = trim( $_REQUEST['cross_ref_title'] ?? '' );
	$template = trim( $_REQUEST['template'] ?? '' );
	$href     = trim( $_REQUEST['cross_ref_href'] ?? '' );
	$multiple    = (int)( $_REQUEST["multiple"] ?? 0 );
	$roleId   = (int)( $_REQUEST['role_id'] ?? 3 );
	if ( $source && $guid && $xrefType && $title ) {
		$gBitDb->query(
			"INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES (?,?,?,?,?,?,?,?,NULL)",
			[ $source, $guid, $xrefType, $title, $multiple, $roleId, $href, $template ]
		);
	}
}

// Delete a source (only if no xref records use it)
if ( !empty( $_REQUEST['fDeleteSource'] ) ) {
	$source = $_REQUEST['source'] ?? '';
	$guid   = $_REQUEST['del_content_type_guid'] ?? '';
	if ( $source && $guid ) {
		$count = $gBitDb->getOne(
			"SELECT COUNT(*) FROM `".BIT_DB_PREFIX."liberty_xref` WHERE `item` = ?",
			[ $source ]
		);
		if ( $count == 0 ) {
			$gBitDb->query(
				"DELETE FROM `".BIT_DB_PREFIX."liberty_xref_item` WHERE `item` = ? AND `content_type_guid` = ?",
				[ $source, $guid ]
			);
		} else {
			$gBitSmarty->assign( 'deleteError', "Cannot delete '$source' — $count xref record(s) still use it." );
		}
	}
}

$guidList  = LibertyXrefType::getContentTypeGuids();
$sources   = LibertyXrefType::getXrefTypeList( $activeGuid ? [ 'content_type_guid' => $activeGuid ] : [] );

// Build group list for the add-form dropdown, filtered to active guid
$groups = $activeGuid
	? LibertyXrefType::getGroupList( [ 'content_type_guid' => $activeGuid ] )
	: [];

$gBitSmarty->assign( 'activeGuid',   $activeGuid );
$gBitSmarty->assign( 'guidList',     $guidList );
$gBitSmarty->assign( 'xref_sources', $sources );
$gBitSmarty->assign( 'xref_groups',  $groups );

$gBitSystem->display( 'bitpackage:liberty/admin_xref_sources.tpl', KernelTools::tra( 'Xref Sources' ), [ 'display_mode' => 'admin' ] );

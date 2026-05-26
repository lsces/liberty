<?php
/**
 * Admin page for managing liberty_xref_group groups across all packages.
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

// Add a new group
if ( !empty( $_REQUEST['fAddGroup'] ) ) {
	$xrefType = trim( $_REQUEST["x_group"] ?? '' );
	$guid     = trim( $_REQUEST['new_content_type_guid'] ?? $activeGuid );
	$title    = trim( $_REQUEST['title'] ?? '' );
	$sortOrder = (int)( $_REQUEST['sort_order'] ?? 0 );
	$roleId    = (int)( $_REQUEST['role_id'] ?? 3 );
	if ( $xrefType && $guid && $title ) {
		$gBitDb->query(
			"INSERT INTO `".BIT_DB_PREFIX."liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`) VALUES (?,?,?,?,?,'')",
			[ $xrefType, $guid, $title, $sortOrder, $roleId ]
		);
	}
}

// Delete a group (only if no sources are attached)
if ( !empty( $_REQUEST['fDeleteGroup'] ) ) {
	$xrefType = $_REQUEST["x_group"] ?? '';
	$guid     = $_REQUEST['del_content_type_guid'] ?? '';
	if ( $xrefType && $guid ) {
		$count = $gBitDb->getOne(
			"SELECT COUNT(*) FROM `".BIT_DB_PREFIX."liberty_xref_item` WHERE `x_group` = ? AND `content_type_guid` = ?",
			[ $xrefType, $guid ]
		);
		if ( $count == 0 ) {
			$gBitDb->query(
				"DELETE FROM `".BIT_DB_PREFIX."liberty_xref_group` WHERE `x_group` = ? AND `content_type_guid` = ?",
				[ $xrefType, $guid ]
			);
		} else {
			$gBitSmarty->assign( 'deleteError', "Cannot delete '$xrefType' — $count source(s) still attached." );
		}
	}
}

$guidList  = LibertyXrefType::getContentTypeGuids();
$groups    = LibertyXrefType::getGroupList( $activeGuid ? [ 'content_type_guid' => $activeGuid ] : [] );

$gBitSmarty->assign( 'activeGuid', $activeGuid );
$gBitSmarty->assign( 'guidList',   $guidList );
$gBitSmarty->assign( 'xref_groups', $groups );

$gBitSystem->display( 'bitpackage:liberty/admin_xref_groups.tpl', KernelTools::tra( 'Xref Groups' ), [ 'display_mode' => 'admin' ] );

<?php
/**
 * @version  $Revision$
 * @package  liberty
 * @subpackage functions
 */

/**
 * bit setup
 */
namespace Smarty;

use Bitweaver\BitBase;
use Bitweaver\HttpStatusCodes;
use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';

$gBitSystem->verifyPermission( 'p_liberty_assign_content_perms' );

require_once LIBERTY_PKG_INCLUDE_PATH.'lookup_content_inc.php';

if( $gContent == null ) {
	$gBitSystem->fatalError('Could not find the requested content.', null, null, HttpStatusCodes::HTTP_GONE );
}

// Process the form
// send the user to the content page if he wants to
if( !empty( $_REQUEST['back'] )) {
	KernelTools::bit_redirect( $gContent->getDisplayUrl() );
}

// Update database if needed
if( !empty( $_REQUEST['action'] ) && BitBase::verifyId( $gContent->mContentId )) {
	if( $_REQUEST["action"] == 'expunge' ) {
		if( $gContent->expungeContentPermissions() ) {
			$feedback['success'] = KernelTools::tra( 'The content permissions were successfully removed.' );
		} else {
			$feedback['error'] = KernelTools::tra( 'The content permissions were not removed.' );
		}
	}

	if( BitBase::verifyId( $_REQUEST["role_id"] ?? 0 ) && !empty( $_REQUEST["perm"] )) {
		$gBitUser->verifyTicket();
		if( $_REQUEST["action"] == 'assign' ) {
			$gContent->storePermission( $_REQUEST["role_id"], $_REQUEST["perm"] );
		} elseif( $_REQUEST["action"] == 'negate' ) {
			$gContent->storePermission( $_REQUEST["role_id"], $_REQUEST["perm"], true );
		} elseif( $_REQUEST["action"] == 'remove' ) {
			$gContent->removePermission( $_REQUEST["role_id"], $_REQUEST["perm"] );
		}
	}
}

// Get a list of roles
$listHash = [ 'sort_mode' => 'role_id_asc', 'visible' => 1 ];
$contentPerms['roles'] = $gBitUser->getAllRoles( $listHash );

$contentPerms['assignable'] = !empty( $gContent->mType['handler_package'] )
	? $gBitUser->getRolePermissions( [ 'package' => $gContent->mType['handler_package'] ] )
	// this is a last resort and will dump all perms a user has
	: $gBitUser->mPerms;

// Now we have to get the individual object permissions if any
if( $contentPerms['assigned'] = $gContent->getContentPermissionsList() ) {
	// merge assigned permissions with rol permissions
	foreach( array_keys( $contentPerms['roles'] ) as $roleId ) {
		if( !empty( $contentPerms['assigned'][$roleId] )) {
			$contentPerms['roles'][$roleId]['perms'] = array_merge( $contentPerms['roles'][$roleId]['perms'], $contentPerms['assigned'][$roleId] );
		}
	}
}
$gBitSmarty->assign( 'contentPerms', $contentPerms );

// if we've called this page as part of an ajax update, we output the appropriate data
if( $gBitThemes->isAjaxRequest() ) {
	$size = count( $contentPerms['roles'] ) <= 10 ? 'large/' : 'small/';

	$gid = $_REQUEST['role_id'];
	$perm = $_REQUEST['perm'];

	// we're applying the same logic as in the template. if you fix / change anything here, please update the template as well.
	$biticon = [
		'ipackage' => 'icons',
		'iname'    => $size.'media-playback-stop',
		'iexplain' => '',
		'iforce'   => 'icon',
	];
	$action = 'assign';
	if( !empty( $contentPerms['roles'][$gid]['perms'][$perm] )) {
		$biticon['iname'] = $size.'dialog-ok';
		if( !empty( $contentPerms['assigned'][$gid][$perm] )) {
			$assigned = $contentPerms['assigned'][$gid][$perm];
			$biticon['iname'] = $size.'list-add';
			$action = 'negate';
		}
		if( !empty( $assigned['is_revoked'] )) {
			$biticon['iname'] = $size.'list-remove';
			$action = 'remove';
		}
	}

	$ret = '<a title="'.$contentPerms['roles'][$gid]['role_name']." :: ".$perm.'" '.
			'href="javascript:void(0);" onclick="BitAjax.updater('.
			"'{$perm}{$gid}', ".
			"'".LIBERTY_PKG_URL."content_role_permissions.php', ".
			"'action={$action}&amp;content_id={$gContent->mContentId}&amp;perm={$perm}&amp;role_id={$gid}'".
		')">'.$gBitweaverExtension->smarty_function_biticon( $biticon, $gBitSmarty ).'</a>';
	echo $ret;
	die;
}

$gBitSystem->display( 'bitpackage:liberty/content_role_permissions.tpl', KernelTools::tra( 'Content Permissions' ), [ 'display_mode' => 'display' ]);
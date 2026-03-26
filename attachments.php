<?php

/**
 * attachment_browser
 *
 * @author   spider <spider@steelsun.com>
 * @version  $Revision$
 * @package  liberty
 * @subpackage functions
 */

/**
 * bit setup
 */
use Bitweaver\KernelTools;
use Bitweaver\Liberty\LibertyMime;

require_once("../kernel/includes/setup_inc.php");

$gContent = new LibertyMime();

if( !$gBitUser->isRegistered() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'You need to be logged in to view this page.' ));
}

$feedback = [];
$listHash = &$_REQUEST;
if( $gBitUser->isAdmin() ) {
	if( !empty( $listHash['login'] ) && $listHash['login'] == 'all' ) {
		$listHash['user_id'] = null;
	} elseif( !empty( $listHash['login'] ) ) {
		if( $userInfo = $gBitUser->getUserInfo( [ 'login' => $listHash['login'] ] ) ) {
			$listHash['user_id'] = $userInfo['user_id'];
		} else {
			$feedback['error'] = KernelTools::tra( 'That user does not exist.' );
		}
	} else {
		$listHash['user_id'] = $gBitUser->mUserId;
	}
} else {
	$listHash['user_id'] = $gBitUser->mUserId;
}
$attachments = $gContent->getAttachmentList( $listHash );

$gBitSmarty->assign( 'listInfo', $listHash['listInfo'] );
$gBitSmarty->assign( 'attachments', $attachments );
$gBitSmarty->assign( 'feedback', $feedback );
$gBitSystem->display( 'bitpackage:liberty/attachments.tpl', KernelTools::tra( 'Attachments' ), [ 'display_mode' => 'display' ]);

<?php
/**
 * display_content_inc
 *
 * @author   spider <spider@steelsun.com>
 * @version  $Revision$
 * @package  liberty
 * @subpackage functions
 */

	global $gBitSmarty, $gBitSystem, $gContent;

	$gBitSmarty->assign( 'pageInfo', $gContent->mInfo );

	$gBitSystem->display( 'bitpackage:liberty/display_content.tpl' , null, [ 'display_mode' => 'display' ]);

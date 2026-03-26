<?php

/**
 * @version  $Header$
 * lookup_content_inc
 *
 * @author   spider <spider@steelsun.com>
 * @package  liberty
 * @subpackage functions
 */

/**
 * Required setup
 */
use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';

if( !empty( $_REQUEST['q'] )) {
	KernelTools::bit_redirect( $_REQUEST['q'] );
} else {
	$_REQUEST['error'] = KernelTools::tra( 'The redirect did not include a url.' );
	include( KERNEL_PKG_PATH . 'error.php' );
}
<?php

namespace Bitweaver\Liberty;

use Bitweaver\KernelTools;
use Bitweaver\Boards\BitBoardPost;
use Bitweaver\Users\RoleUser;

/**
 * @version  $Revision$
 * @package  liberty
 * @subpackage plugins_data
 */
// +----------------------------------------------------------------------+
// | Copyright (c) 2005, bitweaver.org
// +----------------------------------------------------------------------+
// | All Rights Reserved. See below for details and a complete list of authors.
// | Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
// |
// | For comments, please use phpdocu.sourceforge.net documentation standards!!!
// | -> see http://phpdocu.sourceforge.net/
// +----------------------------------------------------------------------+
// | Author (TikiWiki): Gustavo Muslera <gmuslera@users.sourceforge.net>
// | Reworked for Bitweaver (& Undoubtedly Screwed-Up)
// | by: wjames5
// | Reworked from: data.articles.php from wikiplugin_articles.php
// +----------------------------------------------------------------------+
// $Id$

/**
 * definitions
 */
global $gBitSystem, $gBitSmarty;
define( 'PLUGIN_GUID_DATAQUOTE', 'dataquote' );
global $gLibertySystem;
$pluginParams = [
	'tag'           => 'quote',
	'auto_activate' => false,
	'requires_pair' => true,
	'load_function' => '\data_quote',
	'help_function' => '\data_quote_help',
	'title'         => 'Quote',
	'help_page'     => 'DataPluginQuote',
	'description'   => KernelTools::tra( "This plugin allows content to be attributed to other authors and visually indicated." ),
	'syntax'        => "{quote format_guid= user= comment_id= }.. content ..{/quote}",
	'plugin_type'   => DATA_PLUGIN,
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATAQUOTE, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATAQUOTE );

// Help Routine
function data_quote_help() {
	$help ="<table class=\"data help\">
			<tr>
				<th>" . KernelTools::tra( "Key" ) . "</th>
				<th>" . KernelTools::tra( "Type" ) . "</th>
				<th>" . KernelTools::tra( "Comments" ) . "</th>
			</tr>
			<tr class=\"even\">
				<td>comment_id</td>
				<td>" . KernelTools::tra( "comment_id") . '<br />' . KernelTools::tra("(optional)") . "</td>
				<td>" . KernelTools::tra( "specify the comment_id of the comment being quoted") . "</td>
			</tr>
			<tr class=\"odd\">
				<td>user</td>
				<td>" . KernelTools::tra( "user") . '<br />' . KernelTools::tra("(optional)") . "</td>
				<td>" . KernelTools::tra( "specify the user whose comemnt is being quoted") . "</td>
			</tr>
			<tr class=\"even\">
				<td>format_guid</td>
				<td>" . KernelTools::tra( "string") . '<br />' . KernelTools::tra("(required)") . "</td>
				<td>" . KernelTools::tra( "Specify what renderer should be used to render the contents") . "</td>
			</tr>
		</table>".
	KernelTools::tra("Example: ") . '{quote format_guid="tikiwiki" comment_id="7" user="user"} ... {/quote}<br />';
	return $help;
}

// Executable Routine
function data_quote( $pData, $pParams ) {
	global $gLibertySystem, $gBitSmarty, $gBitSystem;

	if( empty( $pParams['format_guid'] )) {
		// default should be set - if not, we'll use tikiwiki - can't use PLUGIN_GUID_TIKIWIKI since it might not be defined.
		$pParams['format_guid'] = $gBitSystem->getConfig( 'default_format', 'tikiwiki' );
	}

	$rendererHash = [];
	$rendererHash['content_id'] = 0;
	$rendererHash['format_guid'] = $pParams['format_guid'];
	$rendererHash['data'] = trim( $pData );
	$formatGuid = $rendererHash['format_guid'];
	$ret = "";

	if( $func = $gLibertySystem->getPluginFunction( $formatGuid, 'load_function' ) ) {
//		$ret = $func( $rendererHash, $this );
	}

	$quote = [];
	$user = empty( $pParams['user'] ) ? null : $pParams['user'];

	if( !empty( $pParams['comment_id'] )) {

		$c = $gBitSystem->getActivePackage() == 'boards'
			? new BitBoardPost( preg_replace( '/[^0-9]/', '', $pParams['comment_id'] ) )
			: new LibertyComment( preg_replace( '/[^0-9]/', '', $pParams['comment_id'] ) );

		if( empty( $c->mInfo['title'] )) {
			$c->mInfo['title'] = "#".$c->mCommentId;
		}

		$quote['cite_url'] = $c->getDisplayUrl();
		$quote['title'] = $c->mInfo['title'];
		$quote['created'] = $c->mInfo['created'];

		if( empty( $user )) {
			$user = $c->mInfo['login'];
		}
	}

	$quote['login'] = $user;

	if( !empty( $user )) {
		$u = new RoleUser();
		$u->load( true, $user );

		$quote['user_url'] = $u->getDisplayUrl();
		$quote['user_display_name'] = $u->mInfo['display_name'];
	}

	$quote['ret'] = $ret;

	$gBitSmarty->assign( "quote", $quote );
	$repl = $gBitSmarty->fetch( "bitpackage:liberty/plugins/data_quote.tpl" );

	return $repl;
}

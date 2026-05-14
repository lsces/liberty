<?php

namespace Bitweaver\Liberty;

use Bitweaver\Wiki\BitPage;
use Bitweaver\KernelTools;

/**
 * assigned_modules
 *
 * @author     xing
 * @version    $Revision$
 * @package    liberty
 * @subpackage plugins_data
 * @copyright  Copyright (c) 2004, bitweaver.org
 */

/**
 * Setup Code
 */
define( 'PLUGIN_GUID_DATAalias', 'dataalias' );
global $gLibertySystem;
$pluginParams = [
	'tag' => 'alias',
	'auto_activate' => false,
	'requires_pair' => false,
	'load_function' => '\data_alias',
	'title' => 'Alias',
	'help_page' => 'DataPluginAlias',
	'description' => KernelTools::tra( "This plugin allows you to easily create an alias for a page." ),
	'help_function' => '\data_alias_help',
	'syntax' => "{alias page='title'}",
	'plugin_type' => DATA_PLUGIN,
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATAalias, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATAalias );

function data_alias_help() {
	$help =
		'<table class="data help">'
			.'<tr>'
				.'<th>' . KernelTools::tra( "Key" ) . '</th>'
				.'<th>' . KernelTools::tra( "Type" ) . '</th>'
				.'<th>' . KernelTools::tra( "Comments" ) . '</th>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>' . KernelTools::tra( "page" ) . '</td>'
				.'<td>' . KernelTools::tra( "string") . '<br />' . KernelTools::tra( "(required)" ) . '</td>'
				.'<td>' . KernelTools::tra( "The name of any other wiki page." ) .'</td>'
			.'</tr>'
		.'</table>'
		. KernelTools::tra( "Example: " ) . "{alias page='Welcome'}";
	return $help;
}

function data_alias( $pData, $pParams, $pCommonObject ) {
	$page = '';
	require_once WIKI_PKG_CLASS_PATH.'BitPage.php';

	foreach( $pParams as $key => $value ) {
		if( !empty( $value ) ) {
			switch( $key ) {
				case 'page':
					$page = $value;
					break;
			default:
				break;
			}
		}
	}
	return KernelTools::tra("This page is an alias for:").'&nbsp;'.BitPage::getPageLink($page, BitPage::pageExists($page));
}

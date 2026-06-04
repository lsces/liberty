<?php

namespace Bitweaver\Liberty;

use Bitweaver\KernelTools;

/**
 * @version  $Revision$
 * @package  liberty
 * @subpackage plugins_storage
 */

/**
 * definitions
 */
define( 'PLUGIN_GUID_DATAJSTABS', 'datajstabs' );
global $gLibertySystem;
$pluginParams = [
	'tag'           => 'jstabs',
	'title'         => 'Javascript Tabs',
	'description'   => KernelTools::tra( "Allow tabbing of content using a simple syntax." ),
	//'help_page'     => 'DataPluginJstabs',

	'auto_activate' => false,
	'requires_pair' => true,
	'syntax'        => '{jstabs}',
	'plugin_type'   => DATA_PLUGIN,

	// display icon in quicktags bar
	'booticon'       => '{biticon ipackage="icons" iname="folder" iexplain="Javascript Tabs"}',
	'taginsert'     => '{jstabs}text{/jstabs}',

	// functions
	'help_function' => '\data_jstabs_help',
	'load_function' => '\data_jstabs',
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATAJSTABS, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATAJSTABS );

function data_jstabs( $pData, $pParams, $pCommonObject ) {
	global $gBitSmarty;

	// collect all tabs
	$tabs = preg_split( "!\n---tab:\s*!i", $pData );
	$html = '';

	foreach( $tabs as $tab ) {
		$tab = trim( $tab );
		if( !empty( $tab )) {
			// first line of every tab is the title
			preg_match( "!(.*?)\n(.*)!s", $tab, $split );

			// we need a valid title and content to work with
			if( !empty( $split[1] ) && !empty( $split[2] )) {
				// prepare data for tabification and parsing
				$params['title'] = trim( $split[1] );

				$parseHash = $pCommonObject->mInfo;
				$parseHash['no_cache'] = true;
				$parseHash['data'] = $split[2];

				$html .= \Bitweaver\Liberty\smarty_block_jstab( $params, LibertyContent::parseDataHash( $parseHash, $pCommonObject ), $gBitSmarty, '' );
			}
		}
	}

	if( !empty( $html )) {
		return \Bitweaver\Liberty\smarty_block_jstabs( [], $html, $gBitSmarty, '' );
	}
		return ' ';

}

function data_jstabs_help() {
	return
		'<p class="data help">'.KernelTools::tra( "This plugin does not take any arguments but you need to use a particular syntax to add tabs. You need to insert something like: <strong>---tab: Title of the tab</strong> on a separate line. This will start a new tab with the title: <em>Title of the tab</em>." ).'</p>'
		. KernelTools::tra( "Example: ") . "<br />{jstabs}<br />---tab:First Tab<br />Some content<br />---tab:Second Tab<br />Some content in the second tab.<br />{/jstabs}";
}

<?php

namespace Bitweaver\Liberty;

use Bitweaver\KernelTools;

/**
 * @version  $Revision$
 * @package  liberty
 * @subpackage plugins_data
 */

/**
 * definitions
 */
define( 'PLUGIN_GUID_DATABITICON', 'databiticon' );
global $gLibertySystem;
$pluginParams = [
	'tag'           => 'biticon',
	'auto_activate' => true,
	'requires_pair' => false,
	'load_function' => '\data_biticon',
	'title'         => 'bitweaver Icon',
	'help_page'     => 'DataPluginBiticon',
	'description'   => KernelTools::tra( "Display any bitweaver icon" ),
	'help_function' => '\data_biticon_help',
	'syntax'        => '{biticon ipackage= iname= iexplain=}',
	'plugin_type'   => DATA_PLUGIN,
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATABITICON, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATABITICON );

/**
 * data_biticon_help 
 * 
 * @access public
 * @return string
 */
function data_biticon_help() {
	$help =
		'<table class="data help">'
			.'<tr>'
				.'<th>' . KernelTools::tra( "Key" ) . '</th>'
				.'<th>' . KernelTools::tra( "Type" ) . '</th>'
				.'<th>' . KernelTools::tra( "Comments" ) . '</th>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>ipackage</td>'
				.'<td>' . KernelTools::tra( "key-words") . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "Package the icon is taken from. The icon style icons take the value 'icons'.") . '</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>iname</td>'
				.'<td>' . KernelTools::tra( "key-words") . '<br />' . KernelTools::tra("(required)") . '</td>'
				.'<td>' . KernelTools::tra( "Name of the icon to be displayed" ) . '</td>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>ixplain</td>'
				.'<td>' . KernelTools::tra( "string" ) . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "Explanation of the icon - visible when hovering over the icon.").'</td>'
			.'</tr>'
		.'</table>'
		. KernelTools::tra( "Example: " ) . '{biticon ipackage="icons" iname="large/accessories-text-editor" iexplain="edit"}';
	return $help;
}

function data_biticon( $pData, $pParams ) {
	global $gBitSmarty;
	require_once LIBERTY_PKG_INCLUDE_PATH.'liberty_lib.php';
	$ret = KernelTools::tra( 'Please provide an icon name as iname parameter. You can <a href="'.THEMES_PKG_URL.'icon_browser.php">select icons here</a>.' );

	if( !empty( $pParams['iname'] )) {

		// sanitise biticon parameters before they are passed to the function
		$biticon['iname']    = $pParams['iname'];
		$biticon['ipackage'] = !empty( $pParams['ipackage'] ) ? $pParams['ipackage'] : 'icons';
		$biticon['iexplain'] = !empty( $pParams['iexplain'] ) ? $pParams['iexplain'] : 'icon';
		$biticon['ipath']    = !empty( $pParams['ipath'] )    ? $pParams['ipath']    : '';
		$ret = \Bitweaver\Liberty\smarty_function_biticon( $biticon, $gBitSmarty );
		$wrapper = \Bitweaver\Liberty\liberty_plugins_wrapper_style( $pParams );
		if( !empty( $wrapper['style'] )) {
			$ret ='<'.$wrapper['wrapper'].' class="'.( !empty( $wrapper['class'] ) ? $wrapper['class'] : "biticon-plugin" ).'" style="'.$wrapper['style'].'">'.$ret.'</'.$wrapper['wrapper'].'>';
		}
	}
	return $ret;
}
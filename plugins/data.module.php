<?php

namespace Bitweaver\Liberty;

use Bitweaver\KernelTools;

/**
 * @version  $Revision$
 * @package  liberty
 * @subpackage plugins_data
 */
// +----------------------------------------------------------------------+
// | Copyright (c) 2004, bitweaver.org
// +----------------------------------------------------------------------+
// | All Rights Reserved. See below for details and a complete list of authors.
// | Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
// |
// | For comments, please use phpdocu.sourceforge.net documentation standards!!!
// | -> see http://phpdocu.sourceforge.net/
// +----------------------------------------------------------------------+
// | Author (TikiWiki): Mose <mose@users.sourceforge.net>
// | Reworked for Bitweaver  by: Christian Fowler <spiderr@users.sourceforge.net>
// +----------------------------------------------------------------------+
// $Id$

/**
 * definitions
 */
define( 'PLUGIN_GUID_DATAMODULE', 'datamodule' );

global $gLibertySystem;
$pluginParams = [
	'tag'           => 'MODULE',
	'auto_activate' => false,
	'requires_pair' => false,
	'load_function' => '\data_datamodule',
	'title'         => 'Module',
	'help_page'     => 'DataPluginModule',
	'description'   => KernelTools::tra("Display a module block in content"),
	'help_function' => '\datamodule_help',
	'syntax'        => "{module module= align='right'}",
	'plugin_type'   => DATA_PLUGIN,
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATAMODULE, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATAMODULE );

function datamodule_help() {
	$help =
		'<table class="data help">'
			.'<tr>'
				.'<th>' . KernelTools::tra( "Key" ) . '</th>'
				.'<th>' . KernelTools::tra( "Type" ) . '</th>'
				.'<th>' . KernelTools::tra( "Comments" ) . '</th>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>module</td>'
				.'<td>' . KernelTools::tra( "string" ) . '<br />' . KernelTools::tra( "(required)" ) . '</td>'
				.'<td>' . KernelTools::tra( "Name of module you want to display.")
			.'</tr>'
			.'<tr class="even">'
				.'<td>package</td>'
				.'<td>' . KernelTools::tra( "string" ) . '<br />' . KernelTools::tra( "(required)" ) . '</td>'
				.'<td>' . KernelTools::tra( "Package the module is part of.")
			.'</tr>'
			.'<tr class="even">'
				.'<td>rows</td>'
				.'<td>' . KernelTools::tra( "numeric" ) . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "Number of rows you wish to show.")
			.'</tr>'
			.'<tr class="odd">'
				.'<td colspan="3">' . KernelTools::tra( "Additional arguments and values depend on the selected module." )
			.'</tr>'
		.'</table>'
		. KernelTools::tra( "Example: " ) . '{MODULE module=last_changes package=liberty title="Recent Changes"}';
	return $help;
}

function data_datamodule( $pData, $pParams ) {
	global $gBitThemes, $gBitSmarty;

	$out = '';
	$ret = ' ';

	extract( $pParams , EXTR_SKIP );

	if( !empty( $module ) && !empty( $package )) {
		$modules_dir = constant( strtoupper( $package ).'_PKG_PATH' ).'modules/';
		if( is_file( $modules_dir.'mod_'.$module.'.tpl' )) {
			$tpl = 'bitpackage:'.$package.'/mod_'.$module.'.tpl';
		} else {
			return '<div class="error">'.KernelTools::tra( "The module / package combination you entered is not valid" ).'</div>';
		}
	} else {
		return '<div class="error">'.KernelTools::tra( "Both paramters 'module' and 'package' are required" ).'</div>';
	}

	// Setup moduleParams the best we can.
	$moduleParams = [];
	$moduleParams['module_params'] = $pParams;
	$moduleParams['module_rows'] = $pParams['rows'] ?? 10;

	if( isset( $pParams['title'] )) {
		$moduleParams['title'] = $pParams['title'];
	}
	$gBitSmarty->assign( 'moduleParams', $moduleParams );

	if( !$out = $gBitSmarty->fetch( $tpl ) ) {
		if( $gBitThemes->isCustomModule( $module ) ) {
			$info = $gBitThemes->getCustomModule( $module );
			$gBitSmarty->assign( 'user_title', $info["title"] );
			$gBitSmarty->assign( 'user_data', $info["data"] );
			$out = $gBitSmarty->fetch( 'modules/user_module.tpl' );
		}
	}
	$out = preg_replace( "#\n#is", "", $out );

	// deal with custom styling
	$style = '';
	$style_options = [ 'float', 'width', 'background', 'color' ];
	foreach( $pParams as $param => $value ) {
		if( in_array( $param, $style_options ) ) {
			$style .= $param.':'.$value.';';
		}
	}

	if( !empty( $style ) ) {
		$style = ' style="'.$style.'"';
	}

	if( $out ) {
		$ret = '<div'.$style.'>'.$out.'</div>';
	}
	return $ret;
}

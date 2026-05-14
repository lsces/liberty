<?php

namespace Bitweaver\Liberty;

use Bitweaver\KernelTools;

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
define( 'PLUGIN_GUID_DATARENDERER', 'datarenderer' );
global $gLibertySystem;
$pluginParams = [
	'tag' => 'renderer',
	'auto_activate' => false,
	'requires_pair' => true,
	'load_function' => '\data_renderer',
	'help_function' => '\data_renderer_help',
	'title' => 'Renderer',
	'help_page' => 'DataPluginRenderer',
	'description' => KernelTools::tra( "This plugin will render the given content as described by the content_type given." ),
	'syntax' => "{renderer class= format_guid= }.. content ..{/renderer}",
	'plugin_type' => DATA_PLUGIN,
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATARENDERER, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATARENDERER );

// Help Routine
function data_renderer_help() {
	$help ="<table class=\"data help\">
		<tr>
			<th>" . KernelTools::tra( "Key" ) . "</th>
			<th>" . KernelTools::tra( "Type" ) . "</th>
			<th>" . KernelTools::tra( "Comments" ) . "</th>
		</tr>
		<tr class=\"even\">
			<td>id</td>
			<td>" . KernelTools::tra( "div id") . '<br />' . KernelTools::tra("(optional)") . "</td>
			<td>" . KernelTools::tra( "specify the id of the outputed div") . "</td>
		</tr>
		<tr class=\"odd\">
			<td>class</td>
			<td>" . KernelTools::tra( "div class") . '<br />' . KernelTools::tra("(optional)") . "</td>
			<td>" . KernelTools::tra( "specify the class of the outputed div") . "</td>
		</tr>
		<tr class=\"even\">
			<td>format_guid</td>
			<td>" . KernelTools::tra( "string") . '<br />' . KernelTools::tra("(required)") . "</td>
			<td>" . KernelTools::tra( "Specify what renderer should be used to render the contents") . "</td>
		</tr>
	</table>".
	KernelTools::tra("Example: ") . "{renderer class=abc format_guid=tiki }.. content ..{/renderer}<br />";
	return $help;
}

// Executable Routine
function data_renderer( $data, $params, $object = null ) { // No change in the parameters with Clyde
	// The next 2 lines allow access to the $pluginParams given above and may be removed when no longer needed
	global $gLibertySystem, $gBitSmarty, $gBitSystem;

	$data = trim($data);

	if (empty($data)) { // If there is NO data to display - why do anything - get out of here
		return " ";
	}

	$rendererHash = [];
	$rendererHash['content_id'] = 0;
	$rendererHash['format_guid'] = empty($params['format_guid']) ? $gBitSystem->getConfig('default_format') : $params['format_guid'];
	$rendererHash['data'] = $data;

	$formatGuid=$rendererHash['format_guid'];
	$ret = "";
	if( $func = $gLibertySystem->getPluginFunction( $formatGuid, 'load_function' ) ) {
		$ret = $func( $rendererHash, $object );
	}

	$display_result = "<div";
	if (!empty($params['id'])) {
		$display_result .= " id=\"".$params['id']."\"";
	}
	if (!empty($params['class'])) {
		$display_result .= " class=\"".$params['class']."\"";
	}
	$display_result .= ">$ret</div>";

	return $display_result;
}

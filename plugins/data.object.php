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
// | Author (TikiWiki): Damian Parker <damosoft@users.sourceforge.net>
// | Reworked for Bitweaver (& Undoubtedly Screwed-Up)
// | by: StarRider <starrrider@users.sourceforge.net>
// | Rewrote data function so plugin can cover more types of objects than just Flash
// | by: Jasp (Jared Woodbridge) <jaspp@users.sourceforge.net>
// +----------------------------------------------------------------------+
// $Id$

/**
 * definitions
 */
global $gBitSystem;
if( $gBitSystem->isPackageActive( 'wiki' ) ) { // Do not include this Plugin if the Package is not active

define( 'PLUGIN_GUID_DATAOBJECT', 'dataobject' );
global $gLibertySystem;
$pluginParams = [
	'tag' => 'OBJECT',
	'auto_activate' => false,
	'requires_pair' => false,
	'load_function' => '\data_object',
	'title' => 'Object',
	'help_page' => 'DataPluginObject',
	'description' => KernelTools::tra("This plugin displays a Flash, Tcl or Java applet/object."),
	'help_function' => '\data_object_help',
	'syntax' => "{OBJECT type= src= width= height=}",
	'plugin_type' => DATA_PLUGIN
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATAOBJECT, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATAOBJECT );


function data_object_help() {
	$help =
		'<table class="data help">'
			.'<tr>'
				.'<th>' . KernelTools::tra( "Key" ) . '</th>'
				.'<th>' . KernelTools::tra( "Type" ) . '</th>'
				.'<th>' . KernelTools::tra( "Comments" ) . '</th>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>type</td>'
				.'<td>' . KernelTools::tra( "key-word" ) . '<br />' . KernelTools::tra( "(manditory)" ) . '</td>'
				.'<td>' . KernelTools::tra( "The type of object being displayed. Possible values are:") . ' <strong>tcl, flash, java</strong>.' . '</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>src</td>'
				.'<td>' . KernelTools::tra( "string" ) . '<br />' . KernelTools::tra( "(manditory)" ) . '</td>'
				.'<td>' . KernelTools::tra( "The location of the file used for the object. This can be any URL or a site value. See Examples.") . '</td>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>param_<i>name</i></td>'
				.'<td>' . KernelTools::tra( "string" ) . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "Can be used to specify custom object parameters. Currently only available for Tcl applets. Replace \"<i>name</i>\" with the name of the parameter.") . '</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>width</td>'
				.'<td>' . KernelTools::tra( "number or percentage" ) . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "The width of the object. This value can be given in pixels or as a percentage of available area. A pixel value is assumed so only a numeric value is needed. To specify a percentage - the character <strong>% MUST</strong> follow the value.") . '</td>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>height</td>'
				.'<td>' . KernelTools::tra( "number or percentage" ) . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "The height of the object. This value can be given in pixels or as a percentage. A pixel value is assumed so only a numeric value is needed. To specify a percentage - the character <strong>% MUST</strong> follow the value.") . '</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>float</td>'
				.'<td>' . KernelTools::tra( "key-words" ) . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "Specifies how the object is to float on the page. Floating elements are positioned on the side specified, with content flowing around. Possible values are:") . ' <strong>left, right, none</strong>. '
				. KernelTools::tra("(Default = ") . '<strong>' . KernelTools::tra( 'none - object is shown inline' ) . '</strong>)</td>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>clear</td>'
				.'<td>' . KernelTools::tra( "key-words" ) . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "Specifies which horizontal sides of the object can not have other content flowing around. Possible values are:") . ' <strong>left, right, both, none</strong>. '
				. KernelTools::tra("(Default = ") . '<strong>' . KernelTools::tra( 'none - content is allowed to flow around object' ) . '</strong>)</td>'
			.'</tr>'
		.'</table>'

		.'<table class="data help">'
			.'<caption>' . KernelTools::tra( "Flash specific parameters" ) . '</caption>'
			.'<tr>'
				.'<th>' . KernelTools::tra( "Key" ) . '</th>'
				.'<th>' . KernelTools::tra( "Type" ) . '</th>'
				.'<th>' . KernelTools::tra( "Comments" ) . '</th>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>quality</td>'
				.'<td>' . KernelTools::tra( "key-word" ) . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "The quality at which to display a Flash applet. Possible values are unknown - except:") . ' <strong>high</strong> ' . KernelTools::tra("and probably") . ' <strong>low</strong>.</td>'
			.'</tr>'
		.'</table>'

		.'<table class="data help">'
			.'<caption>' . KernelTools::tra( "Java specific parameters" ) . '</caption>'
			.'<tr>'
				.'<th>' . KernelTools::tra( "Key" ) . '</th>'
				.'<th>' . KernelTools::tra( "Type" ) . '</th>'
				.'<th>' . KernelTools::tra( "Comments" ) . '</th>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>vmversion</td>'
				.'<td>' . KernelTools::tra( "version number" ) . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "The version of Java required for the applet. Should be in the form of <strong>X.x</strong>, eg: <strong>1.3</strong>." ) . '</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>pagescript</td>'
				.'<td>' . KernelTools::tra( "boolean" ) . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "Specifies if the applet can access Javascript features on the web page. Possible values are:") . ' <strong>true, false</strong>.</td>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>appletscript</td>'
				.'<td>' . KernelTools::tra( "boolean" ) . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "Specifies whether the applet is scriptable from the web page using JavaScript or VBScript. Possible values are:") . ' <strong>true, false</strong>.</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>srcbase</td>'
				.'<td>' . KernelTools::tra( "string" ) . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "The base location of the Java applet." ) . '</td>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>archive</td>'
				.'<td>' . KernelTools::tra( "string" ) . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "Specifies the name of the Java archive." ) . '</td>'
			.'</tr>'
		.'</table>'

		. KernelTools::tra("Example: ") . "{OBJECT type=flash src=../liberty/icons/Mind-Reader.swf}<br />"
		. KernelTools::tra("Example: ") . "{OBJECT type=flash src=https://www.bitweaver.org/liberty/icons/Mind-Reader.swf width='100%' height='600' quality='high'}<br />"
		. KernelTools::tra('Both of these examples display "The Flash Mind Reader" by Andy Naughton. The first example is on your site and is not very large. The second example is located on the bitweaver.org site and takes the width of the center column with an appropriate height.');
	return $help;
}


function data_object ($data, $params) {
	// Need these plugin parameters
	foreach (array("type", "src") as $parameter) {
		if (!array_key_exists($parameter, $params))
			return '<span class="warning">'.KernelTools::tra('When using <strong>{object}</strong>, a <strong>type</strong> and <strong>src</strong> parameter is required.').'</span>';
	}

	$objectParams = [];

	switch ($params["type"]) {
		case "tcl":
			// This loop scans for and sets param_ custom object parmeters. Note that in the future, it may be used for object types other than Tcl, so don't go making this part of the tcl clause below.
			foreach (array_keys($params) as $parameter) {
				if (mb_ereg("param_*", $parameter))
					$objectParams[substr($parameter, 6)] = $params[$parameter];
			}

//		case "tcl":
			// Tcl Plugin applet
			$classid = "clsid:D27CDB6E-AE6D-11cf-96B8-444553540000";
			$objectParams["type"] = "application/x-tcl";
			$objectParams["pluginspage"] = "http://www.tcl.tk/software/plugin/";
			$objectParams["src"] = $params["src"];
			break;

		case "flash":
			// Macromedia Flash movie
			$classid = "clsid:D27CDB6E-AE6D-11cf-96B8-444553540000";
			$objectParams["movie"] = $params["src"];
			if (array_key_exists("quality", $params))
				$objectParams["quality"] = $params["quality"];
			break;

		case "java":
			// Java applet
			$classid = "clsid:8AD9C840-044E-11D1-B3E9-00805F499D93";
			$objectParams["code"] = $params["src"];
			$objectParams["type"] = "application/x-java-applet";
			if (array_key_exists("vmversion", $params))
				$objectParams["type"] .= ';version='.$params["vmversion"];
			if (array_key_exists("pagescript", $params))
				$objectParams["mayscript"] = $params["pagescript"];
			if (array_key_exists("appletscript", $params))
				$objectParams["scriptable"] = $params["appletscript"];
			if (array_key_exists("srcbase", $params))
				$objectParams["codebase"] = $params["srcbase"];
			if (array_key_exists("archive", $params))
				$objectParams["archive"] = $params["archive"];
			break;

		default:
			// Unrecognized object type
			return '<span class="warning">'.KernelTools::tra('The <strong>type</strong> parameter of <strong>{object}</strong> must either be <strong>tcl</strong>, <strong>flash</strong> or <strong>java</strong>.').'</span>';
	}

	// Build the <object> HTML code
	$result  = '<object classid="'.$classid.'" style="';
	$result .= (array_key_exists("float",  $params)) ? ' float: ' .$params["float"]. ';' : '';
	$result .= (array_key_exists("clear",  $params)) ? ' clear: ' .$params["clear"]. ';' : '';
	$result .= '"';
	$result .= (array_key_exists("width",  $params)) ? ' width="' .$params["width"]. '"' : '';
	$result .= (array_key_exists("height", $params)) ? ' height="'.$params["height"].'"' : '';
	$result .= '>';
	foreach (array_keys($objectParams) as $parameter)
		$result .= '<param name="'.$parameter.'" value="'.$objectParams[$parameter].'"/>';
	$result .= '</object>';

	// ...and we're done
	return $result;
}

}
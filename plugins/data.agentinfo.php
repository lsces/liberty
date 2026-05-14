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
// +----------------------------------------------------------------------+
// $Id$

/**
 * definitions
 */
define( 'PLUGIN_GUID_DATAAGENTINFO', 'dataagentinfo' );
global $gLibertySystem;
$pluginParams = [
	'tag' => 'AGENTINFO',
	'auto_activate' => false,
	'requires_pair' => false,
	'load_function' => '\data_agentinfo',
	'title' => 'AgentInfo',
	'help_page' => 'DataPluginAgentInfo',
	'description' => KernelTools::tra("This plugin will display the viewer's IP address, the Browser they are using, or the info about the site's Server software."),
	'help_function' => '\data_agentinfo_help',
	'syntax' => "{AGENTINFO info= }",
	'plugin_type' => DATA_PLUGIN,
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATAAGENTINFO, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATAAGENTINFO );

/**
 * Help Function
 */
function data_agentinfo_help() {
	$help =
		'<table class="data help">'
			.'<tr>'
				.'<th>' . KernelTools::tra( "Key" ) . '</th>'
				.'<th>' . KernelTools::tra( "Type" ) . '</th>'
				.'<th>' . KernelTools::tra( "Comments" ) . '</th>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>info</td>'
				.'<td>' . KernelTools::tra( "string") . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "Show information about the Browser being used.") . '<br />'
				.'<strong>ip</strong>: ' . KernelTools::tra( "To get the client\'s IP address (default)" ) . '<br />'
				.'<strong>browser</strong>: ' . KernelTools::tra( "To get the clients Browser infromation." ) . '<br />'
				.'<strong>server</strong>: ' . KernelTools::tra( "To get the site\'s server software" ) . '</td>'
			.'</tr>'
		.'</table>'
		. KernelTools::tra("Example: ") . "{AGENTINFO info='browser'}";
	return $help;
}

// Load Function
function data_agentinfo($data, $params) {
	$info = 'IP';
	extract ($params, EXTR_SKIP);
	switch (strtoupper ($info)) {
		case 'SVRSW': // To maintain Pre-Clyde Parameters
		case 'SERVER':
			  $ret = $_SERVER["SERVER_SOFTWARE"];
			  return $ret;
		case 'BROWSER':
			  $ret = $_SERVER["HTTP_USER_AGENT"];
			  return $ret;
		default:
			  $ret = $_SERVER["REMOTE_ADDR"];
			  return $ret;
	}

}

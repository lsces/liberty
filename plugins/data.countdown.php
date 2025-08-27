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
// | Author (TikiWiki): Stephan Borg <wolff_borg@users.sourceforge.net>
// | Reworked for Bitweaver (& Undoubtedly Screwed-Up)
// | by: StarRider <starrrider@users.sourceforge.net>
// +----------------------------------------------------------------------+
// $Id$

/**
 * definitions
 */
define( 'PLUGIN_GUID_DATACOUNTDOWN', 'datacountdown' );
global $gLibertySystem;
$pluginParams = array (
	'tag' => 'COUNTDOWN',
	'auto_activate' => false,
	'requires_pair' => false,
	'load_function' => '\data_countdown',
	'title' => 'CountDown',
	'help_page' => 'DataPluginCountDown',
	'description' => KernelTools::tra("Displays a Count-Down until a date:time is reached - then - negative numbers indicate how long it has been since that date. The Count-Down is displayed in the format of (X days, X hours, X minutes and X seconds)."),
	'help_function' => '\data_countdown_help',
	'syntax' => "{COUNTDOWN enddate= localtime= class= punct= text=}",
	'plugin_type' => DATA_PLUGIN
);
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATACOUNTDOWN, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATACOUNTDOWN );

// Help Function
function data_countdown_help() {
	$help =
		'<table class="data help">'
			.'<tr>'
				.'<th>' . KernelTools::tra( "Key" ) . '</th>'
				.'<th>' . KernelTools::tra( "Type" ) . '</th>'
				.'<th>' . KernelTools::tra( "Comments" ) . '</th>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>enddate</td>'
				.'<td>' . KernelTools::tra( "string") . '<br />' . KernelTools::tra("(mandatory)") . '</td>'
				.'<td>' . KernelTools::tra( "A date used to compare to the present date. Several date formats are accepted, but spelling it out like this: <strong>May 10 2004</strong> is probably the simplest. A time can be include with the date like this: <strong>20:02:00 or 8:02pm</strong> . There is <strong>NO</strong> Default.") . '</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>localtime</td>'
				.'<td>' . KernelTools::tra( "boolean") . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "Determins if Local Time is displayed or not. Passing any value in this parameter will make it <strong>true</strong>. The Default = <strong>false</strong> so Local Time will not be displayed") . '</td>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>class</td>'
				.'<td>' . KernelTools::tra( "string") . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "Classname of the SPAN surrounding the countdown. The date/time segments are each wrapped in a VAR-Tag. Default = countdown") . '</td>'
			.'</tr>'			
			.'<tr class="even">'
				.'<td>punct</td>'
				.'<td>' . KernelTools::tra( "string") . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "Any kind of punctuation to divide the date/time segments from each other, a comma, a colon, a pipe ... Default = space. To put a non breaking space, use HTML: &amp;nbsp;") . '</td>'
			.'</tr>'			
			.'<tr class="odd">'
				.'<td>text</td>'
				.'<td>' . KernelTools::tra( "string") . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "Text to be displayed after the date/time string. It's wrapped in &lt;em&gt;.") . '</td>'
			.'</tr>'			
		.'</table>'
		.'<p>' . KernelTools::tra("Example 1: ") . '<input value="{COUNTDOWN enddate=\'8:02pm May 10 2004\' localtime=\'on\' text=\'' . KernelTools::tra(" - Time Passes So Slowly") . '\'}" type="text" size="40" /></p>'
		.'<p>' . KernelTools::tra("Example 2: ") . '<input value="{COUNTDOWN enddate=\'2012-12-22 00:01\' class=\'alert red\' punct=\', \' text=\'Purple Haze\'}" type="text" size="40" /></p>'
	;
	return $help;
}

// Load Function
function data_countdown($data,$params) {
	// The next 2 lines allow access to the $pluginParams given above
	global $gLibertySystem;
	$pluginParams = $gLibertySystem->mPlugins[PLUGIN_GUID_DATACOUNTDOWN];
	extract ($params, EXTR_SKIP);
	
	if (!isset($enddate) ) {  // The Mandatory Parameter is missing
		$ret = KernelTools::tra("The required parameter ") . "<strong>enddate</strong>" . KernelTools::tra(" was missing from the plugin ") . '<strong>"' . $pluginParams['tag'] . '"</strong>';
		$ret.= data_countdown_help();
		return $ret;
	}
	
	$then = strtotime ($enddate);
	
	if ($then == -1) { // strtotime failed so enddate was not a valid date
		$ret = KernelTools::tra("__Error__ - The plugin ") . '<strong>"' . $pluginParams['tag'] . '"</strong>' . KernelTools::tra(" was not given a valid date. The date given was:\n") . "enddate=$enddate";
		return $ret;
	}
	
	$tz = isset($localtime) && $localtime == 'on' && isset($_COOKIE['tz_offset']) ?  $_COOKIE['tz_offset'] : 0;
	
	if (!isset($class)) {
		$class = 'countdown';
	}
	
	if (!isset($punct)) {
		$punct = " ";
	}

	if (!isset($text)) {
		$text = "";
	}

	
	$now = strtotime ("now") + $tz;
	$difference = $then - $now;
	$num = $difference/86400;
	$days = intval($num);
	$num2 = ($num - $days)*24;
	$hours = intval($num2);
	$num3 = ($num2 - $hours)*60;
	$mins = intval($num3);
	$num4 = ($num3 - $mins)*60;
	$secs = intval($num4);
	$ret = "
		<span class='".$class."'>"
		. "<var>" . $days  . " " . KernelTools::tra("days")    . "</var>" . $punct
		. "<var>" . $hours . " " . KernelTools::tra("hours")   . "</var>" . $punct
		. "<var>" . $mins  . " " . KernelTools::tra("minutes") . "</var>" . $punct
		. "<var>" . $secs  . " " . KernelTools::tra("seconds") . "</var>" . " "
		. "<em>"  . $text  . "</em>"
		. "</span>"
	;
	return $ret;
}

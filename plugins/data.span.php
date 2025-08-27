<?php

namespace Bitweaver\Liberty;
use Bitweaver\KernelTools;
use Bitweaver\Liberty\LibertyContent;

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
define( 'PLUGIN_GUID_DATAspan', 'dataspan' );
global $gLibertySystem;
$pluginParams = [
	'tag' => 'span',
	'auto_activate' => false,
	'requires_pair' => true,
	'load_function' => '\data_span',
	'title' => 'Span',
	'help_page' => 'DataPluginspan',
	'description' => KernelTools::tra( "This plugin allows you to easily create a span with a number of optional CSS parameters." ),
	'help_function' => '\data_span_help',
	'syntax' => "{span border='3px solid blue'}",
	'plugin_type' => DATA_PLUGIN
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATAspan, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATAspan );

function data_span_help() {
	$help =
		'<table class="data help">'
			.'<tr>'
				.'<th>' . KernelTools::tra( "Key" ) . '</th>'
				.'<th>' . KernelTools::tra( "Type" ) . '</th>'
				.'<th>' . KernelTools::tra( "Comments" ) . '</th>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>' . KernelTools::tra( "CSS rules or class" ) . '</td>'
				.'<td>' . KernelTools::tra( "string") . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "This can be any CSS style rule. e.g.: ") . "font='small-caps 250% serif'" .'</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>preset</td>'
				.'<td>' . KernelTools::tra( "string") . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "There are a few presets, which you can use to style with. Presets include: caps, smallcaps, big, small, strikethrough, overline, spaced, nodecor.") .'</td>'
			.'</tr>'
		.'</table>'
		. KernelTools::tra( "Example: " ) . "{span preset=overline font='small-caps 250% serif'}";
	return $help;
}

function data_span( $pData, $pParams, $pCommonObject ) {
	$style = '';
	foreach( $pParams as $key => $value ) {
		if( !empty( $value ) ) {
			switch( $key ) {
				case 'preset':
					if( $value == 'caps' ) {
						$style .= 'text-transform: uppercase;';
					} elseif( $value == "smallcaps" ) {
						$style .= 'font-variant: small-caps;';
					} elseif( $value == "big" ) {
						$style .= 'font-size: larger;';
					} elseif( $value == "small" ) {
						$style .= 'font-size: smaller;';
					} elseif( $value == "strikethrough" ) {
						$style .= 'text-decoration: line-through;';
					} elseif( $value == "spaced" ) {
						$style .= 'letter-spacing: 1.5em;';
					} elseif( $value == "overline" ) {
						$style .= 'text-decoration: overline;';
					} elseif( $value == "nodecor" ) {
						$style .= 'text-decoration: none;';
					}
					break;
				case 'style':
					$style .= $value;
					break;
				case 'class':
					$class = $value;
					break;
				default:
					$style .= $key.':'.$value.';';
				break;
			}
		}
	}
	// we need to parse the data. we shouldn't cache this to avoid problems with the regular cache file
	$parseHash = $pCommonObject->mInfo;
	$parseHash['no_cache'] = true;
	$parseHash['data'] = $pData;
	$parsedData = LibertyContent::parseDataHash( $parseHash, $pCommonObject );
	$parsedData = preg_replace( '|<br\s*/?>$|', '', $parsedData );
	return '<span '.( !empty( $class ) ? 'class="'.$class.'" ' : '' ).'style="'.$style.'">'.$parsedData.'</span>';
}

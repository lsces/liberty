<?php

namespace Bitweaver\Liberty;
use Bitweaver\KernelTools;
use Bitweaver\Liberty\LibertyContent;

/**
 * @version  $Revision$
 * @package  liberty
 * @subpackage plugins_data
 */

/**
 * definitions
 */
define( 'PLUGIN_GUID_DATASORT', 'datasort' );
global $gLibertySystem;
$pluginParams = [
	'tag'           => 'SORT',
	'auto_activate' => false,
	'requires_pair' => true,
	'load_function' => '\data_sort',
	'title'         => 'Sort',
	'help_page'     => 'DataPluginSort',
	'description'   => KernelTools::tra( "This plugin will sort the lines within a {sort} block." ),
	'help_function' => '\data_sort_help',
	'syntax'        => "{sort sort= }".KernelTools::tra( "Lines to be sorted" )."{sort}",
	'plugin_type'   => DATA_PLUGIN
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATASORT, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATASORT );

/**
 * Help Function
 */
function data_sort_help() {
	$help ='
		<table class="data help">
			<tr>
				<th>'.KernelTools::tra( 'Key' ).'</th>
				<th>'.KernelTools::tra( 'Value' ).'</th>
				<th>'.KernelTools::tra( 'Comments' ).'</th>
			</tr>
			<tr class="even">
				<td>'.'sort' .'</td>
				<td>'.KernelTools::tra( "key-words").'<br />'.KernelTools::tra("(optional)").'</td>
				<td>'.KernelTools::tra( 'Will sort the lines in the desired direction.  Choices are:' ).'<strong>asc</strong>, <strong>desc</strong>, <strong>reverse</strong>, <strong>shuffle</strong>'.KernelTools::tra( 'Default:' ).'<strong>asc</strong>'.'</td>
			</tr>
		</table>'.
		KernelTools::tra( "Example: " ).'{sort sort=shuffle}<br />Line 1<br />Line 2<br />Line 3<br />{sort}';
	return $help;
}

/**
 * Load Function
 */
function data_sort( $pData, $pParams, $pCommonObject, $pParseHash ) {
	$sort = ( !empty( $pParams['sort'] )) ? $pParams['sort'] : 'asc';
	$lines = explode( "\n", $pData );
	if( $sort == "asc" ) {
		sort( $lines );
	} elseif( $sort == "desc" ) {
		rsort( $lines );
	} elseif( $sort == "reverse" ) {
		$lines = array_reverse( $lines );
	} elseif( $sort == "shuffle" ) {
		srand(( float )microtime() * 1000000 );
		shuffle( $lines );
	}
	reset( $lines );
	if( is_array( $lines )) {
		$pData = implode( "\n", $lines );
	}

	$parseHash['content_id'] = $pParseHash['content_id'];
	$parseHash['user_id']    = $pParseHash['user_id'];
	$parseHash['no_cache']   = true;
	$parseHash['data']       = trim( $pData );
	return LibertyContent::parseDataHash( $parseHash, $pCommonObject );
}

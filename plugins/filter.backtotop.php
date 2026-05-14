<?php
/**
 * @version  $Header$
 * @package  liberty
 * @subpackage plugins_filter
 */

 namespace Bitweaver\Liberty;

 use Bitweaver\KernelTools;

/**
 * definitions ( guid character limit is 16 chars )
 */
define( 'PLUGIN_GUID_FILTERBACKTOTOP', 'filterbacktotop' );

global $gLibertySystem;

$pluginParams = [
	'title'              => 'Back to Top',
	'description'        => 'Add back to top link to headers.<br />We recommend that you add something like this to your CSS file:<br /><code>a.backtotop {display:block;text-align:right;}</code>',
	'auto_activate'      => false,
	'plugin_type'        => FILTER_PLUGIN,

	// filter functions
	'postparse_function' => '\Bitweaver\Liberty\backtotop_postparsefilter',

	// these settings are to get the plugin help working on content edit pages
	'tag'                => 'backtotop',
	'help_page'          => 'FilterPluginBacktoTop',
	'help_function'      => 'backtotop_help',
	'syntax'             => '{backtotop}',
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_FILTERBACKTOTOP, $pluginParams );

function backtotop_postparsefilter( &$pData, &$pFilterHash ) {
	global $gBitSmarty;
	if( preg_match( "/\{(backtotop[^\}]*)\}/i", $pData, $backtotop )) {
		// remove {backtotop} from page
		$pData = preg_replace( '#(<p>)?'.preg_quote( $backtotop[0], '#' ).'(</p>)?(\s*<[Bb][Rr]\s*/?>)?#', '', $pData );
		// default values
		$params['min'] = 1;
		$params['max'] = 6;
		$params = array_merge( $params, KernelTools::parse_xml_attributes( $backtotop[1] ));

		$biticon = [
			'ipackage' => 'icons',
			'iname'    => 'go-top',
			'iexplain' => 'Back to top',
		];

		// get all headers into an array
		preg_match_all( "/<h(\d[^>]*)>.*?<\/h\d>/i", $pData, $headers );
		$link = '<a class="backtotop" href="#content">'.\Bitweaver\Liberty\smarty_function_biticon( $biticon, $gBitSmarty ).'</a>';
		foreach( $headers[0] as $key => $header ) {
			if( $headers[1][$key] >= $params['min'] && $headers[1][$key] <= $params['max'] ) {
				$pData = str_replace( $header, $link.$header, $pData );
			}
		}
		$pData .= $link;
	}
}

function backtotop_help() {
	return
		'<table class="data help">
			<tr>
				<th>'.KernelTools::tra( "Key" ).'</th>
				<th>'.KernelTools::tra( "Type" ).'</th>
				<th>'.KernelTools::tra( "Comments" ).'</th>
			</tr>
			<tr class="even">
				<td>min</td>
				<td>'.KernelTools::tra( "numeric").'<br />('.KernelTools::tra("optional").')</td>
				<td>'.KernelTools::tra( 'If you specify 2 here, backtotop will only add links from h2 to h6 level.' ).'</td>
			</tr>
			<tr class="odd">
				<td>max</td>
				<td>'.KernelTools::tra( "numeric").'<br />('.KernelTools::tra("optional").')</td>
				<td>'.KernelTools::tra( 'If you specify 3 here, backtotop will only add links from h1 to h3 level.' ).'</td>
			</tr>
		</table>'.
		KernelTools::tra("Example: ").'{backtotop min=2 max=3}';
}
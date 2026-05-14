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

global $gLibertySystem;
define( 'PLUGIN_GUID_DATAGOOGLEVIEWER', 'datagoogleviewer' );
$pluginParams = [
	'tag'           => 'GOOGLEVIEWER',
	'auto_activate' => false,
	'requires_pair' => false,
	'load_function' => '\data_googleviewer',
	'title'         => 'Google Viewer',
	'help_page'     => 'DataPluginGoogleviewer',
	'description'   => KernelTools::tra( "This plugin allows you to simply embed a PDF document in a page using the embeddable Google Viewer." ),
	'help_function' => '\data_googleviewer_help',
	'syntax'        => "{googleviewer url=}",
	'plugin_type'   => DATA_PLUGIN,
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATAGOOGLEVIEWER, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATAGOOGLEVIEWER );

/**
 * data_googleviewer_help 
 * 
 * @access public
 * @return string HTML help in a table
 */
function data_googleviewer_help() {
	return
		'<table class="data help">'
			.'<tr>'
				.'<th>' . KernelTools::tra( "Key" ) . '</th>'
				.'<th>' . KernelTools::tra( "Type" ) . '</th>'
				.'<th>' . KernelTools::tra( "Comments" ) . '</th>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>url</td>'
				.'<td>' . KernelTools::tra( "string" ) . '<br />' . KernelTools::tra("(required)") . '</td>'
				.'<td>' . KernelTools::tra( "URL of the PDF online" ) . '</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>width</td>'
				.'<td>' . KernelTools::tra( "numeric" ) . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "Alternate width of the Google Viewer box in pixels.  Default is 100%." ) . '</td>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>height</td>'
				.'<td>' . KernelTools::tra( "numeric" ) . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "Alternate height of the Google Viewer box in pixels. Default is 650." ) . '</td>'
			.'</tr>'
		.'</table>'
		. KernelTools::tra( "Example: " ) . '{googleviewer url=XXXXX width=425 height=355}';
}

/**
 * data_googleviewer 
 * 
 * @param array $pData 
 * @param array $pParams 
 * @access public
 * @return string
 */
function data_googleviewer( $pData, $pParams ) {
	extract( $pParams );
	$width   = !empty( $width )  ? $width  : "100%";
	$height  = !empty( $height ) ? $height : "650";

	if( !empty( $url )) {
		return '<!--~np~--><iframe width="'.$width.'" height="'.$height.'" style="border:none;" src="http://docs.google.com/viewer?embedded=true&url='.urlencode($url).'"></iframe><!--~/np~-->';
	}
		return KernelTools::tra( 'No URL given' );

}

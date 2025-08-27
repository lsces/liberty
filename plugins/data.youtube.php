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
define( 'PLUGIN_GUID_DATAYOUTUBE', 'datayoutube' );
$pluginParams = array (
	'tag'           => 'YOUTUBE',
	'auto_activate' => false,
	'requires_pair' => false,
	'load_function' => '\data_youtube',
	'title'         => 'Youtube',
	'help_page'     => 'DataPluginYoutube',
	'description'   => KernelTools::tra( "This plugin allows you to simply and safely insert a YouTube video in a page." ),
	'help_function' => '\data_youtube_help',
	'syntax'        => "{youtube id=}",
	'plugin_type'   => DATA_PLUGIN
);
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATAYOUTUBE, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATAYOUTUBE );

/**
 * data_youtube_help 
 * 
 * @access public
 * @return string HTML help in a table
 */
function data_youtube_help() {
	return
		'<table class="data help">'
			.'<tr>'
				.'<th>' . KernelTools::tra( "Key" ) . '</th>'
				.'<th>' . KernelTools::tra( "Type" ) . '</th>'
				.'<th>' . KernelTools::tra( "Comments" ) . '</th>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>id</td>'
				.'<td>' . KernelTools::tra( "string" ) . '<br />' . KernelTools::tra("(required)") . '</td>'
				.'<td>' . KernelTools::tra( "ID nr of the Youtube video. You can get this from the URL of the Youtube video you are watching e.g.: pShf2VuAu_Q" ) . '</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>width</td>'
				.'<td>' . KernelTools::tra( "numeric" ) . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "Alternate width of the youtube box in pixels." ) . '</td>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>height</td>'
				.'<td>' . KernelTools::tra( "numeric" ) . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "Alternate height of the youtube box in pixels." ) . '</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>lang</td>'
				.'<td>' . KernelTools::tra( "string" ) . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "Alternate language of the Youtube interface, default is 'en'" ).'</td>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>format</td>'
				.'<td>' . KernelTools::tra( "numeric" ) . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "Fetch different video format instead of the default one. Format 6 and Format 18 seem to work quite well for some videos. Please note that this might be buggy and is not available for all videos." ).'</td>'
			.'</tr>'
		.'</table>'
		. KernelTools::tra( "Example: " ) . '{youtube id=XXXXX width=425 height=355 lang=en}';
}

/**
 * data_youtube 
 * 
 * @param array $pData 
 * @param array $pParams 
 * @access public
 * @return string
 */
function data_youtube( $pData, $pParams ) {
	extract( $pParams );
	$width   = !empty( $width )  ? $width  : "425";
	$height  = !empty( $height ) ? $height : "355";
	$lang    = "&amp;hl=".( !empty( $lang ) ? $lang : "en" );
	$format  = !empty( $format )  ? "&amp;ap=%2526fmt%3D".$format  : "";

	if( !empty( $id )) {
		return '<!--~np~--><object width="'.$width.'" height="'.$height.'"><param name="movie" value="http://www.youtube.com/v/'.$id.$lang.$format.'"></param><param name="wmode" value="transparent"></param><embed src="http://www.youtube.com/v/'.$id.$lang.$format.'" type="application/x-shockwave-flash" wmode="transparent" width="'.$width.'" height="'.$height.'""></embed></object><!--~/np~-->';
	} else {
		return KernelTools::tra( 'No ID given' );
	}
}

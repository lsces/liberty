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
define( 'PLUGIN_GUID_DATACOMMENT', 'datacomment' );
global $gLibertySystem;
$pluginParams = [
	'tag'           => 'COMMENT',
	'auto_activate' => false,
	'requires_pair' => true,
	'load_function' => '\data_comment',
	'title'         => 'Comment',
	'help_page'     => 'DataPluginComment',
	'description'   => KernelTools::tra("This plugin allows Comments (Text that will not be displayed) to be added to a page."),
	'help_function' => '\data__comment_help',
	'syntax'        => "{comment}Data Not Displayed{/comment}",
	'plugin_type'   => DATA_PLUGIN
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATACOMMENT, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATACOMMENT );

/**
 * data_comment_help 
 * 
 * @access public
 * @return string help
 */
function data_comment_help() {
	$help =
		'<table class="data help">'
			.'<tr>'
				.'<th>' . KernelTools::tra( "Key" ) . '</th>'
				.'<th>' . KernelTools::tra( "Type" ) . '</th>'
				.'<th>' . KernelTools::tra( "Comments" ) . '</th>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>' . KernelTools::tra("This plugin uses no parameters. Anything located between the two")
				. ' <strong>{COMMENT}</strong> ' . KernelTools::tra("Blocks is not displayed.") . '</td>'
			.'</tr>'
		.'</table>'
		. KernelTools::tra("Example: ") . "{COMMENT}" . KernelTools::tra("Everything in here is not displayed.") . "{/COMMENT}";
	return $help;
}

/**
 * data_comment 
 * 
 * @access public
 * @return string ' '
 */
function data_comment( $pData, $pParams ) {
	return ' ';
}

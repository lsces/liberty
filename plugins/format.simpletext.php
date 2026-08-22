<?php

namespace Bitweaver\Liberty;

/**
 * @version  $Revision$
 * @package  liberty
 * @subpackage plugins_format
 */
global $gLibertySystem;

/**
 * definitions
 */
define( 'PLUGIN_GUID_SIMPLETEXT', 'simpletext' );

$pluginParams = [
	'load_function'   => 'simpletext_parse_data',
	'verify_function' => 'simpletext_verify_data',
	'description'     => 'Simple Syntax Format Parser',
	'edit_label'      => 'Plain Text',
	'edit_field'      => PLUGIN_GUID_SIMPLETEXT,
	'help_page'       => 'SimpleTextSyntax',
	'plugin_type'     => FORMAT_PLUGIN,
	'linebreak'       => '<br />',
	// Missing since this plugin was written — every other real default plugin
	// (format.bithtml.php etc.) sets this. Without it, LibertySystem::
	// loadActivePlugins() (the normal per-request loader, which only includes
	// plugin files already marked active in kernel_config) never even includes
	// this file, so registerPlugin()'s own auto_activate check never runs —
	// the plugin stays permanently inactive on any site unless someone visits
	// liberty/admin/plugins.php (which force-scans the whole plugins/ directory)
	// or a fresh install's scanAllPlugins() picks it up. Found via FoodComponent's
	// Notes field: format_guid='simpletext' was set correctly, but
	// getPluginFunction('simpletext','verify_function') silently returned null on
	// every save, so 'edit' never made it into content_store['data'] — every note
	// ever typed was discarded with no error anywhere. See Claude memory
	// project_food_package_scoping for the full incident.
	'auto_activate'   => true,
];

$gLibertySystem->registerPlugin( PLUGIN_GUID_SIMPLETEXT, $pluginParams );

function simpletext_verify_data( &$pParamHash ) {
	$pParamHash['content_store']['data'] = $pParamHash['edit'];
}

function simpletext_parse_data( &$pParseHash, &$pCommonObject ) {
	return nl2br( htmlentities( $pParseHash['data'] ) );
}

<?php
use Bitweaver\KernelTools;

require_once '../../../kernel/includes/setup_inc.php';
include_once KERNEL_PKG_INCLUDE_PATH . 'simple_form_functions_lib.php';

$gBitSystem->verifyPermission( 'p_admin' );

$feedback = [];

$pdfSettings = [
	'pdftotext_path'  => [
		'label' => 'Path to pdf text layer extractor',
		'note'  => 'Path to pdftotext executable.',
		'type'  => 'text',
	],
	'convert_path'  => [
		'label' => 'Path to image extractor from pdf file',
		'note'  => 'Path to convert executable.',
		'type'  => 'text',
	],
	'pdf_thumbnails'  => [
		'label' => 'PDF Upload Thumbnails',
		'note'  => 'Create thumbnails for PDF uploads.',
		'type'  => 'checkbox',
	],
];

if( function_exists( 'shell_exec' )) {
	$pdfSettings['pdftotext_path']['default'] =  shell_exec( 'which pdftotext' );
} else {
	$feedback['error'] = "You can not execute binaries on your server. You can not make use of this plugin.";
}

$gBitSmarty->assign( 'pdfSettings', $pdfSettings );

if( !empty( $_REQUEST['plugin_settings'] )) {
	foreach( $pdfSettings as $item => $data ) {
		if( $data['type'] == 'checkbox' ) {
			simple_set_toggle( $item, LIBERTY_PKG_NAME );
		} elseif( $data['type'] == 'numeric' ) {
			simple_set_int( $item, LIBERTY_PKG_NAME );
		} else {
			simple_set_value( $item, LIBERTY_PKG_NAME );
		}
	}

	$feedback['success'] = KernelTools::tra( 'The plugin was successfully updated' );
}

$gBitSmarty->assign( 'feedback', $feedback );
$gBitSystem->display( 'bitpackage:liberty/mime/pdf/admin.tpl', KernelTools::tra( 'PDF Plugin Settings' ), [ 'display_mode' => 'admin' ]);

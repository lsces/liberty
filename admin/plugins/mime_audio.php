<?php
use Bitweaver\KernelTools;

require_once '../../../kernel/includes/setup_inc.php';
include_once KERNEL_PKG_INCLUDE_PATH . 'simple_form_functions_lib.php';

$gBitSystem->verifyPermission( 'p_admin' );

if( function_exists( 'shell_exec' )) {
	$gBitSmarty->assign( 'ffmpeg_path', shell_exec( 'which ffmpeg' ));
	$gBitSmarty->assign( 'mplayer_path', shell_exec( 'which mplayer' ));
	$gBitSmarty->assign( 'lame_path', shell_exec( 'which lame' ));
}

$feedback = [];

$options = [
	'mp3_lib'          => [
		'libmp3lame' => 'libmp3lame',
		'mp3'        => 'mp3',
	],
	'audio_bitrate'    => [
		32000  => 32,
		64000  => 64,
		96000  => 96,
		128000 => 128,
		160000 => 160,
		192000 => 192,
	],
	'audio_samplerate' => [
		11025 => 11025,
		22050 => 22050,
		44100 => 44100,
	],
];
$gBitSmarty->assign( 'options', $options );

if( !empty( $_REQUEST['plugin_settings'] )) {
	$audioSettings = [
		'ffmpeg_path'             => [
			'type' => 'text',
		],
		'ffmpeg_mp3_lib'          => [
			'type' => 'text',
		],
		'mplayer_path'            => [
			'type' => 'text',
		],
		'lame_path'               => [
			'type' => 'text',
		],
		'mime_audio_ffmpeg_use'   => [
			'type' => 'checkbox',
		],
		'mime_audio_samplerate'   => [
			'type' => 'numeric',
		],
		'mime_audio_bitrate'      => [
			'type' => 'numeric',
		],
		'mime_audio_lame_options' => [
			'type' => 'text',
		],
		'mime_audio_backcolor'    => [
			'type' => 'text',
		],
		'mime_audio_frontcolor'   => [
			'type' => 'text',
		],
		'mime_audio_force_encode' => [
			'type' => 'checkbox',
		],
	];

	foreach( $audioSettings as $item => $data ) {
		if( $data['type'] == 'checkbox' ) {
			simple_set_toggle( $item, LIBERTY_PKG_NAME );
		} elseif( $data['type'] == 'numeric' ) {
			simple_set_int( $item, LIBERTY_PKG_NAME );
		} else {
			$gBitSystem->storeConfig( $item, !empty( $_REQUEST[$item] ) ? $_REQUEST[$item] : null, LIBERTY_PKG_NAME );
		}
	}

	$feedback['success'] = KernelTools::tra( 'The plugin was successfully updated' );
}

$gBitSmarty->assign( 'feedback', $feedback );
$gBitSystem->display( 'bitpackage:liberty/mime/audio/admin.tpl', KernelTools::tra( 'Audio Plugin Settings' ), [ 'display_mode' => 'admin' ]);

<?php
use Bitweaver\KernelTools;

require_once '../../../kernel/includes/setup_inc.php';
include_once KERNEL_PKG_INCLUDE_PATH.'simple_form_functions_lib.php';

$gBitSystem->verifyPermission( 'p_admin' );

if( function_exists( 'shell_exec' )) {
	$gBitSmarty->assign( 'ffmpeg_path', shell_exec( 'which ffmpeg' ));
	$gBitSmarty->assign( 'mp4box_path', shell_exec( 'which MP4Box' ));
}

if( extension_loaded( 'ffmpeg' )) {
	$gBitSmarty->assign( 'ffmpeg_extension', true );
}

$feedback = [];

$options = [
	'me_method' => [
		'me_method' => 'me_method',
		'me'        => 'me',
	],
	'mp3_lib' => [
		'libmp3lame' => 'libmp3lame',
		'mp3'        => 'mp3',
	],
	'video_codec' => [
		'flv'        => 'Flashvideo using flv codec',
		'h264'       => 'MP4/AVC using h264 codec',
		'h264-2pass' => 'MP4/AVC using h264 codec - 2 passes',
	],
	'video_bitrate' => [
		160000 => 200,
		240000 => 300,
		320000 => 400,
		400000 => 500,
		480000 => 600,
		560000 => 700,
		640000 => 800,
	],
	'video_width' => [
		240 => 240,
		320 => 320,
		480 => 480,
		640 => 640,
	],
	'audio_bitrate' => [
		32000  => 32,
		64000  => 64,
		96000  => 96,
		128000 => 128,
	],
	'audio_samplerate' => [
		11025 => 11025,
		22050 => 22050,
		44100 => 44100,
	],
];
$options['display_size'] = Bitweaver\Liberty\get_image_size_options( 'Same as encoded video' );
$gBitSmarty->assign( 'options', $options );

if( !empty( $_REQUEST['plugin_settings'] )) {
	$videoSettings = [
		'ffmpeg_path' => [
			'type'  => 'text',
		],
		'ffmpeg_mp3_lib' => [
			'type'  => 'text',
		],
		'ffmpeg_me_method' => [
			'type'  => 'text',
		],
		'mp4box_path' => [
			'type'  => 'text',
		],
		'mime_video_video_codec' => [
			'type'  => 'text',
		],
		'mime_video_video_bitrate' => [
			'type'  => 'numeric',
		],
		'mime_video_force_encode' => [
			'type'  => 'checkbox',
		],
		'mime_video_audio_samplerate' => [
			'type'  => 'numeric',
		],
		'mime_video_audio_bitrate' => [
			'type'  => 'numeric',
		],
		'mime_video_width' => [
			'type'  => 'numeric',
		],
		'mime_video_default_size' => [
			'type'  => 'text',
		],
		'mime_video_backcolor' => [
			'type'  => 'text',
		],
		'mime_video_frontcolor' => [
			'type'  => 'text',
		],
	];

	foreach( $videoSettings as $item => $data ) {
		switch ($data['type']) {
			case 'checkbox':
				simple_set_toggle( $item, LIBERTY_PKG_NAME );
				break;
			case 'numeric':
				simple_set_int( $item, LIBERTY_PKG_NAME );
				break;
			default:
				$gBitSystem->storeConfig( $item, !empty( $_REQUEST[$item] ) ? $_REQUEST[$item] : null, LIBERTY_PKG_NAME );
				break;
		}
	}

	$feedback['success'] = KernelTools::tra( 'The plugin was successfully updated' );
}

$gBitSmarty->assign( 'feedback', $feedback );
$gBitSystem->display( 'bitpackage:liberty/mime/video/admin.tpl', KernelTools::tra( 'Flashvideo Plugin Settings' ), [ 'display_mode' => 'admin' ] );

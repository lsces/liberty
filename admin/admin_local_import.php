<?php
/**
 * Gateway admin page for a site's private one-off imports - <site root>/config/local/import/*.php, the same
 * site-specific-but-not-a-package arrangement as Local Scheme beside it. Nothing site-specific or source-specific lives
 * in the package: the page discovers whatever import files the site being administered has, shows each with its
 * parameters, and runs one on request, as a dry run or for real.
 *
 * An import file returns an array:
 *   'title'       short name
 *   'description' what it does, one or two sentences
 *   'params'      optional, name => [ 'label' => ..., 'default' => ..., 'size' => width ] - one text field each
 *   'run'         function( array $pParams, bool $pApply ): string|array  - the output as text or a list of lines
 * The run function does the work (nothing is written unless $pApply) and must not bootstrap the application, check
 * permissions or send headers: this page has done all three. It is called with the parameter values as submitted.
 *
 * Runs as a genuine authenticated admin request (php-fpm as the web server user), so file ownership and permissions
 * are handled the normal way.
 *
 * @package liberty
 */

use Bitweaver\KernelTools;

require_once '../../kernel/includes/setup_inc.php';

$gBitSystem->verifyPermission( 'p_admin' );

$importDir = BIT_ROOT_PATH.'config/local/import';
$files = is_dir( $importDir ) ? glob( $importDir.'/*.php' ) : [];
sort( $files );

$imports = [];
foreach( $files as $file ) {
	$def = require $file;
	if( is_array( $def ) && is_callable( $def['run'] ?? null ) ) {
		$imports[basename( $file )] = [
			'file'        => basename( $file ),
			'title'       => (string)( $def['title'] ?? basename( $file, '.php' ) ),
			'description' => (string)( $def['description'] ?? '' ),
			'params'      => (array)( $def['params'] ?? [] ),
			'run'         => $def['run'],
		];
	}
}

$result = null;
$chosen = (string)( $_REQUEST['fImport'] ?? '' );
if( $chosen !== '' && isset( $imports[$chosen] ) && ( isset( $_REQUEST['fDryRun'] ) || isset( $_REQUEST['fApply'] ) ) ) {
	$import = $imports[$chosen];
	$values = [];
	foreach( $import['params'] as $name => $param ) {
		$values[$name] = trim( (string)( $_REQUEST['p'][$chosen][$name] ?? $param['default'] ?? '' ) );
	}
	$apply = isset( $_REQUEST['fApply'] );
	set_time_limit( 0 );
	try {
		$out = ( $import['run'] )( $values, $apply );
		$text = is_array( $out ) ? implode( "\n", $out ) : (string)$out;
	} catch( \Throwable $e ) {
		$text = 'FAILED: '.$e->getMessage().' ('.basename( $e->getFile() ).':'.$e->getLine().')';
	}
	$result = [ 'file' => $chosen, 'title' => $import['title'], 'apply' => $apply, 'text' => $text ];
}

// What the template needs - never the callables.
$list = [];
foreach( $imports as $import ) {
	$params = [];
	foreach( $import['params'] as $name => $param ) {
		$params[$name] = [
			'label' => (string)( $param['label'] ?? $name ),
			'size'  => (int)( $param['size'] ?? 30 ),
			'value' => (string)( $_REQUEST['p'][$import['file']][$name] ?? $param['default'] ?? '' ),
		];
	}
	$list[] = [ 'file' => $import['file'], 'title' => $import['title'], 'description' => $import['description'], 'params' => $params ];
}

$gBitSmarty->assign( 'importDir', $importDir );
$gBitSmarty->assign( 'imports', $list );
$gBitSmarty->assign( 'result', $result );

$gBitSystem->display( 'bitpackage:liberty/admin_local_import.tpl', KernelTools::tra( 'Local Imports' ), [ 'display_mode' => 'admin' ] );

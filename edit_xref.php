<?php
/**
 * @package liberty
 * @subpackage functions
 */

namespace Bitweaver\Liberty;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty, $gBitUser, $gContent;

if( empty( $_REQUEST['content_id'] ) || !is_numeric( $_REQUEST['content_id'] ) ) {
	$gBitSystem->fatalError( 'No content ID specified.' );
}

$gContent = LibertyContent::getLibertyObject( (int)$_REQUEST['content_id'] );

if( !$gContent || !$gContent->isValid() ) {
	$gBitSystem->fatalError( 'Content not found.' );
}

if( !empty( $_REQUEST['xref_id'] ) ) {
	$gContent->loadXref( $_REQUEST['xref_id'] );
}

// multiple = -1 = read-only (see liberty/MANUAL.md's Data model section) — refuse to even
// render the edit form, not just reject a save; LibertyXref::verify() rejects the write
// too (defense-in-depth for any caller that bypasses this controller). multiple = -2
// (mutually exclusive) is freely editable in place, only -1 is blocked here.
if( (int)( $gContent->mInfo['xref_store']['data']['multiple'] ?? 0 ) === -1 ) {
	header( 'Location: '.$gContent->getEditUrl() );
	die;
}

if( !empty( $_REQUEST['fCancel'] ) ) {
	header( 'Location: '.$gContent->getEditUrl() );
	die;
}

if( !empty( $_REQUEST['fSaveXref'] ) ) {
	$gContent->verifyUpdatePermission();
	if( isset( $_REQUEST['json_field'] ) && is_array( $_REQUEST['json_field'] ) ) {
		// json-list/json-text edit forms (edit_xref_json-list_item.tpl) submit one
		// field per JSON key rather than a single 'edit' textarea — reassemble into
		// the JSON string storeXref() actually saves into 'data'. Numeric strings cast
		// back to int/float first so a human edit doesn't quietly turn a field's JSON
		// type from number to string (every form input value arrives as a string).
		$jsonFields = array_map(
			fn( $v ) => is_numeric( $v ) ? $v + 0 : $v,
			$_REQUEST['json_field']
		);
		// Drop blank/zero entries rather than storing every possible field every time —
		// the edit form shows the item's full known field list (liberty_xref_item.data)
		// so a currently-missing field can be added, but most components only ever have
		// a few real values (matches the sparse-write convention every importer already
		// uses) — keeps the stored blob, and anything reading it later, tidy.
		$jsonFields = array_filter( $jsonFields, fn( $v ) => $v !== '' && $v !== 0 && $v !== 0.0 );
		$_REQUEST['edit'] = json_encode( (object)$jsonFields );
	}
	if( isset( $_REQUEST['sod_salt'] ) || isset( $_REQUEST['sod_sodium'] ) ) {
		// Food-specific: SOD's edit form (food/templates/xref/foodcomponent/edit_sod_item.tpl)
		// lets a human enter either the UK-label salt figure or sodium directly — salt takes
		// priority when given (sodium_mg = salt_g / 2.5 * 1000, the standard conversion).
		// Only triggers when one of these fields is actually present, so it can't affect any
		// other item type's save.
		$salt = trim( (string)( $_REQUEST['sod_salt'] ?? '' ) );
		if( $salt !== '' && is_numeric( $salt ) ) {
			$_REQUEST['xkey'] = (string)(int)round( (float)$salt / 2.5 * 1000 );
		} else {
			$_REQUEST['xkey'] = trim( (string)( $_REQUEST['sod_sodium'] ?? '' ) );
		}
	}
	if( method_exists( $gContent, 'replaceXrefFile' ) ) {
		// Generic file-lifecycle hook - deliberately no knowledge here of what content type or
		// item this is (was fisheye/'image'-specific hardcoded logic until 2026-09-02, moved onto
		// the content class itself so this controller stays package-agnostic; also fixed a real
		// bug in passing - the old code always resolved a film's storage root regardless of
		// content type, silently wrong for a season/show on any deployment where that root
		// genuinely differs). Accepts whatever file was actually uploaded, under any field name.
		foreach( $_FILES as $file ) {
			if( !empty( $file['tmp_name'] ) && is_uploaded_file( $file['tmp_name'] ) ) {
				$gContent->replaceXrefFile(
					$gContent->mInfo['xref_store']['data']['item'] ?? '',
					$gContent->mInfo['xref_store']['data']['xkey_ext'] ?? '',
					$file['tmp_name']
				);
				break;
			}
		}
	}
	if( $gContent->storeXref( $_REQUEST ) ) {
		header( 'Location: '.$gContent->getEditUrl() );
		die;
	}
	$xrefInfo = $_REQUEST;
	$xrefInfo['data'] = $_REQUEST['edit'] ?? '';
} elseif( !empty( $_REQUEST['fSetAsThumbnail'] ) ) {
	// A second named submit button on the same form - fSaveXref is never set when this one is
	// clicked, so this needs its own top-level branch, not a nested check inside fSaveXref's.
	$gContent->verifyUpdatePermission();
	if( method_exists( $gContent, 'promoteImageToThumbnail' ) ) {
		// Generic hook, same shape as replaceXrefFile()/deleteXrefFile() - lets a content class
		// expose "make this xref row's file my own real thumbnail" without this controller
		// needing to know what that means.
		$gContent->promoteImageToThumbnail( $gContent->mInfo['xref_store']['data']['xkey_ext'] ?? '' );
	}
	header( 'Location: '.$gContent->getEditUrl() );
	die;
} elseif( isset( $_REQUEST['expunge'] ) ) {
	// expunge=3 is a real hard delete (no history trace left) — gated behind the
	// stricter expunge permission. Archive (1), restore (default/-1), and step (2)
	// are all reversible/audit-trail operations, gated behind ordinary update
	// permission like any other edit.
	if( (int)$_REQUEST['expunge'] === 3 ) {
		$gContent->verifyExpungePermission();
	} else {
		$gContent->verifyUpdatePermission();
	}
	if( (int)$_REQUEST['expunge'] === 3 && method_exists( $gContent, 'deleteXrefFile' ) ) {
		// Generic file-lifecycle hook, same reasoning as replaceXrefFile() above - the content
		// class itself decides whether this item has a disposable file worth cleaning up at all
		// (only true for fisheye's 'image' items; an 'episode' row's own xkey_ext is the real,
		// precious video file and must never be touched here). Read before stepXref() runs - the
		// xref row (and its xkey_ext) won't exist to read afterward.
		$gContent->deleteXrefFile(
			$gContent->mInfo['xref_store']['data']['item'] ?? '',
			$gContent->mInfo['xref_store']['data']['xkey_ext'] ?? ''
		);
	}
	if( $gContent->stepXref( $_REQUEST ) ) {
		header( 'Location: '.$gContent->getEditUrl() );
		die;
	}
	$xrefInfo = $gContent->mInfo['xref_store']['data'] ?? [];
} else {
	$gContent->verifyUpdatePermission();
	$xrefInfo = $gContent->mInfo['xref_store']['data'] ?? [];
}

$gContent->enrichXrefDisplay( $xrefInfo );

$gBitSmarty->assign( 'gContent', $gContent );
$gBitSmarty->assign( 'xrefInfo', $xrefInfo );
$gBitSmarty->assign( 'errors', $gContent->mErrors );

$xrefTemplate = $gContent->mInfo['xref_store']['data']['template'] ?? 'text';
$gBitSystem->display( $gContent->getXrefEditTemplate( $xrefTemplate ), 'Edit Detail', [ 'display_mode' => 'edit' ] );

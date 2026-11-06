<?php
/**
 * Admin page: the xref schema, package by package. Pick a package, then one of its content types, then one of that type's
 * groups, so only one slice of the map is on screen at a time. The roles that gate each group and item can be changed
 * here, and a whole package can be moved between Registered and Anonymous in one step.
 * @package liberty
 */

use Bitweaver\Liberty\LibertyXrefType;
use Bitweaver\KernelTools;

require_once '../../kernel/includes/setup_inc.php';

$gBitSystem->verifyPermission( 'p_admin' );

$packages = LibertyXrefType::getSchemaPackages();
$roles    = LibertyXrefType::getSchemaRoles();
// most open first: Anonymous, then the others from the highest role id down (Registered, Editors, Administrators)
uksort( $roles, fn( $a, $b ) => $a === -1 ? -1 : ( $b === -1 ? 1 : $b <=> $a ) );
$feedback = [];

// What is selected: each choice has to be one the schema really has, so a stray request value selects nothing.
$package = isset( $_REQUEST['package'] ) && isset( $packages[(string)$_REQUEST['package']] ) ? (string)$_REQUEST['package'] : null;
$guids   = $package !== null ? $packages[$package] : [];
$guid    = isset( $_REQUEST['guid'] ) && in_array( (string)$_REQUEST['guid'], $guids, true ) ? (string)$_REQUEST['guid'] : null;
$groups  = $guid !== null ? LibertyXrefType::getGroupList( [ 'content_type_guid' => $guid ] ) : [];
$groupKeys = array_column( $groups, 'x_group' );
$group   = isset( $_REQUEST['x_group'] ) && in_array( (string)$_REQUEST['x_group'], $groupKeys, true ) ? (string)$_REQUEST['x_group'] : null;

$isRole = fn( $pRole ) => is_numeric( $pRole ) && isset( $roles[(int)$pRole] );

if( $package !== null && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	// Package switch: the ticked content types only, and only the rows sitting at the 'from' role. Rows at any other role stay.
	foreach( [ 'fOpenPackage' => [ 3, -1 ], 'fClosePackage' => [ -1, 3 ] ] as $button => [ $from, $to ] ) {
		if( empty( $_REQUEST[$button] ) || !isset( $roles[$from], $roles[$to] ) ) {
			continue;
		}
		$chosen = array_values( array_intersect( $guids, (array)( $_REQUEST['guids'] ?? [] ) ) );
		if( $chosen ) {
			$moved = LibertyXrefType::moveSchemaRoles( $chosen, $from, $to );
			$feedback['success'][] = sprintf( KernelTools::tra( 'Moved %d groups and %d items from %s to %s in %d content types' ),
				$moved['groups'], $moved['items'], $roles[$from], $roles[$to], count( $chosen ) );
		} else {
			$feedback['error'][] = KernelTools::tra( 'Tick at least one content type' );
		}
	}
	// One group's role, from its row in the group table
	if( !empty( $_REQUEST['fSetGroupRole'] ) && $guid !== null ) {
		$rowGroup = (string)( $_REQUEST['row_group'] ?? '' );
		if( in_array( $rowGroup, $groupKeys, true ) && $isRole( $_REQUEST['role_id'] ?? null ) ) {
			LibertyXrefType::setGroupRole( $guid, $rowGroup, (int)$_REQUEST['role_id'] );
			$feedback['success'][] = KernelTools::tra( 'Group role changed' );
		}
	}
	// One item's role, from its row in the item table: the item and its group have to be a pair this content type really has
	if( !empty( $_REQUEST['fSetItemRole'] ) && $guid !== null && $isRole( $_REQUEST['role_id'] ?? null ) ) {
		foreach( LibertyXrefType::getItemList( $guid ) as $row ) {
			if( $row['item'] === (string)( $_REQUEST['item'] ?? '' ) && $row['x_group'] === (string)( $_REQUEST['row_group'] ?? '' ) ) {
				LibertyXrefType::setItemRole( $guid, $row['x_group'], $row['item'], (int)$_REQUEST['role_id'] );
				$feedback['success'][] = KernelTools::tra( 'Item role changed' );
				break;
			}
		}
	}
}

// The package list, each with what its groups and items are gated by.
$packageSummary = [];
$allCounts = LibertyXrefType::getSchemaRoleCounts( array_merge( ...array_values( $packages ) ) ?: [] );
foreach( $packages as $pkg => $pkgGuids ) {
	$tally = [];
	foreach( $pkgGuids as $g ) {
		foreach( $allCounts[$g] ?? [] as $roleId => $n ) {
			$tally[$roleId] = ( $tally[$roleId] ?? 0 ) + $n['groups'] + $n['items'];
		}
	}
	ksort( $tally );
	// rows gated by a role id that is not a role at all: nobody can see them, so they are worth showing
	$other = [];
	foreach( $tally as $roleId => $n ) {
		if( !isset( $roles[$roleId] ) ) {
			$other[] = $roleId.': '.$n;
		}
	}
	$packageSummary[$pkg] = [ 'types' => count( $pkgGuids ), 'roles' => $tally, 'other' => implode( ', ', $other ) ];
}

// The chosen package's content types, with the per-role counts the switch works from.
$typeCounts = [];
$typeOther  = [];
foreach( $guids as $g ) {
	$typeCounts[$g] = $allCounts[$g] ?? [];
	foreach( $typeCounts[$g] as $roleId => $n ) {
		if( !isset( $roles[$roleId] ) ) {
			$typeOther[$g][] = $roleId.': '.$n['groups'].' / '.$n['items'];
		}
	}
	if( isset( $typeOther[$g] ) ) {
		$typeOther[$g] = implode( ', ', $typeOther[$g] );
	}
}

// The chosen content type's groups, each with its role, and the chosen group's items.
// A content type with no groups of its own keeps its items in package-level groups, so all of its items are listed.
$items = [];
$itemsAllGroups = $group === null;
if( $guid !== null && ( $group !== null || !$groups ) ) {
	foreach( LibertyXrefType::getItemList( $guid ) as $row ) {
		if( $group === null || $row['x_group'] === $group ) {
			$items[] = $row;
		}
	}
}

// Packages other than this one whose names start the same way (fisheye / fisheyemedia, contact / contactwiki): their
// content types overlap in use, so the page points at them rather than leaving the overlap to be found.
$related = [];
if( $package !== null && $package !== '' ) {
	foreach( array_keys( $packages ) as $other ) {
		if( $other !== '' && $other !== $package && ( str_starts_with( $other, $package ) || str_starts_with( $package, $other ) ) ) {
			$related[] = $other;
		}
	}
}

$gBitSmarty->assign( 'packages',       $packages );
$gBitSmarty->assign( 'packageSummary', $packageSummary );
$gBitSmarty->assign( 'roles',          $roles );
$gBitSmarty->assign( 'package',        $package );
$gBitSmarty->assign( 'guids',          $guids );
$gBitSmarty->assign( 'typeCounts',     $typeCounts );
$gBitSmarty->assign( 'typeOther',      $typeOther );
$gBitSmarty->assign( 'guid',           $guid );
$gBitSmarty->assign( 'groups',         $groups );
$gBitSmarty->assign( 'group',          $group );
$gBitSmarty->assign( 'items',          $items );
$gBitSmarty->assign( 'itemsAllGroups',  $itemsAllGroups );
$gBitSmarty->assign( 'itemsShown',      $guid !== null && ( $group !== null || !$groups ) );
$gBitSmarty->assign( 'related',        $related );
$gBitSmarty->assign( 'feedback',       $feedback );

$gBitSystem->display( 'bitpackage:liberty/admin_xref_schema.tpl', KernelTools::tra( 'Xref Schema' ), [ 'display_mode' => 'admin' ] );

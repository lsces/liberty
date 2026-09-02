<?php
/**
 * Idempotent, data-driven loader for liberty_xref_group/liberty_xref_item rows - deliberately
 * outside the package-upgrade/version-tracking system (registerPackageUpgrade(),
 * registerSchemaDefault()). For private, site-specific xref vocabulary that shouldn't be forced
 * onto every site a package is installed on via that package's own public upgrade chain - see
 * fisheye.md's 2026-09-02 entry for the design history that led here.
 *
 * @package liberty
 */
namespace Bitweaver\Liberty;

class LibertyXrefScheme {

	/**
	 * Applies a set of xref group/item definitions: inserts a row if missing, updates it in
	 * place if it exists but differs, leaves it alone if it already matches - same "check the DB
	 * first, update in place if it differs" convention LibertySystem::registerContentType()
	 * already uses for liberty_content_types, just applied to the two xref schema tables instead.
	 * Safe to call repeatedly (e.g. every time a scheme script runs) - never double-inserts.
	 *
	 * @param array $pGroups Each entry an assoc array of liberty_xref_group columns - must
	 *                       include x_group and content_type_guid (the natural key).
	 * @param array $pItems  Each entry an assoc array of liberty_xref_item columns - must
	 *                       include item and content_type_guid (the natural key).
	 * @return array Counts: groups_inserted/groups_updated/groups_unchanged,
	 *               items_inserted/items_updated/items_unchanged.
	 */
	public static function apply( array $pGroups, array $pItems ): array {
		global $gBitDb;
		$counts = [
			'groups_inserted' => 0, 'groups_updated' => 0, 'groups_unchanged' => 0,
			'items_inserted'  => 0, 'items_updated'  => 0, 'items_unchanged'  => 0,
		];

		foreach( $pGroups as $group ) {
			$key = [ 'x_group' => $group['x_group'], 'content_type_guid' => $group['content_type_guid'] ];
			$existing = $gBitDb->getRow(
				"SELECT * FROM `".BIT_DB_PREFIX."liberty_xref_group` WHERE `x_group`=? AND `content_type_guid`=?",
				array_values( $key )
			);
			if( !$existing ) {
				$gBitDb->associateInsert( BIT_DB_PREFIX.'liberty_xref_group', $group );
				$counts['groups_inserted']++;
			} elseif( self::rowDiffers( $existing, $group ) ) {
				$gBitDb->associateUpdate( BIT_DB_PREFIX.'liberty_xref_group', $group, $key );
				$counts['groups_updated']++;
			} else {
				$counts['groups_unchanged']++;
			}
		}

		foreach( $pItems as $item ) {
			$key = [ 'item' => $item['item'], 'content_type_guid' => $item['content_type_guid'] ];
			$existing = $gBitDb->getRow(
				"SELECT * FROM `".BIT_DB_PREFIX."liberty_xref_item` WHERE `item`=? AND `content_type_guid`=?",
				array_values( $key )
			);
			if( !$existing ) {
				$gBitDb->associateInsert( BIT_DB_PREFIX.'liberty_xref_item', $item );
				$counts['items_inserted']++;
			} elseif( self::rowDiffers( $existing, $item ) ) {
				$gBitDb->associateUpdate( BIT_DB_PREFIX.'liberty_xref_item', $item, $key );
				$counts['items_updated']++;
			} else {
				$counts['items_unchanged']++;
			}
		}

		return $counts;
	}

	/**
	 * True if any column in $pNew differs from the loaded DB row $pExisting. Keys compared
	 * case-insensitively - ADOdb's column-name casing on fetched rows is a driver/config detail
	 * this loader shouldn't need to know about.
	 */
	private static function rowDiffers( array $pExisting, array $pNew ): bool {
		$existingLower = array_change_key_case( $pExisting );
		foreach( $pNew as $col => $val ) {
			if( ( $existingLower[strtolower( $col )] ?? null ) != $val ) {
				return true;
			}
		}
		return false;
	}
}

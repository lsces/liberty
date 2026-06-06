<?php
/**
 * @package liberty
 * @subpackage classes
 */

namespace Bitweaver\Liberty;

use Bitweaver\BitBase;

/**
 * Read-only query class for the xref schema tables.
 *
 * The xref system is defined by two DB tables before any data exists:
 *
 *   liberty_xref_group  — one row per logical group of xref slots for a content type
 *                         (e.g. 'address', 'reference', 'quantity').  The group sets
 *                         the display title, sort order, Smarty template, and role gate
 *                         for all items within it.
 *
 *   liberty_xref_item   — one row per named slot within a group (e.g. '#P', 'REQN',
 *                         'SGL').  Defines the item key, display title, cardinality
 *                         (multiple), role gate, and which Smarty template renders it.
 *
 * Neither table holds user data.  Live data lives in liberty_xref.
 *
 * Methods are split into two groups:
 *
 *   Runtime queries  — role-filtered, content-type-scoped.  Called via delegate
 *                      methods on LibertyContent (e.g. $gContent->getXrefTypeList())
 *                      or directly by page files that have already resolved
 *                      the content type guid.
 *
 *   Admin queries    — unfiltered, with usage counts.  Used by admin pages that
 *                      need the full picture across all roles and content.
 */
class LibertyXrefType extends LibertyBase {

	public function __construct() {
		parent::__construct();
	}

	/**
	 * Return all liberty_xref_item rows, optionally filtered.
	 *
	 * Each returned row is augmented with num_entries: the count of live liberty_xref
	 * rows that use that item key (across all content).  Useful for admin listings.
	 *
	 * Supported keys in $pOptionHash:
	 *   content_type_guid  — restrict to one content type
	 *   active_role        — restrict to items visible to one role_id
	 *   item               — restrict to one item key
	 *
	 * @param array|null $pOptionHash optional filter hash
	 * @return array[] liberty_xref_item rows with num_entries appended
	 */
	public static function getXrefTypeList( $pOptionHash = NULL ) {
		global $gBitSystem;

		$where     = '';
		$bindVars  = [];

		if( !empty( $pOptionHash['content_type_guid'] ) ) {
			$where     = " WHERE cxs.`content_type_guid` = ? ";
			$bindVars[] = $pOptionHash['content_type_guid'];
		}
		if( !empty( $pOptionHash['active_role'] ) ) {
			$where     = " WHERE cxs.`role_id` = ? ";
			$bindVars[] = $pOptionHash['active_role'];
		}
		if( !empty( $pOptionHash['item'] ) ) {
			$where     = " WHERE cxs.`item` = ? ";
			$bindVars[] = $pOptionHash['item'];
		}

		$query = "SELECT cxs.*
				  FROM `".BIT_DB_PREFIX."liberty_xref_item` cxs
				  $where ORDER BY cxs.`x_group`, cxs.`item`";

		$result = $gBitSystem->mDb->query( $query, $bindVars );

		$ret = [];
		while( $res = $result->fetchRow() ) {
			$res["num_entries"] = $gBitSystem->mDb->getOne(
				"SELECT COUNT(*) FROM `".BIT_DB_PREFIX."liberty_xref` WHERE `item` = ?",
				[ $res["item"] ]
			);
			$ret[] = $res;
		}

		return $ret;
	}

	/**
	 * Return the distinct content_type_guid values that have at least one group defined.
	 *
	 * @return string[]
	 */
	public static function getContentTypeGuids(): array {
		global $gBitSystem;
		$result = $gBitSystem->mDb->query(
			"SELECT DISTINCT `content_type_guid` FROM `".BIT_DB_PREFIX."liberty_xref_group` ORDER BY `content_type_guid`",
			[]
		);
		$ret = [];
		while ( $res = $result->fetchRow() ) {
			$ret[] = $res['content_type_guid'];
		}
		return $ret;
	}

	// -------------------------------------------------------------------------
	// Runtime queries — role-filtered, content-type-scoped.
	// These are the methods called from page files and delegates on LibertyContent.
	// -------------------------------------------------------------------------

	/**
	 * Return display groups (sort_order > 0) for a content type, filtered to the
	 * current user's roles.
	 *
	 * Used by add-xref pages to build the group selector.  Sort_order = 0 is the
	 * 'type' group (category markers); that is excluded here and loaded separately
	 * via getContentTypeMarkers().
	 *
	 * @param string $contentTypeGuid  e.g. 'contact', 'stockassembly'
	 * @return array[]  liberty_xref_group rows ordered by sort_order
	 */
	public static function getDisplayGroups( string $contentTypeGuid ): array {
		global $gBitSystem, $gBitUser;
		$roles    = array_keys( $gBitUser->mRoles ?? [] ) ?: [-1];
		$bindVars = array_merge( $roles, [ $gBitUser->mUserId ] );
		$result = $gBitSystem->mDb->query(
			"SELECT g.* FROM `".BIT_DB_PREFIX."liberty_xref_group` g
			 LEFT OUTER JOIN `".BIT_DB_PREFIX."users_roles_map` purm
			     ON purm.`user_id` = ".(int)($gBitUser->mUserId ?? 0)." AND purm.`role_id` = g.`role_id`
			 WHERE g.`content_type_guid` = '$contentTypeGuid' AND g.`sort_order` > 0
			   AND (g.`role_id` IN(".implode(',', array_fill(0, count($roles), '?')).") OR purm.`user_id` = ?)
			 ORDER BY g.`sort_order`",
			$bindVars
		);
		$ret = [];
		while( $res = $result->fetchRow() ) {
			$ret[] = $res;
		}
		return $ret;
	}

	/**
	 * Return sort_order=0 item slots for a content type, filtered to the current
	 * user's roles.
	 *
	 * These are top-level type/category markers (e.g. contact's $00/$02+ person/
	 * business subtypes).  Used by type-selector forms in add_business.php, edit.php
	 * and similar.
	 *
	 * @param string $contentTypeGuid
	 * @return array[]  [{item: string, name: string}, ...] ordered by item key
	 */
	public static function getTypeMarkers( string $contentTypeGuid ): array {
		global $gBitSystem, $gBitUser;
		$roles    = array_keys( $gBitUser->mRoles ?? [] ) ?: [-1];
		$bindVars = array_merge( $roles, [ $gBitUser->mUserId ] );
		$result = $gBitSystem->mDb->query(
			"SELECT g.`cross_ref_title` AS `type_name`, g.`item`
			 FROM `".BIT_DB_PREFIX."liberty_xref_item` g
			 JOIN `".BIT_DB_PREFIX."liberty_xref_group` t
			     ON t.`x_group` = g.`x_group` AND t.`content_type_guid` = '$contentTypeGuid'
			 LEFT OUTER JOIN `".BIT_DB_PREFIX."users_roles_map` purm
			     ON purm.`user_id` = ".(int)($gBitUser->mUserId ?? 0)." AND purm.`role_id` = g.`role_id`
			 WHERE g.`content_type_guid` = '$contentTypeGuid' AND t.`sort_order` = 0
			   AND (g.`role_id` IN(".implode(',', array_fill(0, count($roles), '?')).") OR purm.`user_id` = ?)
			 ORDER BY g.`item`",
			$bindVars
		);
		$ret = [];
		$cnt = 0;
		while( $res = $result->fetchRow() ) {
			$ret[$cnt]['item'] = $res['item'];
			$ret[$cnt++]['name'] = trim( $res['type_name'] );
		}
		return $ret;
	}

	/**
	 * Return available item slots for the add-xref type selector.
	 *
	 * Three modes controlled by the arguments:
	 *   $xrefTemplate set  — all items whose template matches, regardless of group
	 *   $xrefGroup > -1    — items in the group at that sort_order, excluding slots
	 *                        already filled for this content item (single-cardinality
	 *                        items that already have an active row)
	 *   $xrefGroup == -1   — same but across all groups (sort_order > 0)
	 *
	 * Returns ['list' => [item => display_name, ...], 'type' => [item => template, ...]]
	 * where template defaults to 'generic' when the DB value is empty.
	 *
	 * @param string      $contentTypeGuid
	 * @param int         $contentId     liberty_content.content_id of the current item
	 * @param int         $xrefGroup     sort_order of the target group, or -1 for all
	 * @param string|null $xrefTemplate  filter by template name instead of group
	 * @return array{list: array<string,string>, type: array<string,string>}
	 */
	public static function getAvailableItems( string $contentTypeGuid, int $contentId, int $xrefGroup = 0, ?string $xrefTemplate = null ): array {
		global $gBitSystem;
		$db = $gBitSystem->mDb;
		if( $xrefTemplate ) {
			$result = $db->query(
				"SELECT s.`cross_ref_title` AS `type_name`, s.`item`, s.`template`
				 FROM `".BIT_DB_PREFIX."liberty_xref_item` s
				 WHERE s.`content_type_guid` = '$contentTypeGuid' AND s.`template` = ?
				 ORDER BY s.`cross_ref_title`",
				[ $xrefTemplate ]
			);
		} elseif( $xrefGroup > -1 ) {
			$result = $db->query(
				"SELECT s.`cross_ref_title` AS `type_name`, s.`item`, s.`template`
				 FROM `".BIT_DB_PREFIX."liberty_xref_item` s
				 JOIN `".BIT_DB_PREFIX."liberty_xref_group` t
				     ON t.`x_group` = s.`x_group` AND t.`content_type_guid` = '$contentTypeGuid'
				 LEFT JOIN `".BIT_DB_PREFIX."liberty_xref` x
				     ON x.`item` = s.`item` AND x.`content_id` = ? AND (x.`end_date` IS NULL OR x.`end_date` > CURRENT_TIMESTAMP)
				 WHERE s.`content_type_guid` = '$contentTypeGuid' AND t.`sort_order` = ?
				   AND (x.`xref_id` IS NULL OR x.`xorder` > 0)
				 ORDER BY s.`cross_ref_title`",
				[ $contentId, $xrefGroup ]
			);
		} else {
			$result = $db->query(
				"SELECT s.`cross_ref_title` AS `type_name`, s.`item`, s.`template`
				 FROM `".BIT_DB_PREFIX."liberty_xref_item` s
				 JOIN `".BIT_DB_PREFIX."liberty_xref_group` t
				     ON t.`x_group` = s.`x_group` AND t.`content_type_guid` = '$contentTypeGuid'
				 LEFT JOIN `".BIT_DB_PREFIX."liberty_xref` x
				     ON x.`item` = s.`item` AND x.`content_id` = ? AND (x.`end_date` IS NULL OR x.`end_date` > CURRENT_TIMESTAMP)
				 WHERE s.`content_type_guid` = '$contentTypeGuid' AND t.`sort_order` > 0
				   AND (x.`xref_id` IS NULL OR x.`xorder` > 0)
				 ORDER BY s.`cross_ref_title`",
				[ $contentId ]
			);
		}
		$ret = [];
		while( $res = $result->fetchRow() ) {
			$ret['list'][$res['item']] = trim( $res['type_name'] );
			$ret['type'][$res['item']] = trim( $res['template'] ) !== '' ? trim( $res['template'] ) : 'generic';
		}
		return $ret;
	}

	/**
	 * Return the distinct template format names defined across all item slots for
	 * a content type, filtered to the current user's roles.
	 *
	 * Used by the add-xref UI to know which item template types are available.
	 * Empty template values are normalised to 'generic'.
	 *
	 * @param string $contentTypeGuid
	 * @return string[]
	 */
	public static function getTemplateFormats( string $contentTypeGuid ): array {
		global $gBitSystem, $gBitUser;
		$roles    = array_keys( $gBitUser->mRoles ?? [] ) ?: [-1];
		$bindVars = array_merge( $roles, [ $gBitUser->mUserId ] );
		$result = $gBitSystem->mDb->query(
			"SELECT DISTINCT g.`template`
			 FROM `".BIT_DB_PREFIX."liberty_xref_item` g
			 LEFT OUTER JOIN `".BIT_DB_PREFIX."users_roles_map` purm
			     ON purm.`user_id` = ".(int)($gBitUser->mUserId ?? 0)." AND purm.`role_id` = g.`role_id`
			 WHERE g.`content_type_guid` = '$contentTypeGuid'
			   AND (g.`role_id` IN(".implode(',', array_fill(0, count($roles), '?')).") OR purm.`user_id` = ?)
			 ORDER BY g.`template`",
			$bindVars
		);
		$ret = [];
		while( $res = $result->fetchRow() ) {
			$ret[] = trim( $res['template'] ) !== '' ? trim( $res['template'] ) : 'generic';
		}
		return $ret;
	}

	/**
	 * Return sort_order=0 type markers for a content item, showing which apply.
	 *
	 * Queries all item slots at sort_order=0 for the content type and left-joins
	 * liberty_xref to show which ones have an active row for the given content item.
	 * Each row includes 'content_id' (non-null when the marker is set on the item).
	 *
	 * @param string $contentTypeGuid
	 * @param int    $contentId  liberty_content.content_id of the item to check
	 * @return array[]
	 */
	public static function getContentTypeMarkers( string $contentTypeGuid, int $contentId ): array {
		global $gBitSystem, $gBitUser;
		$roles    = array_keys( $gBitUser->mRoles ?? [] ) ?: [-1];
		$bindVars = array_merge( [ $contentId ], $roles, [ $gBitUser->mUserId ] );
		$result = $gBitSystem->mDb->query(
			"SELECT r.`item`, r.`cross_ref_title`, d.`content_id`
			 FROM `".BIT_DB_PREFIX."liberty_xref_item` r
			 JOIN `".BIT_DB_PREFIX."liberty_xref_group` t
			     ON t.`x_group` = r.`x_group` AND t.`content_type_guid` = '$contentTypeGuid'
			 LEFT JOIN `".BIT_DB_PREFIX."liberty_xref` d ON d.`content_id` = ? AND d.`item` = r.`item`
			 LEFT OUTER JOIN `".BIT_DB_PREFIX."users_roles_map` purm
			     ON purm.`user_id` = ".(int)($gBitUser->mUserId ?? 0)." AND purm.`role_id` = r.`role_id`
			 WHERE r.`content_type_guid` = '$contentTypeGuid' AND t.`sort_order` = 0
			   AND (r.`role_id` IN(".implode(',', array_fill(0, count($roles), '?')).") OR purm.`user_id` = ?)
			 ORDER BY r.`item`",
			$bindVars
		);
		$ret = [];
		while( $res = $result->fetchRow() ) {
			$ret[] = $res;
		}
		return $ret;
	}

	// -------------------------------------------------------------------------
	// Admin queries — unfiltered, with usage counts.
	// -------------------------------------------------------------------------

	/**
	 * Return liberty_xref_group rows, optionally filtered by content_type_guid.
	 *
	 * Each row is augmented with num_sources: count of liberty_xref_item rows
	 * defined for that group.  Rows are ordered by content_type_guid, sort_order.
	 *
	 * @param array|null $pOptionHash  optional; supports key 'content_type_guid'
	 * @return array[]
	 */
	public static function getGroupList( $pOptionHash = NULL ): array {
		global $gBitSystem;
		$where    = '';
		$bindVars = [];
		if ( !empty( $pOptionHash['content_type_guid'] ) ) {
			$where     = " WHERE cxt.`content_type_guid` = ?";
			$bindVars[] = $pOptionHash['content_type_guid'];
		}
		$query = "SELECT cxt.* FROM `".BIT_DB_PREFIX."liberty_xref_group` cxt
				  $where ORDER BY cxt.`content_type_guid`, cxt.`sort_order`";
		$result = $gBitSystem->mDb->query( $query, $bindVars );
		$ret = [];
		while ( $res = $result->fetchRow() ) {
			$res['num_sources'] = $gBitSystem->mDb->getOne(
				"SELECT COUNT(*) FROM `".BIT_DB_PREFIX."liberty_xref_item` WHERE `x_group` = ? AND `content_type_guid` = ?",
				[ $res["x_group"], $res['content_type_guid'] ]
			);
			$ret[] = $res;
		}
		return $ret;
	}
}

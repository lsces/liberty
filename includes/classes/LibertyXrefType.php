<?php
/**
 * @package liberty
 * @subpackage classes
 */

namespace Bitweaver\Liberty;

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
 * ## Dual-guid schema (package-level + content-type-level)
 *
 * A package with multiple content types may define groups/items at two levels:
 *
 *   Package-level   — groups shared across all content types in the package.
 *                     content_type_guid = the package guid (e.g. 'stock').
 *                     Example: 'stgrp', 'supplier', 'kitlocker' in stock — these
 *                     apply equally to stockcomponent and stockassembly.
 *
 *   Content-type-level — groups specific to one content type.
 *                        content_type_guid = the content type guid
 *                        (e.g. 'stockcomponent', 'stockassembly').
 *                        Example: 'quantity', 'values' for stockcomponent.
 *
 * To cover both levels, supply $packageGuid to the constructor. Two different join
 * strategies are used, depending on what the method already knows:
 *
 *   - getTypeMarkers()/getAvailableItems()/getContentTypeMarkers()/getDisplayGroups()
 *     query items/groups generically and use the same IN(contentTypeGuid, packageGuid)
 *     filter on both sides of the item↔group JOIN — safe because that admits only this
 *     one content type's own two guid levels, never a sibling type's guid (a
 *     stockcomponent instance's filter can never match a stockassembly-only group).
 *     Handles a site whose groups were never split per content type (item rows
 *     split, but their shared group still sits at the package level) transparently.
 *   - loadContent() loads groups first (via the same IN-filter), then joins each
 *     group's own items using that specific row's own real content_type_guid —
 *     exact-match there is what prevents two guids sharing an x_group name (e.g.
 *     'quantity' on both stockcomponent and stockassembly) from cross-attributing
 *     rows to the wrong loaded group once several groups are loaded side by side.
 *
 * The stock package is the reference implementation of this pattern.
 *
 * ## Usage
 *
 * Always access via LibertyContent::xrefType(), which lazily constructs and caches
 * the instance using mContentTypeGuid and mPackageGuid (set by registerContentType()).
 * Do not instantiate directly in page code.
 *
 * Instance methods — content-type-scoped runtime queries, role-filtered.
 * Static methods  — unfiltered admin queries across all content types.
 */
class LibertyXrefType {

	public function __construct(
		private string $contentTypeGuid,
		private ?string $packageGuid = null
	) {}

	// -------------------------------------------------------------------------
	// Runtime queries — role-filtered, content-type-scoped.
	// Called via LibertyContent::xrefType()->method().
	// -------------------------------------------------------------------------

	/**
	 * Return display groups (sort_order > 0) for this content type, filtered to the
	 * current user's roles.
	 *
	 * Used by add-xref pages to build the group selector.  Sort_order = 0 is the
	 * 'type' group (category markers); that is excluded here and loaded separately
	 * via getContentTypeMarkers().
	 *
	 * @return array[]  liberty_xref_group rows ordered by sort_order
	 */
	public function getDisplayGroups(): array {
		global $gBitSystem, $gBitUser;
		$roles      = array_keys( $gBitUser->mRoles ?? [] ) ?: [-1];
		$bindVars   = array_merge( $roles, [ $gBitUser->mUserId ] );
		// Dual-guid join — see class docblock.
		$guidFilter = $this->packageGuid
			? "IN ('$this->contentTypeGuid', '$this->packageGuid')"
			: "= '$this->contentTypeGuid'";
		$result = $gBitSystem->mDb->query(
			"SELECT g.* FROM `".BIT_DB_PREFIX."liberty_xref_group` g
			 LEFT OUTER JOIN `".BIT_DB_PREFIX."users_roles_map` purm
			     ON purm.`user_id` = ".(int)($gBitUser->mUserId ?? 0)." AND purm.`role_id` = g.`role_id`
			 WHERE g.`content_type_guid` $guidFilter AND g.`sort_order` > 0
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
	 * Return item slots in the x_group='type' group (sort_order=0) for this
	 * content type, filtered to the current user's roles.
	 *
	 * These are top-level type/category markers (e.g. contact's P01/P02 person
	 * subtypes). Used by type-selector forms in add_business.php, edit.php and
	 * similar. Scoped to x_group='type' specifically, not just any sort_order=0
	 * group - sort_order=0 alone only means "hidden from the general xref grid"
	 * (see loadContent()'s own sort_order>0 filter), a property some other,
	 * non-toggleable items also want (e.g. contact's own NAME item, x_group=
	 * 'name') without being swept into this picker too — sharing 'type'
	 * instead would leak such an item into this list as a bogus toggle option.
	 *
	 * @return array[]  [{item: string, name: string}, ...] ordered by item key
	 */
	public function getTypeMarkers(): array {
		global $gBitSystem, $gBitUser;
		$roles      = array_keys( $gBitUser->mRoles ?? [] ) ?: [-1];
		$bindVars   = array_merge( $roles, [ $gBitUser->mUserId ] );
		// Dual-guid join — see class docblock.
		$guidFilter = $this->packageGuid
			? "IN ('$this->contentTypeGuid', '$this->packageGuid')"
			: "= '$this->contentTypeGuid'";
		$result = $gBitSystem->mDb->query(
			"SELECT g.`cross_ref_title` AS `type_name`, g.`item`
			 FROM `".BIT_DB_PREFIX."liberty_xref_item` g
			 JOIN `".BIT_DB_PREFIX."liberty_xref_group` t
			     ON t.`x_group` = g.`x_group` AND t.`content_type_guid` $guidFilter
			 LEFT OUTER JOIN `".BIT_DB_PREFIX."users_roles_map` purm
			     ON purm.`user_id` = ".(int)($gBitUser->mUserId ?? 0)." AND purm.`role_id` = g.`role_id`
			 WHERE g.`content_type_guid` = '$this->contentTypeGuid' AND t.`x_group` = 'type' AND t.`sort_order` = 0
			   AND (g.`role_id` IN(".implode(',', array_fill(0, count($roles), '?')).") OR purm.`user_id` = ?)
			 ORDER BY g.`sort_order`, g.`item`",
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
	 * Return the xref_id actually stored for each of this content item's own
	 * type-marker rows, keyed by item code — the "what's actually set" counterpart
	 * to getTypeMarkers() above (which returns what's *possible*, from schema
	 * metadata only, not what's stored for any particular content item).
	 *
	 * Type-marker items live in the x_group='type' group (sort_order=0), which
	 * loadContent() only loads groups with sort_order > 0 for — so they never
	 * appear in a loaded LibertyXrefContent (mXrefInfo), by design, not an
	 * omission. Callers managing a content item's own type markers (e.g.
	 * Contact's P01/P02/B01-B04 checkboxes) need this instead of reading
	 * mXrefInfo, and should use this rather than querying `liberty_xref`
	 * directly. Scoped to x_group='type' specifically (not just any
	 * sort_order=0 group) — see getTypeMarkers()'s own docblock for why.
	 *
	 * @param  int $pContentId
	 * @return array<string,int>  item code => xref_id
	 */
	public function getTypeMarkerXrefs( int $pContentId ): array {
		global $gBitSystem;
		// Dual-guid join — see class docblock. A false negative here makes a caller's
		// diff logic think nothing is set yet and re-insert an existing item as new.
		$guidFilter = $this->packageGuid
			? "IN ('$this->contentTypeGuid', '$this->packageGuid')"
			: "= '$this->contentTypeGuid'";
		$result = $gBitSystem->mDb->query(
			"SELECT x.`item`, x.`xref_id`
			 FROM `".BIT_DB_PREFIX."liberty_xref` x
			 JOIN `".BIT_DB_PREFIX."liberty_xref_item` i ON i.`item` = x.`item` AND i.`content_type_guid` $guidFilter
			 JOIN `".BIT_DB_PREFIX."liberty_xref_group` g ON g.`x_group` = i.`x_group` AND g.`content_type_guid` $guidFilter
			 WHERE x.`content_id` = ? AND g.`x_group` = 'type' AND g.`sort_order` = 0",
			[ $pContentId ]
		);
		$ret = [];
		while( $row = $result->fetchRow() ) {
			$ret[$row['item']] = (int)$row['xref_id'];
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
	 * When a packageGuid was supplied at construction, item/group rows for both guids
	 * are included; each item only joins its own group (guid-consistent join).
	 *
	 * @param int         $contentId     liberty_content.content_id of the current item
	 * @param int         $xrefGroup     sort_order of the target group, or -1 for all
	 * @param string|null $xrefTemplate  filter by template name instead of group
	 * @return array{list: array<string,string>, type: array<string,string>}
	 */
	public function getAvailableItems( int $contentId, int $xrefGroup = 0, ?string $xrefTemplate = null ): array {
		global $gBitSystem;

		$db = $gBitSystem->mDb;
		$guidFilter = $this->packageGuid
			? "IN ('$this->contentTypeGuid', '$this->packageGuid')"
			: "= '$this->contentTypeGuid'";
		// s.multiple = -1 = read-only (see liberty/MANUAL.md's Data model section) — never
		// offer a read-only item as something to add. multiple = -2 (mutually exclusive)
		// stays offered — that's a normal, still-addable item, just one that evicts a
		// sibling on store.
		if( $xrefTemplate ) {
			$result = $db->query(
				"SELECT s.`cross_ref_title` AS `type_name`, s.`item`, s.`template`
				 FROM `".BIT_DB_PREFIX."liberty_xref_item` s
				 WHERE s.`content_type_guid` $guidFilter AND s.`template` = ? AND s.`multiple` <> -1
				 ORDER BY s.`sort_order`, s.`cross_ref_title`",
				[ $xrefTemplate ]
			);
		} elseif( $xrefGroup > -1 ) {
			$result = $db->query(
				"SELECT s.`cross_ref_title` AS `type_name`, s.`item`, s.`template`
				 FROM `".BIT_DB_PREFIX."liberty_xref_item` s
				 JOIN `".BIT_DB_PREFIX."liberty_xref_group` t
				     ON t.`x_group` = s.`x_group` AND t.`content_type_guid` $guidFilter
				 LEFT JOIN `".BIT_DB_PREFIX."liberty_xref` x
				     ON x.`item` = s.`item` AND x.`content_id` = ? AND (x.`end_date` IS NULL OR x.`end_date` > CURRENT_TIMESTAMP)
				 WHERE s.`content_type_guid` $guidFilter AND t.`sort_order` = ?
				   AND (x.`xref_id` IS NULL OR x.`xorder` > 0) AND s.`multiple` <> -1
				 ORDER BY s.`sort_order`, s.`cross_ref_title`",
				[ $contentId, $xrefGroup ]
			);
		} else {
			$result = $db->query(
				"SELECT s.`cross_ref_title` AS `type_name`, s.`item`, s.`template`
				 FROM `".BIT_DB_PREFIX."liberty_xref_item` s
				 JOIN `".BIT_DB_PREFIX."liberty_xref_group` t
				     ON t.`x_group` = s.`x_group` AND t.`content_type_guid` $guidFilter
				 LEFT JOIN `".BIT_DB_PREFIX."liberty_xref` x
				     ON x.`item` = s.`item` AND x.`content_id` = ? AND (x.`end_date` IS NULL OR x.`end_date` > CURRENT_TIMESTAMP)
				 WHERE s.`content_type_guid` $guidFilter AND t.`sort_order` > 0
				   AND (x.`xref_id` IS NULL OR x.`xorder` > 0) AND s.`multiple` <> -1
				 ORDER BY s.`sort_order`, s.`cross_ref_title`",
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
	 * Load all xref groups and their rows for a specific content item.
	 *
	 * Replaces the former LibertyXrefInfo::load() + LibertyXrefGroup::loadXrefs()
	 * two-class pattern. Returns a LibertyXrefContent whose mGroups array is keyed
	 * by x_group name. A synthetic 'history' group (sort_order 999) is appended
	 * when any expired rows are found.
	 *
	 * Each group's mXrefs array holds LibertyXref instances built via
	 * LibertyXref::fromRow(). Templates continue to use {$xrefInfo.xkey} dot
	 * notation — LibertyXref::ArrayAccess maps this transparently.
	 *
	 * Packages that enrich rows after loading (e.g. resolving contact titles)
	 * should override loadXrefInfo() in their class, call the parent, then walk
	 * $this->mXrefInfo->mGroups and mutate as needed.
	 *
	 * Rows within a group are ordered by liberty_xref_item.sort_order first (added
	 * 2026-08-21 — previously the query ignored that column entirely and rows fell
	 * back to alphabetical-by-item-code, which is why an item-level sort_order was
	 * effectively dead everywhere despite the column existing and several packages
	 * already populating it). Ties (the common case — most items still default to
	 * sort_order=0) fall back to item code, then xorder, matching prior behaviour
	 * exactly, so this is backward compatible for every group that hasn't set it.
	 *

	 * @param int $contentId  liberty_content.content_id to load rows for
	 * @return LibertyXrefContent
	 */
	public function loadContent( int $contentId ): LibertyXrefContent {
		global $gBitSystem, $gBitUser;

		$db          = $gBitSystem->mDb;
		$roles       = array_keys( $gBitUser->mRoles ?? [] ) ?: [-1];
		$userId      = (int)( $gBitUser->mUserId ?? 0 );
		$guidFilter  = $this->packageGuid
			? "IN ('$this->contentTypeGuid', '$this->packageGuid')"
			: "= '$this->contentTypeGuid'";
		$rolePlaceholders = implode( ',', array_fill( 0, count( $roles ), '?' ) );

		$content    = new LibertyXrefContent();
		$allHistory = [];

		$groupResult = $db->query(
			"SELECT g.`x_group`, g.`title`, g.`sort_order`, g.`template`, g.`role_id`, g.`content_type_guid` AS group_guid
			 FROM `".BIT_DB_PREFIX."liberty_xref_group` g
			 LEFT OUTER JOIN `".BIT_DB_PREFIX."users_roles_map` purm
			     ON purm.`user_id` = $userId AND purm.`role_id` = g.`role_id`
			 WHERE g.`content_type_guid` $guidFilter AND g.`sort_order` > 0
			   AND (g.`role_id` IN($rolePlaceholders) OR purm.`user_id` = ?)
			 ORDER BY g.`sort_order`",
			array_merge( $roles, [ $userId ] )
		);
		if( !$groupResult ) return $content;

		while( $groupRow = $groupResult->fetchRow() ) {
			$group     = new LibertyXrefGroup( $groupRow, $this->contentTypeGuid, $this->packageGuid );
			$xGroup    = $groupRow['x_group'];
			$groupGuid = $groupRow['group_guid'];
			$rowResult = $db->query(
				"SELECT x.`xref_id`, x.`item`, x.`xref`, x.`xkey`, x.`xkey_ext`,
				        x.`xorder`, x.`data`, x.`start_date`, x.`end_date`, x.`last_update_date`, x.`entry_date`,
				        s.`template`, s.`cross_ref_href`, s.`multiple`, s.`data` AS `item_data`,
				        CASE WHEN x.`xorder` = 0 THEN s.`cross_ref_title`
				             ELSE s.`cross_ref_title` || '-' || x.`xorder` END AS xref_title,
				        CASE WHEN x.`end_date` IS NOT NULL AND x.`end_date` < ? THEN 'history'
				             ELSE s.`x_group` END AS type_source,
				        pc.`add1` || ',' || pc.`add2` || ',' || pc.`add4` || ',' || pc.`town` AS address,
				        lc_linked.`title` AS linked_title,
				        lc_linked.`data` AS linked_data
				 FROM `".BIT_DB_PREFIX."liberty_xref` x
				 JOIN `".BIT_DB_PREFIX."liberty_xref_item` s
				     ON s.`item` = x.`item`
				     AND s.`content_type_guid` = '$groupGuid'
				     AND s.`x_group` = '$xGroup'
				 LEFT JOIN `".BIT_DB_PREFIX."address_postcode` pc ON pc.`postcode` = x.`xkey`
				 LEFT JOIN `".BIT_DB_PREFIX."liberty_content` lc_linked ON lc_linked.`content_id` = x.`xref`
				 LEFT OUTER JOIN `".BIT_DB_PREFIX."users_roles_map` purm
				     ON purm.`user_id` = $userId AND purm.`role_id` = s.`role_id`
				 WHERE x.`content_id` = ?
				   AND (s.`role_id` IN($rolePlaceholders) OR purm.`user_id` = ?)
				 ORDER BY s.`sort_order`, x.`item`, x.`xorder`",
				array_merge( [ $db->NOW(), $contentId ], $roles, [ $userId ] )
			);
			if( $rowResult ) {
				while( $row = $rowResult->fetchRow() ) {
					$xref = LibertyXref::fromRow( $row );
					if( $row['type_source'] === 'history' ) {
						$allHistory[] = $xref;
					} else {
						$group->mXrefs[] = $xref;
					}
				}
			}
			$content->mGroups[$xGroup] = $group;
		}

		// The synthetic history group is built here in PHP rather than fetched by the
		// role-filtered query above, so it never goes through that query's role_id
		// check — admin-only visibility has to be enforced explicitly instead.
		if( !empty( $allHistory ) && $gBitUser->isAdmin() ) {
			$historyGroup          = new LibertyXrefGroup(
				[ 'x_group' => 'history', 'title' => 'History', 'sort_order' => 999, 'template' => null, 'role_id' => 0 ],
				$this->contentTypeGuid,
				$this->packageGuid
			);
			$historyGroup->mXrefs  = $allHistory;
			$content->mGroups['history'] = $historyGroup;
		}

		return $content;
	}

	/**
	 * Return the distinct template format names defined across all item slots for
	 * this content type, filtered to the current user's roles.
	 *
	 * Used by the add-xref UI to know which item template types are available.
	 * Empty template values are normalised to 'generic'.
	 *
	 * @return string[]
	 */
	public function getTemplateFormats(): array {
		global $gBitSystem, $gBitUser;
		$roles    = array_keys( $gBitUser->mRoles ?? [] ) ?: [-1];
		$bindVars = array_merge( $roles, [ $gBitUser->mUserId ] );
		$result = $gBitSystem->mDb->query(
			"SELECT DISTINCT g.`template`
			 FROM `".BIT_DB_PREFIX."liberty_xref_item` g
			 LEFT OUTER JOIN `".BIT_DB_PREFIX."users_roles_map` purm
			     ON purm.`user_id` = ".(int)($gBitUser->mUserId ?? 0)." AND purm.`role_id` = g.`role_id`
			 WHERE g.`content_type_guid` = '$this->contentTypeGuid'
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
	 * Return x_group='type' markers (sort_order=0) for a content item, showing
	 * which apply.
	 *
	 * Queries all item slots in the 'type' group for this content type and
	 * left-joins liberty_xref to show which ones have an active row for the
	 * given content item. Each row includes 'content_id' (non-null when the
	 * marker is set on the item). Scoped to x_group='type' specifically — see
	 * getTypeMarkers()'s own docblock for why.
	 *
	 * @param int $contentId  liberty_content.content_id of the item to check
	 * @return array[]
	 */
	public function getContentTypeMarkers( int $contentId ): array {
		global $gBitSystem, $gBitUser;
		$roles      = array_keys( $gBitUser->mRoles ?? [] ) ?: [-1];
		$bindVars   = array_merge( [ $contentId ], $roles, [ $gBitUser->mUserId ] );
		// Dual-guid join — see class docblock.
		$guidFilter = $this->packageGuid
			? "IN ('$this->contentTypeGuid', '$this->packageGuid')"
			: "= '$this->contentTypeGuid'";
		$result = $gBitSystem->mDb->query(
			"SELECT r.`item`, r.`cross_ref_title`, d.`content_id`
			 FROM `".BIT_DB_PREFIX."liberty_xref_item` r
			 JOIN `".BIT_DB_PREFIX."liberty_xref_group` t
			     ON t.`x_group` = r.`x_group` AND t.`content_type_guid` $guidFilter
			 LEFT JOIN `".BIT_DB_PREFIX."liberty_xref` d ON d.`content_id` = ? AND d.`item` = r.`item`
			 LEFT OUTER JOIN `".BIT_DB_PREFIX."users_roles_map` purm
			     ON purm.`user_id` = ".(int)($gBitUser->mUserId ?? 0)." AND purm.`role_id` = r.`role_id`
			 WHERE r.`content_type_guid` = '$this->contentTypeGuid' AND t.`x_group` = 'type' AND t.`sort_order` = 0
			   AND (r.`role_id` IN(".implode(',', array_fill(0, count($roles), '?')).") OR purm.`user_id` = ?)
			 ORDER BY r.`sort_order`, r.`item`",
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

	/**
	 * Return every liberty_xref_item row registered for a content type, across
	 * every group - not just the 'type' group (see getTypeMarkers()), and with
	 * no role/permission filtering or per-content-item exclusion (see
	 * getAvailableItems()) - a plain content-type-wide item catalog, for a
	 * picker/summary that wants to know about every registered item regardless
	 * of who's asking or what's already set on any one content item. Found
	 * hand-rolled identically in health's list_item.php and
	 * HealthIndexSummary.php.
	 *
	 * @param string $pContentTypeGuid
	 * @return array[]  Full liberty_xref_item rows, ordered by sort_order then item.
	 */
	public static function getItemList( string $pContentTypeGuid ): array {
		global $gBitSystem;
		$result = $gBitSystem->mDb->query(
			"SELECT * FROM `".BIT_DB_PREFIX."liberty_xref_item`
			 WHERE `content_type_guid` = ? ORDER BY `sort_order`, `item`",
			[ $pContentTypeGuid ]
		);
		$ret = [];
		while ( $res = $result->fetchRow() ) {
			$ret[] = $res;
		}
		return $ret;
	}
}

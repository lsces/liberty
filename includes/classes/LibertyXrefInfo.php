<?php
/**
 * @package liberty
 * @subpackage classes
 */

namespace Bitweaver\Liberty;

/**
 * Top-level container for all xref groups belonging to one content item.
 *
 * LibertyXrefInfo::load() is the single entry point for fetching the complete
 * xref picture for a content item.  It:
 *   1. Queries liberty_xref_group for all groups defined for the content type
 *      (sort_order > 0 only — sort_order 0 is reserved for the 'type' group
 *       which is loaded separately by loadXrefTypeList()).
 *   2. Applies the current user's role filter on each group's role_id gate.
 *   3. Creates a LibertyXrefGroup for each row and calls loadXrefs() on it.
 *   4. Collects any expired rows returned by loadXrefs() and, if any exist,
 *      synthesises a 'history' group (sort_order 999) to hold them.
 *
 * After load(), $mGroups is a keyed array of LibertyXrefGroup objects ordered
 * by sort_order.  Templates iterate this directly:
 *
 *   {foreach $gXrefInfo->mGroups as $xrefGroup}
 *       {include file=$gContent->getXrefListTemplate($xrefGroup->mTemplate) xrefGroup=$xrefGroup}
 *   {/foreach}
 *
 * Package-level enrichment (resolving contact titles, component details, etc.)
 * is done in the package class's loadXrefInfo() override after calling
 * parent::loadXrefInfo(), by walking $mXrefInfo->mGroups and mutating rows.
 */
class LibertyXrefInfo {
	/** content_type_guid this info object was built for */
	public string $mContentTypeGuid;
	/** optional package-level guid whose xref_group/xref_item rows are also loaded (e.g. 'stock') */
	public ?string $mPackageGuid;
	/** @var LibertyXrefGroup[] keyed by x_group, ordered by sort_order */
	public array $mGroups = [];

	/**
	 * @param string      $contentTypeGuid e.g. 'stockassembly', 'contact'
	 * @param string|null $packageGuid     e.g. 'stock' — additional guid whose xref_group rows are merged in
	 */
	public function __construct( string $contentTypeGuid, ?string $packageGuid = null ) {
		$this->mContentTypeGuid = $contentTypeGuid;
		$this->mPackageGuid     = $packageGuid;
	}

	/**
	 * Load all visible xref groups and their rows for a content item.
	 *
	 * Populates $this->mGroups.  Safe to call multiple times — each call
	 * rebuilds mGroups from scratch.
	 *
	 * @param int $contentId  liberty_content.content_id
	 */
	public function load( int $contentId ): void {
		global $gBitDb, $gBitUser;

		$roles    = array_keys( $gBitUser->mRoles ?? [] ) ?: [-1];
		$userId   = $gBitUser->mUserId;
		$bindVars = array_merge( $roles, [ $userId ] );

		$guidFilter = $this->mPackageGuid
			? "g.`content_type_guid` IN ('{$this->mContentTypeGuid}', '{$this->mPackageGuid}')"
			: "g.`content_type_guid` = '{$this->mContentTypeGuid}'";

		$sql = "SELECT g.`x_group`, g.`title`, g.`sort_order`, g.`template`, g.`role_id`
				FROM `" . BIT_DB_PREFIX . "liberty_xref_group` g
				LEFT OUTER JOIN `" . BIT_DB_PREFIX . "users_roles_map` purm
					ON purm.`user_id` = $userId AND purm.`role_id` = g.`role_id`
				WHERE $guidFilter
				AND g.`sort_order` > 0
				AND (g.`role_id` IN(" . implode( ',', array_fill( 0, count( $roles ), '?' ) ) . ") OR purm.`user_id` = ?)
				ORDER BY g.`sort_order`";

		$result = $gBitDb->query( $sql, $bindVars );
		if( !$result ) return;

		$allHistory = [];
		while( $row = $result->fetchRow() ) {
			$group      = new LibertyXrefGroup( $row, $this->mContentTypeGuid, $this->mPackageGuid );
			$allHistory = array_merge( $allHistory, $group->loadXrefs( $contentId ) );
			$this->mGroups[$row['x_group']] = $group;
		}

		if( !empty( $allHistory ) ) {
			$historyGroup = new LibertyXrefGroup(
				[ 'x_group' => 'history', 'title' => 'History', 'sort_order' => 999, 'template' => null, 'role_id' => 0 ],
				$this->mContentTypeGuid,
				$this->mPackageGuid
			);
			$historyGroup->mXrefs          = $allHistory;
			$this->mGroups['history']      = $historyGroup;
		}
	}
}

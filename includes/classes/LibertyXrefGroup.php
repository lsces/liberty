<?php
/**
 * @package liberty
 * @subpackage classes
 */

namespace Bitweaver\Liberty;

/**
 * One xref group for a specific content item.
 *
 * A group is identified by (content_type_guid, x_group).  Its metadata (title,
 * sort order, Smarty template, role gate) comes from liberty_xref_group; its live
 * data rows come from liberty_xref filtered to that group's items.
 *
 * loadXrefs() separates rows into two buckets:
 *   - active rows  → stored in mXrefs, rendered by the group's template
 *   - expired rows → returned to LibertyXrefInfo, which collects them into
 *                    a synthetic 'history' group (sort_order 999)
 *
 * Instances are created by LibertyXrefInfo::load(); do not instantiate directly
 * except in tests or package-level loadXrefInfo() overrides.
 *
 * Template access: group templates receive the whole object as $xrefGroup and
 * iterate $xrefGroup->mXrefs.  The first two lines of every group template must be:
 *
 *   {assign var=xrefAllowEdit value=$allow_edit|default:false}
 *   {assign var=isHistory value=($xrefGroup->mXGroup eq 'history')}
 */
class LibertyXrefGroup extends LibertyBase {
	/** x_group key (e.g. 'address', 'reference', 'quantity', 'history') */
	public string  $mXGroup;
	/** content_type_guid this group belongs to (e.g. 'contact', 'stockmovement') */
	public string  $mContentTypeGuid;
	/** display title from liberty_xref_group.title */
	public string  $mTitle;
	/** render order; liberty_xref_group.sort_order; history group is always 999 */
	public int     $mSortOrder;
	/** Smarty template name from liberty_xref_group.template; null falls back to liberty/list_xref.tpl */
	public ?string $mTemplate;
	/** role_id gate from liberty_xref_group.role_id; 0 = visible to all */
	public int     $mRoleId;
	/** optional package-level guid — xref_item rows with this guid are also matched */
	public ?string $mPackageGuid;
	/** @var array[] active liberty_xref data rows for the current content item */
	public array   $mXrefs = [];

	/**
	 * @param array       $groupRow        row from liberty_xref_group (x_group, title, sort_order, template, role_id)
	 * @param string      $contentTypeGuid content type this group belongs to
	 * @param string|null $packageGuid     optional package-level guid (e.g. 'stock')
	 */
	public function __construct( array $groupRow, string $contentTypeGuid, ?string $packageGuid = null ) {
		parent::__construct();
		$this->mXGroup          = $groupRow['x_group'];
		$this->mContentTypeGuid = $contentTypeGuid;
		$this->mPackageGuid     = $packageGuid;
		$this->mTitle           = $groupRow['title'];
		$this->mSortOrder       = (int)( $groupRow['sort_order'] ?? 0 );
		$this->mTemplate        = !empty( $groupRow['template'] ) ? trim( $groupRow['template'] ) : null;
		$this->mRoleId          = (int)( $groupRow['role_id'] ?? 0 );
	}

	/**
	 * Load liberty_xref rows for this group under a given content item.
	 *
	 * Queries liberty_xref joined to liberty_xref_item (scoped to this group and
	 * content type) and address_postcode (for address groups).  Role filtering is
	 * applied against the current user's roles; anonymous gets role_id -1.
	 *
	 * Rows whose end_date is in the past are classified as 'history' and returned
	 * separately so LibertyXrefInfo can accumulate them into the synthetic history
	 * group.  Active rows are stored directly in $this->mXrefs.
	 *
	 * Packages that need to enrich rows (e.g. resolving contact titles from xref
	 * content_ids) should do so by overriding loadXrefInfo() in their own class
	 * after calling parent::loadXrefInfo(), not by modifying this method.
	 *
	 * @param int $contentId  liberty_content.content_id to fetch rows for
	 * @return array[]        expired rows (type_source='history') for the caller to collect
	 */
	public function loadXrefs( int $contentId ): array {
		global $gBitUser;

		if( empty( $this->mContentTypeGuid ) ) {
			$this->mErrors[] = 'LibertyXrefGroup::load() requires mContentTypeGuid — cannot scope liberty_xref_item query without it.';
			return [];
		}

		$roles    = array_keys( $gBitUser->mRoles ?? [] ) ?: [-1];
		$userId   = $gBitUser->mUserId;
		$bindVars = array_merge( [ $this->mDb->NOW(), $contentId ], $roles, [ $userId ] );

		$guidFilter = $this->mPackageGuid
			? "IN ('{$this->mContentTypeGuid}', '{$this->mPackageGuid}')"
			: "= '{$this->mContentTypeGuid}'";

		$sql = "SELECT x.`xref_id`, x.`item`, x.`xref`, x.`xkey`, x.`xkey_ext`,
					x.`xorder`, x.`data`, x.`start_date`, x.`end_date`, x.`last_update_date`,
					s.`template`, s.`cross_ref_href`,
					CASE WHEN x.`xorder` = 0 THEN s.`cross_ref_title`
						 ELSE s.`cross_ref_title` || '-' || x.`xorder` END AS xref_title,
					CASE WHEN x.`end_date` IS NOT NULL AND x.`end_date` < ? THEN 'history'
						 ELSE s.`x_group` END AS type_source,
					pc.`add1` || ',' || pc.`add2` || ',' || pc.`add4` || ',' || pc.`town` AS address
				FROM `" . BIT_DB_PREFIX . "liberty_xref` x
				JOIN `" . BIT_DB_PREFIX . "liberty_xref_item` s
					ON s.`item` = x.`item`
					AND s.`content_type_guid` $guidFilter
					AND s.`x_group` = '{$this->mXGroup}'
				LEFT JOIN `" . BIT_DB_PREFIX . "address_postcode` pc ON pc.`postcode` = x.`xkey`
				LEFT OUTER JOIN `" . BIT_DB_PREFIX . "users_roles_map` purm
					ON purm.`user_id` = $userId AND purm.`role_id` = s.`role_id`
				WHERE x.`content_id` = ?
				AND (s.`role_id` IN(" . implode( ',', array_fill( 0, count( $roles ), '?' ) ) . ") OR purm.`user_id` = ?)
				ORDER BY x.`item`, x.`xorder`";

		$historyRows = [];
		$result = $this->mDb->query( $sql, $bindVars );
		if( $result ) {
			while( $row = $result->fetchRow() ) {
				if( $row['type_source'] === 'history' ) {
					$historyRows[] = $row;
				} else {
					$this->mXrefs[] = $row;
				}
			}
		}
		return $historyRows;
	}
}

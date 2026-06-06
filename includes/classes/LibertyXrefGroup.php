<?php
/**
 * @package liberty
 * @subpackage classes
 */

namespace Bitweaver\Liberty;

/**
 * One xref group (content_type_guid + x_group) with its raw xref data rows for a content item.
 * Defined by liberty_xref_item rows sharing this x_group; data loaded from liberty_xref.
 * Active items land in mXrefs; expired items (type_source='history') are returned by load()
 * for LibertyXrefInfo to collect into the synthetic history group.
 */
class LibertyXrefGroup extends LibertyBase {
	public string  $mXGroup;
	public string  $mContentTypeGuid;
	public string  $mTitle;
	public int     $mSortOrder;
	public ?string $mTemplate;
	public int     $mRoleId;
	/** @var array[] active xref data rows for the current content item */
	public array   $mXrefs = [];

	public function __construct( array $groupRow, string $contentTypeGuid ) {
		parent::__construct();
		$this->mXGroup          = $groupRow['x_group'];
		$this->mContentTypeGuid = $contentTypeGuid;
		$this->mTitle           = $groupRow['title'];
		$this->mSortOrder       = (int)( $groupRow['sort_order'] ?? 0 );
		$this->mTemplate        = !empty( $groupRow['template'] ) ? trim( $groupRow['template'] ) : null;
		$this->mRoleId          = (int)( $groupRow['role_id'] ?? 0 );
	}

	/**
	 * Load xref rows for this group. Active rows go into mXrefs.
	 * Expired rows (type_source='history') are returned for LibertyXrefInfo to collect.
	 * Requires mContentTypeGuid — fails loudly if not set.
	 *
	 * @return array expired rows to be added to the history group
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
					AND s.`content_type_guid` = '{$this->mContentTypeGuid}'
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

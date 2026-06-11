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
 *   - expired rows → returned to the caller, which collects them into
 *                    a synthetic 'history' group (sort_order 999)
 *
 * Instances are created by LibertyXrefType::loadContent(); do not instantiate
 * directly except in tests or package-level loadXrefInfo() overrides.
 *
 * Template access: group templates receive the whole object as $xrefGroup and
 * iterate $xrefGroup->mXrefs.  The first two lines of every group template must be:
 *
 *   {assign var=xrefAllowEdit value=$allow_edit|default:false}
 *   {assign var=isHistory value=($xrefGroup->mXGroup eq 'history')}
 */
class LibertyXrefGroup {
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
	/** @var LibertyXref[] active xref rows for the current content item */
	public array   $mXrefs = [];

	/**
	 * @param array       $groupRow        row from liberty_xref_group (x_group, title, sort_order, template, role_id)
	 * @param string      $contentTypeGuid content type this group belongs to
	 * @param string|null $packageGuid     optional package-level guid (e.g. 'stock')
	 */
	public function __construct( array $groupRow, string $contentTypeGuid, ?string $packageGuid = null ) {
		$this->mXGroup          = $groupRow['x_group'];
		$this->mContentTypeGuid = $contentTypeGuid;
		$this->mPackageGuid     = $packageGuid;
		$this->mTitle           = $groupRow['title'];
		$this->mSortOrder       = (int)( $groupRow['sort_order'] ?? 0 );
		$this->mTemplate        = !empty( $groupRow['template'] ) ? trim( $groupRow['template'] ) : null;
		$this->mRoleId          = (int)( $groupRow['role_id'] ?? 0 );
	}

}

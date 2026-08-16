<?php
/**
 * @package liberty
 * @subpackage classes
 */

namespace Bitweaver\Liberty;

/**
 * Data container for all xref groups loaded for a specific content item.
 *
 * Produced by LibertyXrefType::loadContent(). Holds one LibertyXrefGroup per
 * display group (sort_order > 0) plus a synthetic 'history' group when expired
 * rows exist. Groups are keyed by x_group name.
 *
 * Packages that need to enrich rows (e.g. resolving contact titles from xref
 * content_ids) should override loadXrefInfo() in their own class, call the
 * parent, then walk $this->mXrefInfo->mGroups and mutate as needed.
 *
 * Template access: {foreach $gXrefInfo->mGroups as $xrefGroup}
 */
class LibertyXrefContent {
	/** @var LibertyXrefGroup[] Loaded groups, keyed by x_group name */
	public array $mGroups = [];

	/**
	 * Every xref_id for a given item tag, scanning already-loaded groups — no
	 * fresh query, just a lookup into data loadContent() already fetched.
	 *
	 * Returns an array (not a single id) because an item tag can be multiple=1
	 * (several rows sharing one item code, e.g. FoodAssembly's ingredient rows)
	 * — for a multiple=0 item (a type marker or single-value field, e.g. Food's
	 * own REM/DUID/PFID) this is just a single-element or empty array.
	 *
	 * @return int[]
	 */
	public function findByItem( string $pItem ): array {
		$ret = [];
		foreach( $this->mGroups as $group ) {
			foreach( $group->mXrefs as $xref ) {
				if( $xref['item'] === $pItem ) {
					$ret[] = (int)$xref['xref_id'];
				}
			}
		}
		return $ret;
	}
}

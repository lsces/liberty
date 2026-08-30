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

	/**
	 * The first loaded row for a given item tag, scanning already-loaded groups —
	 * no fresh query, same spirit as findByItem() but returning the row itself
	 * (for reading xkey/xkey_ext/data/xref) rather than just its xref_id. For a
	 * single-cardinality item (multiple=0) this is unambiguous; for a multiple=1
	 * item this is only the first row, same single-row assumption as allItems().
	 *
	 * @return LibertyXref|null
	 */
	public function findRowByItem( string $pItem ): ?LibertyXref {
		foreach( $this->mGroups as $group ) {
			foreach( $group->mXrefs as $xref ) {
				if( $xref['item'] === $pItem ) {
					return $xref;
				}
			}
		}
		return null;
	}

	/**
	 * Every loaded xref_id keyed by its item code — scanning already-loaded
	 * groups, no fresh query, same spirit as findByItem() but for callers that
	 * need to see the whole set at once (e.g. diffing a caller-submitted item
	 * list against what's currently stored) rather than one item at a time.
	 *
	 * Single-cardinality assumption: for a multiple=1 item (several rows can
	 * share one item code) only the last-loaded row's xref_id survives here —
	 * use findByItem() instead for those, this is for type-marker-style
	 * single-value item sets.
	 *
	 * @return array<string,int>  item code => xref_id
	 */
	public function allItems(): array {
		$ret = [];
		foreach( $this->mGroups as $group ) {
			foreach( $group->mXrefs as $xref ) {
				$ret[$xref['item']] = (int)$xref['xref_id'];
			}
		}
		return $ret;
	}
}

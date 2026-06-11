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
}

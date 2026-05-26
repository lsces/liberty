<?php
/**
 * @package liberty
 * @subpackage classes
 */

namespace Bitweaver\Liberty;

use Bitweaver\BitBase;

class LibertyXrefType extends LibertyBase {

	public function __construct() {
		parent::__construct();
	}

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
				"SELECT COUNT(*) FROM `".BIT_DB_PREFIX."liberty_xref` WHERE `source` = ?",
				[ $res["source"] ]
			);
			$ret[] = $res;
		}

		return $ret;
	}

	/**
	 * Returns the distinct content_type_guid values present in liberty_xref_group.
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
	 * Returns liberty_xref_group rows, optionally filtered by content_type_guid.
	 * Each row includes num_sources: count of sources defined for that group.
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

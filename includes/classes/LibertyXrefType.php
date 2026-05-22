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

		if( !empty( $pOptionHash['package'] ) ) {
			$where     = " WHERE cxs.`package` = ? ";
			$bindVars[] = $pOptionHash['package'];
		}
		if( !empty( $pOptionHash['active_role'] ) ) {
			$where     = " WHERE cxs.`role_id` = ? ";
			$bindVars[] = $pOptionHash['active_role'];
		}
		if( !empty( $pOptionHash['source'] ) ) {
			$where     = " WHERE cxs.`source` = ? ";
			$bindVars[] = $pOptionHash['source'];
		}

		$query = "SELECT cxs.*
				  FROM `".BIT_DB_PREFIX."liberty_xref_source` cxs
				  $where ORDER BY cxs.`xref_type`, cxs.`source`";

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
}

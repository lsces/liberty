<?php
/**
 * @package liberty
 * @subpackage classes
 */

namespace Bitweaver\Liberty;

use Bitweaver\BitBase;
use Bitweaver\BitDate;

class LibertyXref extends LibertyBase {
	public $mType;
	public $mItem;
	public $mXrefId;
	public $mContentId;
	public $mDate;
	protected $mContentTypeGuid = '';

	public function __construct( $iXrefId = NULL ) {
		$this->mXrefId = NULL;
		$this->mItem = NULL;
		parent::__construct();
		if( $iXrefId ) {
			$this->load( $iXrefId );
		}

		$this->mDate = new BitDate();
		$this->mDate->get_display_offset();
	}

	public function isValid() {
		return $this->verifyId( $this->mXrefId );
	}

	public function load( $pXrefId = NULL ) {
		if( BitBase::verifyId( $pXrefId ) ) {
			$guidFilter  = !empty( $this->mContentTypeGuid ) ? "AND s.`content_type_guid` = ?" : '';
			$bindVars    = !empty( $this->mContentTypeGuid ) ? [ $this->mContentTypeGuid, $pXrefId ] : [ $pXrefId ];
			$sql = "SELECT x.*, CASE
					WHEN x.`xorder` = 0 THEN s.`cross_ref_title`
					ELSE s.`cross_ref_title` || '-' || x.`xorder` END
					AS source_title, s.`item`, s.`x_group`,
					CASE WHEN x.`start_date` IS NULL THEN 'y' ELSE 'n' END AS `ignore_start_date`,
					CASE WHEN x.`end_date` IS NULL THEN 'y' ELSE 'n' END AS `ignore_end_date`,
					s.`cross_ref_title` AS `template_title`, s.`template`
					FROM `".BIT_DB_PREFIX."liberty_xref` x
					JOIN `".BIT_DB_PREFIX."liberty_xref_item` s ON s.`item` = x.`item` $guidFilter
					WHERE x.`xref_id` = ?
					ORDER BY x.`xorder`";
			$result = $this->mDb->getRow( $sql, $bindVars );
			if( $result['content_id'] ) {
				$this->mXrefId    = $pXrefId;
				$this->mContentId = $result['content_id'];
				$this->mType      = $result["x_group"];
				$this->mItem    = $result['item'];
				$this->mInfo['title']       = $result['source_title'];
				$this->mInfo['format_guid'] = 'text';
				unset( $result['source_title'] );
				$this->mInfo['data'] = $result;
			}
		}
	}

	public function verify( &$pParamHash ) {
		$pParamHash['xref_id'] = ( @$this->verifyId( $pParamHash['xref_id'] ) ) ? (int) $pParamHash['xref_id'] : null;

		if( isset( $pParamHash['content_id'] ) ) {
			$pParamHash['xref_store']['content_id'] = $pParamHash['content_id'];
		}
		if( isset( $pParamHash['item'] ) ) {
			$pParamHash['xref_store']['item'] = $pParamHash['item'];
		}

		$pParamHash['xref_store']['xorder'] = 0;

		if( isset( $pParamHash['fAddXref'] ) ) {
			$pParamHash['xref_store']['item']       = isset( $pParamHash['Array_xref_type_list'] ) ? $pParamHash['Array_xref_type_list']['Array.item'] : $pParamHash['item'];
			$pParamHash['xref_store']['content_id'] = $pParamHash['content_id'];
			$guidWhere = !empty( $this->mContentTypeGuid ) ? "AND x.`content_type_guid` = ?" : '';
			$guidBind  = !empty( $this->mContentTypeGuid ) ? [ $pParamHash['xref_store']['item'], $this->mContentTypeGuid ] : [ $pParamHash['xref_store']['item'] ];
			$sql  = "SELECT x.`multiple` FROM `".BIT_DB_PREFIX."liberty_xref_item` x WHERE x.`item` = ? $guidWhere";
			$next = $this->mDb->getOne( $sql, $guidBind );
			if( $next > 0 ) {
				$sql  = "SELECT COALESCE( MAX(x.`xorder`) + 1, 1 ) FROM `".BIT_DB_PREFIX."liberty_xref` x WHERE x.`content_id` = ? AND x.`item` = ?";
				$next = $this->mDb->getOne( $sql, [ $pParamHash['xref_store']['content_id'], $pParamHash['xref_store']['item'] ] );
			}
			$pParamHash['xref_store']['xorder'] = $next;
		}

		if( isset( $pParamHash['fStepXref'] ) ) {
			$pParamHash['xref_store']['item']       = $this->mItem;
			$pParamHash['xref_store']['xorder']     = $this->mInfo['data']['xorder'] + 1;
			$pParamHash['xref_store']['content_id'] = $this->mContentId;
			$pParamHash['start_date']               = $this->mDb->NOW();
			$pParamHash['ignore_end_date']          = 'on';
			$pParamHash['xref_store']['xref']       = 0;
			$pParamHash['xref_store']['xkey']       = '';
			$pParamHash['xref_store']['xkey_ext']   = '';
			$pParamHash['xref_store']['data']       = '';
		}

		if( isset( $pParamHash['xref'] ) )     { $pParamHash['xref_store']['xref']     = $pParamHash['xref']; }
		if( isset( $pParamHash['xkey'] ) )     { $pParamHash['xref_store']['xkey']     = $pParamHash['xkey']; }
		if( isset( $pParamHash['xkey_ext'] ) ) { $pParamHash['xref_store']['xkey_ext'] = $pParamHash['xkey_ext']; }
		if( isset( $pParamHash['edit'] ) )     { $pParamHash['xref_store']['data']     = $pParamHash['edit']; }

		$pParamHash['xref_store']['last_update_date'] = $this->mDb->NOW();

		if( !empty( $pParamHash['start_Month'] ) ) {
			$dateString = $this->mDate->gmmktime(
				$pParamHash['start_Hour'],
				$pParamHash['start_Minute'],
				$pParamHash['start_Second'] ?? 0,
				$pParamHash['start_Month'],
				$pParamHash['start_Day'],
				$pParamHash['start_Year'],
			);
			$timestamp = $this->mDate->getUTCFromDisplayDate( $dateString );
			if( $timestamp !== -1 ) { $pParamHash['start_date'] = $timestamp; }
		}
		if( !empty( $pParamHash['start_date'] ) )                                                    { $pParamHash['xref_store']['start_date'] = $pParamHash['start_date']; }
		if( isset( $pParamHash['ignore_start_date'] ) && $pParamHash['ignore_start_date'] == 'on' ) { $pParamHash['xref_store']['start_date']  = ''; }

		if( !empty( $pParamHash['end_Month'] ) ) {
			$dateString = $this->mDate->gmmktime(
				$pParamHash['end_Hour'],
				$pParamHash['end_Minute'],
				$pParamHash['end_Second'] ?? 0,
				$pParamHash['end_Month'],
				$pParamHash['end_Day'],
				$pParamHash['end_Year'],
			);
			$timestamp = $this->mDate->getUTCFromDisplayDate( $dateString );
			if( $timestamp !== -1 ) { $pParamHash['end_date'] = $timestamp; }
		}
		if( !empty( $pParamHash['end_date'] ) )                                                    { $pParamHash['xref_store']['end_date'] = $pParamHash['end_date']; }
		if( isset( $pParamHash['ignore_end_date'] ) && $pParamHash['ignore_end_date'] == 'on' ) { $pParamHash['xref_store']['end_date']  = ''; }

		return count( $this->mErrors ) == 0;
	}

	public function store( &$pParamHash = NULL ) {
		if( $this->verify( $pParamHash ) ) {
			$table = BIT_DB_PREFIX."liberty_xref";
			$this->mDb->StartTrans();
			if( isset( $pParamHash['xref_id'] ) ) {
				$this->mDb->associateUpdate( $table, $pParamHash['xref_store'], [ "xref_id" => $pParamHash['xref_id'] ] );
			} else {
				$this->mXrefId                        = $this->mDb->GenID( 'liberty_xref_seq' );
				$pParamHash['xref_id']                = $this->mXrefId;
				$pParamHash['xref_store']['xref_id']  = $this->mXrefId;
				$this->mDb->associateInsert( $table, $pParamHash['xref_store'] );
			}
			$this->load( $this->mXrefId );
			$this->mDb->CompleteTrans();
			return true;
		}
		return false;
	}

	public function stepXref( &$pParamHash = NULL ) {
		if( isset( $pParamHash["expunge"] ) ) {
			switch( $pParamHash["expunge"] ) {
				case 2:
					$pParamHash['end_date'] = $this->mDb->NOW();
					$this->store( $pParamHash );
					unset( $pParamHash['xref_id'] );
					$pParamHash['fStepXref'] = 1;
					break;
				case 1:
					$pParamHash['end_date'] = $this->mDb->NOW();
					break;
				default:
					$pParamHash['ignore_end_date'] = 'on';
					break;
			}
		}
		$this->store( $pParamHash );
		return true;
	}
}

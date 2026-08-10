<?php
/**
 * @package liberty
 * @subpackage classes
 */

namespace Bitweaver\Liberty;

use Bitweaver\BitBase;
use Bitweaver\BitDate;

/**
 * Represents a single row in liberty_xref.
 *
 * liberty_xref is the live data table of the xref system.  Each row attaches a
 * typed key-value record to a content item:
 *
 *   content_id   — the liberty_content row this xref belongs to
 *   item         — the slot key (matches liberty_xref_item.item), e.g. '#P', 'REQN', 'SGL'
 *   xorder       — sequence within multiple-cardinality items; 0 for single items
 *   xref         — optional FK to another content_id (e.g. linked contact or component)
 *   xkey         — short key value (max 32 chars), e.g. SCREF code, postcode, quantity
 *   xkey_ext     — longer extension of xkey (max 250 chars), e.g. pipe-separated name parts
 *   data         — free-text blob, e.g. notes or a "from" label
 *   start_date   — when this xref became active (NULL = open / not date-bounded)
 *   end_date     — when this xref expired (NULL = still active)
 *   entry_date   — when this row was first created; set once on insert, never touched by
 *                  updates thereafter. Pass 'entry_date' in $pParamHash to override the
 *                  default (NOW()) — e.g. to stamp a batch of related xrefs (inserted across
 *                  several store() calls) with one shared value for later grouping.
 *   last_update_date — last write timestamp
 *
 * This class handles load/verify/store for a single row.  For bulk loading of all
 * xref rows belonging to a content item, use LibertyXrefGroup / LibertyXrefContent.
 *
 * The stepXref() method implements an audit-trail pattern: instead of updating a row
 * in place it closes the current row (sets end_date) and opens a new one, preserving
 * history. Expired rows are swept into the synthetic 'history' group by LibertyXrefType::loadContent().
 */
class LibertyXref extends BitBase implements \ArrayAccess {
	/** x_group value of the loaded xref's item definition */
	public $mType;
	/** item key of the loaded row (matches liberty_xref_item.item) */
	public $mItem;
	/** primary key of the loaded liberty_xref row */
	public $mXrefId;
	/** content_id this xref belongs to */
	public $mContentId;
	/** BitDate instance used for start/end date conversions */
	public $mDate;
	/** when set, scopes liberty_xref_item lookups to this content type */
	public $mContentTypeGuid = '';
	/** when set alongside mContentTypeGuid, extends the scope to include package-level items (dual-guid schema) */
	public string $mPackageGuid = '';
	/** flat row data — populated by load() and fromRow(); underpins ArrayAccess */
	protected array $mRow = [];

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

	/**
	 * Construct a LibertyXref instance from a bulk-loaded result row.
	 *
	 * Used by LibertyXrefType::loadContent() to populate LibertyXrefGroup::mXrefs
	 * without going through load(). The row is stored flat in $mRow; ArrayAccess
	 * exposes it so templates continue to use {$xrefInfo.xkey} syntax unchanged.
	 *
	 * @param array $row  result row from the LibertyXrefType::loadContent() query
	 */
	public static function fromRow( array $row ): self {
		$xref              = new self();
		$xref->mRow        = $row;
		$xref->mXrefId     = $row['xref_id'] ?? null;
		$xref->mItem       = $row['item'] ?? null;
		$xref->mContentId  = $row['content_id'] ?? null;
		return $xref;
	}

	// ArrayAccess — lets templates use {$xrefInfo.key} dot notation on bulk-loaded rows.
	public function offsetExists( mixed $offset ): bool  { return isset( $this->mRow[$offset] ); }
	public function offsetGet( mixed $offset ): mixed    { return $this->mRow[$offset] ?? null; }
	public function offsetSet( mixed $offset, mixed $value ): void { $this->mRow[$offset] = $value; }
	public function offsetUnset( mixed $offset ): void   { unset( $this->mRow[$offset] ); }

	/** @return bool true if a row has been loaded (mXrefId is a valid integer) */
	public function isValid() {
		return $this->verifyId( $this->mXrefId );
	}

	/**
	 * Load a single liberty_xref row by its primary key.
	 *
	 * Joins liberty_xref_item to resolve the group (mType), item display title,
	 * and template.  Also derives source_title by appending xorder to the item
	 * title for multi-cardinality rows, and computes ignore_start/end_date flags
	 * so templates can treat NULL dates as "not set".
	 *
	 * On success populates mXrefId, mContentId, mType, mItem, and mInfo.
	 *
	 * @param int|null $pXrefId  liberty_xref.xref_id to load
	 */
	public function load( $pXrefId = NULL ) {
		if( BitBase::verifyId( $pXrefId ) ) {
			if( !empty( $this->mContentTypeGuid ) && !empty( $this->mPackageGuid ) ) {
				$guidFilter = "AND s.`content_type_guid` IN(?,?)";
				$bindVars   = [ $this->mContentTypeGuid, $this->mPackageGuid, $pXrefId ];
			} elseif( !empty( $this->mContentTypeGuid ) ) {
				$guidFilter = "AND s.`content_type_guid` = ?";
				$bindVars   = [ $this->mContentTypeGuid, $pXrefId ];
			} else {
				$guidFilter = '';
				$bindVars   = [ $pXrefId ];
			}
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
				$this->mItem      = $result['item'];
				$this->mInfo['title']       = $result['source_title'];
				$this->mInfo['format_guid'] = 'text';
				unset( $result['source_title'] );
				$this->mInfo['data'] = $result;
				$this->mRow = $result;
			}
		}
	}

	/**
	 * Validate and normalise the param hash before store().
	 *
	 * Reads raw POST/form values from $pParamHash and builds a clean
	 * $pParamHash['xref_store'] array ready for associateInsert/Update.
	 *
	 * Special flags in the hash:
	 *   fAddXref   — new row for a multiple-cardinality item; auto-increments xorder
	 *   fStepXref  — audit-trail step: opens a new row as the continuation of this one
	 *
	 * Date fields (start_Month/Day/Year/Hour/Minute + ignore_start_date etc.) are
	 * converted from display timezone to UTC and stored as SQL TIMESTAMP strings.
	 * Setting ignore_start_date/ignore_end_date to 'on' stores NULL for that date.
	 *
	 * @param array &$pParamHash  in/out; errors appended to $this->mErrors on failure
	 * @return bool true if no errors
	 */
	public function verify( &$pParamHash ) {
		global $gBitSystem;
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
			if( !empty( $this->mContentTypeGuid ) && !empty( $this->mPackageGuid ) ) {
				$guidWhere = "AND x.`content_type_guid` IN(?,?)";
				$guidBind  = [ $pParamHash['xref_store']['item'], $this->mContentTypeGuid, $this->mPackageGuid ];
			} elseif( !empty( $this->mContentTypeGuid ) ) {
				$guidWhere = "AND x.`content_type_guid` = ?";
				$guidBind  = [ $pParamHash['xref_store']['item'], $this->mContentTypeGuid ];
			} else {
				$guidWhere = '';
				$guidBind  = [ $pParamHash['xref_store']['item'] ];
			}
			$sql  = "SELECT x.`multiple` FROM `".BIT_DB_PREFIX."liberty_xref_item` x WHERE x.`item` = ? $guidWhere";
			$next = $this->mDb->getOne( $sql, $guidBind );
			if( $next > 0 ) {
				$sql  = "SELECT COALESCE( MAX(x.`xorder`) + 1, 1 ) FROM `".BIT_DB_PREFIX."liberty_xref` x WHERE x.`content_id` = ? AND x.`item` = ?";
				$next = $this->mDb->getOne( $sql, [ $pParamHash['xref_store']['content_id'], $pParamHash['xref_store']['item'] ] );
			}
			$pParamHash['xref_store']['xorder'] = (int)$next;
		}

		if( isset( $pParamHash['fStepXref'] ) ) {
			$pParamHash['xref_store']['item']       = $this->mItem;
			$pParamHash['xref_store']['xorder']     = $this->mInfo['data']['xorder'] + 1;
			$pParamHash['xref_store']['content_id'] = $this->mContentId;
			$pParamHash['start_date']               = time();
			$pParamHash['ignore_end_date']          = 'on';
			$pParamHash['xref_store']['xref']       = 0;
			$pParamHash['xref_store']['xkey']       = '';
			$pParamHash['xref_store']['xkey_ext']   = '';
			$pParamHash['xref_store']['data']       = '';
		}

		if( isset( $pParamHash['xorder'] ) )   { $pParamHash['xref_store']['xorder']   = (int)$pParamHash['xorder']; }
		if( isset( $pParamHash['xref'] ) )     { $pParamHash['xref_store']['xref']     = $pParamHash['xref']; }
		if( isset( $pParamHash['xkey'] ) )     { $pParamHash['xref_store']['xkey']     = $pParamHash['xkey']; }
		if( isset( $pParamHash['xkey_ext'] ) ) { $pParamHash['xref_store']['xkey_ext'] = $pParamHash['xkey_ext']; }
		if( isset( $pParamHash['edit'] ) )     { $pParamHash['xref_store']['data']     = $pParamHash['edit']; }

		// entry_date: stamped once at insert time, left untouched on update — unless the
		// caller explicitly overrides it (e.g. to align a batch of related xrefs).
		if( isset( $pParamHash['entry_date'] ) ) {
			$pParamHash['xref_store']['entry_date'] = $pParamHash['entry_date'];
		} elseif( empty( $pParamHash['xref_id'] ) ) {
			$pParamHash['xref_store']['entry_date'] = $this->mDb->NOW();
		}

		$pParamHash['xref_store']['last_update_date'] = $this->mDb->NOW();

		$gBitSystem->mServerTimestamp->get_display_offset();

		if( !empty( $pParamHash['start_date'] ) ) {
			$d = $pParamHash['start_date'];
			if( is_int( $d ) ) {
				$d = gmdate( 'Y-m-d H:i:s', $d );
			} else {
				$d = str_replace( 'T', ' ', trim( (string)$d ) );
				if( strlen( $d ) === 16 ) { $d .= ':00'; }
				$d = gmdate( 'Y-m-d H:i:s', $gBitSystem->mServerTimestamp->getUTCFromDisplayDate( $d ) );
			}
			$pParamHash['xref_store']['start_date'] = $d;
		}
		if( isset( $pParamHash['ignore_start_date'] ) && $pParamHash['ignore_start_date'] == 'on' ) { $pParamHash['xref_store']['start_date'] = null; }

		if( !empty( $pParamHash['end_date'] ) ) {
			$d = $pParamHash['end_date'];
			if( is_int( $d ) ) {
				$d = gmdate( 'Y-m-d H:i:s', $d );
			} else {
				$d = str_replace( 'T', ' ', trim( (string)$d ) );
				if( strlen( $d ) === 16 ) { $d .= ':00'; }
				$d = gmdate( 'Y-m-d H:i:s', $gBitSystem->mServerTimestamp->getUTCFromDisplayDate( $d ) );
			}
			$pParamHash['xref_store']['end_date'] = $d;
		}
		if( isset( $pParamHash['ignore_end_date'] ) && $pParamHash['ignore_end_date'] == 'on' ) { $pParamHash['xref_store']['end_date'] = null; }

		return count( $this->mErrors ) == 0;
	}

	/**
	 * Persist one liberty_xref row (insert or update).
	 *
	 * Calls verify() first.  Inserts if $pParamHash['xref_id'] is absent;
	 * updates the matching row otherwise.  On insert, allocates a new xref_id
	 * from liberty_xref_seq and writes it back into $pParamHash['xref_id'].
	 * Always reloads the row after writing so mInfo reflects the stored state.
	 *
	 * IMPORTANT: always pass a named variable — this method takes &$pParamHash
	 * by reference and will fatal if given a literal array.
	 *
	 * @param array &$pParamHash  see verify() for expected keys
	 * @return bool true on success
	 */
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

	/**
	 * Store an xref row using the audit-trail stepping pattern.
	 *
	 * The expunge value in $pParamHash controls the step behaviour:
	 *   2  — close the current row now (end_date = now) and immediately open a fresh
	 *        continuation row (fStepXref), preserving the full history chain
	 *   1  — close the current row (end_date = now) with no continuation; the xref
	 *        is retired and will appear in the history group on next load
	 *   0  — update in place (end_date cleared); standard non-stepping store
	 *
	 * @param array &$pParamHash  must include 'expunge' and 'xref_id'
	 * @return bool always true
	 */
	public function stepXref( &$pParamHash = NULL ) {
		if( isset( $pParamHash["expunge"] ) ) {
			switch( $pParamHash["expunge"] ) {
				case 2:
					$pParamHash['end_date'] = time();
					$this->store( $pParamHash );
					unset( $pParamHash['xref_id'] );
					$pParamHash['fStepXref'] = 1;
					break;
				case 1:
					$pParamHash['end_date'] = time();
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

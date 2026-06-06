<?php
/**
 * Base class for Management of Liberty Content
 *
 * @package  liberty
 * @version  $Header$
 * @author   spider <spider@steelsun.com>
 */
// +----------------------------------------------------------------------+
// | Copyright (c) 2004, bitweaver.org
// +----------------------------------------------------------------------+
// | All Rights Reserved. See below for details and a complete list of authors.
// | Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
// |
// | For comments, please use phpdocu.sourceforge.net documentation standards!!!
// | -> see http://phpdocu.sourceforge.net/
// +----------------------------------------------------------------------+
// | Authors: spider <spider@steelsun.com>
// +----------------------------------------------------------------------+

/**
 * required setup
 */
namespace Bitweaver\Liberty;

use Bitweaver\BitBase;

/**
 * Intermediate base class between BitBase and LibertyContent.
 *
 * Adds `$mContentId` and the static object-factory methods used throughout
 * the codebase to instantiate the correct subclass for a given content item.
 *
 * `getLibertyObject()` is the single entry point for loading any content by
 * content_id: it looks up the content_type_guid, resolves the handler class
 * registered with `$gLibertySystem`, checks the APCu cache, and calls `load()`
 * on a fresh instance if not cached.  All "open this content item" code paths
 * go through here.
 *
 * @package liberty
 */
#[\AllowDynamicProperties]
class LibertyBase extends BitBase {
	/** liberty_content.content_id of the loaded object, or null when not loaded */
	public $mContentId;

	public function __construct() {
		parent::__construct();
	}

	/**
	 * Return an unloaded instance of the handler class for a content type.
	 *
	 * Useful for type introspection when no content_id is available — e.g. to
	 * call static methods or check class capabilities.
	 *
	 * @param string $pContentTypeGuid  e.g. 'contact', 'stockassembly'
	 * @return object|null  new instance with mContentId unset, or null if unknown type
	 */
	public function getLibertyClass( $pContentTypeGuid ) {
		// We can abuse getLibertyObject to do the work
		$ret = LibertyBase::getLibertyObject('1', $pContentTypeGuid, false);
		// Make sure we don't have a content_id set though.
		unset($ret->mContentId);
		return $ret;
	}

	/**
	 * Load and return an object of the correct handler class for a content item.
	 *
	 * This is the canonical way to load any content by content_id when the type
	 * is not known in advance.  Resolution order:
	 *   1. If $pContentTypeGuid is not supplied, look it up from liberty_content.
	 *   2. Resolve the handler class from $gLibertySystem->mContentTypes.
	 *   3. Return the cached APCu object if $pLoadFromCache is true and one exists.
	 *   4. Otherwise instantiate the handler class and call load().
	 *
	 * @param int         $pContentId        liberty_content.content_id
	 * @param string|null $pContentTypeGuid  skip the DB lookup if already known
	 * @param bool        $pLoadFromCache    check APCu before instantiating (default true)
	 * @return object|null  loaded content object, or null if not found / unknown type
	 */
	public static function getLibertyObject( $pContentId, $pContentTypeGuid=null, $pLoadFromCache = true ) {
		$ret = null;
		global $gLibertySystem, $gBitUser, $gBitSystem;

		if( static::verifyId( $pContentId ) ) {
			// remove non integer bits from structure_id and content_id requests
			// can happen with period's at the end of url's that are email'ed around
			$typeClass = null;
			$pContentId = preg_replace( '/[\D]/', '', $pContentId );
			if( empty( $pContentTypeGuid ) ) {
				$pContentTypeGuid = $gLibertySystem->mDb->getOne( "SELECT `content_type_guid` FROM `".BIT_DB_PREFIX."liberty_content` WHERE `content_id`=?", [ $pContentId ], null, null, 3600 );
			}
			if( !empty( $pContentTypeGuid ) && isset( $gLibertySystem->mContentTypes[$pContentTypeGuid] ) ) {
				$typeClass = $gLibertySystem->getContentClassName( $pContentTypeGuid );
			}
			if( $pLoadFromCache && ($ret = static::loadFromCache( $pContentId, $typeClass )) ) {
				$ret->mCacheObject = true;
			} else {
				if( $typeClass ) {
					$creator = new $typeClass();
					if( $ret = $creator->getNewObject( $typeClass, $pContentId, $pLoadFromCache ) ) {
						$ret->setCacheableObject( false );
						$ret->clearFromCache();
					}
				}
			}
		}
		return $ret;
	}

	/**
	 * Instantiate $pClass with a package-table primary key and call load().
	 *
	 * Override point for subclasses whose constructor takes a package-specific
	 * primary id rather than a content_id (e.g. legacy FishEye gallery_id).
	 *
	 * @param string $pClass      fully-qualified class name
	 * @param int    $pPrimaryId  package-table primary key
	 * @return object
	 */
	public static function getNewObjectById( $pClass, $pPrimaryId, $pLoadFromCache=true ) {
		if( $ret = new $pClass( $pPrimaryId ) ) {
			$ret->load();
		}
		return $ret;
	}

	/**
	 * Instantiate $pClass with a content_id and call load().
	 *
	 * Default factory used by getLibertyObject().  Subclasses that need
	 * different constructor signatures override this method.
	 *
	 * @param string $pClass      fully-qualified class name
	 * @param int    $pContentId  liberty_content.content_id
	 * @return object
	 */
	public static function getNewObject( $pClass, $pContentId, $pLoadFromCache=true ) {
		if( $ret = new $pClass( null, $pContentId ) ) {
			$ret->load();
		}
		return $ret;
	}
}
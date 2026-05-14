<?php

namespace Bitweaver\Liberty;

use Bitweaver\KernelTools;
use Bitweaver\BitBase;
use Bitweaver\Wiki\BitPage;

/**
 * @version  $Header$
 * @package  liberty
 */

 /**
 * definitions ( guid character limit is 16 chars )
 */
define( 'PLUGIN_GUID_FILTERWIKILINKS', 'filterbitlinks' );

global $gLibertySystem;

$pluginParams = [
	'title'               => 'WikiLinks',
	'description'         => 'If you use links of the format ((Wiki Page)) this filter will convert that to a link to a wiki page entitled <em>Wiki Page</em>',
	'auto_activate'       => true,
	'plugin_type'         => FILTER_PLUGIN,

	// filter functions
	'presplit_function'   => '\Bitweaver\Liberty\bitlinks_prefilter',
	'preparse_function'   => '\Bitweaver\Liberty\bitlinks_prefilter',
	'postsplit_function'  => '\Bitweaver\Liberty\bitlinks_postfilter',
	'postparse_function'  => '\Bitweaver\Liberty\bitlinks_postfilter',
	'poststore_function'  => '\Bitweaver\Liberty\bitlinks_storefilter',
	'expunge_function'    => 'bitlinks_expungefilter',
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_FILTERWIKILINKS, $pluginParams );

define( 'WIKI_WORDS_REGEX', '[A-z0-9]{2}[\w\d_\-]+[A-Z_][\w\d_\-]+[A-z0-9]+' );

/**
 * bitlinks_prefilter
 *
 * @param string $pData
 * @param string $pFilterHash
 * @param array $pObject
 * @access public
 * @return void
 */
function bitlinks_prefilter( &$pData, &$pFilterHash, $pObject ) {
	static $sBitLinks;
	if( empty( $sBitLinks )) {
		$sBitLinks = new BitLinks();
	}

	// extract ((Page|Description)) type links that they don't enter the parser.
	// these can cause problems in various places such as tiki tables due to the |
	preg_match_all( "@\({2}({$sBitLinks->mWikiWordRegex})\|(.+?)\){2}@", $pData, $protected );

	if( !empty( $protected )) {
		foreach( $protected[0] as $i => $prot ) {
			$key = md5( mt_rand() );
			$pFilterHash['bitlinks']['replacements'][$key] = $protected[0][$i];
			$pData = str_replace( $prot, $key, $pData );
		}
	}
}

/**
 * convert wiki links to html links e.g.: ((Wiki Page)) --> <a href="/wiki/Wiki+Page">Wiki Page</a>
 *
 * @param string $pData
 * @param array $pFilterHash
 * @param bool $pCase
 * @access public
 * @return void
 */
function bitlinks_postfilter( &$pData, &$pFilterHash, $pCase ) {
	static $sBitLinks;
	if( empty( $sBitLinks )) {
		$sBitLinks = new BitLinks();
	}

	// first we need to put the ((Page|Description)) type links back in that we can parse them below
	if( !empty( $pFilterHash['bitlinks']['replacements'] )) {
		foreach( $pFilterHash['bitlinks']['replacements'] as $key => $replace ) {
			$pData = str_replace( $key, $replace, $pData );
		}
	}
	$pFilterHash['data'] = $pData;
	$pData = $sBitLinks->parseLinks( $pData, $pFilterHash, $pCase );
}

/**
 * store links to existing wiki pages in the database
 *
 * @param string $pData
 * @param array $pFilterHash
 * @param object $pObject
 * @access public
 * @return void
 */
function bitlinks_storefilter( &$pData, &$pFilterHash, $pObject ) {
	global $gBitSystem;
	static $sBitLinks;
	if( empty( $sBitLinks )) {
		$sBitLinks = new BitLinks();
	}
	$sBitLinks->storeLinks( $pData, $pFilterHash );

	// if the title of this object was changed, we need to update links to it
	if(
		$gBitSystem->isPackageActive( 'wiki' )
		&& $pObject->mContentTypeGuid == BITPAGE_CONTENT_TYPE_GUID
		&& !empty( $pFilterHash['title'] )
		&& !empty( $pObject->mInfo['title'] )
		&& $pFilterHash['title'] != $pObject->mInfo['title']
	) {
		$sBitLinks->renameLinks( $pObject->mContentId, $pObject->mInfo['title'], $pFilterHash['title'] );
	}
}

/**
 * expunge bitlinks in the database
 *
 * @param string $pData
 * @param array $pFilterHash
 * @param object $pObject
 * @access public
 * @return void
 */
function bitlinks_expungefilter( &$pData, &$pFilterHash, $pObject ) {
	static $sBitLinks;
	if( empty( $sBitLinks )) {
		$sBitLinks = new BitLinks();
	}
	$sBitLinks->expungeLinks( $pObject->mContentId );
}

/**
 * BitLinks class
 *
 * @package liberty
 * @uses BitBase
 */
class BitLinks extends BitBase {
	/**
	 * mLinks
	 *
	 * @var array of links pointing to this page
	 * @access public
	 */
	public $mLinks = null;

	public $mWikiWordRegex = '';

	/**
	 * Initiate class
	 *
	 * @access public
	 * @return bool true on success, false on failure - mErrors will contain reason for failure
	 */
	public function __construct() {
		parent::__construct();

		global $gBitSystem;
		$mode = $gBitSystem->getConfig( 'wiki_page_regex', 'strict' );
		switch ( $mode ) {
			case 'strict':
				$this->mWikiWordRegex = "([A-Za-z0-9_])([\'\.: A-Za-z0-9_\-])*([\.:A-Za-z0-9_])";
			break;
			case 'full':
				$this->mWikiWordRegex = "([A-Za-z0-9_]|[\x80-\xFF])([\'\.: A-Za-z0-9_\-]|[\x80-\xFF])*([\.:A-Za-z0-9_]|[\x80-\xFF])";
			break;
			default:
				// This is just evil. The middle section means "anything, as long
				// as it's not a | and isn't followed by ))". -rlpowell
				$this->mWikiWordRegex = "([^|\(\)])([^|](?!\)\)))*?([^|\(\)])";
		}

		// append anchor to regex
		$this->mWikiWordRegex .= "(#\w+)?";
	}

	/**
	 * Get all pages linking to a given content id
	 *
	 * @param int $pContentId
	 * @access public
	 * @return array
	 */
	public function getAllPages( $pContentId ) {
		global $gBitSystem;
		$ret = [];
		if( $gBitSystem->isPackageActive( 'wiki' ) && BitBase::verifyId( $pContentId )) {
			$query = "SELECT `page_id`, lc.`content_id`, lc.`last_modified`, lc.`title`, lcds.`data` AS `summary`
				FROM `".BIT_DB_PREFIX."liberty_content_links` lcl
				INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON( lcl.`to_content_id`=lc.`content_id` )
				INNER JOIN `".BIT_DB_PREFIX."wiki_pages` wp ON( wp.`content_id`=lc.`content_id` )
				LEFT OUTER JOIN `".BIT_DB_PREFIX."liberty_content_data` lcds ON (lc.`content_id` = lcds.`content_id` AND lcds.`data_type`='summary')
				WHERE lcl.`from_content_id`=? ORDER BY lc.`title`";
			if( $result = $this->mDb->query( $query, [ $pContentId ] ) ) {
				$lastTitle = '';
				while( $row = $result->fetchRow() ) {
					if( array_key_exists( strtolower( $row['title'] ), $ret )) {
						$row['description'] = KernelTools::tra( 'Multiple pages with this name' );
					}
					$ret[strtolower( $row['title'] )] = $row;
				}
			}
		}
		return $ret;
	}

	/**
	 * see if page has already been created and stored
	 *
	 * @param string $pTitle title of the page
	 * @param bool $pCaseSensitive
	 * @param int $pContentId content_id of the current page - sometimes we don't have the object but a content_id to work with
	 * @access public
	 * @return bool true on success, false on failure - mErrors will contain reason for failure
	 */
	public function pageExists( $pTitle, $pCaseSensitive, $pContentId ) {
		global $gBitSystem;

		// only update this hash once - this is initiated as null and will be set to at least array after first call
		if( $this->mLinks === null ) {
			$this->mLinks = $this->getAllPages( $pContentId );
		}

		$ret = false;
		if( !empty( $pTitle ) && !empty( $this->mLinks )) {
			if( array_key_exists( strtolower( $pTitle ), $this->mLinks )) {
				$ret = $this->mLinks[strtolower( $pTitle )];
			}
		}

		// final attempt to get page details
		if( empty( $ret ) && $gBitSystem->isPackageActive( 'wiki' ) ) {
			if( $ret = BitPage::pageExists( $pTitle, false, $pContentId )) {
				if( count( $ret ) > 1 ) {
					$ret[0]['description'] = KernelTools::tra( 'Multiple pages with this name' );
				}
				$ret = $ret[0];
			}
		}
		return $ret;
	}

	/**
	 * extractWikiWords
	 *
	 * @param string $pData
	 * @access public
	 * @return array of wiki words in the data string
	 */
	public function extractWikiWords( $pData ) {
		global $gBitSystem;
		// we need to remove text that might contain unexpected wiki words
		$protect = [
			"!<a\b[^>]*>.*?</a>!si", // links
			"!<[^>]*>!",             // any html tags
		];
		$tmpData = preg_replace( $protect, "", $pData );

		$words1[1] = $words2[1] = $words3[1] = [];
		preg_match_all( "@\({2}($this->mWikiWordRegex)\){2}@", $tmpData, $words2 );
		preg_match_all( "@\({2}($this->mWikiWordRegex)\|(.+?)\){2}@", $tmpData, $words3 );
		if( $gBitSystem->isFeatureActive( 'wiki_words' )) {
			preg_match_all( '/\b('.WIKI_WORDS_REGEX.')\b/', $tmpData, $words1 );
		}
		return array_unique( array_merge( $words1[1], $words2[1], $words3[1] ));
	}

	/**
	 * convert wiki links to html links
	 *
	 * @param string $pData
	 * @param array $pParamHash
	 * @param bool $pCase
	 * @access public
	 * @return string
	 */
	public function parseLinks( $pData, $pParamHash, $pCase ) {
		global $gBitSystem, $gLibertySystem;

		// if wiki isn't active, there isn't much we can do here
		if( !$gBitSystem->isPackageActive( 'wiki' )) {
			return $pData;
		}

		// fetch BitPage in case it hasn't been loaded yet
		$gLibertySystem->getContentClassName( 'bitpage' );

		// We need to remove ))WikiWords(( before links get made.
		// users just need to be strict about not inserting spaces between
		// words and brackets
		preg_match_all( "@\){2}(".WIKI_WORDS_REGEX.")\({2}@", $pData, $protected );

		// this array is used to fill the text with temporary placeholders that get replaced back in further down
		$replacements = [];

		if( !empty( $protected )) {
			foreach( $protected[0] as $i => $prot ) {
				$key = md5( mt_rand() );
				$replacements[$key] = $protected[1][$i];
				$pData = str_replace( $prot, $key, $pData );
			}
		}

		// Process ((Wiki Page|Wiki Page Description)) type links first. Here
		// we don't handle plurals and the like since the user should know what
		// he's linking to when using these links
		preg_match_all( "@\({2}({$this->mWikiWordRegex})\|(.+?)\){2}@", $pData, $pages );
		for( $i = 0; $i < count( $pages[1] ); $i++ ) {
			$page = str_replace( $pages[5][$i], "", $pages[1][$i] );
			$exists = $this->pageExists( $page, $pCase, $pParamHash['content_id'] );

			// anchor
			$repl = !empty( $pages[5][$i] )
				? preg_replace( '!href="([^"]*)"!', "href=\"$1{$pages[5][$i]}\"", BitPage::getPageLink( $page, $exists ))
				: BitPage::getPageLink( $page, $exists );

			// alternate title
			if( strlen( trim( $pages[6][$i] )) > 0 ) {
				$repl = str_replace( $page."</a>", "{$pages[6][$i]}</a>", $repl );
			}

			$key = md5( mt_rand() );
			$replacements[$key] = $repl;
			$pData = str_replace( $pages[0][$i], $key, $pData );
		}

		// Process the simpler ((Wiki Page)) type links without the description
		preg_match_all( "@\({2}({$this->mWikiWordRegex})\){2}@", $pData, $pages );
		foreach( array_unique( $pages[1] ) as $i => $page ) {
			$page = str_replace( $pages[5][$i], "", $pages[1][$i] );
			$exists = $this->pageExists( $page, $pCase, $pParamHash['content_id'] );

			$repl = !empty( $pages[5][$i] )
				?  preg_replace( '!href="([^"]*)"!', "href=\"$1{$pages[5][$i]}\"", BitPage::getPageLink( $page, $exists ))
				: BitPage::getPageLink( $page, $exists );

			$key = md5( mt_rand() );
			$replacements[$key] = $repl;
			$pData = str_replace( "(({$pages[1][$i]}))", $key, $pData );
		}

		// Finally we deal with WikiWord links
		if( $gBitSystem->isFeatureActive( 'wiki_words' )) {
			$pages = $this->extractWikiWords( $pData );
			foreach( $pages as $page) {
				if( $exists = $this->pageExists( $page, $pCase, $pParamHash['content_id'] )) {
					$repl = BitPage::getPageLink( $page, $exists );
				} elseif( $gBitSystem->isFeatureActive( 'wiki_plurals' ) && $this->getLocale() == 'en_US' ) {
					// Link plural topic names to singular topic names if the plural
					// doesn't exist, and the language is english
					$plural_tmp = $page;
					// Plurals like policy / policies
					$plural_tmp = preg_replace( "/ies$/", "y", $plural_tmp );
					// Plurals like address / addresses
					$plural_tmp = preg_replace( "/sses$/", "ss", $plural_tmp );
					// Plurals like box / boxes
					$plural_tmp = preg_replace( "/([Xx])es$/", "$1", $plural_tmp );
					// Others, excluding ending ss like address(es)
					$plural_tmp = preg_replace( "/([A-Za-rt-z])s$/", "$1", $plural_tmp );
					// prevent redundant pageExists calls if plurals are on, and plural is same as original word
					if( $page != $plural_tmp ) {
						$exists = $this->pageExists( $plural_tmp, $pCase, $pParamHash['content_id'] );
					}
					$repl = BitPage::getPageLink( $plural_tmp, $exists );
				} else {
					$repl = BitPage::getPageLink( $page, $exists );
				}

				// old code
				//$slashed = preg_replace( "/([\/\[\]\(\)])/", "\\\\$1", $page_parse );
				//$data = preg_replace( "#([\s\,\;])\b$slashed\b([\s\,\;\.])#", "$1 ".$repl."$2", $data);

				// new code
				// i never understood why the simple stuff never worked but it
				// seems to work now - xing - Sunday Jul 22, 2007   17:37:17 CEST
				$pData = preg_replace( "/\b".preg_quote( $page, "/" )."\b/", $repl, $pData );
			}
		}

		// replace protection keys with original words
		foreach( $replacements as $key => $replace ) {
			$pData = str_replace( $key, $replace, $pData );
		}

		return $pData;
	}

	/**
	 * getLocale
	 *
	 * @access public
	 * @return string locale
	 */
	public function getLocale() {
		static $locales = [
			'cs' => 'cs_CZ',
			'de' => 'de_DE',
			'dk' => 'da_DK',
			'en' => 'en_US',
			'fr' => 'fr_FR',
			'he' => 'he_IL', # hebrew
			'it' => 'it_IT', # italian
			'pl' => 'pl_PL', # polish
			'po' => 'po',
			'ru' => 'ru_RU',
			'es' => 'es_ES',
			'sw' => 'sw_SW', # swahili
			'tw' => 'tw_TW',
		];

		if( empty( $locale )) {
			$locale = '';
			if( isset( $locales[$this->getLanguage()] )) {
				$locale = $locales[$this->getLanguage()];
			}
		}

		return $locale;
	}

	/**
	 * getLanguage
	 *
	 * @access public
	 * @return string language
	 */
	public function getLanguage() {
		static $sBitLanguage = false;
		global $gBitUser, $gBitSystem;

		if( empty( $sBitLanguage )) {
			$sBitLanguage = $gBitUser->isValid()
				? $gBitUser->getPreference( 'bitLanguage', 'en' )
				: $gBitSystem->getPreference( 'bitLanguage', 'en' );
		}

		return $sBitLanguage;
	}

	/**
	 * storeLinks
	 *
	 * @param string $pData
	 * @param array $pFilterHash
	 * @access public
	 * @return bool store wiki links in database
	 */
	public function storeLinks( $pData, $pFilterHash ) {
		global $gBitSystem;

		// if we don't have a content_id or wiki isn't active, get out of here.
		if( empty( $pFilterHash['content_id'] ) || !$gBitSystem->isPackageActive( 'wiki' )) {
			return false;
		}

		$from_content_id = $pFilterHash['content_id'];
		$from_title = $pFilterHash['title'] ?? '';

		// we need to remove the cache of any pages pointing to this one
		$query = "SELECT `from_content_id` FROM `".BIT_DB_PREFIX."liberty_content_links` WHERE ( `to_content_id` = ? OR `to_content_id` IS null ) AND `to_title` = ?";
		$clearCache = $gBitSystem->mDb->getCol( $query, [ $from_content_id, $from_title ] );
		if( is_array( $clearCache )) {
			foreach( $clearCache as $content_id ) {
				LibertyContent::expungeCacheFile( $content_id );
			}
		}

		// if this is a new page, fix up any links that may already point to it
		$query = "UPDATE `".BIT_DB_PREFIX."liberty_content_links` SET `to_content_id` = ? WHERE ( `to_content_id` = ? OR `to_content_id` IS null ) AND `to_title` = ?";
		$gBitSystem->mDb->query( $query, [ $from_content_id, 0, $from_title ] );

		// get all the current links from this page
		$query = "SELECT LOWER( `to_title` ), `to_content_id` FROM `".BIT_DB_PREFIX."liberty_content_links` WHERE `from_content_id` = ?";
		$oldLinks = $gBitSystem->mDb->getAssoc( $query, [ $from_content_id ] );

		// get list of all wiki links on this page
		$extractedWikiWords = $this->extractWikiWords( $pData );

		// wiki links with anchors are pulled out as well. we'll make copies of the wiki pagenames without the anchor to process these correctly as well
		foreach( $extractedWikiWords as $link ) {
			if( strstr( $link, '#' ) !== false ) {
				$extractedWikiWords[] = preg_replace( "!#.*!", "", $link );
			}
		}

		// create list of unique new wiki links on this page
		$uniqueNewWikiLinks = [];
		foreach( $extractedWikiWords as $to_title ) {
			if( !empty( $to_title ) && !isset( $oldLinks[strtolower($to_title)] )) {
				$uniqueNewWikiLinks[] = $to_title;
			}
		}
		// remove duplicates
		array_unique( $uniqueNewWikiLinks );

		// get list of all new links that point to existing content
		if( !empty( $uniqueNewWikiLinks )) {
			$inSql = '?'.str_repeat( ',?', count( $uniqueNewWikiLinks ) - 1 );
			// All arguments to MySQL in() function have to be passed as strings or it doesn't work correctly
			$bindVars = [];
			foreach( $uniqueNewWikiLinks as $var ) {
				$bindVars[] = ( string )$var;
			}
			$bindVars[] = BITPAGE_CONTENT_TYPE_GUID;

			$query = "SELECT LOWER( `title` ), `title` FROM `".BIT_DB_PREFIX."liberty_content` WHERE `title` IN( $inSql ) AND `content_type_guid` = ?";
			$newLinksPointingToExistingContent = $gBitSystem->mDb->getAssoc( $query, $bindVars );

			// insert all new links pointing to existing content
			if( !empty( $newLinksPointingToExistingContent )) {
				// All arguments to MySQL in() function have to be passed as strings or it doesn't work correctly
				$bindVars = [];
				foreach( $newLinksPointingToExistingContent as $var ) {
					$bindVars[] = ( string )$var;
				}
				$bindVars[] = BITPAGE_CONTENT_TYPE_GUID;
				$inSql      = '?'.str_repeat( ',?', count( $newLinksPointingToExistingContent ) - 1 );
				$query      = "
					INSERT INTO `".BIT_DB_PREFIX."liberty_content_links` ( `from_content_id`, `to_content_id`, `to_title` )
					SELECT ?, `content_id`, `title` FROM `".BIT_DB_PREFIX."liberty_content`
					WHERE `title` IN( $inSql ) AND `content_type_guid` = ?";
				array_unshift( $bindVars, $from_content_id );
				$result = $gBitSystem->mDb->query( $query, $bindVars );
			}

			// insert all new links pointing to non-existing content and that are not in the db yet
			foreach( $uniqueNewWikiLinks as $to_title ) {
				if( !isset( $newLinksPointingToExistingContent[strtolower( $to_title )] ) && !in_array( strtolower( $to_title ), array_keys( $oldLinks ))) {
					$query = "INSERT INTO `".BIT_DB_PREFIX."liberty_content_links` ( `from_content_id`, `to_title` ) VALUES( ?, ? )";
					$result = $gBitSystem->mDb->query( $query, [ $from_content_id, $to_title ] );
				}
			}
		}

		// now delete any links no longer on page
		foreach( $extractedWikiWords as $to_title ) {
			$obsoleteLinks[strtolower( $to_title )] = 1;
		}

		foreach( array_keys( $oldLinks ) as $to_title ) {
			if( !isset( $obsoleteLinks[$to_title] )) {
				$query = "DELETE FROM `".BIT_DB_PREFIX."liberty_content_links` WHERE `from_content_id`=? AND LOWER( `to_title` ) = ?";
				$result = $gBitSystem->mDb->query( $query, [ $from_content_id, $to_title ] );
			}
		}

		return true;
	}

	/**
	 * renameLinks
	 *
	 * @param int $pContentId
	 * @param string $pOldName
	 * @param string $pNewName
	 * @access public
	 * @return void
	 */
	public function renameLinks( $pContentId, $pOldName, $pNewName ) {
		$query = "
			SELECT `from_content_id`, `data`
			FROM `".BIT_DB_PREFIX."liberty_content_links` lcl
				INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON( lcl.`from_content_id`=lc.`content_id` )
			WHERE `to_content_id` = ?";

		if( $result = $this->mDb->query( $query, [ $pContentId ] ) ) {
			while( $row = $result->fetchRow() ) {
				// check if there are occasions of the old name with alternate display link name
				// --- ((Wiki Page|Description))
				// \([2]              # check for ((
				// \b$pOldName\b      # make sure the old name is on it's own
				// \|                 # the seperating deliminator
				// ([^\)]*)           # get as many characters as possible up to the next ) - put this in $1
				// \)[2]              # closing brackets ))
				$pattern[] = "!\([2]\b$pOldName\b\|([^\)]*)\)[2]!";

				// - replace with new name leaving description in tact
				$replace[] = "(($pNewName|$1))";

				// --- ((Wiki Page)) or WikiPage
				// (\([2])?           # check for (( - optional - put this in $1
				// \b$pOldName\b      # make sure the old name is on it's own
				// (\)[2])?           # closing brackets )) - optional - put this in $2
				$pattern[] = "!(\([2])?\b$pOldName\b(\)[2])?!";

				// - the replacement depends on the new name
				$replace[] = preg_match( "! !", $pNewName )
					// since we have a space in the final name, we need to have ((
					// and )) to make the link work
					?  "(($pNewName))"
					// no spaces in the new name either, so we only insert the ((
					// and )) if the author used them to start off with
					: "$1$pNewName$2";

				$data = preg_replace( $pattern, $replace, $row['data'] );
				if( md5( $data ) != md5( $row['data'] ) ) {
					$query = "UPDATE `".BIT_DB_PREFIX."liberty_content` SET `data`=? WHERE `content_id`=?";
					$this->mDb->query( $query, [ $data, $row['from_content_id'] ] );

					// remove any chached files pointing here
					LibertyContent::expungeCacheFile( $row['from_content_id'] );
				}
			}
		}

		# Fix up titles in the link table
		$query = "UPDATE `".BIT_DB_PREFIX."liberty_content_links` SET `to_title`=? WHERE `to_content_id`=?";
		$this->mDb->query( $query, [ $pNewName, $pContentId ] );
	}

	/**
	 * expunge bitlinks in the database
	 *
	 * @param numeric $pContentId
	 * @access public
	 * @return void
	 */
	public function expungeLinks( $pContentId ) {
		if( !empty( $pContentId )) {
			// remove any cached file pointing to this page
			$links = $this->mDb->getCol( "SELECT `from_content_id` FROM `".BIT_DB_PREFIX."liberty_content_links` WHERE to_content_id=?", [ $pContentId ] );
			foreach( $links as $content_id ) {
				LibertyContent::expungeCacheFile( $content_id );
			}
			$this->mDb->query( "DELETE FROM `".BIT_DB_PREFIX."liberty_content_links` WHERE from_content_id=? OR to_content_id=?", [ $pContentId, $pContentId ] );
		}
	}
}
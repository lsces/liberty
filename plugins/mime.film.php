<?php
/**
 * Mime handler for external, uncopied film files.
 *
 * Registers an already-on-disk video file as a liberty attachment WITHOUT copying or moving it —
 * for the srv9/desktop film library (Plex-managed today), too large to duplicate into
 * storage/attachments/. The source file's path (relative to the `fisheye_disk_storage_root`
 * kernel_config setting) is stored directly in liberty_files.file_name; nothing under
 * liberty_process_upload() is ever called, since there is no upload event for a scanned-in file.
 *
 * Requires the fisheye_disk_storage_root kernel_config value (set via the fisheye admin page) -
 * filesystem path the film library lives under. Playback goes through the same PHP-mediated
 * download endpoint fisheye's play_episode.php uses for episodes (mime_film_download() calls the
 * same liberty_serve_range_file() helper, see liberty_lib.php) rather than a direct nginx-served
 * static URL - real single-range HTTP Range support, proven live for episode/featurette playback
 * since 2026-09-02/03, so the originally-planned nginx location block for this tree was dropped
 * as unnecessary (2026-09-04, see fisheye.md).
 *
 * Lives in liberty/plugins/ (not fisheye/liberty_plugins/) even though fisheye is currently its
 * only consumer - a package-scoped liberty_plugins/ dir for a non-default mime guid only gets
 * scanned when that specific package is bootstrapped for the current request, which core liberty
 * pages (e.g. download_file.php) never do. Every other real mime handler (mime.default.php,
 * mime.video.php, mime.audio.php, mime.image.php, mime.pdf.php) already lives here for the same
 * reason.
 *
 * @package     liberty
 * @subpackage  liberty_mime_handler
 **/

namespace Bitweaver\Liberty;

use Bitweaver\BitBase;
use Bitweaver\KernelTools;

global $gLibertySystem;

/**
 * As a naming convention, the liberty mime handler definition should start with PLUGIN_MIME_GUID_
 */
define( 'PLUGIN_MIME_GUID_FILM', 'mimefilm' );

$pluginParams = [
	'verify_function'   => 'mime_film_verify',
	'store_function'    => 'mime_film_store',
	'update_function'   => 'mime_film_update',
	'load_function'     => 'mime_film_load',
	'download_function' => 'mime_film_download',
	'expunge_function'  => 'mime_film_expunge',
	'title'              => 'External Film Library',
	'description'        => 'Registers an already-on-disk video file as an attachment without copying or moving it.',
	// video.js templates already built for mime.video.php - a source_url is a source_url
	// regardless of where the file physically lives, so these are reused as-is.
	'view_tpl'           => 'bitpackage:liberty/mime/video/view.tpl',
	'inline_tpl'         => 'bitpackage:liberty/mime/video/inline.tpl',
	'storage_tpl'        => 'bitpackage:liberty/mime/video/storage.tpl',
	'attachment_tpl'     => 'bitpackage:liberty/mime/video/attachment.tpl',
	'plugin_type'        => MIME_PLUGIN,
	// Deliberately not auto-activated and no 'mimetypes' pattern registered - this plugin is
	// never picked for an ordinary upload, only ever set explicitly by an import/scan script.
	'auto_activate'      => false,
	// Required for plugins included by other plugins/scripts (not found via the normal scan
	// loop) - without this, registerPlugin() falls back to $this->mPluginFilePath, whatever
	// file LibertySystem last happened to be scanning, and stores the WRONG path into
	// kernel_config's liberty_plugin_path_mimefilm - silently breaking every future normal
	// page load's loadActivePlugins() (which include_once's that stored path directly).
	'file_name'          => 'mime.film.php',
];
$gLibertySystem->registerPlugin( PLUGIN_MIME_GUID_FILM, $pluginParams );

/**
 * fisheye_disk_storage_root, normalised to exactly one trailing slash - the admin field is a
 * plain text input and shouldn't need to be typed with a trailing slash to work correctly.
 *
 * @access public
 * @return string empty string if unconfigured
 */
if( !function_exists( '\Bitweaver\Liberty\mime_film_get_storage_root' )) {
	function mime_film_get_storage_root() {
		global $gBitSystem;
		$root = $gBitSystem->getConfig( 'fisheye_disk_storage_root', '' );
		return !empty( $root ) ? rtrim( $root, '/' ).'/' : '';
	}
}

/**
 * TV's two-root A-M/N-Z split (srv9: /media1/TV Shows/ = A-M, /media2/TV Shows/ = N-Z - desktop
 * has no physical split, both config keys point at the same /media3/) - derived from the show
 * title's first letter at read time rather than stored per-row, per the 2026-09-01 design
 * decision (see liberty.md). Film/music still use the single fisheye_disk_storage_root above;
 * only TV needs this.
 *
 * @param string $pShowTitle The show's title - only the first letter matters.
 * @return string empty string if unconfigured
 */
if( !function_exists( '\Bitweaver\Liberty\mime_film_get_tvshow_storage_root' )) {
	function mime_film_get_tvshow_storage_root( string $pShowTitle ): string {
		global $gBitSystem;
		$firstLetter = strtoupper( substr( ltrim( $pShowTitle ), 0, 1 ) );
		$configKey = ( $firstLetter >= 'A' && $firstLetter <= 'M' )
			? 'fisheye_tvshow_storage_root_am'
			: 'fisheye_tvshow_storage_root_nz';
		$root = $gBitSystem->getConfig( $configKey, '' );
		return !empty( $root ) ? rtrim( $root, '/' ).'/' : '';
	}
}

/**
 * Confirm the referenced file actually exists under the configured storage root.
 *
 * Unlike every other mime plugin, there is no $_FILES upload here - the caller (a future
 * import/scan script) must already have set $pStoreRow['file_name'] to the file's path
 * relative to fisheye_disk_storage_root, and $pStoreRow['content_id'] to whatever content
 * object this attachment belongs to.
 *
 * @param array $pStoreRow
 * @access public
 * @return bool true on success, false on failure - $pStoreRow['errors'] will contain reason
 */
if( !function_exists( '\Bitweaver\Liberty\mime_film_verify' )) {
	function mime_film_verify( &$pStoreRow ) {
		global $gBitSystem, $gBitUser;
		$ret = false;

		$root = mime_film_get_storage_root();
		if( empty( $root )) {
			$pStoreRow['errors']['file_name'] = KernelTools::tra( 'fisheye_disk_storage_root is not configured.' );
		} elseif( empty( $pStoreRow['file_name'] ) || !is_file( $root.$pStoreRow['file_name'] )) {
			$pStoreRow['errors']['file_name'] = KernelTools::tra( 'The source file could not be found under the configured storage root.' );
		} else {
			$pStoreRow['user_id']   = BitBase::verifyId( $pStoreRow['user_id'] ?? 0 ) ? $pStoreRow['user_id'] : ( BitBase::verifyId( $gBitUser->mUserId ?? 0 ) ? $gBitUser->mUserId : ROOT_USER_ID );
			$pStoreRow['mime_type'] = $gBitSystem->verifyMimeType( $root.$pStoreRow['file_name'] );
			// liberty_files.file_size is I4 (32-bit signed, max ~2.1GB) and regularly overflows
			// for a film - don't even try to store the real byte count, mime_film_load() always
			// computes it fresh via filesize() instead (same live-computed pattern as
			// source_file/thumbnail_url), so the DB value is never actually read for anything.
			$pStoreRow['file_size'] = 0;
			// no separate attachment_id sequence - matches the convention mime.flatdefault.php
			// already established for this site (attachment_id = content_id in the simple case)
			$pStoreRow['attachment_id'] = BitBase::verifyId( $pStoreRow['attachment_id'] ?? 0 ) ? $pStoreRow['attachment_id'] : $pStoreRow['content_id'];
			$ret = true;
		}
		return $ret;
	}
}

/**
 * Store the data in the database - no file copy/move, the row just points at the existing file.
 *
 * @param array $pStoreRow
 * @access public
 * @return bool true on success, false on failure
 */
if( !function_exists( '\Bitweaver\Liberty\mime_film_store' )) {
	function mime_film_store( &$pStoreRow ) {
		global $gBitSystem;
		$ret = false;
		if( BitBase::verifyId( $pStoreRow['attachment_id'] ?? 0 )) {
			$storeHash = [
				'file_id'   => $pStoreRow['attachment_id'],
				'file_name' => $pStoreRow['file_name'],
				'mime_type' => $pStoreRow['mime_type'],
				'file_size' => (int)$pStoreRow['file_size'],
				'user_id'   => $pStoreRow['user_id'],
			];
			$gBitSystem->mDb->associateInsert( BIT_DB_PREFIX.'liberty_files', $storeHash );

			$storeHash = [
				'attachment_id'          => $pStoreRow['attachment_id'],
				'content_id'             => $pStoreRow['content_id'],
				'attachment_plugin_guid' => PLUGIN_MIME_GUID_FILM,
				'foreign_id'             => $pStoreRow['attachment_id'],
				'user_id'                => $pStoreRow['user_id'],
			];
			$gBitSystem->mDb->associateInsert( BIT_DB_PREFIX.'liberty_attachments', $storeHash );
			$ret = true;
		}
		return $ret;
	}
}

/**
 * Called on every store() against an *existing* film - including a pure title/event_time edit
 * with no file change at all (that's the only real caller so far). Deliberately does NOT
 * write file_name back to liberty_files here, even though $pStoreRow['file_name'] is present -
 * by the time updateAttachmentParams() calls this, $pStoreRow is the already-*loaded* attachment
 * hash (LibertyMime::$mStorage), and mime_film_load() has already overwritten file_name to
 * basename($row['file_name']) for display. Writing that back would silently truncate the real
 * relative path in the DB, permanently losing the directory prefix - hit for real 2026-09-01,
 * corrupted three films' file_name via nothing more than a title edit before this fix (see
 * liberty.md's 2026-09-01 "update path also corrupts file_name" entry). Re-pointing a film at a
 * genuinely renamed file needs its own explicit field once that's a real use case, not this one.
 *
 * @param array $pStoreRow
 * @access public
 * @return bool true on success, false on failure
 */
if( !function_exists( '\Bitweaver\Liberty\mime_film_update' )) {
	function mime_film_update( &$pStoreRow ) {
		$ret = false;
		if( BitBase::verifyId( $pStoreRow['attachment_id'] ?? 0 )) {
			$ret = true;
		}
		return $ret;
	}
}

/**
 * Load file data from the database, resolving source_file/source_url against the configured
 * external storage root instead of STORAGE_PKG_PATH/liberty_mime_get_storage_branch().
 *
 * @param array $pFileHash
 * @param array $pPrefs
 * @access public
 * @return array|bool
 */
if( !function_exists( '\Bitweaver\Liberty\mime_film_load' )) {
	function mime_film_load( $pFileHash, &$pPrefs ) {
		global $gBitSystem;
		$ret = false;
		if( BitBase::verifyId( $pFileHash['attachment_id'] ?? 0 )) {
			$query = "
				SELECT la.`attachment_id`, la.`content_id`, la.`caption`, la.`hits` AS `downloads`,
					lf.`file_id`, lf.`file_name`, lf.`file_size`, lf.`mime_type`, lf.`user_id`
				FROM `".BIT_DB_PREFIX."liberty_attachments` la
				INNER JOIN `".BIT_DB_PREFIX."liberty_files` lf ON( la.`foreign_id`=lf.`file_id` )
				WHERE la.`attachment_id`=?";
			if( $row = $gBitSystem->mDb->getRow( $query, [ $pFileHash['attachment_id'] ] ) ) {
				$ret = array_merge( $pFileHash, $row );
				$root = mime_film_get_storage_root();

				$ret['source_file'] = !empty( $root ) ? $root.$row['file_name'] : null;
				// display name only - never the path, same defensive strip mime.default.php does
				$ret['file_name']   = basename( $row['file_name'] );
				$ret['display_url'] = LIBERTY_PKG_URL.'view_file.php?attachment_id='.$row['attachment_id'];
				$ret['preferences'] = $pPrefs;

				if( !empty( $ret['source_file'] ) && is_file( $ret['source_file'] )) {
					$ret['last_modified'] = filemtime( $ret['source_file'] );
					// real byte count, computed live - the DB column can't hold it (see verify)
					$ret['file_size'] = filesize( $ret['source_file'] );
					$ret['download_url']  = LibertyMime::getAttachmentDownloadUrl( $row['attachment_id'] );
					$ret['thumbnail_url'] = mime_film_get_thumbnail_url( $row['attachment_id'], $ret['source_file'] );
					$ret['media_url'] = $ret['source_file'];
					// Plays through the same PHP-mediated download endpoint fisheye's own
					// episode/featurette playback uses - mime_film_download() now streams via
					// liberty_serve_range_file(), real HTTP Range support, seeking works (see
					// mime_film_download()'s own docblock).
					$ret['source_url'] = $ret['download_url'];
				}
			}
		}
		return $ret;
	}
}

/**
 * Resolve (creating if necessary) a thumbnail for a film's source file. Tries, in order:
 *   1. a sidecar poster image already sitting next to the source file (<basename>-poster.jpg)
 *   2. a frame grab via ffmpegthumbnailer/ffmpeg (same tool mime.video.php's
 *      mime_video_create_thumbnail() uses, but that function can't be called directly here -
 *      it computes its cache branch as STORAGE_PKG_PATH-relative to the source file's own
 *      directory, which breaks for a source file living outside STORAGE_PKG_PATH entirely)
 * Either way, the actual thumbnail image is small and gets cached normally inside
 * storage/attachments/ (keyed by attachment_id), same as any other mime plugin - only the
 * (large) source video itself stays external and uncopied.
 *
 * Deliberately NOT implemented yet: copying Plex's own cached artwork bundle as a third
 * fallback (see liberty.md's 2026-09-01 scoping entries) - that's a one-time bulk-import
 * concern, not something to run per page load here.
 *
 * @param int $pAttachmentId
 * @param string $pSourceFile
 * @access public
 * @return array thumbnail_url hash, same shape liberty_fetch_thumbnails() returns
 */
/**
 * Grab a single representative frame from a video file as a JPEG - ffmpegthumbnailer first
 * (fast, seeks intelligently), falling back to a plain ffmpeg seek-and-grab if
 * ffmpegthumbnailer isn't installed or produced nothing. Factored out of
 * mime_film_get_thumbnail_url() below (same two commands, same 60s timeout, unchanged) so
 * FisheyeSeason::reloadPlexImages() can reuse the exact same chain for a season with no
 * Plex-provided artwork at all (2026-09-03) - a season has no attachment of its own for the
 * normal mime-plugin thumbnail pipeline to hook into, so it calls this directly against one
 * of its episode video files instead.
 *
 * @param string $pSourceFile    the video file to grab a frame from
 * @param string $pDestJpegPath  where to write the grabbed frame - overwritten if it exists
 * @param int $pSeekSeconds      how far into the video to seek before grabbing (only used by
 *   the ffmpeg fallback - ffmpegthumbnailer picks its own seek point)
 * @return bool  true if $pDestJpegPath now holds a real frame grab
 */
if( !function_exists( '\Bitweaver\Liberty\mime_film_grab_video_frame' )) {
	function mime_film_grab_video_frame( string $pSourceFile, string $pDestJpegPath, int $pSeekSeconds = 60 ): bool {
		global $gBitSystem;
		@unlink( $pDestJpegPath );
		$thumbnailer = trim( shell_exec( 'which ffmpegthumbnailer' ) ?? '' );
		if( !empty( $thumbnailer ) && is_executable( $thumbnailer )) {
			shell_exec( "timeout 60 ".escapeshellcmd( $thumbnailer )." -i ".escapeshellarg( $pSourceFile )." -o ".escapeshellarg( $pDestJpegPath )." -s 1024" );
		}
		if( !is_file( $pDestJpegPath ) || filesize( $pDestJpegPath ) <= 1 ) {
			$ffmpeg = trim( $gBitSystem->getConfig( 'ffmpeg_path', shell_exec( 'which ffmpeg' ) ?? '' ) );
			if( !empty( $ffmpeg ) && is_executable( $ffmpeg )) {
				shell_exec( "timeout 60 ".escapeshellcmd( $ffmpeg )." -i ".escapeshellarg( $pSourceFile )." -an -ss ".(int)$pSeekSeconds." -t 00:00:01 -r 1 -y ".escapeshellarg( $pDestJpegPath )." 2>&1" );
			}
		}
		return is_file( $pDestJpegPath ) && filesize( $pDestJpegPath ) > 1;
	}
}

if( !function_exists( '\Bitweaver\Liberty\mime_film_get_thumbnail_url' )) {
	function mime_film_get_thumbnail_url( $pAttachmentId, $pSourceFile ) {
		global $gBitSystem;
		$ret = [];
		$destBranch = liberty_mime_get_storage_branch( [ 'attachment_id' => $pAttachmentId ] );
		$destPath   = STORAGE_PKG_PATH.$destBranch;

		// The first downloaded Plex alternate (FisheyeFilm::reloadPlexImages(), stored right
		// here in this same branch - "storage/attachments/<branch>/ has always been used as home
		// for extras like the plex images and any manual uploads", Lester 2026-09-04) always
		// wins over an auto-generated frame grab when one's available - a real DVD-style poster
		// beats a random video frame. attachment_id === content_id for this plugin (see
		// mime_film_verify()'s own comment), so $pAttachmentId doubles as the xref lookup key
		// directly. Checked and regenerated every call, same as the external sidecar file this
		// replaced always was - cheap, and the only way a promoteImageToThumbnail() xorder change
		// or a fresh reloadPlexImages() ever gets picked up without extra bookkeeping.
		$posterSource = null;
		$imageRows = $gBitSystem->mDb->getAll(
			"SELECT xkey_ext FROM `".BIT_DB_PREFIX."liberty_xref` WHERE content_id = ? AND item = 'image' ORDER BY xorder ASC",
			[ $pAttachmentId ]
		);
		foreach( $imageRows as $imageRow ) {
			if( is_file( $destPath.$imageRow['xkey_ext'] ) ) {
				$posterSource = $destPath.$imageRow['xkey_ext'];
				break;
			}
		}

		$tmpFramePath = $destPath.'film_thumb_tmp.jpg';
		if( empty( $posterSource ) && !is_file( $destPath.'thumbs/small.jpg' ) && !is_file( $destPath.'thumbs/small.png' )) {
			// nothing cached yet and no Plex image - grab a frame
			KernelTools::mkdir_p( $destPath );
			if( mime_film_grab_video_frame( $pSourceFile, $tmpFramePath ) ) {
				$posterSource = $tmpFramePath;
			}
		}

		if( !empty( $posterSource )) {
			$fileHash = [
				'type'        => 'image/jpeg',
				'source_file' => $posterSource,
				'dest_branch' => $destBranch,
			];
			liberty_generate_thumbnails( $fileHash );
			if( $posterSource === $tmpFramePath ) {
				@unlink( $posterSource );
			}
		}

		// bare directory (trailing slash, no filename) - liberty_fetch_thumbnails() only uses
		// this to derive the directory to scan, and checks its own thumbs/ subdir already
		$ret = liberty_fetch_thumbnails( [ 'source_file' => $destPath ] );
		return $ret;
	}
}

/**
 * Serve the download. Deliberately NOT delegating to mime_default_download() - its nginx
 * branch builds the X-Accel-Redirect target as str_replace(STORAGE_PKG_PATH, STORAGE_PKG_URL,
 * source_file), which is a no-op for a source_file outside STORAGE_PKG_PATH and would hand
 * nginx a raw filesystem path instead of a URI (nginx would reject it).
 *
 * Streams via liberty_serve_range_file() (liberty_lib.php) - real single-range HTTP Range
 * support, same implementation fisheye's play_episode.php uses for episode/featurette playback.
 * Previously used a plain readfile() fallback that advertised `Accept-Ranges: bytes` without
 * ever actually honouring a Range request (always sent the full file as 200 OK) - worse than not
 * advertising it at all, since a <video> element's seek bar believed seeking should work and
 * silently failed. Fixed 2026-09-04 (see fisheye.md) once play_episode.php's own approach was
 * proven live, making the originally-planned nginx static-URL piece unnecessary.
 *
 * @param array $pFileHash
 * @access public
 * @return bool
 */
if( !function_exists( '\Bitweaver\Liberty\mime_film_download' )) {
	function mime_film_download( &$pFileHash ) {
		$ret = false;
		if( !empty( $pFileHash['source_file'] ) && is_readable( $pFileHash['source_file'] )) {
			header( 'Last-Modified: '.gmdate( 'D, d M Y H:i:s T', $pFileHash['last_modified'] ?? time() ), true, 200 );
			// inline, not attachment - this is also player.tpl's <video><source> target
			// (download_file.php is the one shared route for both playback and an explicit
			// download), and Content-Disposition: attachment is respected by some browsers even
			// inside a <video> element, working against smooth streaming/seeking on a multi-GB
			// file (found live 2026-09-04 - view_film.php "slow loading"). play_episode.php
			// already gets this right for episodes/featurettes; mime_default_download() stays
			// attachment since that generic default never feeds a <video> tag.
			header( 'Content-Disposition: inline; filename="'.$pFileHash['file_name'].'"' );
			header( 'Cache-Control: no-cache,must-revalidate' );
			liberty_serve_range_file( $pFileHash['source_file'], $pFileHash['mime_type'] );
			$ret = true;
		} else {
			$pFileHash['errors']['no_file'] = KernelTools::tra( 'No matching file found.' );
		}
		return $ret;
	}
}

/**
 * Remove the database rows only - NEVER touch the physical file, it's not liberty's to delete.
 * This is why mime_default_expunge() (which unlinks the source) cannot be reused here.
 *
 * @param integer $pAttachmentId
 * @access public
 * @return bool
 */
if( !function_exists( '\Bitweaver\Liberty\mime_film_expunge' )) {
	function mime_film_expunge( $pAttachmentId ) {
		global $gBitSystem;
		$ret = false;
		if( BitBase::verifyId( $pAttachmentId )) {
			if( $fileHash = LibertyMime::loadAttachment( $pAttachmentId )) {
				$gBitSystem->mDb->query( "DELETE FROM `".BIT_DB_PREFIX."liberty_files` WHERE `file_id`=?", [ $fileHash['foreign_id'] ] );
				$ret = true;
			}
		}
		return $ret;
	}
}

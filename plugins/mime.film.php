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
 * filesystem path the film library lives under. source_url is still null until an nginx
 * location serving that same tree exists (not configured on any server yet).
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
					// Interim: play through the same PHP-mediated download endpoint rather than
					// a direct static URL - works right now (confirmed streaming the real file
					// correctly), but mime_film_download()'s readfile() fallback doesn't honour
					// HTTP Range requests, so seeking in the player won't work and a PHP-FPM
					// worker stays busy for the whole stream. Replace with a direct nginx-served
					// URL (fisheye_disk_storage_root's own location block) once that exists -
					// see liberty.md's 2026-09-01 entries.
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
if( !function_exists( '\Bitweaver\Liberty\mime_film_get_thumbnail_url' )) {
	function mime_film_get_thumbnail_url( $pAttachmentId, $pSourceFile ) {
		global $gBitSystem;
		$ret = [];
		$destBranch = liberty_mime_get_storage_branch( [ 'attachment_id' => $pAttachmentId ] );
		$destPath   = STORAGE_PKG_PATH.$destBranch;

		$posterSource = null;
		$sidecar = preg_replace( '/\.[^.\/]+$/', '', $pSourceFile ).'-poster.jpg';
		if( is_file( $sidecar )) {
			$posterSource = $sidecar;
		} elseif( !is_file( $destPath.'thumbs/small.jpg' ) && !is_file( $destPath.'thumbs/small.png' )) {
			// nothing cached yet and no sidecar - grab a frame
			$thumbnailer = trim( shell_exec( 'which ffmpegthumbnailer' ) ?? '' );
			$tmpFile = $destPath.'film_thumb_tmp.jpg';
			KernelTools::mkdir_p( $destPath );
			if( !empty( $thumbnailer ) && is_executable( $thumbnailer )) {
				shell_exec( "timeout 60 ".escapeshellcmd( $thumbnailer )." -i ".escapeshellarg( $pSourceFile )." -o ".escapeshellarg( $tmpFile )." -s 1024" );
			}
			if( !is_file( $tmpFile ) || filesize( $tmpFile ) <= 1 ) {
				$ffmpeg = trim( $gBitSystem->getConfig( 'ffmpeg_path', shell_exec( 'which ffmpeg' ) ?? '' ) );
				if( !empty( $ffmpeg ) && is_executable( $ffmpeg )) {
					shell_exec( "timeout 60 ".escapeshellcmd( $ffmpeg )." -i ".escapeshellarg( $pSourceFile )." -an -ss 60 -t 00:00:01 -r 1 -y ".escapeshellarg( $tmpFile )." 2>&1" );
				}
			}
			if( is_file( $tmpFile ) && filesize( $tmpFile ) > 1 ) {
				$posterSource = $tmpFile;
			}
		}

		if( !empty( $posterSource )) {
			$fileHash = [
				'type'        => 'image/jpeg',
				'source_file' => $posterSource,
				'dest_branch' => $destBranch,
			];
			liberty_generate_thumbnails( $fileHash );
			if( $posterSource !== $sidecar ) {
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
 * nginx a raw filesystem path instead of a URI (nginx would reject it). Until source_url is
 * actually wired up (see load_function's TODO), always use the plain readfile() fallback -
 * correct for any web server, just not as efficient for a large file as X-Accel-Redirect would
 * be. Swap this for the nginx branch once an internal-serving location for the storage root
 * exists.
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
			header( 'Content-Disposition: attachment; filename="'.$pFileHash['file_name'].'"' );
			header( 'Content-type: '.$pFileHash['mime_type'] );
			header( 'Cache-Control: no-cache,must-revalidate' );
			header( 'Accept-Ranges: bytes' );
			header( 'Content-Length: '.filesize( $pFileHash['source_file'] ) );
			// ob_clean() alone only empties the current buffer, it doesn't stop buffering -
			// readfile()'s output would still get captured into it instead of streamed, which
			// for a multi-GB film means readfile() tries to hold the whole file in memory at
			// once and hits PHP's memory_limit. Fully exit every active buffer level first.
			while( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			flush();
			readfile( $pFileHash['source_file'] );
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

<?php
/**
 * Gateway admin page for a site's private local scheme(s) -
 * <site root>/config/local/xref_schemes/*.php, same site-specific-but-not-a-package philosophy
 * as config_inc.php. Discovers whatever scheme files exist for the site currently being
 * administered and, on request, applies them: xref group/item rows via LibertyXrefScheme::apply(),
 * and any 'galleries' entries by ensuring each named top-level fisheye gallery exists (checked by
 * title first, so re-running is always safe).
 *
 * Deliberately minimal for now - just discover+apply, no in-browser editing of the scheme files
 * themselves (that's real content-management work, parked as a known follow-up). Runs as a
 * genuine authenticated admin request, so ownership/permissions are handled the normal way -
 * no CLI login impersonation needed, unlike the retracted /etc/webstack/scripts/
 * apply_local_scheme.php this replaces (wrong location - that was real framework logic, not
 * server/infra config, see fisheye.md's 2026-09-02 entry).
 *
 * @package liberty
 */

use Bitweaver\Liberty\LibertyXrefScheme;
use Bitweaver\KernelTools;

require_once '../../kernel/includes/setup_inc.php';

$gBitSystem->verifyPermission( 'p_admin' );

$schemeDir = BIT_ROOT_PATH.'config/local/xref_schemes';
$schemeFiles = is_dir( $schemeDir ) ? glob( $schemeDir.'/*.php' ) : [];

$results = null;
if( !empty( $_REQUEST['fApply'] ) && $schemeFiles ) {
	$groups = [];
	$items  = [];
	$galleryTitles = [];
	foreach( $schemeFiles as $file ) {
		$scheme = require $file;
		$groups = array_merge( $groups, $scheme['groups'] ?? [] );
		$items  = array_merge( $items,  $scheme['items']  ?? [] );
		$galleryTitles = array_merge( $galleryTitles, $scheme['galleries'] ?? [] );
	}

	$counts = LibertyXrefScheme::apply( $groups, $items );

	$galleryResults = [];
	// Pragmatic simplification, not generic: this creates fisheye galleries specifically,
	// the only real consumer of the 'galleries' key so far. Generalising to "create any named
	// content object of any class" is real design work, parked deliberately for now.
	if( $galleryTitles && class_exists( '\Bitweaver\Fisheye\FisheyeGallery' ) ) {
		foreach( array_unique( $galleryTitles ) as $title ) {
			$existing = $gBitDb->getOne(
				"SELECT lc.content_id FROM `".BIT_DB_PREFIX."liberty_content` lc INNER JOIN `".BIT_DB_PREFIX."fisheye_gallery` fg ON fg.content_id = lc.content_id WHERE lc.content_type_guid = 'fisheyegallery' AND lc.title = ?",
				[ $title ]
			);
			if( $existing ) {
				$galleryResults[] = "'$title' already exists (content_id=$existing)";
				continue;
			}
			$gallery = new \Bitweaver\Fisheye\FisheyeGallery();
			$pParamHash = [ 'title' => $title ];
			if( $gallery->store( $pParamHash ) ) {
				$galleryResults[] = "Created '$title' (content_id={$gallery->mContentId}, gallery_id={$gallery->mGalleryId})";
			} else {
				$galleryResults[] = "FAILED '$title': ".implode( '; ', $gallery->mErrors );
			}
		}
	}

	$results = [
		'counts'          => $counts,
		'gallery_results' => $galleryResults,
	];
}

$gBitSmarty->assign( 'schemeDir',   $schemeDir );
$gBitSmarty->assign( 'schemeFiles', array_map( 'basename', $schemeFiles ) );
$gBitSmarty->assign( 'results',     $results );

$gBitSystem->display( 'bitpackage:liberty/admin_local_scheme.tpl', KernelTools::tra( 'Local Scheme' ), [ 'display_mode' => 'admin' ] );

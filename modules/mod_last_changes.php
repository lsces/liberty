<?php
/**
 * @version $Header$
 * @package liberty
 * @subpackage modules
 * Params:
 * - content_type_guid : if set, show only those content_type_guid's
 * - show_date : if set, show date of last modification
 */

/**
 * Initialization
 */
use Bitweaver\KernelTools;
global $gQueryUser, $gBitUser, $gLibertySystem, $moduleParams;
extract( $moduleParams );

$userId = null;
if( !empty( $gQueryUser->mUserId ) ) {
	$userId = $gQueryUser->mUserId;
}

if( empty( $module_title ) ) {
	if( !empty( $module_params['content_type_guid'] ) && !empty( $gLibertySystem->mContentTypes[$module_params['content_type_guid']] ) ) {
		$title = KernelTools::tra( "Last Changes" ).': '.$gLibertySystem->getContentTypeName( $module_params['content_type_guid'] );
	} else {
		$title = KernelTools::tra( "Last Changes" );
	}
	$moduleParams['title'] = $title;
}

if( !empty( $module_params['show_date'] ) ) {
	$gBitSmarty->assign( 'showDate',  true  );
}

$gBitSmarty->assign( 'contentType', !empty( $module_params['content_type_guid'] ) );

$listHash = [
	'content_type_guid' => !empty( $module_params['content_type_guid'] ) ? $module_params['content_type_guid'] : null,
	'offset' => 0,
	'max_records' => $module_rows,
	'sort_mode' => 'last_modified_desc',
	'user_id' => $userId,
];
$modLastContent = $gBitUser->getContentList( $listHash );
$gBitSmarty->assign( 'modLastContent', $modLastContent );
?>

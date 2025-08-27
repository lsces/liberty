<?php
/**
 * @version $Header$
 * @package liberty
 * @subpackage modules
 */

/**
 * Initial Setup
 */
use Bitweaver\KernelTools;
use Bitweaver\Liberty\LibertyComment;
global $gQueryUser, $gBitUser, $gLibertySystem, $moduleParams;
$params = $moduleParams['module_params'];
$moduleTitle = !empty($moduleParams['title'])? $moduleParams['title'] : null;

$userId = null;
if( !empty( $gQueryUser->mUserId ) ) {
	$userId = $gQueryUser->mUserId;
}

$listHash = array(
	'user_id' => $userId,
	'max_records' => $moduleParams['module_rows'],
);

if (!empty($params['full'])) {
	$listHash['parse'] = true;
}

if (!empty($params['sort'])) {
	$listHash['sort_mode'] = $params['sort'];
}

if (!empty($params['pigeonholes'])) {
	$listHash['pigeonholes']['root_filter'] = $params['pigeonholes'];
}

if( !empty( $params['root_content_type_guid'] ) ) {
	if( empty($moduleTitle) && is_string( $params['root_content_type_guid'] ) ) {
		$moduleTitle = $gLibertySystem->getContentTypeName( $params['root_content_type_guid'] ).' '. KernelTools::tra( 'Comments' );
	}
	$listHash['root_content_type_guid'] = $params['root_content_type_guid'];
}
$gBitSmarty->assign( 'moduleTitle', $moduleTitle );

$lcom = new LibertyComment();
$modLastComments = $lcom->getList( $listHash );
$gBitSmarty->assign( 'modLastComments', $modLastComments );

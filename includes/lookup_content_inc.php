<?php
/**
 * lookup_content_inc
 *
 * @author   spider <spider@steelsun.com>
 * @version  $Revision$
 * @package  liberty
 * @subpackage functions
 */

/**
 * required setup
 */
namespace Bitweaver\Liberty;
use Bitweaver\BitBase;

global $gContent;

if( !empty($_REQUEST['structure_id']) && BitBase::verifyId( $_REQUEST['structure_id'] ) ) {
	$_REQUEST['structure_id'] = preg_replace( '/[\D]/', '', $_REQUEST['structure_id'] );
	$gStructure = new LibertyStructure( $_REQUEST['structure_id'] );
	if( $gStructure->load() ) {
		$gStructure->loadNavigation();
		$gStructure->loadPath();
		$gBitSmarty->assign( 'structureInfo', $gStructure->mInfo );
//		$_REQUEST['page_id'] = $gStructure->mInfo['page_id'];
		if( $viewContent = LibertyBase::getLibertyObject( $gStructure->mInfo['content_id'], $gStructure->mInfo['content_type']['content_type_guid'] ) ) {
			$viewContent->setStructure( $_REQUEST['structure_id'] );
			$gBitSmarty->assign( 'pageInfo', $viewContent->mInfo );
			$gContent = &$viewContent;
			$gBitSmarty->assign( 'gContent', $gContent );
		}
	}
} elseif( !empty($_REQUEST['content_id']) && BitBase::verifyId( $_REQUEST['content_id'] ) ) {
	$_REQUEST['content_id'] = preg_replace( '/[\D]/', '', $_REQUEST['content_id'] );

	if( $gContent = LibertyBase::getLibertyObject( $_REQUEST['content_id'] ) ) {
		$gBitSmarty->assign( 'gContent', $gContent );
		$gBitSmarty->assign( 'pageInfo', $gContent->mInfo );
	}
}


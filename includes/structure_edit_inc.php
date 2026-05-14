<?php
/**
 * structure_edit_inc
 *
 * @author   Christian Fowler
 * @package  liberty
 * @subpackage functions
 */

// Copyright (c) 2004, Christian Fowler, et. al.
// All Rights Reserved. See below for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details.

namespace Bitweaver\Liberty;

require_once '../kernel/includes/setup_inc.php';
use Bitweaver\BitBase;
use Bitweaver\KernelTools;

if( !BitBase::verifyId( $_REQUEST["structure_id"] ) ) {
	$gBitSystem->fatalError( KernelTools::tra( "No structure indicated" ));
} else {
	global $gStructure;
	$gStructure = new LibertyStructure( $_REQUEST["structure_id"] );
	$gStructure->load();

	// order matters for these conditionals
	if( empty( $gStructure ) || !$gStructure->isValid() ) {
		$gBitSystem->fatalError( KernelTools::tra( 'Invalid structure' ));
	}

	if( $gStructure->mInfo['root_structure_id'] == $gStructure->mInfo['structure_id'] ) {
		$rootStructure = &$gStructure;
	} else {
		$rootStructure = new LibertyStructure( $gStructure->mInfo['root_structure_id'] );
		$rootStructure->load();
		$rootStructure->loadNavigation();
		$rootStructure->loadPath();
	}

	if( empty( $gContent ) ) {
		$gContent = LibertyContent::getLibertyObject( $gStructure->getField( 'content_id' ) );
		$gContent->verifyUpdatePermission();
	}

	$gBitSmarty->assign( 'gStructure', $gStructure );
	$gBitSmarty->assign( 'editingStructure', true );
	$gBitSmarty->assign('structureInfo', $gStructure->mInfo);

	// Store the actively stored structure name
	$gBitUser->storePreference( 'edit_structure_name', $rootStructure->mInfo['title'] );
	$gBitUser->storePreference( 'edit_structure_id', $rootStructure->mStructureId );

	if( ( isset( $_REQUEST["action"] ) && ( $_REQUEST["action"] == 'remove' ) ) || !empty( $_REQUEST["confirm"] ) ) {
		$gBitUser->verifyTicket();
		if( $_REQUEST["action"] == 'remove' && ($gBitThemes->isAjaxRequest() || !empty( $_REQUEST["confirm"] )) ) {
			$gBitUser->verifyTicket();
			if( $gStructure->removeStructureNode( $_REQUEST["structure_id"], false ) ) {
				if( $gBitThemes->isAjaxRequest() ) {
					$feedback['success'] = KernelTools::tra( "removed from" ).' '.$gContent->getContentTypeName();
				} else {
					KernelTools::bit_redirect( $_SERVER['SCRIPT_NAME'].'?structure_id='.$gStructure->mInfo["parent_id"] );
				}
			} else {
				$feedback['error'] = $gStructure->mErrors;
			}
			$gBitSmarty->assign('feedback', $feedback);
		} elseif( $_REQUEST["action"] == 'remove' ) {
			$gBitSystem->setBrowserTitle( KernelTools::tra('Confirm removal of ').$gContent->getTitle() );
			$formHash['action'] = 'remove';
			$formHash['remove'] = true;
			$formHash['structure_id'] = $_REQUEST['structure_id'];
			$msgHash = [
				'label' => KernelTools::tra('Remove content from Structure'),
				'confirm_item' => $gContent->getTitle().KernelTools::tra('and any subitems'),
				'warning' => KernelTools::tra('This will remove the content from the structure but will <strong>not</strong> modify or remove the content itself.'),
			];
			$gBitSystem->confirmDialog( $formHash,$msgHash );
		}
	} elseif (isset($_REQUEST["move_node"])) {
		if ($_REQUEST["move_node"] == '1') {
			$gStructure->moveNodeWest();
		} elseif ($_REQUEST["move_node"] == '2') {
			$gStructure->moveNodeNorth();
		}	elseif ($_REQUEST["move_node"] == '3') {
			$gStructure->moveNodeSouth();
		} elseif ($_REQUEST["move_node"] == '4') {
			$gStructure->moveNodeEast();
		}
		KernelTools::bit_redirect( $_SERVER['SCRIPT_NAME'].'?structure_id='.$gStructure->mInfo["structure_id"] );
	} elseif( !empty( $_REQUEST['submit_structure'] ) ) {
		if( $gStructure->storeStructure( $_REQUEST ) ) {
			$feedback['success'] = KernelTools::tra( "Your changes were successfully saved." );
		} else {
			$feedback['error'] = $gStructure->mErrors;
		}
	} elseif (isset($_REQUEST["create"]) || (isset( $_REQUEST["action"] ) && $_REQUEST["action"] == 'add') ) {
		$structureHash['root_structure_id'] = $rootStructure->mStructureId;
		$structureHash['parent_id'] = $_REQUEST['structure_id'];

		$after = null;
		if (isset($_REQUEST['after_ref_id'])) {
			$structureHash['after_ref_id'] = $_REQUEST['after_ref_id'];
		}
		if (!(empty($_REQUEST['name']))) {
//			$gStructure->s_create_page($_REQUEST["structure_id"], $after, $_REQUEST["name"], '');
		} elseif(!empty($_REQUEST['content'])) {
			foreach ($_REQUEST['content'] as $conId ) {
				$structureHash['content_id'] = $conId;
				if( $new_structure_id = $gStructure->storeNode( $structureHash ) ) {
					$structureHash['after_ref_id'] = $new_structure_id;
					$feedback['success'] = KernelTools::tra( "added to" ).' '.$gContent->getContentTypeName();
				} else {
					$feedback['failure'] = $gStructure->mErrors;
				}
			}
		}
	}

	$structureTocId = $rootStructure->mStructureId;
	$gBitSmarty->assign( 'structureToc', $rootStructure->getToc() );
	$gBitSmarty->assign( 'structureTocId', $structureTocId );
	$gBitSmarty->assign('feedback', $feedback);
}
	$gBitSmarty->assign( 'editingStructure', false );

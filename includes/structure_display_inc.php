<?php
/**
 * structure_display_inc
 *
 * @author   spider <spider@steelsun.com>
 * @version  $Revision$
 * @package  liberty
 * @subpackage functions
 */

/**
 * required setup
 */
use Bitweaver\KernelTools;
global $gContent;
include_once( LIBERTY_PKG_INCLUDE_PATH.'lookup_content_inc.php' );
if( is_object( $gContent ) && $gContent->isValid() ) {
	$gBitSystem->setBrowserTitle( $gStructure->getRootTitle().' : '.$gContent->getTitle() );
	$gBitSystem->setCanonicalLink( $gContent->getDisplayUrl() );
	include $gContent->getRenderFile();
} else {
	$gBitSystem->fatalError( KernelTools::tra( 'Page cannot be found' ), null, null, HttpStatusCodes::HTTP_GONE );
}

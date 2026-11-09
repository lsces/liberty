<?php
/**
 * Admin page: bring the PDFs already stored on this site up to the current text handling (see LibertyPdfText) - the whole text layer in
 * liberty_content_data, a short summary in the description, the text in the search index. A press converts the next few, and carries on from where
 * it stopped; a PDF with no text layer (a scan) is reported and skipped.
 * @package liberty
 */

use Bitweaver\Liberty\LibertyPdfText;
use Bitweaver\KernelTools;

require_once '../../kernel/includes/setup_inc.php';

$gBitSystem->verifyPermission( 'p_admin' );

$result = null;
if( !empty( $_REQUEST['convert'] ) ) {
	$result = LibertyPdfText::convertNext( (int)( $_REQUEST['after'] ?? 0 ) );
}

$gBitSmarty->assign( 'counts', LibertyPdfText::counts() );
$gBitSmarty->assign( 'result', $result );
$gBitSmarty->assign( 'batch', LibertyPdfText::BATCH );
$gBitSystem->display( 'bitpackage:liberty/admin_pdf_text.tpl', KernelTools::tra( 'PDF text' ), [ 'display_mode' => 'admin' ] );

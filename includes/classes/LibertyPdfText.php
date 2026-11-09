<?php
/**
 * @package liberty
 * @subpackage classes
 */

namespace Bitweaver\Liberty;

use Bitweaver\BitBase;

/**
 * Brings the PDFs stored through the mimepdf plugin up to the current text handling: the whole text layer in liberty_content_data
 * (data_type 'text'), only a short summary in liberty_content.data, and the text in the search index. PDFs stored before that
 * change kept the whole text in liberty_content.data (or, when no text was extracted at the time, no text at all). Used by
 * admin/admin_pdf_text.php; each content is done on its own, so a run can be stopped and repeated.
 *
 * A PDF counts as done when its content has a 'text' row. One with no text layer (a scan) never gets one, so it is reported
 * and skipped by the cursor the page passes on, not retried by every press.
 */
class LibertyPdfText {
	/** PDFs converted per press: each is read by pdftotext, large ones take a while. */
	public const BATCH = 10;

	/** Text shorter than this is a scan's stray characters (a page number, a header), not a text layer: reported as "no text" and not stored. */
	private const MIN_TEXT_LENGTH = 200;

	/** A description longer than this is taken to be the old text layer and replaced by a summary; a shorter one is somebody's own and stays. */
	private const OLD_TEXT_LENGTH = 1000;

	/** @return array{total:int,done:int,todo:int} mimepdf attachments, those whose content has a text row, and the rest */
	public static function counts(): array {
		global $gBitDb;
		$total = (int)$gBitDb->getOne( "SELECT COUNT(*) FROM `".BIT_DB_PREFIX."liberty_attachments` WHERE `attachment_plugin_guid` = 'mimepdf'" );
		$todo = (int)$gBitDb->getOne( "SELECT COUNT(*) FROM `".BIT_DB_PREFIX."liberty_attachments` a WHERE a.`attachment_plugin_guid` = 'mimepdf' AND ".self::notDoneSql() );
		return [ 'total' => $total, 'done' => $total - $todo, 'todo' => $todo ];
	}

	/** SQL for "this attachment's content has no text row yet" (a.content_id). */
	private static function notDoneSql(): string {
		return "NOT EXISTS ( SELECT 1 FROM `".BIT_DB_PREFIX."liberty_content_data` d WHERE d.`content_id` = a.`content_id` AND d.`data_type` = 'text' )";
	}

	/**
	 * Convert the next BATCH PDFs after a content_id (0 = from the start).
	 *
	 * @return array{rows:array,last:int,more:bool} one row per PDF - content_id, title, result (converted, no text, missing, error) and the text length
	 */
	public static function convertNext( int $pAfter = 0 ): array {
		global $gBitDb;
		$rows = [];
		$found = $gBitDb->getAll(
			"SELECT FIRST ".self::BATCH." a.`attachment_id`, a.`content_id`, lc.`title` FROM `".BIT_DB_PREFIX."liberty_attachments` a
			 JOIN `".BIT_DB_PREFIX."liberty_content` lc ON lc.`content_id` = a.`content_id`
			 WHERE a.`attachment_plugin_guid` = 'mimepdf' AND a.`content_id` > ? AND ".self::notDoneSql()."
			 ORDER BY a.`content_id`", [ $pAfter ] ) ?: [];
		$last = $pAfter;
		foreach( $found as $row ) {
			$last = (int)$row['content_id'];
			$rows[] = [ 'content_id' => $last, 'title' => (string)$row['title'] ] + self::convertOne( (int)$row['attachment_id'], $last );
		}
		$more = (bool)$gBitDb->getOne(
			"SELECT a.`content_id` FROM `".BIT_DB_PREFIX."liberty_attachments` a WHERE a.`attachment_plugin_guid` = 'mimepdf' AND a.`content_id` > ? AND ".self::notDoneSql(), [ $last ] );
		return [ 'rows' => $rows, 'last' => $last, 'more' => $more ];
	}

	/** @return array{result:string,length:int} */
	public static function convertOne( int $pAttachmentId, int $pContentId ): array {
		global $gBitSystem, $gBitDb, $gLibertySystem;
		$attachment = LibertyMime::loadAttachment( $pAttachmentId );
		$file = is_array( $attachment ) ? (string)( $attachment['source_file'] ?? '' ) : '';
		if( $file === '' || !is_file( $file ) ) {
			return [ 'result' => 'missing', 'length' => 0 ];
		}
		$pdftotext = trim( (string)$gBitSystem->getConfig( 'pdftotext_path', trim( (string)shell_exec( 'which pdftotext' ) ) ?: '/usr/bin/pdftotext' ) );
		if( !is_executable( $pdftotext ) ) {
			return [ 'result' => 'error: pdftotext is not installed', 'length' => 0 ];
		}
		$text = (string)shell_exec( escapeshellarg( $pdftotext ).' -enc UTF-8 '.escapeshellarg( $file ).' - 2>/dev/null' );
		if( mb_strlen( trim( $text ) ) < self::MIN_TEXT_LENGTH ) {
			return [ 'result' => 'no text', 'length' => 0 ];
		}
		$content = LibertyBase::getLibertyObject( $pContentId );
		if( !$content || !$content->isValid() ) {
			return [ 'result' => 'error: the content could not be loaded', 'length' => 0 ];
		}
		$content->storeData( $text, 'text' );

		// the summary is the plugin's own; its functions are loaded with the mime plugins
		if( !function_exists( '\Bitweaver\Liberty\mime_pdf_summary' ) ) {
			$gLibertySystem->scanAllPlugins( null, "mime\\." );
		}
		$old = (string)$gBitDb->getOne( "SELECT `data` FROM `".BIT_DB_PREFIX."liberty_content` WHERE `content_id` = ?", [ $pContentId ] );
		if( trim( $old ) === '' || mb_strlen( $old ) > self::OLD_TEXT_LENGTH ) {
			$gBitDb->query( "UPDATE `".BIT_DB_PREFIX."liberty_content` SET `data` = ? WHERE `content_id` = ?", [ mime_pdf_summary( $text ), $pContentId ] );
		}

		// the search index, when there is one: it reads the object's own words again (title, description and the text row)
		if( $gBitSystem->isPackageActive( 'search' ) && function_exists( '\Bitweaver\Liberty\refresh_index' ) ) {
			unset( $content->mInfo['index_data'] );
			refresh_index( $content );
		}
		return [ 'result' => 'converted', 'length' => mb_strlen( $text ) ];
	}
}

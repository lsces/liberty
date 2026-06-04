<?php

namespace Bitweaver\Liberty;

use Bitweaver\BitBase;
use Bitweaver\KernelTools;
use Bitweaver\Liberty\LibertyBase;
use Bitweaver\Liberty\LibertyContent;
use Bitweaver\Wiki\BitPage;

/**
 * @version  $Revision$
 * @package  liberty
 * @subpackage plugins_data
 */
// +----------------------------------------------------------------------+
// | Copyright (c) 2004, bitweaver.org
// +----------------------------------------------------------------------+
// | All Rights Reserved. See below for details and a complete list of authors.
// | Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
// |
// | For comments, please use phpdocu.sourceforge.net documentation standards!!!
// | -> see http://phpdocu.sourceforge.net/
// +----------------------------------------------------------------------+
// | Authors: drewslater <andrew@andrewslater.com>
// +----------------------------------------------------------------------+
// $Id$

/**
 * definitions
 */
define( 'PLUGIN_GUID_DATAATTACHMENT', 'dataattachment' );
global $gLibertySystem, $gBitSystem;

$pluginParams = [
	'tag'           => 'attachment',
	'auto_activate' => true,
	'requires_pair' => false,
	'load_function' => '\data_attachment',
	'title'         => 'Attachment',
	'help_page'     => 'DataPluginAttachment',
	'description'   => KernelTools::tra("Display attachment in content"),
	'help_function' => '\data_attachment_help',
	'syntax'        => '{attachment id= size= align= }',
	'plugin_type'   => DATA_PLUGIN,
	'booticon'       => '{biticon ipackage="icons" iname="mail-attachment" iexplain="Attachment"}',
	'taginsert'     => '{attachment id= align= size= description= alt=}',
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATAATTACHMENT, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATAATTACHMENT );

function data_attachment_help() {
	$help =
		'<table class="data help">'
			.'<tr>'
				.'<th>' . KernelTools::tra( "Key" ) . '</th>'
				.'<th>' . KernelTools::tra( "Type" ) . '</th>'
				.'<th>' . KernelTools::tra( "Comments" ) . '</th>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>id</td>'
				.'<td>' . KernelTools::tra( "numeric") . '<br />' . KernelTools::tra("(required)") . '</td>'
				.'<td>' . KernelTools::tra( "Id number of Attachment to display inline.") . '</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>size</td>'
				.'<td>' . KernelTools::tra( "key-words") . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "If the Attachment is an image, you can specify the size of the thumbnail displayed. Possible values are:") . ' <strong>avatar, small, medium, large, original</strong> '
				. KernelTools::tra( "(Default = " ) . '<strong>medium</strong>)</td>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>description</td>'
				.'<td>' . KernelTools::tra( "string") . '<br />' . KernelTools::tra( "(optional)" ) . '</td>'
				.'<td>' . KernelTools::tra( "The text to use in the title attribute or as the link text if output=desc. Will also be used for the alt attribute if no alt is specified. This text is parsed." )
				.KernelTools::tra( "(Default = " ) . '<strong>'.KernelTools::tra( 'Image' ).'</strong>)</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>alt</td>'
				.'<td>' . KernelTools::tra( "string") . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "The text to use in the alt tag. Will also be used for the title attribute if no description is specified.")
				. KernelTools::tra("(Default = ") . '<strong>'.KernelTools::tra( 'Image' ).'</strong>)</td>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>link</td>'
				.'<td>' . KernelTools::tra( "string") . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "Allows you to specify a relative or absolute URL the image will link to if clicked. You can also link to one of the sizes of the image: icon, avatar, small, medium, large, original and download (insert download link, which will activate the download counter). If set to false, no link is inserted.")
				. KernelTools::tra("(Default = ") . '<strong>'.KernelTools::tra( 'link to image details page' ).'</strong>)</td>'
			.'</tr>'
			.'<tr class="even">
				<td>page_id</td>
				<td>'.KernelTools::tra( 'numeric (optional)' ).'</td>
				<td>'.KernelTools::tra( "To include any wiki page you can use it's page_id number." ).'</td>
			</tr>
			<tr class="odd">
				<td>content_id</td>
				<td>'.KernelTools::tra( 'numeric (optional)' ).'</td>
				<td>'.KernelTools::tra( 'To include any content from bitweaver insert the appropriate numeric content id. This can include blog posts, images, wiki texts...<br />
					Available content can be viewed <a href="'.LIBERTY_PKG_URL.'list_content.php">here</a>' ).'</td>
			</tr>
			<tr class="even">
				<td>output</td>
				<td>'.KernelTools::tra( 'keyword (optional)' ).'</td>
				<td>'.KernelTools::tra( "If you are attaching a file and you only want to display the description and not the image that goes with it, use: output=desc. If you want to force the use of a thumbnail, use output=thumbnail." ).'</td>
			</tr>'
			.'<tr class="odd">'
				.'<td>'.KernelTools::tra( "styling" ).'</td>'
				.'<td>'.KernelTools::tra( "string").'<br />'.KernelTools::tra("(optional)").'</td>'
				.'<td>'.KernelTools::tra( "Multiple styling options available: width, height, background, background-color, border, color, display, float, font, font-family, font-size, font-weight, margin, overflow, padding, text-align, align. Please view <a href='http://www.w3.org/TR/CSS21/indexlist.html'>CSS guidelines</a> on what values these settings take.").'</td>'
			.'</tr>'
		.'</table>'
		. KernelTools::tra("Example: ") . ' ' . "{ATTACHMENT id='13' size='small' text-align='center' link='http://www.google.com'}"
		. '<br />'
		. KernelTools::tra("Example: ") . ' ' . "{ATTACHMENT id='11' description='Text, the link will be wrapped around' output=desc}";
	return $help;
}

function data_attachment( $pData, $pParams, $pCommonObject, $pParseHash ) {

	// at a minimum, return blank string (not empty) so we still replace the tag
	$ret = ' ';

	// The Manditory Parameter is missing. we are not gonna trow an error, and
	// just return empty since many sites use the old style required second
	// "closing" empty tag
	if( empty( $pParams['id'] )) {
		return $ret;
	}

	$att = [];

	if( is_a( $pCommonObject, 'LibertyMime' ) && !($att = $pCommonObject->getAttachment( $pParams['id'], $pParams )) ) {
		$ret = KernelTools::tra( "The attachment id given is not valid." );
		return $ret;
	}

	global $gBitSmarty, $gLibertySystem, $gContent;
	// convert parameters into display properties
	$wrapper = \Bitweaver\Liberty\liberty_plugins_wrapper_style( $pParams );
	// work out custom display_url if there is one
	if( BitBase::verifyId( $pParams['page_id'] )) {
		// link to page by page_id
		// avoid endless loops

		$wp = new BitPage( $pParams['page_id'] );
		if( $wp->load() ) {
				$wrapper['display_url'] = $wp->getDisplayUrl();
		}
	} elseif( BitBase::verifyId( $pParams['content_id'] )) {
		// link to any content by content_id
		if( $obj = LibertyBase::getLibertyObject( $pParams['content_id'] )) {
				$wrapper['display_url'] = $obj->getDisplayUrl();
		}
	} elseif( !empty( $pParams['page_name'] )) {
		// link to page by page_name
		require_once WIKI_PKG_CLASS_PATH.'BitPage.php';
		$wp = new BitPage();
			$wrapper['display_url'] = $wp->getDisplayUrlfromHash( $pParams );
	} elseif( !empty( $pParams['link'] ) && $pParams['link'] == 'false' ) {
		// no link
	} elseif( !empty( $pParams['link'] )) {
		// Allow the use of icon, avatar, small, medium and large to link to certain size of image directly
		if( !empty( $att['thumnail_url'][$pParams['link']] )) {
			$pParams['link'] = $att['thumnail_url'][$pParams['link']];

		// Allow the use of 'original' to link to original file directly
		} elseif( $pParams['link'] == 'original' && !empty( $att['source_url'] )) {
			$pParams['link'] = $att['source_url'];

		// Allow the use of 'download' to link to download link. this will allow us to count downloads
		} elseif( $pParams['link'] == 'download' && !empty( $att['download_url'] )) {
			$pParams['link'] = $att['download_url'];

		// Adjust class name if we are leaving this server
		} elseif( !strstr( $pParams['link'], $_SERVER["SERVER_NAME"] ) && strstr( $pParams['link'], '//' )) {
			$wrapper['href_class'] = 'class="external"';
		}
		$wrapper['display_url'] = $pParams['link'];
	} elseif( !empty( $att['display_url'] ) )  {
		$wrapper['display_url'] = $att['display_url'];
	}

	if( !empty( $wrapper['description'] )) {
		$parseHash['content_id'] = $pParseHash['content_id'];
		$parseHash['user_id']    = $pParseHash['user_id'];
		$parseHash['no_cache']   = true;
		$parseHash['data']       = $wrapper['description'];
		$wrapper['description_parsed'] = LibertyContent::parseDataHash( $parseHash );
	}

	// pass stuff to the template
	$gBitSmarty->assign( 'attachment', $att );
	$gBitSmarty->assign( 'wrapper', $wrapper );
	$gBitSmarty->assign( 'thumbsize', ( !empty( $pParams['size'] ) && $pParams['size'] == 'original' ) || !empty( $att['thumbnail_url'][$pParams['size']] ) ? $pParams['size'] : 'medium' );

	//Carry only these attributes to the image tags
	$width = !empty( $pParams['width'] ) ? $pParams['width'] : '';
	$gBitSmarty->assign( 'width', $width );

	$height = !empty( $pParams['height'] ) ? $pParams['height'] : '';
	$gBitSmarty->assign( 'height', $height );

	$mimehandler = !empty( $wrapper['output'] ) && $wrapper['output'] == 'thumbnail' ? LIBERTY_DEFAULT_MIME_HANDLER : $att['attachment_plugin_guid'];
	$ret = $gBitSmarty->fetch( $gLibertySystem->getMimeTemplate( 'attachment', $mimehandler ));
	return $ret;
}

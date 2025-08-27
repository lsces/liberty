<?php
/**
 * help_format_tikiwiki_inc
 *
 * @author   Christian Fowler>
 * @version  $Revision$
 * @package  liberty
 * @subpackage functions
 */

/**
 * required setup
 */
use Bitweaver\BitCache;
use Bitweaver\Liberty\LibertyContent;

global $gBitSystem, $gBitSmarty;

$cache = new BitCache( 'liberty/help' );

// only regenerate this thing if it's not cached yet
$cacheFile = 'tikiwiki';
if( $cache->isCached( $cacheFile, filemtime( __FILE__ ))) {
	$examples = unserialize( $cache->readCacheFile( $cacheFile ));
} else {
	// help for generic options
	$tikiwiki = [
		'Emphasis'      => [
			'Headings'            => [
				'data' => "! heading 1\n!! heading 2\n!!! heading 3",
				'note' => "Number of ! correponds to heading level.",
			],
			'Italics'             => [
				'data' => "''text''",
				'note' => "Two single quotes not one double quote",
			],
			'Underline'           => [
				'data' => "===text===",
			],
			'Coloured Background' => [
				'data' => "++yellow:text++",
			],
			'Coloured Text'       => [
				'data' => "~~red:text~~",
			],
			'Bold'                => [
				'data' => "__text__",
			],
			'Centered Text'       => [
				'data' => "::text::",
			],
			'Combined'            => [
				'data' => "::__~~red:++yellow:text++~~__::",
				'note' => "When you combine options make sure you open and close in the opposite order analogous to: {[(text)]}",
			],
		],
		'Lists'         => [
			'Unordered Lists'  => [
				'data' => "* First item\n** First subitem\n** Second subitem\n* Second item",
			],
			'Ordered Lists'    => [
				'data' => "# First item\n## First subitem\n## Second subitem\n# Second item",
			],
			'Definition Lists' => [
				'data' => ";Term: Definition",
			],
		],
		'Links'         => [
			'Wiki Links'                           => [
				'data'   => "((Wiki Page))",
				'result' => '<a href="#">Wiki Page</a>',
			],
			'Wiki Links + Description'             => [
				'data'   => "((Wiki Page|Page Description))",
				'result' => '<a href="#">Page Description</a>',
			],
			'Wiki Links + Anchor + Description'    => [
				'data'   => "((Wiki Page#Anchor|Page Description))",
				'result' => '<a href="#">Page Description</a>',
			],
			'External Link'                        => [
				'data' => "[http://www.example.com]",
			],
			'External Link + Description'          => [
				'data' => "[http://www.example.com|Description]",
			],
			'External Link + Anchor + Description' => [
				'data' => "[http://www.example.com/Page#Anchor|Description]",
			],
		],
		'Miscellaneous' => [
			'Horizontal Rule' => [
				'data' => '---',
			],
			'Highlighted Bar' => [
				'data' => '-=text=-',
			],
			'Highlighted Box' => [
				'data' => "^text\nmore text^",
			],
			'As is Text'      => [
				'data' => "~np~~~yellow:yellow~~\nand\n__bold__ text~/np~",
				'note' => "This text will not be parsed",
			],
			'Pre Parsed'      => [
				'data' => "~pp~~~yellow:yellow~~\nand\n__bold__ text~/pp~",
				'note' => "This text will be treated like code and will not be altered and will be displayed using a monospace font. The same can be achieved by using &lt;pre&gt;text&lt;/pre&gt;.",
			],
			'Monospaced Text' => [
				'data' => "-+text+-",
			],
			'Right to Left'   => [
				'data' => "{r2l}this text is from\nright to left\n{l2r}and back to\nleft to right.",
			],
		],
		'Simple Tables' => [
			'Simple Table' => [
				'data' => "|| Row1-Col1 | Row1-Col2\nRow2-Col1 | Row2-Col2 ||",
			],
			'With Headers' => [
				'data' => "||~ Header1 | Header2\nRow1-Col1 | Row1-Col2\nRow2-Col1 | Row2-Col2 ||",
			],
		],
	];

	if( $gBitSystem->getConfig( 'wiki_tables' ) == 'old' ) {
		$tikiwiki['Simple Tables'] = [
			'Tables' => [
				'data' => "|| Row1-Col1 | Row1-Col2 || Row2-Col1 | Row2-Col2 ||",
			],
		];
	}

	foreach( array_keys( $tikiwiki ) as $section ) {
		foreach( $tikiwiki[$section] as $title => $example ) {
			if( empty( $example['result'] )) {
				$example['format_guid'] = 'tikiwiki';
				$tikiwiki[$section][$title]['result'] = LibertyContent::parseDataHash( $example );
			}
		}
	}

	// mediawiki type tables
	$mediawiki = [
		'Example 1' => [
			'data' =>
				'{| class="table"
|+A Simple Table
|-
! Col 1 !! Col 2 !! Col 3
|-
| Row1-Col1 || Row1-Col2 || Row1-Col3
|-
| Row2-Col1
| Row2-Col2
| Row2-Col3
|-
| Row3-Col1 || Row3-Col2 || Row3-Col3
|}',
		],
		'Example 2' => [
			'data' =>
				'{| class="table table-boardered"
|+Multiplication table
|-
! X !! 1 !! 2 !! 3
|-
! 1
| 1 || 2 || 3
|-
! 2
| 2 || 4 || 6
|-
! 3
| 3 || 6 || 9
|-
! 4
| 4 || 8 || 12
|-
! 5
| 5 || 10 || 15
|}',
		],
		'Example 3' => [
			'data' =>
				'{| class="table table-striped"
|+ Table with alternating rows
|-
| one
| two
|-
| three
| four
|- 
| five
| six
|- 
| seven
| eight
|- class="success"
| colspan="2" | success
|- class="error"
| colspan="2" | error
|- class="warning"
| colspan="2" | warning
|- class="info"
| colspan="2" | info
|}',
		],
		'Example 4' => [
			'data' =>
				'{| class="table" style="background:yellow;color:green"
|+ Table with many colours
|-
| abc
| colspan="2" style="text-align:center;background:lightblue;" | defghi
|- style="background:red;color:white"
| jkl
| mno
| pqr
|-
| style="font-weight:bold" | stu
| style="background:silver" | vwx
| yz
|}',
		],
	];

	// parse tables
	foreach( $mediawiki as $title => $example ) {
		if( empty( $example['result'] )) {
			$example['format_guid'] = 'tikiwiki';
			$mediawiki[$title]['result'] = LibertyContent::parseDataHash( $example );
		}
	}

	$examples['tikiwiki'] = $tikiwiki;
	$examples['mediawiki'] = $mediawiki;
	$cache->writeCacheFile( $cacheFile, serialize( $examples ));
}
$gBitSmarty->assign( 'examples', $examples );
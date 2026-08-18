<?php
/**
 * @package liberty
 */

global $gBitInstaller;

$gBitInstaller->registerPackageUpgrade(
	[
		'package'     => 'liberty',
		'version'     => '5.0.2',
		'description' => 'Add sort_order to liberty_xref_item — items within a group previously had no display-order column at all (unlike liberty_xref_group\'s own sort_order for tabs), so LibertyXrefType::loadContent() fell back to ordering alphabetically by item code. Defaults every existing row to 0, preserving today\'s alphabetical order until a package explicitly sets real values.',
	],
	[
		[ 'QUERY' => [
			'SQL92' => [
				"ALTER TABLE liberty_xref_item ADD sort_order SMALLINT DEFAULT 0 NOT NULL",
				"ALTER TABLE liberty_xref_item ALTER COLUMN sort_order POSITION 6",
			],
		]],
	]
);

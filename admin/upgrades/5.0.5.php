<?php
/**
 * @package liberty
 */

global $gBitInstaller;

$gBitInstaller->registerPackageUpgrade(
	[
		'package'     => 'liberty',
		'version'     => '5.0.5',
		'description' => 'Index liberty_xref on (item, xkey_ext). The packages look an xref up by the value it holds (a contact by its '
			.'TMDb or Wikidata id, a film by its imdb id), and with only the content_id index each of those was a scan of the whole '
			.'table - several per person in the film/TV people passes, 17 s of a 40 s batch on a 230,000-row table. A site that already '
			.'has the index (created by hand) will report the CREATE as failed; that is harmless.',
	],
	[
		[ 'QUERY' => [
			'SQL92' => [
				"CREATE INDEX liberty_xref_item_key_idx ON liberty_xref (item, xkey_ext)",
			],
		]],
	]
);

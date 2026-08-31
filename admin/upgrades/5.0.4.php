<?php
/**
 * @package liberty
 */

global $gBitInstaller;

$gBitInstaller->registerPackageUpgrade(
	[
		'package'     => 'liberty',
		'version'     => '5.0.4',
		'description' => 'Second half of the liberty_xref TIMESTAMP->I8 conversion (see 5.0.3\'s '
			.'own description for the full background) - only meant to run on a site where '
			.'liberty_xref2 has already been checked against liberty_xref (row counts and a '
			.'value spot-check) following 5.0.3. Drops the original liberty_xref, rebuilds it '
			.'fresh with the new BIGINT date columns plus its one index, copies the converted '
			.'data back in from the staging table. liberty_xref2 is deliberately left in place '
			.'afterward (not dropped here) for one more comparison pass against the rebuilt '
			.'liberty_xref before it\'s cleaned up.',
	],
	[
		[ 'QUERY' => [
			'SQL92' => [
				"DROP TABLE liberty_xref",
				"CREATE TABLE liberty_xref (
					xref_id BIGINT NOT NULL PRIMARY KEY,
					content_id BIGINT NOT NULL,
					item VARCHAR(20),
					xorder SMALLINT DEFAULT 0 NOT NULL,
					xref BIGINT,
					xkey VARCHAR(32),
					xkey_ext VARCHAR(250),
					data BLOB SUB_TYPE TEXT,
					start_date BIGINT,
					last_update_date BIGINT,
					entry_date BIGINT,
					end_date BIGINT
				)",
				"CREATE INDEX liberty_xref_content_idx ON liberty_xref (content_id)",
				"INSERT INTO liberty_xref SELECT * FROM liberty_xref2",
			],
		]],
	]
);

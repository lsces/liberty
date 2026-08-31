<?php
/**
 * @package liberty
 */

global $gBitInstaller;

$gBitInstaller->registerPackageUpgrade(
	[
		'package'     => 'liberty',
		'version'     => '5.0.3',
		'description' => 'liberty_xref\'s four native TIMESTAMP columns (start_date/end_date/'
			.'entry_date/last_update_date) are being converted to I8 epoch-seconds, matching the '
			.'convention every other date/time column in the codebase already uses (see '
			.'kernel/DATETIME.md\'s "Separate track" section for the full history). Firebird '
			.'cannot ALTER a TIMESTAMP column to BIGINT in place ("Conversion from base type '
			.'TIMESTAMP to BIGINT is not supported", confirmed by direct test), and has no table '
			.'RENAME statement at all (confirmed - both ALTER TABLE...TO and RENAME TABLE are '
			.'unrecognised tokens), so this is a genuine two-hop swap done as two separate '
			.'upgrades so the converted data can be verified before the original is touched. '
			.'This step (first half): build liberty_xref2 as a converted staging copy '
			.'(DATEDIFF(SECOND, epoch, col), verified NULL-safe) and populate it from '
			.'liberty_xref, which is left completely untouched. 5.0.4 (second half, run only '
			.'once liberty_xref2 has been checked against liberty_xref - row counts and a value '
			.'spot-check) does the actual drop-and-rebuild swap.',
	],
	[
		[ 'QUERY' => [
			'SQL92' => [
				"CREATE TABLE liberty_xref2 (
					xref_id BIGINT NOT NULL,
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
				"INSERT INTO liberty_xref2
					(xref_id, content_id, item, xorder, xref, xkey, xkey_ext, data,
					 start_date, last_update_date, entry_date, end_date)
				 SELECT
					xref_id, content_id, item, xorder, xref, xkey, xkey_ext, data,
					DATEDIFF( SECOND, TIMESTAMP '1970-01-01 00:00:00', start_date ),
					DATEDIFF( SECOND, TIMESTAMP '1970-01-01 00:00:00', last_update_date ),
					DATEDIFF( SECOND, TIMESTAMP '1970-01-01 00:00:00', entry_date ),
					DATEDIFF( SECOND, TIMESTAMP '1970-01-01 00:00:00', end_date )
				 FROM liberty_xref",
			],
		]],
	]
);

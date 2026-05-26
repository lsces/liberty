<?php
/**
 * @package liberty
 */

global $gBitInstaller;

$gBitInstaller->registerPackageUpgrade(
	[
		'package'     => 'liberty',
		'version'     => '5.0.1',
		'description' => 'Add liberty_xref tables: a package-agnostic cross-reference mechanism for attaching typed, multi-valued, date-tracked attributes to any liberty content.',
	],
	[
		[ 'DATADICT' => [
			[ 'CREATE' => [
				'liberty_xref_group'  => "
					x_group C(32) PRIMARY,
					content_type_guid C(32) PRIMARY,
					title C(64),
					sort_order I2,
					role_id I4,
					type_href C(256),
					multiple I2,
					template C(32)
				",
				'liberty_xref_item'   => "
					item C(20) PRIMARY,
					content_type_guid C(32) PRIMARY,
					x_group C(32),
					cross_ref_title C(64),
					multiple I2,
					role_id I4,
					cross_ref_href C(256),
					template C(32),
					data X
				",
				'liberty_xref'        => "
					xref_id I8 PRIMARY,
					content_id I8 NOTNULL,
					item C(20),
					xorder I2,
					xref I8,
					xkey C(32),
					xkey_ext C(250),
					data X,
					start_date T,
					last_update_date T,
					entry_date T,
					end_date T
				",
			]],
			[ 'CREATESEQUENCE' => [ 'liberty_xref_seq' ] ],
			[ 'CREATEINDEX' => [
				'liberty_xref_content_idx'   => [ 'liberty_xref',       'content_id',        null ],
				'liberty_xref_item_pkg_idx'  => [ 'liberty_xref_item',  'content_type_guid', null ],
				'liberty_xref_group_pkg_idx' => [ 'liberty_xref_group', 'content_type_guid', null ],
			]],
		]],
	]
);

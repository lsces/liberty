<?php
/**
 * @package liberty
 */

global $gBitInstaller;

$gBitInstaller->registerPackageUpgrade(
	[
		'package'      => 'liberty',
		'version'      => '5.1.1',
		'description'  => 'A PDF now keeps its whole text layer in liberty_content_data (data_type text) and a short summary in the description, '
			.'instead of the text in liberty_content.data. PDFs stored before this still display and search as they did; they are brought '
			.'up to the new layout from Liberty admin > PDF text, a few per press (a scan with no text layer is reported and skipped). '
			.'The upgrade changes no data itself, the conversion is too slow for the installer on a site with hundreds of PDFs.',
		'post_upgrade' => 'Open Liberty admin > PDF text and press Convert until nothing is left to do. The PDF text page is listed only '
			.'while liberty is below 5.1.1.',
	],
	[
		[ 'PHP' => '$this->storeVersion( \'liberty\', \'5.1.1\' );' ],
	]
);

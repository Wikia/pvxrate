<?php
/**
 * Curse Inc.
 * PvX Rate
 * Adds tab to Rate articles, List user ratings, and list recent ratings.
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2015 Curse Inc.
 * @license		All Rights Reserved
 * @package		PvXRate
 * @link		http://www.curse.com/
 *
**/


if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'PvXRate' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['PvXRate'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for PvX Rate extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the FooBar extension requires MediaWiki 1.25+' );
}

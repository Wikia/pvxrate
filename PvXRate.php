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

/******************************************/
/* Credits                                */
/******************************************/
$wgExtensionCredits['specialpage'][] = [
	'path'           => __FILE__,
	'name'           => 'PvX Rate',
	'author'         => ['gcardinal', 'Hhhippo', 'Alexia E. Smith', 'Cameron Chunn'],
	'descriptionmsg' => 'pvxrate_description',
];

//$wgAvailableRights[] = 'pvxrate';

/******************************************/
/* Language Strings, Page Aliases, Hooks  */
/******************************************/
$wgMessagesDirs['PvXRate'] = __DIR__.'/i18n';

// Classes
$wgAutoloadClasses['RateAction'] = __DIR__.'/classes/RateAction.php';
$wgAutoloadClasses['PvXRateHooks'] = __DIR__.'/PvXRate.hooks.php';

// Actions
$wgActions['rate'] = 'RateAction';

// Special Pages
$wgAutoloadClasses['SpecialUserRatings'] = __DIR__."/specials/SpecialUserRatings.php";
$wgSpecialPages['UserRatings']           = 'SpecialUserRatings';

$wgAutoloadClasses['SpecialRecentRatings'] = __DIR__."/specials/SpecialRecentRatings.php";
$wgSpecialPages['RecentRatings']           = 'SpecialRecentRatings';

// Hooks
$wgHooks['LoadExtensionSchemaUpdates'][] 	= 'PvXRateHooks::onLoadExtensionSchemaUpdates';


$wgGroupPermissions['user']['ratebuild'] = true;

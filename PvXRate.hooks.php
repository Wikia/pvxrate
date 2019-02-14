<?php
/**
 * Curse Inc.
 * PvX Rate
 * Adds tab to Rate articles, List user ratings, and list recent ratings.
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2015 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package		PvXRate
 * @link		https://gitlab.com/hydrawiki
 *
**/

class PvXRateHooks {
	/**
	 * @param  DatabaseUpdater $updater
	 * @return true;
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionUpdate( array( 'addTable', 'rating',
			__DIR__ . '/install/sql/table_rating.sql', true ) );
		return true;
	}
}

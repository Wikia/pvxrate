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

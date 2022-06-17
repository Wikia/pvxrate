<?php

declare( strict_types=1 );

namespace Fandom\PvXRate;

use DatabaseUpdater;
use MediaWiki\MediaWikiServices;
use SkinTemplate;
use SpecialPage;
use Title;
use User;

/**
 * Curse Inc.
 * PvX Rate
 * Adds tab to Rate articles, List user ratings, and list recent ratings.
 *
 * @author        Cameron Chunn
 * @copyright    (c) 2015 Curse Inc.
 * @license        GNU General Public License v2.0 or later
 * @package        PvXRate
 * @link        https://gitlab.com/hydrawiki
 *
 **/
class PvXRateHooks {
	/**
	 * @param DatabaseUpdater $updater
	 * @return true;
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionUpdate( [
			'addTable',
			'rating',
			__DIR__ . '/install/sql/table_rating.sql',
			true,
		] );
		return true;
	}

	/**
	 * For logged in users, add a link to the special 'UserRatings' page
	 * among the user's "personal URLs" at the top, if they have
	 * the 'adminlinks' permission.
	 *
	 * Modified from https://www.mediawiki.org/wiki/Extension:Admin_Links
	 */
	public static function addURLToUserLinks( array &$personal_urls, Title &$title, SkinTemplate $skinTemplate ) {
		if ( $skinTemplate->getUser()->isRegistered() ) {
			$ur = SpecialPage::getTitleFor( 'UserRatings' );
			$href = $ur->getLocalURL();
			$userratings_links_vals = [
				'text' => $skinTemplate->msg( 'myratings' )->text(),
				'href' => $href,
				'active' => ( $href == $title->getLocalURL() ),
			];

			// find the location of the 'my preferences' link, and
			// add the link to 'UserRatings' right before it.
			// this is a "key-safe" splice - it preserves both the
			// keys and the values of the array, by editing them
			// separately and then rebuilding the array.
			// based on the example at http://us2.php.net/manual/en/function.array-splice.php#31234
			$tab_keys = array_keys( $personal_urls );
			$tab_values = array_values( $personal_urls );
			$prefs_location = array_search( 'preferences', $tab_keys );
			array_splice( $tab_keys, $prefs_location, 0, 'myratings' );
			array_splice( $tab_values, $prefs_location, 0, [ $userratings_links_vals ] );

			$personal_urls = [];
			$tabKeysCount = count( $tab_keys );
			for ( $i = 0; $i < $tabKeysCount; $i ++ ) {
				$personal_urls[$tab_keys[$i]] = $tab_values[$i];
			}
		}
	}

	/**
	 * Add a tab or an icon the new way (MediaWiki 1.18+)
	 *
	 * @param SkinTemplate &$skin
	 * @param array &$links Navigation links
	 */
	public static function onSkinTemplateNavigation( SkinTemplate &$skin, array &$links ) {
		// The links array has four possible keys
		//   (1) "namespace" => like Special, Page, Talk
		//   (2) "views" => like Read, Edit, History
		//   (3) "actions" => like Move, Delete, Protect (sometimes pushed into dropdown menus)
		//   (4) "variants" => like French, German, Spanish
		// but we're choosing views because Rating is like Editing
		// Get the correct target build page (valid if in the build or build_talk namespaces)
		$target = PvXRateHooks::getBuildPage( $skin->getTitle(), $skin->getUser() );
		if ( $target !== null ) {
			$links['views']['pvxrate'] = [
				'text' => $skin->msg( 'pvxrate-tab-text' )->text(),
				'href' => $skin->getTitle()->getLocalURL( "action=rate" ),
			];
		}
	}

	/**
	 * Helper function
	 * Find the build namespace page associated with the build. Includes sanity checks.
	 */
	public static function getBuildPage( Title $title, User $user ): ?Title {
		// Exit early if the voting user isn't logged in
		if ( !$user->isRegistered() ) {
			return null;
		}

		// Exit early if the page is in the wrong namespace
		$ns = $title->getNamespace();
		if ( $ns != NS_BUILD && $ns != NS_BUILD_TALK ) {
			return null;
		}
		$buildTitle = self::getBuildTitle( $title );

		// If it's a redirect, exit. We don't follow redirects since it might confuse the user or
		// lead to an endless loop (like if the talk page redirects to the user page or a subpage).
		// This means that the Rate tab will not appear on build pages if the build page is a redirect.
		if ( $buildTitle === null || $buildTitle->isRedirect() ) {
			return null;
		}

		// Make sure we can edit the page
		if ( !$user->probablyCan( 'edit', $buildTitle ) ) {
			return null;
		}

		return $buildTitle;
	}

	/**
	 * @param Title $title
	 * @return Title|null
	 */
	private static function getBuildTitle( Title $title ) {
		// If we're on a subpage, get the root page title
		$baseTitle = $title->getRootTitle();

		if ( $title->getNamespace() == NS_BUILD_TALK ) {
			$nsInfo = MediaWikiServices::getInstance()->getNamespaceInfo();

			// We're on the build talk page, so retrieve the rate page instead
			return Title::castFromLinkTarget( $nsInfo->getSubjectPage( $baseTitle ) );
		}

		return $baseTitle;
	}
}

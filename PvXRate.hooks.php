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


	/**
	 * For logged in users, add a link to the special 'UserRatings' page
	 * among the user's "personal URLs" at the top, if they have
	 * the 'adminlinks' permission.
	 *
	 * Modified from https://www.mediawiki.org/wiki/Extension:Admin_Links
	 */
	public static function addURLToUserLinks( array &$personal_urls, Title &$title, SkinTemplate $skinTemplate ) {
		// Display if the user is logged in
		if ( $skinTemplate->getUser()->isLoggedIn() ) {

			$ur = SpecialPage::getTitleFor( 'UserRatings' );
			$href = $ur->getLocalURL();
			$userratings_links_vals = array(
				'text' => $skinTemplate->msg( 'myratings' )->text(),
				'href' => $href,
				'active' => ( $href == $title->getLocalURL() )
			);

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
			array_splice( $tab_values, $prefs_location, 0, array( $userratings_links_vals ) );

			$personal_urls = array();
			$tabKeysCount = count( $tab_keys );
			for ( $i = 0; $i < $tabKeysCount; $i++ ) {
				$personal_urls[$tab_keys[$i]] = $tab_values[$i];
			}
		}
		return true;
	}

	/**
	 * Add a tab or an icon the new way (MediaWiki 1.18+)
	 *
	 * @param SkinTemplate &$skin
	 * @param array &$links Navigation links
	 */
	public static function onSkinTemplateNavigation( &$skin, &$links ) {
		// The links array has four possible keys
		//   (1) "namespace" => like Special, Page, Talk
		//   (2) "views" => like Read, Edit, History
		//   (3) "actions" => like Move, Delete, Protect (sometimes pushed into dropdown menus)
		//   (4) "variants" => like French, German, Spanish
		// but we're choosing views because Rating is like Editing
		if ( self::showIcon( $skin ) ) {
			self::skinConfigViewsLinks( $skin, $links['views'] );
		} else {
			self::skinConfigViewsLinks( $skin, $links['actions'] );
		}
	}

	/**
	 * Configure views links.
	 *
	 * Helper function for SkinTemplateTabs and SkinTemplateNavigation hooks
	 * to configure views links.
	 *
	 * @param Skin $skin
	 * @param array $views
	 */
	private static function skinConfigViewsLinks( $skin, &$views ) {
		// Get the correct target build page (valid if in the build or build_talk namespaces)
		$target = self::getBuildPage( $skin->getTitle(), $skin->getUser() ); // FIXME

		// Acquire any URL parameters so we can check what the current action is
		$request = $skin->getRequest();
		$action = $request->getText( 'action' );

		// getBuildPage() returns an ApiMessage on error
		if ( $target !== false ) {
			$views['pvxrate'] = [
				// Use default tab CSS class unless we're on a rating page, in which case add the selected class
				'class' => ( $action == 'rate') ? 'selected' : false,
				// Name the tab using i18n.json (e.g. en.json)
				'text' => $skin->msg( 'pvxrate-tab-text' )->text(),
				// Create the full url to the target page
				'href' => $skin->makeArticleUrlDetails( $target->getFullText(), 'action=rate' )['href']
			];
			// Need additional fields for Vector/Monobook (non-cologne blue) to prevent php error missing Class field etc.
			if ( self::showIcon( $skin ) ) {
				$views['pvxrate']['class'] = 'icon';
				$views['pvxrate']['primary'] = true;
			}
		}
	}

	/**
	 * Only show an icon when the global preference is enabled and the current skin isn't CologneBlue.
	 *
	 * @param Skin $skin
	 * @return bool
	 */
	private static function showIcon( $skin ) {
		return $skin->getSkinName() !== 'cologneblue';
	}


	/**
	 * Helper function
	 * Find the build namespace page associated with the build. Includes sanity checks.
	 */
	public static function getBuildPage( $title, $user ) {
		// Exit early if the voting user isn't logged in
		if ( !$user->isLoggedIn() ) {
			return false;
		}

		// Exit early if the page is in the wrong namespace
		// Note: These namespace constants will need to be defined in extension.json if they are not defined in localsettings.php
		$ns = $title->getNamespace();
		if ( $ns != NS_BUILD && $ns != NS_BUILD_TALK ) {
			return false;
		}

		// If we're on a subpage, get the root page title
		$baseTitle = $title->getRootTitle();

		// Get the associated build page
		if ( $ns == NS_BUILD ) {
			// We're already on the rate page
			$buildTitle = $baseTitle;
		} elseif ( $ns == NS_BUILD_TALK ) {
			// We're on the build talk page, so retrieve the rate page instead
			$buildTitle = $baseTitle->getSubjectPage();
		}

		// If it's a redirect, exit. We don't follow redirects since it might confuse the user or
		// lead to an endless loop (like if the talk page redirects to the user page or a subpage).
		// This means that the Rate tab will not appear on build pages if the build page is a redirect.
		if ( $buildTitle->isRedirect() ) {
			return false;
		}

		// Make sure we can edit the page
		if ( !$buildTitle->quickUserCan( 'edit' ) ) {
			return false;
		}

		return $buildTitle;
	}

}

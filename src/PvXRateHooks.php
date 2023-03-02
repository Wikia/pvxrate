<?php

declare( strict_types=1 );

namespace Fandom\PvXRate;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MWException;
use NamespaceInfo;
use SkinTemplate;
use SpecialPage;
use Title;
use User;

/**
 * Curse Inc.
 * PvX Rate
 * Adds tab to Rate articles, List user ratings, and list recent ratings.
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2015 Curse Inc.
 * @license		GPL-2.0-or-later
 * @package		PvXRate
 * @link		https://gitlab.com/hydrawiki
 *
 */
class PvXRateHooks implements
	LoadExtensionSchemaUpdatesHook,
	SkinTemplateNavigation__UniversalHook
{

	public function __construct( private NamespaceInfo $namespaceInfo ) {
	}

	public static function onRegistration(): void {
		require_once __DIR__ . '/defines.php';
	}

	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionUpdate( [
			'addTable',
			'rating',
			__DIR__ . '/install/sql/table_rating.sql',
			true,
		] );
	}

	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		// The links array has four possible keys
		//   (1) "namespace" => like Special, Page, Talk
		//   (2) "views" => like Read, Edit, History
		//   (3) "actions" => like Move, Delete, Protect (sometimes pushed into dropdown menus)
		//   (4) "variants" => like French, German, Spanish
		// and we're choosing views because Rating is like Editing
		// Get the correct target build page (valid if in the build or build_talk namespaces)
		$target = $this->getBuildPage( $sktemplate->getTitle(), $sktemplate->getUser() );
		if ( $target !== null ) {
			$links['views']['pvxrate'] = [
				'text' => $sktemplate->msg( 'pvxrate-tab-text' )->text(),
				'href' => $sktemplate->getTitle()->getLocalURL( "action=rate" ),
			];
		}
		$this->addURLToUserLinks( $links['user-menu'], $sktemplate );
	}

	/**
	 * For logged-in users, add a link to the special 'UserRatings' page
	 * among the user's "personal URLs" at the top, if they have
	 * the 'adminlinks' permission.
	 *
	 * Modified from https://www.mediawiki.org/wiki/Extension:Admin_Links
	 * @throws MWException
	 */
	private function addURLToUserLinks( array &$personal_urls, SkinTemplate $skinTemplate ) {
		if ( $skinTemplate->getUser()->isRegistered() ) {
			$ur = SpecialPage::getTitleFor( 'UserRatings' );
			$href = $ur->getLocalURL();
			$userratings_links_vals = [
				'text' => $skinTemplate->msg( 'myratings' )->text(),
				'href' => $href,
				'active' => ( $href == $skinTemplate->getTitle()->getLocalURL() ),
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
			for ( $i = 0; $i < $tabKeysCount; $i++ ) {
				$personal_urls[$tab_keys[$i]] = $tab_values[$i];
			}
		}
	}

	/**
	 * Helper function
	 * Find the build namespace page associated with the build. Includes sanity checks.
	 */
	public function getBuildPage( Title $title, User $user ): ?Title {
		// Exit early if the voting user isn't logged in
		if ( !$user->isRegistered() ) {
			return null;
		}

		// Exit early if the page is in the wrong namespace
		$ns = $title->getNamespace();
		if ( $ns != NS_BUILD && $ns != NS_BUILD_TALK ) {
			return null;
		}
		$buildTitle = $this->getBuildTitle( $title );

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

	private function getBuildTitle( Title $title ): ?Title {
		// If we're on a subpage, get the root page title
		$baseTitle = $title->getRootTitle();

		if ( $title->getNamespace() == NS_BUILD_TALK ) {
			// We're on the build talk page, so retrieve the rate page instead
			return Title::castFromLinkTarget( $this->namespaceInfo->getSubjectPage( $baseTitle ) );
		}

		return $baseTitle;
	}
}

<?php

namespace Fandom\PvXRate;

use MediaWiki\User\UserIdentityLookup;
use MWException;
use RuntimeException;
use SpecialPage;
use Title;

/**
 * Curse Inc.
 * PvX Rate
 * Adds tab to Rate articles, List user ratings, and list recent ratings.
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2015 Curse Inc.
 * @license		GPL-2.0-or-later
 * @package 	PvXRate
 * @link		https://gitlab.com/hydrawiki
 *
 */
class SpecialUserRatings extends SpecialPage {
	public function __construct(
		private RateService $rateService,
		private RatingListRenderer $renderer,
		private UserIdentityLookup $userLookup
	) {
		parent::__construct(
			'UserRatings', // name
			null, // required user right
			true // display on Special:Specialpages
		);
	}

	/**
	 * @throws MWException
	 */
	public function execute( $subPage = null ) {
		$this->getOutput()->addModules( 'ext.pvxrate' );
		$this->getOutput()->setPageTitle( wfMessage( 'userratings' ) );
		$targetUserId = $this->getTargetUserId( $subPage );
		$ratings = $this->rateService->getRatingsByUser( $targetUserId );
		$this->renderer->render( $ratings, $this->getOutput(), $this->getUser(), $this->getLanguage() );
	}

	/**
	 * @throws RuntimeException
	 */
	private function getTargetUserId( ?string $par ): int {
		// Default to showing the logged-in user's contributions
		if ( !empty( $par ) ) {
			$targetUserSafe = Title::makeTitleSafe( NS_USER, $par );
			if ( $targetUserSafe ) {
				$user = $this->userLookup->getUserIdentityByName( $targetUserSafe->getText() );
				if ( $user ) {
					return $user->getId();
				}
			}
			throw new RuntimeException( 'Invalid user' );
		} else {
			// or show for current user
			return $this->getUser()->getID();
		}
	}

	public function isListed(): bool {
		return true;
	}

	public function isRestricted(): bool {
		return false;
	}

	protected function getGroupName(): string {
		return 'pvx';
	}
}

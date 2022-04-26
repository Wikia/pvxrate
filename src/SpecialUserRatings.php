<?php

namespace Fandom\PvXRate;

use BadRequestException;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentityLookup;
use SpecialPage;
use Title;
use User;
use Wikimedia\Rdbms\DBConnRef;

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
class SpecialUserRatings extends SpecialPage {

	/** @var RateService */
	private $rateService;
	/** @var RatingListRenderer */
	private $renderer;
	/** @var UserIdentityLookup */
	private $userLookup;

	public function __construct() {
		parent::__construct(
			'UserRatings', // name
			null, // required user right
			true // display on Special:Specialpages
		);

		$services = MediaWikiServices::getInstance();
		$this->rateService = $services->getService( RateService::class );
		$this->renderer = $services->getService( RatingListRenderer::class );
		$this->userLookup = $services->getUserIdentityLookup();
	}

	/**
	 * Main Executor
	 *
	 * @access    public
	 * @param string    Sub page passed in the URL.
	 * @return    void    [Outputs to screen]
	 */
	public function execute( $subPage = null ) {
		$this->getOutput()->addModules( 'ext.pvxrate' );
		$this->getOutput()->setPageTitle( wfMessage( 'userratings' ) );
		$targetUserId = $this->getTargetUserId( $subPage );
		$ratings = $this->rateService->getRatingsByUser( $targetUserId );
		$this->renderer->render( $ratings, $this->getOutput(), $this->getUser(), $this->getLanguage() );
	}

	/**
	 * @param $par
	 * @return int
	 * @throws BadRequestException
	 */
	private function getTargetUserId( ?string $par ): int {
		// Default to showing the logged in user's contributions
		if ( !empty( $par ) ) {
			$targetUserSafe = Title::makeTitleSafe( NS_USER, $par );
			if ( $targetUserSafe ) {
				$user = $this->userLookup->getUserIdentityByName( $targetUserSafe->getText() );
				if ( $user ) {
					return $user->getId();
				}
			}
			throw new BadRequestException( 'Invalid user' );
		} else {
			// or show for current user
			return $this->getUser()->getID();
		}
	}

	/**
	 * Hides special page from SpecialPages special page.
	 *
	 * @access    public
	 * @return    boolean    False
	 */
	public function isListed(): bool {
		return true;
	}

	/**
	 * Lets others determine that this special page is restricted.
	 *
	 * @access    public
	 * @return    boolean    True
	 */
	public function isRestricted(): bool {
		return false;
	}

	protected function getGroupName(): string {
		return 'pvx'; //Change to display in a different category on Special:SpecialPages.
	}
}

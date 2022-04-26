<?php

namespace Fandom\PvXRate;

use MediaWiki\MediaWikiServices;
use SpecialPage;
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
class SpecialRecentRatings extends SpecialPage {

	public function __construct() {
		parent::__construct(
			'RecentRatings', // name
			null, // required user right
			true // display on Special:Specialpages
		);
	}

	/**
	 * Main Executor
	 *
	 * @access    public
	 * @param string    Sub page passed in the URL.
	 * @return    void    [Outputs to screen]
	 */
	public function execute( $par = null ) {
		$this->getOutput()->addModules( 'ext.pvxrate' );
		$this->getOutput()->setPageTitle( wfMessage( 'recentratings' ) );

		$services = MediaWikiServices::getInstance();
		$rateService = $services->getService( RateService::class );
		$renderer = $services->getService( RatingListRenderer::class );

		$ratings = $rateService->getRatings();
		$renderer->render( $ratings, $this->getOutput(), $this->getUser(), $this->getLanguage() );
	}

	protected function getGroupName(): string {
		return 'pvx'; //Change to display in a different category on Special:SpecialPages.
	}
}

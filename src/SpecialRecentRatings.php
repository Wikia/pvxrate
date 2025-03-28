<?php

namespace Fandom\PvXRate;

use MediaWiki\SpecialPage\SpecialPage;

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
class SpecialRecentRatings extends SpecialPage {

	public function __construct(
		private readonly RateService $rateService,
		private readonly RatingListRenderer $renderer
	) {
		parent::__construct(
			'RecentRatings', // name
			null, // required user right
			true // display on Special:Specialpages
		);
	}

	public function execute( $subPage = null ): void {
		$this->getOutput()->addModules( 'ext.pvxrate' );
		$this->getOutput()->setPageTitle( wfMessage( 'recentratings' ) );

		$ratings = $this->rateService->getRatings();
		$this->renderer->render( $ratings, $this->getOutput(), $this->getUser(), $this->getLanguage() );
	}

	protected function getGroupName(): string {
		return 'pvx'; // Change to display in a different category on Special:SpecialPages.
	}
}

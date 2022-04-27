<?php

use Fandom\ImageReview\Domain\ImageReviewCaching;
use Fandom\ImageReview\Domain\ImageReviewClient;
use Fandom\ImageReview\Domain\ImageReviewHistoryRenderer;
use Fandom\ImageReview\Domain\ImageReviewNotifier;
use Fandom\ImageReview\Domain\ImageReviewRemover;
use Fandom\Includes\Rabbit\SimplePublisher;
use Fandom\Includes\Rpc\UrlProvider;
use Fandom\PvXRate\RateService;
use Fandom\PvXRate\RatingListRenderer;
use Fandom\WikiConfig\WikiVariablesDataService;
use MediaWiki\MediaWikiServices;

return [
	RateService::class => static function ( MediaWikiServices $services ) {
		return new RateService( $services->getDBLoadBalancer() );
	},
	RatingListRenderer::class => static function ( MediaWikiServices $services ) {
		return new RatingListRenderer( $services->getUserOptionsLookup() );
	},
];

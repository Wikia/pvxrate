<?php

use Fandom\PvXRate\RateService;
use Fandom\PvXRate\RatingListRenderer;
use MediaWiki\MediaWikiServices;

return [
	RateService::class => static function ( MediaWikiServices $services ): RateService {
		return new RateService( $services->getDBLoadBalancer() );
	},
	RatingListRenderer::class => static function ( MediaWikiServices $services ): RatingListRenderer {
		return new RatingListRenderer( $services->getUserOptionsLookup() );
	},
];

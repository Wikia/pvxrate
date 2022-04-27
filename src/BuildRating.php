<?php

declare( strict_types=1 );

namespace Fandom\PvXRate;

class BuildRating {

	/** @var int */
	public $voteNumber;
	/** @var float[] */
	public $averages;
	/** @var int[][] */
	public $histogram;
}

<?php

declare( strict_types=1 );

namespace Fandom\PvXRate;

use Wikimedia\Rdbms\ILoadBalancer;

class RateService {

	/** @var float[] # weighting of criteria */
	private const WEIGHTS = [
		1 => 0.8,
		2 => 0.2,
		3 => 0.0,
	];

	/** @var ILoadBalancer */
	private $lb;

	public function __construct( ILoadBalancer $lb ) {
		$this->lb = $lb;
	}

	/**
	 *  Determine overall rating (equal weighting of all voters, not counting rolled back votes)
	 * @param int $pageId
	 * @return BuildRating
	 */
	public function getBuildRating( int $pageId ): BuildRating {
		$dbr = $this->lb->getConnection( DB_REPLICA );
		$res = $dbr->selectRow(
			[ 'rating' ],
			[
				'count(*) AS count',
				'sum( rating1 ) AS r1',
				'sum( rating2 ) AS r2',
				'sum( rating3 ) AS r3',
			],
			[
				'rollback != 1',
				"page_id = $pageId",
			]
		);

		$r = new BuildRating();
		$r->voteNumber = (float)$res->count;
		if ( $res ) {
			$r->averages = [
				( $res->r1 * self::WEIGHTS[1] + $res->r2 * self::WEIGHTS[2] + $res->r3 * self::WEIGHTS[3] ) /
				$r->voteNumber,
				$res->r1 / $r->voteNumber,
				$res->r2 / $r->voteNumber,
				$res->r3 / $r->voteNumber,
			];

		} else {
			$r->averages = [
				0.0,
				0.0,
				0.0,
				0.0,
			];
		}

		$r->histogram = [];
		for ( $y = 1; $y <= 3; $y ++ ) { # y=1..3 counts criteria
			for ( $i = 0; $i <= 5; $i ++ ) { # i=0..5 counts rating
				$r->histogram[$y][$i] = (int)$dbr->fetchObject(
					$dbr->query(
						"SELECT count(rating" . $y . ") AS count FROM rating
									 WHERE rating" . $y . " = " . $i . " AND rollback != 1 AND page_id = "
						. $pageId
					)
				)->count;
			}
		}
		return $r;
	}

	public function deleteRating( int $rateId ): void {
		$this->lb->getConnection( DB_PRIMARY )->delete( 'rating', [
			'rate_id' => $rateId,
		] );
	}

	/**
	 * Save rating to database
	 * @param array $input
	 */
	public function rateSave( array $input ): void {
		$this->lb->getConnection( DB_PRIMARY )->insert(
			'rating',
			[
				'page_id' => $input['page_id'],
				'user_id' => $input['user_id'],
				'comment' => $input['comment'],
				'rollback' => 0,
				'admin_id' => 0,
				'reason' => '',
				'rating1' => $input['rating'][0],
				'rating2' => $input['rating'][1],
				'rating3' => $input['rating'][2],
				'ip_address' => '',
			]
		);
	}

	/**
	 * Read ratings from db
	 * @param int|null $pageId
	 * @param int|null $userId
	 * @param int|null $rateId
	 * @return array
	 */
	public function rateRead( ?int $pageId, ?int $userId, ?int $rateId ): array {
		$where = [];
		$limit = 1000;
		if ( $rateId ) {
			$where['rate_id'] = $rateId;
			$limit = 1;
		} else {
			$where['page_id'] = $pageId;
			if ( $userId ) {
				$where['user_id'] = $userId;
				$limit = 1;
			}
		}
		$res = $this->lb->getConnection( DB_REPLICA )->select(
			[ 'rating' ],
			[ '*' ],
			$where,
			__METHOD__,
			[ 'LIMIT' => $limit ]
		);

		# Make list
		$i = 0;
		$rate_out = [];
		foreach ( $res as $row ) {
			$rate_out[$i ++] = $this->map( $row );
		}
		return $rate_out;
	}

	public function findRatingById( int $rateId ): ?array {
		return $this->map(
			$this->lb
				->getConnection( DB_REPLICA )
				->selectRow(
					[ 'rating' ],
					[ '*' ],
					[ 'rate_id' => $rateId ]
				)
		);
	}

	/**
	 * Update rating in database.
	 */
	public function rateUpdate( int $rateId, array $input ): void {
		$this->lb->getConnection( DB_PRIMARY )->update(
			'rating',
			[
				'comment' => $input['comment'],
				'rating1' => $input['rating'][0],
				'rating2' => $input['rating'][1],
				'rating3' => $input['rating'][2],
			],
			[ 'rate_id' => $rateId ]
		);
	}

	public function rollbackOrRestore( int $rateId, int $adminId, bool $rollback, string $reason ) {
		$this->lb->getConnection( DB_PRIMARY )->update(
			'rating',
			[
				'rollback' => $rollback ? 1 : 0,
				'reason' => $reason,
				'admin_id' => $adminId,
			],
			[ 'rate_id' => $rateId ]
		);
	}

	private function map( $row ): ?array {
		if ( !$row ) {
			return null;
		}
		return [
			'rate_id' => intval( $row->rate_id ),
			'page_id' => intval( $row->page_id ),
			'user_id' => intval( $row->user_id ),
			'admin_id' => intval( $row->admin_id ),
			'comment' => $row->comment,
			'rollback' => intval( $row->rollback ),
			'reason' => $row->reason,
			'rating' => [
				$row->rating1,
				$row->rating2,
				$row->rating3,
			],
			'timestamp' => $row->timestamp,
		];
	}

	/**
	 * Get ratings from database
	 */
	public function getRatings( ?int $userId = null ): array {
		$dbr = $this->lb->getConnection( DB_REPLICA );
		$where = [
			'page.page_namespace' => NS_BUILD,
		];
		if ( $userId !== null ) {
			$where['rating.user_id'] = $userId;
		}
		$res = $dbr->select(
			[ 'rating', 'user', 'page' ],
			[
				'user_name',
				'rating.user_id',
				'page_title',
				'comment',
				'rollback',
				'admin_id',
				'reason',
				'rating1',
				'rating2',
				'rating3',
				'timestamp',
			],
			$where,
			__METHOD__,
			[
				'ORDER BY' => "rating.timestamp DESC",
				"LIMIT" => '200',
			],
			[
				'user' => [ 'LEFT JOIN', [ 'rating.user_id=user.user_id' ] ],
				'page' => [ 'LEFT JOIN', [ 'rating.page_id=page.page_id' ] ],
			]
		);

		$ratings = [];
		while ( $row = $dbr->fetchObject( $res ) ) {
			$ratings[] = [
				'user_name' => $row->user_name,
				'comment' => $row->comment,
				'page_title' => str_replace( '_', ' ', $row->page_title ),
				'rollback' => $row->rollback,
				'admin_id' => $row->admin_id,
				'reason' => $row->reason,
				'rating' => [
					$row->rating1,
					$row->rating2,
					$row->rating3,
				],
				'timestamp' => $row->timestamp,
			];
		}
		return $ratings;
	}

	public function getRatingsByUser( int $userId ): array {
		return $this->getRatings( $userId );
	}
}

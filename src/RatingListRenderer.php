<?php

declare( strict_types=1 );

namespace Fandom\PvXRate;

use Language;
use MediaWiki\User\UserOptionsLookup;
use OutputPage;
use User;

class RatingListRenderer {

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	public function __construct(UserOptionsLookup $userOptionsLookup ) {
		$this->userOptionsLookup = $userOptionsLookup;
	}

	public function render( array $ratings, OutputPage $output, User $user, Language $language ) {
		$timeCorrection = $this->userOptionsLookup->getOption( $user, 'timecorrection' );

		if ( !$ratings ) {
			$out = wfMessage( 'no-ratings-found' );
			$output->addWikiTextAsContent( $out );
		}
		$previousTime = '';
		foreach ( $ratings as $rating ) {
			if ( $rating['page_title'] ) {

				$currentTime = $language->date(
					wfTimestamp( TS_MW, $rating['timestamp'] ),
					true,
					false,
					$timeCorrection
				);
				$out = '* ';

				if ( $previousTime != $currentTime ) {
					$tc = '===' . $currentTime . '===';
					$output->addWikiTextAsContent( $tc );
				}
				$previousTime = $currentTime;
				$time = $language->time( wfTimestamp( TS_MW, $rating['timestamp'] ), true, false, $timeCorrection );

				if ( $rating['user_name'] ) {
					$user_link =
						'[[User:' . $rating['user_name'] . '|' . $rating['user_name'] . ']]' . ' ' . '(' .
						'[[User_talk:' . $rating['user_name'] . '|Talk]]' . ' | ' . '[[Special:Contributions/' .
						$rating['user_name'] . '|contribs]])';
				} else {
					$user_link = '';
				}

				$page_link =
					'[[Build:' . $rating['page_title'] . '|' . $rating['page_title'] . ']] - [[Build_talk:' .
					$rating['page_title'] . '|talk]] - [{{FULLURL:Build:' .
					str_replace( " ", "_", $rating['page_title'] ) . '|action=rate}} Rate]';

				if ( $rating['admin_id'] ) {
					$admin_name = User::whoIs( $rating['admin_id'] );
					$admin_link = '[[User:' . $admin_name . '|' . $admin_name . ']]';
				}

				$out .= $time;
				$out .= ' . . ';
				if ( $rating['rollback'] ) {
					$out .= '<font color="red"><b>Rollback</b></font> ';
				}
				if ( !$rating['rollback'] && $rating['reason'] ) {
					$out .= '<font color="green"><b>Restore</b></font> ';
				}
				$out .= $page_link;
				$out .= '; ';

				$total = $rating['rating'][0] * .8 + $rating['rating'][1] * .2 + $rating['rating'][2] * .0;
				if ( $total < 3.75 ) {
					$rating['text'] = 'Rating: \'\'\'' . $total . '\'\'\' (\'\'trash\'\')';
				} elseif ( $total < 4.75 ) {
					$rating['text']  = 'Rating: \'\'\'' . $total . '\'\'\' (\'\'good\'\')';
				} elseif ( $total >= 4.75 ) {
					$rating['text']  = 'Rating: \'\'\'' . $total . '\'\'\' (\'\'great\'\')';
				}

				if ( $rating['rollback'] ) {
					$out .= '\'\'\'' . $admin_link . '\'\'\'' . ' removed ' . strtolower( $rating['text'] ) . ' posted by: ' .
							$user_link;
				} elseif ( !$rating['rollback'] && $rating['reason'] ) {
					$out .= '\'\'\'' . $admin_link . '\'\'\'' . ' restored ' . strtolower( $rating['text'] ) . ' posted by: ' .
							$user_link;
				} else {
					$out .= $rating['text'] ;
					$out .= ' . . ';
					$out .= ' E:' . $rating['rating'][0];
					$out .= ' U:' . $rating['rating'][1];
					$out .= ' I:' . $rating['rating'][2];
					$out .= ' . . ';
					$out .= $user_link;
				}
				$output->addWikiTextAsContent( $out );
			}
		}
	}
}

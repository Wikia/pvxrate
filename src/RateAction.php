<?php

declare( strict_types=1 );

namespace Fandom\PvXRate;

use Article;
use Fandom\BannerNotifications\Controller\BannerNotificationsController;
use FormlessAction;
use MediaWiki\Context\IContextSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\User\User;
use RuntimeException;

class RateAction extends FormlessAction {
	const ACTION_ROLLBACK = 'rollback';
	const ACTION_RESTORE = 'restore';
	const ACTION_DELETE = 'delete';
	const ACTION_EDIT = 'edit';
	private readonly LinkRenderer $linkRenderer;
	private readonly RateService $service;
	private readonly Parser $parser;
	private readonly int $editsReq;

	public function __construct( Article $page, ?IContextSource $context = null ) {
		parent::__construct( $page, $context );
		$services = MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'main' );
		$this->editsReq = $config->get( 'PvXRateEditsRequired' );
		$this->linkRenderer = $services->getLinkRenderer();
		$this->parser = $services->getParser();
		$this->service = $services->getService( RateService::class );
	}

	/**
	 * Return the name of the action
	 * @return string
	 */
	public function getName(): string {
		return 'rate';
	}

	/**
	 * Handle viewing of action.
	 */
	public function onView(): void {
		$output = $this->getOutput();
		$request = $this->getRequest();
		$output->addModules( 'ext.pvxrate' );

		$subAction = $request->getText( 'sub-action', null );
		$rateId = $request->getInt( 'rateId', null );

		$isAdmin = $this->getUser()->isAllowed( 'vote_rollback' );

		// There are two scenarios where we do not show all existing votes:
		// (1) Page does not exist
		// (2) Page is in the wrong namespace

		// Check whether associated page exists
		if ( !$this->getTitle()->exists() ) {
			$perm_msg = '=== Page does not exist ===
__NOEDITSECTION__
The target page does not exist yet.

&larr; <i>Back to [{{FULLURL:{{SUBJECTPAGENAME}}}} {{SUBJECTPAGENAME}}]</i>';
			$output->addWikiTextAsContent( $perm_msg );
			return;
		}

		// Check whether page is in the wrong namespace
		if ( ( $this->getTitle()->getNamespace() == NS_SPECIAL )
			 || ( $this->getTitle()->getNamespace() !== NS_BUILD ) ) {
			$perm_msg = '=== Incorrect namespace ===
__NOEDITSECTION__
Pages in this namespace cannot be voted upon.

&larr; <i>Back to [{{FULLURL:{{SUBJECTPAGENAME}}}} {{SUBJECTPAGENAME}}]</i>';
			$output->addWikiTextAsContent( $perm_msg );
			return;
		}

		// For all other scenarios, we display all existing votes.

		// Check if user has the correct permissions (autoconfirmed, not blocked)
		// User does not have permission to rate: show your own rating, don't show the form, show current ratings, readonly
		if ( !$this->checkPermissions() ) {
			$this->ratePrintAll( true, false, true, true );
			return;
		}

		if ( $this->getRequest()->wasPosted() ) {
			$this->handlePost( $subAction, $rateId, $isAdmin );
			return;
		}

		// Page title
		$output->setPageTitle( 'Build rating' );

		// sub-action=delete|edit|rollback

		if ( ( $subAction == self::ACTION_EDIT ) && ( $this->rateCheckRights( $rateId ) ) ) {
			$output->addHtml( '<h2> Rate this build </h2>' );
			$output->addHtml( $this->rateForm( $rateId ) );
			$this->ratePrintAll( false, true, true, false );
			return;
		}

		if ( ( $subAction == self::ACTION_ROLLBACK ) && ( $isAdmin ) && ( $rateId ) ) {
			$output->addHtml( $this->rateRollback( $rateId ) );
			$this->ratePrintAll( true, false, true, false );
			return;
		}

		if ( ( $subAction == self::ACTION_RESTORE ) && ( $isAdmin ) && ( $rateId ) ) {
			$output->addHtml( $this->rateRestore( $rateId ) );
			$this->ratePrintAll( true, false, true, false );
			return;
		}

		// default action
		$this->ratePrintAll( true, true, true, false );
	}

	/**
	 * @throws RuntimeException
	 */
	private function handlePost( ?string $subAction, ?int $rateId, bool $isAdmin ): void {
		if ( $subAction == null ) {
			if ( $rateId ) {
				throw new RuntimeException( 'Rate ID should not be provided for new rates' );
			}
			$formData = $this->formData();
			$validationError = $this->validateRate( $formData );
			if ( $validationError ) {
				BannerNotificationsController::addConfirmation(
					$validationError,
					BannerNotificationsController::CONFIRMATION_ERROR
				);
				$this->redirect();
				return;
			}
			$this->service->rateSave( $formData );
			BannerNotificationsController::addConfirmation( 'Rating added!' );
			$this->getOutput()->redirect( $this->getTitle()->getFullURL( 'action=rate' ) );
			return;
		}

		// all the other actions will require a valid rating
		if ( $rateId == null ) {
			throw new RuntimeException( 'Rate ID missing' );
		}
		$rating = $this->service->findRatingById( $rateId );
		if ( $rating == null || $rating['page_id'] !== $this->getTitle()->getId() ) {
			BannerNotificationsController::addConfirmation(
				'Invalid rating',
				BannerNotificationsController::CONFIRMATION_ERROR
			);
			$this->redirect();
			return;
		}

		if ( $subAction === self::ACTION_EDIT ) {
			if ( $rating['user_id'] !== $this->getUser()->getId() ) {
				BannerNotificationsController::addConfirmation(
					'Cannot edit someone else\'s rating.',
					BannerNotificationsController::CONFIRMATION_ERROR
				);
				$this->redirect();
				return;
			}
			$formData = $this->formData();
			$validationError = $this->validateRate( $formData );
			if ( $validationError ) {
				BannerNotificationsController::addConfirmation(
					$validationError,
					BannerNotificationsController::CONFIRMATION_ERROR
				);
				$this->redirect();
				return;
			}
			$this->service->rateUpdate( $rateId, $formData );
			$this->redirect();
			return;
		}

		if ( $subAction === self::ACTION_DELETE ) {
			if ( $rating['user_id'] !== $this->getUser()->getId() ) {
				BannerNotificationsController::addConfirmation(
					'Cannot delete someone else\'s rating.',
					BannerNotificationsController::CONFIRMATION_ERROR
				);
				$this->getOutput()->redirect( $this->getTitle()->getFullURL( 'action=rate' ) );
				return;
			}
			$this->service->deleteRating( $rateId );
			BannerNotificationsController::addConfirmation( 'Your rating was deleted.' );
			$this->getOutput()->redirect( $this->getTitle()->getFullURL( 'action=rate' ) );
			return;
		}

		$request = $this->getRequest();
		if ( $subAction === self::ACTION_ROLLBACK || $subAction === self::ACTION_RESTORE ) {
			$reason = $request->getText( 'reason' );
			if ( empty( $reason ) ) {
				BannerNotificationsController::addConfirmation(
					'Reason cannot be empty',
					BannerNotificationsController::CONFIRMATION_ERROR
				);
				$this->redirect( $rateId, $subAction );
				return;
			}
			if ( !$isAdmin ) {
				BannerNotificationsController::addConfirmation(
					'You are not admin.',
					BannerNotificationsController::CONFIRMATION_ERROR
				);
				$this->redirect();
				return;
			}
			$isRollback = $subAction == self::ACTION_ROLLBACK;
			$this->service->rollbackOrRestore(
				$rateId,
				$this->getUser()->getId(),
				$isRollback,
				$reason
			);
			if ( $isRollback ) {
				BannerNotificationsController::addConfirmation( 'Successfully removed rating.' );
			} else {
				BannerNotificationsController::addConfirmation( 'Successfully restored rating.' );
			}
			$this->redirect();
			return;
		}

		throw new RuntimeException( 'Unexpected action' );
	}

	private function validateRate( array $rate ): ?string {
		if ( ( !is_numeric( $rate['rating'][0] ) ) || ( $rate['rating'][0] > 5 ) ||
			 ( $rate['rating'][0] < 0 ) || ( !is_numeric( $rate['rating'][1] ) ) ||
			 ( $rate['rating'][1] > 5 ) || ( $rate['rating'][1] < 0 ) ) {
			return 'Invalid rate';
		}
		if ( strlen( $rate['comment'] ) < 12 ) {
			return 'Comment is too short';
		}
		return null;
	}

	/**
	 * Check permissions, and add WikiText if permissions fail.
	 */
	public function checkPermissions(): bool {
		$output = $this->getOutput();

		// Check if user allowed to rate this build, then display a message if not. Requirements:
		// * Logged in
		// * Not a blocked user
		// * Email authenticated
		// * Minimum of X edits

		$block = $this->getUser()->getBlock();
		if ( $block && $block->isSitewide() ) {
			$perm_msg = '=== Read-only mode: You are currently blocked. ===
__NOEDITSECTION__
Blocked users cannot vote. You will need to wait until your current block expires.';
			$output->addWikiTextAsContent( $perm_msg );
			return false;
		} elseif ( $this->getUser()->isAnon() ) {
			$perm_msg = '=== Read-only mode: You are currently not logged in. ===
__NOEDITSECTION__
For security reasons you need to fulfill the following requirements in order to submit a vote:
* You need to log in. Visit [[Special:UserLogin]] to log in or create a new account.
* You need to authenticate your e-mail address.
* You need to make at least ' . $this->editsReq . ' edits to the wiki.';
			$output->addWikiTextAsContent( $perm_msg );
			return false;
		} elseif ( !$this->getUser()->mEmailAuthenticated ) {
			$perm_msg = '=== Read-only mode: Your e-mail address is not authenticated. ===
__NOEDITSECTION__
For security reasons you need to fulfill the following requirements in order to submit a vote:
* You need to log in.
* You need to authenticate your e-mail address. Please edit/add your e-mail address using [[Special:Preferences]]
and a confirmation e-mail will be sent to that address. Follow the instructions in the e-mail to confirm
 that the account is actually yours.
* You need to make at least ' . $this->editsReq . ' edits to the wiki.';
			$output->addWikiTextAsContent( $perm_msg );
			return false;
		} elseif ( $this->getUser()->getEditCount() < $this->editsReq ) {
			$perm_msg = '=== Read-only mode: You made only ' . $this->getUser()->getEditCount() . ' edits so far. ===
__NOEDITSECTION__
For security reasons you need to fulfill the following requirements in order to submit a vote:
* You need to log in.
* You need to authenticate your e-mail address.
* You need to make at least ' . $this->editsReq .
						' contributions to the wiki. A contribution is any edit to any page. A good way to get
						your first few contributions is adding some information
						about yourself to [[Special:Mypage|your userpage]].';
			$output->addWikiTextAsContent( $perm_msg );
			return false;
		} elseif ( !$this->getUser()->isAllowed( 'ratebuild' ) ) {
			$perm_msg = '=== Permissions error. ===
__NOEDITSECTION__
Whilst your account meets all of the basic requirements for the rating permission
(logged in, not blocked, email authenticated, edit count threshold met),
miraculously your user account lacks the "ratebuild" rights. This may indicate a bug with the PvXRate extension.
Please report this bug to your site administrator.';
			$output->addWikiTextAsContent( $perm_msg );
			return false;
		}
		// Default to true otherwise, i.e. user has permission to rate builds.
		return true;
	}

	/**
	 * Format a link for use elsewhere
	 * @param string $label link title
	 * @param string $subAction sets the ?sub-action= request variable.
	 * @param int $rateId the build id. sets ?rateId=
	 * @return string
	 */
	public function rateLink( string $label, string $subAction, int $rateId ): string {
		return $this->linkRenderer->makeLink(
			$this->getTitle(),
			$label,
			[ 'class' => 'wds-button wds-is-text' ],
			[ 'action' => 'rate', 'sub-action' => $subAction, 'rateId' => $rateId ]
		);
	}

	public function deleteForm( int $rateId ): string {
		$action = $this->getTitle()->getFullURL( 'action=rate&sub-action=delete' );
		return '<form method="post" action="' . $action . '">'
			   . '<input name="rateId" type="hidden" value="' . $rateId . '" />'
			   . '<button class="wds-button wds-is-text" type="submit">Delete</button></form>';
	}

	/**
	 * Builds output for a specific rating
	 * @param array $ratings array containing the rating records
	 * @param string $link links (update, delete) to include
	 * @return string
	 */
	public function ratePrint( array $ratings, string $link ): string {
		$userName = User::whoIs( $ratings['user_id'] );

		$number_max = 5;
		$bar_width = 168; // width in pixels of bar

		$cate = [
			1 => 0.8,
			2 => 0.2,
			3 => 0.0,
		];
		$rate = [
			1 => $ratings['rating'][0],
			2 => $ratings['rating'][1],
			3 => $ratings['rating'][2],
		];
		$cur_score = ( $rate[1] * $cate[1] + $rate[2] * $cate[2] + $rate[3] * $cate[3] );
		$sze_table = [
			1 => ( ( $rate[1] / $number_max ) * $bar_width ),
			2 => ( ( $rate[2] / $number_max ) * $bar_width ),
			3 => ( ( $rate[3] / $number_max ) * $bar_width ),
		];

		$overall_rating_bar_width = ( $cur_score / $number_max ) * $bar_width;

		$parserOptions = ParserOptions::newFromUser( $this->getUser() );
		// this is deprecated but as of now I don't believe there's a replacement
		// $this->parser->mShowToc = false;
		$parsedComment = $this->parser->parse( $ratings['comment'], $this->getTitle(), $parserOptions )->mText;

		if ( $ratings[self::ACTION_ROLLBACK] ) {
			$comment =
				'<b>Removed: </b><s>' . $parsedComment . '</s><br> <b>Reason: </b>' . $ratings['reason'] .
				'<br><b>Removed by: </b> ' . User::whois( $ratings['admin_id'] );
		} else {
			$comment = $parsedComment;
		}

		$timestamp = strtotime( $ratings['timestamp'] );
		$timestr = date( 'H:i, d M Y', $timestamp ) . ' (EST)'; # GMT on test box, EST on main server

		$tduser = $this->parser->parse(
			'[[User:' . $userName . '|' . $userName . ']]',
			$this->getTitle(),
			$parserOptions
		)->mText;
		if ( $rate[3] > 0 ) {
			$inno_out = 'X';
		} else {
			$inno_out = 'O';
		}
		return '
<div class="rating">
	<table>
		<tr>
			<td class="tdrating">
				<div class="r1" style="width:' . $overall_rating_bar_width . 'px;"><span>Overall</span></div>
			</td>
			<td class="tdresult">' . sprintf( '%3.1f', round( $cur_score * 10 ) / 10 ) . '</td>
			<td class="tdcomment" rowspan="4">
				<table class="tablecomment" style="border:0;">
					<tr>
						<td class="tduser">' . $tduser . '</td>
						<td class="tdedit"><div> Last edit: ' . $timestr . '&nbsp;</div>' . $link . '</td>
					</tr>
					<tr>
						<td colspan="2">' . $comment . '</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td class="tdrating">
				<div class="r2" style="width:' . $sze_table[1] . 'px;"><span>Effectiveness</span></div>
			</td>
			<td class="tdresult">' . $rate[1] . '</td>
		</tr>
		<tr>
			<td class="tdrating">
				<div class="r3" style="width:' . $sze_table[2] . 'px;"><span>Universality</span></div>
			</td>
			<td class="tdresult">' . $rate[2] . '</td>
		</tr>
		<tr>
			<td class="tdrating">
				<div class="r4" style="width:' . $sze_table[3] . 'px;"><span>Innovation</span></div>
			</td>
			<td class="tdresult">' . $inno_out . '</td>
		</tr>
	</table>
</div><br>';
	}

	/**
	 * print ratings for a specific build
	 * @param bool $show_own Show users own rating or not
	 * @param bool $show_form Show rating form
	 * @param bool $show_all Show all current ratings
	 * @param bool $read_only if true, just call ratePrint
	 * @return void prints to screen
	 */
	public function ratePrintAll(
		bool $show_own,
		bool $show_form,
		bool $show_all,
		bool $read_only
	): void {
		$out_rmv_count = 0;
		$out_own_count = 0;
		$out_all_count = 0;

		$out_rmv = '';
		$out_own = '';
		$out_all = '';

		// Get all the ratings for this build
		$current = $this->service->rateRead( $this->getTitle()->getID(), null, null );

		// Check if there are any current ratings in the database.
		if ( $current ) {
			// Getting the info from database.
			foreach ( $current as $array ) {
				if ( $read_only ) {
					$out_all .= $this->ratePrint( $array, '' );
					$out_all_count++;
				} else {
					$rateId = $array['rate_id'];
					if ( $array[self::ACTION_ROLLBACK] ) {
						// Rating has been removed, and is owned by the current logged-in user
						if ( $array['user_id'] == $this->getUser()->getID() ) {
							$link = $this->deleteForm( $rateId );
							$link .= $this->rateLink( 'Edit', self::ACTION_EDIT, $rateId );
							$out_rmv = $this->ratePrint( $array, $link );
							$out_rmv_count++;
							$show_form = false;
						} elseif ( $this->getUser()->isAllowed( 'vote_rollback' ) ) {
							// Rating has been removed, and the current logged-in user has permissions
							// to restore the rating (admin)
							$link = $this->rateLink( 'Restore', self::ACTION_RESTORE, $rateId );
							$out_rmv .= $this->ratePrint( $array, $link );
							$out_rmv_count++;
						} else {
							// Rating has been removed, and its somebody elses
							$out_rmv .= $this->ratePrint( $array, '' );
							$out_rmv_count++;
						}
					} else {
						// Rating is current, and is owned by the current logged in user
						if ( $array['user_id'] == $this->getUser()->getID() ) {
							$link = $this->deleteForm( $rateId );
							$link .= $this->rateLink( 'Edit', self::ACTION_EDIT, $rateId );
							$out_own = $this->ratePrint( $array, $link );
							$out_own_count++;
							$show_form = false;
						} elseif ( $this->getUser()->isAllowed( 'vote_rollback' ) ) {
							// Rating is current, and the current logged in user has permissions to remove the rating (admin)
							$link = $this->rateLink( 'Remove', self::ACTION_ROLLBACK, $rateId );
							$out_all .= $this->ratePrint( $array, $link );
							$out_all_count++;
						} else {
							// Rating is current, and it's somebody elses
							$out_all .= $this->ratePrint( $array, '' );
							$out_all_count++;
						}
					}
				}
			}

			// Existing overall voting results
			$out = $this->ratePrintResults();

			// If you have already voted
			if ( ( $out_own_count > 0 ) && ( $show_own ) ) {
				$out .= '<h2> Your Rating </h2>';
				$out .= $out_own;
			}

			// If there are other ratings which are valid
			if ( ( $out_all_count > 0 ) && ( $show_all ) ) {
				$out .= '<h2> Current Ratings </h2>';
				$out .= $out_all;
			}

			// If there are other ratings which are not valid
			if ( ( $out_rmv_count > 0 ) && ( $show_all ) ) {
				$out .= ( '<h2> Removed Ratings </h2>' );
				$out .= $out_rmv;
			}
		}

		$output = $this->getOutput();
		// If there are no ratings in the database, and you have permission to rate, and haven't voted already
		if ( $show_form ) {
			$output->addHtml( '<h2> Rate this build </h2>' );
			$output->addHtml( $this->rateForm( null ) );
		}

		// If there were ratings in the database, append them to the page.
		if ( isset( $out ) ) {
			$output->addHtml( $out );
		}
	}

	public function ratePrintResults(): string {
		# names of criteria
		$rate_names = [
			1 => 'Effectiveness',
			2 => 'Universality',
			3 => 'Innovation',
		];

		# tooltip text for the above criteria
		$rate_descr = [
			1 => 'This criterion describes how effective the build does what it was designed for.
			That is, how much damage does a spiker build deal, a healer build heal or a protector build prevent?
			How good is the chance to get through the specified area with a running build
			or to reach and defeat the specified foes with a farming build?',
			2 => 'This criterion describes how flexible the build is when used in a situation slightly different
			from what the build was designed for. This includes the ability to change strategy in case a foe
			shows unexpected actions, in case an ally does not perform as expected, or when used
			in a different location than originally intended.',
			3 => 'This criterion describes how new the idea behind this build is. Does it use a new approach
			for dealing with a known task or even act as a precursor for dealing with a previously unconsidered task?
			To what extend is it expected to become a prototype for a new class of builds?',
		];

		$r = $this->service->getBuildRating( $this->getTitle()->getId() );

		# fill histogram
		# $r[x][y] is number of 'y' ratings on criterion 'x'

		# overall r output - Bars increase in horizontal direction
		$out = ( '<h2> Rating totals: ' . $r->voteNumber . ' votes</h2>' );
		$out .= '<table><tr>';
		$out .= '
<td>
	<div class="sum">
		<table>
			<tr>
				<td class="tdrating">
					<div class="r1" style="width:' . round( $r->averages[0] * 168 / 5 ) . 'px;"><span>Overall</span></div>
				</td>
				<td class="tdresult">' . sprintf( '%4.2f', round( $r->averages[0] * 100 ) / 100 ) . '</td>
			</tr>
			<tr>
				<td class="tdrating">
					<div class="r2" style="width:' . round( $r->averages[1] * 168 / 5 ) . 'px;"><span>Effectiveness</span></div>
				</td>
				<td class="tdresult">' . sprintf( '%4.2f', round( $r->averages[1] * 100 ) / 100 ) . '</td>
			</tr>
			<tr>
				<td class="tdrating">
					<div class="r3" style="width:' . round( $r->averages[2] * 168 / 5 ) . 'px;"><span>Universality</span></div>
				</td>
				<td class="tdresult">' . sprintf( '%4.2f', round( $r->averages[2] * 100 ) / 100 ) . '</td>
			</tr>
			<tr>
				<td class="tdrating">
					<div class="r4" style="width:' . round( $r->averages[3] * 168 / 5 ) . 'px;"><span>Innovation</span></div>
				</td>
				<td class="tdresult">' . sprintf( '%4.0f', round( $r->averages[3] * 20 ) ) . '%</td>
			</tr>
		</table>
	</div>
</td>';

		# histograms
		# $r[c][q] is number of 'q' ratings on criterion 'c'
		for ( $c = 1; $c <= 2; $c++ ) {
			# normalize histogram
			for ( $q = 0; $q <= 5; $q++ ) {
				if ( $r->voteNumber > 0 ) {
					$r->histogram[$c][$q] = round( $r->histogram[$c][$q] / $r->voteNumber * 77 );
				} else {
					$r->histogram[$c][$q] = 0;
				}
			}

			# plot - Bars increase in vertical direction
			$out .= '
<td>
	<div class="result">
		<table>
			<tr>
				<td colspan="6" class="tdresult"><span title="' . $rate_descr[$c] . '">' . $rate_names[$c] . '</span></td>
			</tr>
			<tr>
				<td class="tdrating">
					<div style="height:' . ( $r->histogram[$c][0] ) . 'px;"></div>
				</td>
				<td class="tdrating"><div class="v' . ( $c + 1 ) . '" style="height:' . ( $r->histogram[$c][1] ) .
					'px;" title="' . $r->histogram[$c][1] . 'votes "></div></td>
				<td class="tdrating"><div class="v' . ( $c + 1 ) . '" style="height:' . ( $r->histogram[$c][2] ) .
					'px;" title="' . $r->histogram[$c][1] . 'votes "></div></td>
				<td class="tdrating"><div class="v' . ( $c + 1 ) . '" style="height:' . ( $r->histogram[$c][3] ) .
					'px;" title="' . $r->histogram[$c][1] . 'votes "></div></td>
				<td class="tdrating"><div class="v' . ( $c + 1 ) . '" style="height:' . ( $r->histogram[$c][4] ) .
					'px;" title="' . $r->histogram[$c][1] . 'votes "></div></td>
				<td class="tdrating"><div class="v' . ( $c + 1 ) . '" style="height:' . ( $r->histogram[$c][5] ) .
					'px;" title="' . $r->histogram[$c][1] . 'votes "></div></td>
			</tr>
			<tr>
				<td class="tdresult">0</td>
				<td class="tdresult">1</td>
				<td class="tdresult">2</td>
				<td class="tdresult">3</td>
				<td class="tdresult">4</td>
				<td class="tdresult">5</td>
			</tr>
		</table>
	</div>
</td>';
		}

		$out .= '</tr></table><br>';
		return $out;
	}

	public function rateCheckRights( int $rateId ): bool {
		$rating = $this->service->findRatingById( $rateId );
		if ( !$rating ) {
			$this->getOutput()->addWikiTextAsContent( 'Rating not found' );
			return false;
		}
		if ( $rating['page_id'] !== $this->getTitle()->getId() ) {
			$this->getOutput()->addWikiTextAsContent( 'Invalid rating' );
			return false;
		}
		if ( $rating['user_id'] !== $this->getUser()->getId() ) {
			$this->getOutput()->addWikiTextAsContent( 'Invalid user' );
			return false;
		}
		return true;
	}

	/**
	 * Generates the rate form
	 * @param int|null $rateId Values to populate form with
	 * @return string
	 */
	public function rateForm( ?int $rateId ): string {
		$rate_names = [
			1 => 'Effectiveness',
			2 => 'Universality',
			3 => 'Innovation',
		];

		$rate_descr = [
			1 => 'This criterion describes how effective the build does what it was designed for.
			That is, how much damage does a spiker build deal, a healer build heal or a protector build prevent?
			How good is the chance to get through the specified area with a running build or to reach
			and defeat the specified foes with a farming build?',
			2 => 'This criterion describes how flexible the build is when used in a situation slightly different
			from what the build was designed for. This includes the ability to change strategy in case
			a foe shows unexpected actions, in case an ally does not perform as expected,
			or when used in a different location than originally intended.',
			3 => 'This criterion describes how new the idea behind this build is.
			Does it use a new approach for dealing with a known task or even act as a precursor
			for dealing with a previously unconsidered task? To what extend
			is it expected to become a prototype for a new class of builds?',
		];

		// ---------- Loading form with values.
		if ( $rateId ) {
			$rate = $this->service->findRatingById( $rateId );
			$input = $rate['rating'];
			$comment = $rate['comment'];
			$submit = 'Save';
			$update = $rate['rate_id'];
			$action = '&sub-action=' . self::ACTION_EDIT;
		} else {
			$input = false;
			$comment = '';
			$submit = 'Rate';
			$update = 0;
			$action = '';
		}

		// ---------- HEAD
		$out =
			'<div class="ratingform"><form method="post" action="' .
			$this->getTitle()->getFullURL( 'action=rate' . $action ) . '"><table class="rating_table">';

		// ---------- Printing form.
		foreach ( $rate_names as $key => $value ) {
			$out .= ( '<tr><td><span class="rating_cat" title="' . $rate_descr[$key] . '">' . $value .
					  '</span></td><td>' );
			if ( $value == 'Innovation' ) {
				if ( ( $input ) && ( $input[$key - 1] ) ) {
					$checked = ' checked ';
				} else {
					$checked = '';
				}
				$out .= ( '<div class="rating_input">' . '<input name="p' . $key . '" type="checkbox" value="5"' .
						  $checked . '>' . '</div>' );
				$out .= ( '</td></tr>' );
			} else {
				for ( $i = 0; $i <= 5; $i++ ) {
					if ( ( $input ) && ( $input[$key - 1] == $i ) ) {
						$checked = ' checked ';
					} else {
						$checked = '';
					}
					$out .= ( '<div class="rating_input"' . ' onMouseOver="swapColor(\'p' . $key . $i . '\',\'#f' .
							  ( 8 - ( $i + 3 ) ) . 'f' . ( 8 - ( $i + 3 ) ) . 'f' . ( 8 - ( $i + 3 ) ) . '\');"' .
							  ' onMouseOut="swapColor(\'p' . $key . $i . '\',\'#ffffff\');" id="p' . $key . $i .
							  '">' .
							  '<input name="p' . $key . '" type="radio" value="' . $i . '"' . $checked . '>' .
							  '<span>' . $i . '</span></div>' );
				}
			}
			$out .= ( '</td></tr>' );
		}

		$out .= ( '<tr><td><span class="rating_cat">Comments</span></td>' .
				  '<td><textarea class="rating_text" name="comment" cols="10" rows="5">' . $comment .
				  '</textarea>' );
		$out .= ( '<input name="rateId" type="hidden" value="' . $update . '" />' );
		$out .= ( '</table><input class="wds-button" type="submit" value="' . $submit . '" /></form></div><br>' );
		return $out;
	}

	/**
	 * Generate rating rollback form
	 * @param int $rateId values to populate form with
	 * @return string
	 */
	public function rateRollback( int $rateId ): string {
		$submit = 'Remove';
		$default_comment = '';

		$out = ( '<div class="ratingform"><form method="post" action="' .
				 $this->getTitle()->getFullURL( 'action=rate&sub-action=rollback' ) . '"><table class="rating_table">' );
		$out .= ( '<tr><td><span class="rating_cat">Reason</span></td><td>
				<textarea class="rating_text" name="reason" cols="10" rows="5">' .
				  $default_comment . '</textarea>' );
		$out .= ( '<input name="rollback" type="hidden" value=1 /><input name="rateId" type="hidden" value="' .
				  $rateId . '" />' );
		$out .= ( '</table><input class="button" type="submit" value="' . $submit . '" /></form></div><br>' );
		return $out;
	}

	/**
	 * Generate rating restore form.
	 */
	public function rateRestore( int $rateId ): string {
		$submit = 'Restore';
		$action = '&sub-action=restore';
		$default_comment = '';

		$out =
			( '<div class="ratingform"><form method="post" action="' .
			  $this->getTitle()->getFullURL( 'action=rate' . $action ) . '"><table class="rating_table">' );
		$out .= ( '<tr><td><span class="rating_cat">Reason</span></td><td>
				<textarea class="rating_text" name="reason" cols="10" rows="5">' .
				  $default_comment . '</textarea>' );
		$out .= ( '<input name="restore" type="hidden" value=1 /><input name="rateId" type="hidden" value="' .
				  $rateId . '" />' );
		$out .= ( '</table><input class="button" type="submit" value="' . $submit . '" /></form></div><br>' );
		return $out;
	}

	/**
	 * Get rating data array, can be used with other functions.
	 */
	public function formData(): array {
		$request = $this->getRequest();
		return [
			'page_id' => $this->getTitle()->getID(),
			'user_id' => $this->getUser()->getID(),
			'comment' => $request->getText( 'comment' ),
			'rating' => [
				$request->getInt( 'p1' ),
				$request->getInt( 'p2' ),
				$request->getInt( 'p3', 0 ),
			],
		];
	}

	private function redirect( ?int $rateId = null, ?string $subAction = null ): void {
		$query = [ 'action' => 'rate' ];
		if ( $rateId ) {
			$query['rateId'] = $rateId;
		}
		if ( $subAction ) {
			$query['sub-action'] = $subAction;
		}
		$this->getOutput()->redirect( $this->getTitle()->getFullURL( $query ) );
	}
}

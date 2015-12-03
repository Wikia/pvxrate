<?php

class RateAction extends FormlessAction {


    /**
     * Return the name of the action
     * @return string
     */
    public function getName() {
        return 'rate';
    }


    /**
	 * Handle viewing of action.
	 * @param  string $action
	 * @param  Article $article
	 * @return bool
	 */
	function onView() {
		global $wgOut, $wgUser, $wgRequest,$wgPvXRateBuildNamespace;

        $wgOut->addModules('ext.pvxrate');
        $action = $wgRequest->getText('rating');
        $article = $this->page;


        $buildNamespace = @constant($wgPvXRateBuildNamespace);
        if (!$buildNamespace) {
            wfWarn('The PvXRateBuildNamespace defined in PvX Rate\'s extension.json file ('.$wgPvXRateBuildNamespace.') is not a valid namespace.',2);
        }
		////////////////////////////////////////////////////////////////////
		// Checking permissions. if now, lets quit.
		////////////////////////////////////////////////////////////////////
		if (!$this->getUser()->isAllowed('ratebuild')) {
			$wgOut->permissionRequired('ratebuild');
			return false;
		}

		if ($this->getUser()->isBlocked()) {
			$wgOut->blockedPage();
			return false;
		}


		if ($this->ratePermissions()) {
			if (($wgRequest->getText('action') == 'rate')
            && ($this->getTitle()->getNamespace() !== NS_SPECIAL)
            && ($this->getTitle()->getNamespace() == $buildNamespace)
            && ($article->getID() !== 0)) {

                // page title
				$wgOut->setPageTitle('Build rating');

				// rating=delete|edit|rollback
				$posted_action = $wgRequest->getText('rating');
				$posted_build  = $wgRequest->getText('build');
				$posted_update = $wgRequest->getText('ratingid');
				$rate_input    = $this->rateGet($article);

				$is_admin = $this->getUser()->isAllowed('vote_rollback');

				$show_own  = true;
				$show_all  = true;
				$show_rate = true;

				if (($posted_action == 'edit') && ($this->rateCheckRights($article, $posted_build)) ) {
					$wgOut->addHtml('<h2> Rate this build </h2>');
					$wgOut->addHtml($this->rateForm($this->rateRead(false, false, $posted_build)));
					$show_own = false;
				}

				if (($posted_action == 'rollback') && ($is_admin) && ($posted_build)) {
					$wgOut->addHtml($this->rateRollback($this->rateRead(false, false, $posted_build)));
				}

				if (($posted_action == 'restore') && ($is_admin) && ($posted_build)) {
					$wgOut->addHtml($this->rateRestore($this->rateRead(false, false, $posted_build)));
				}

				if (($posted_action == 'delete') && ($this->rateCheckRights($article, $posted_build))) {
					$this->rateDelete($posted_build, $article->getID(), $this->getUser()->getID());
					$wgOut->addHtml('<h2> Your rating </h2>');
					$wgOut->addWikiText('Your rating was deleted from our database.');
					$show_own  = false;
					$show_rate = false;
				}

				////////////////////////////////////////////////////////////////////
				// Checking if we got a form submitted to us. if so lets store it.
				////////////////////////////////////////////////////////////////////

				if ($wgRequest->wasPosted()) {
					if ($rate_input['error']) {
						// ERROR! Something went wrong.
						$wgOut->addHtml($rate_input['error_msg']);
					} else {
						if (($posted_action == 'update') && ($this->rateCheckRights($article, $posted_update))) {
							$wgOut->addHtml($this->rateUpdate($rate_input));
						} elseif ($posted_action == 'rollback') {
							$wgOut->addHtml($this->rateUpdate($rate_input));
						} elseif ($posted_action == 'restore') {
							$wgOut->addHtml($this->rateUpdate($rate_input));
						} else {
							// posting was good, lets procces it.
							$rate_input['rollback'] = 0;
							$rate_input['admin_id'] = 0;
							$rate_input['reason']   = '';
							if (!$this->rateRead($article->getID(), $this->getUser()->getID(), false))
								$this->rateSave($rate_input);
						}
					}
				}

				# Lets print our standart output. Rate, list currents, yours.
				$this->ratePrintAll($article, $show_own, $show_rate, $show_all, false);
				$wgOut->addHtml($this->rateOverLib());
				return false;
			} else {
				return true;
			}
			return true;
			# if no permission to rate: show list, but no form
		} else {
			$this->ratePrintAll($article, true, false, true, true);
			$wgOut->addHtml($this->rateOverLib());
			return false;
		}

	}


	/**
	 * Check permissins, and add WikiText if permissions fail.
	 * @return boolean
	 */
	public function ratePermissions() {
		global $wgUser, $wgOut, $perm_msg;

		# check if user allowed to rate this build
		# construct a message explaining the results
		if ($this->getUser()->isAnon()) {
			$perm_msg = '=== Read-only mode: You are currently not logged in. ===
             __NOEDITSECTION__
	     For security reasons you need to fulfill the following requirements in order to submit a vote:
* You need to log in. Visit [[Special:Userlogin]] to log in or create a new account.
* You need to authenticate your e-mail address.
* You need to make at least 8 edits to the wiki.';
			$wgOut->addWikiText($perm_msg);
			return false;
		} elseif (!$this->getUser()->mEmailAuthenticated) {
			$perm_msg = '=== Read-only mode: Your e-mail address is not authenticated. ===
             __NOEDITSECTION__
	     For security reasons you need to fulfill the following requirements in order to submit a vote:
* You need to log in.
* You need to authenticate your e-mail address. Please edit/add your e-mail address using [[Special:Preferences]] and a confirmation e-mail will be sent to that address. Follow the instructions in the e-mail to confirm that the account is actually yours.
* You need to make at least 8 edits to the wiki.';
			$wgOut->addWikiText($perm_msg);
			return false;
		} elseif ($this->getUser()->getEditCount() < 8) {
			$perm_msg = '=== Read-only mode: You made only ' . $this->getUser()->edits($this->getUser()->getID()) . ' edits so far. ===
             __NOEDITSECTION__
	     For security reasons you need to fulfill the following requirements in order to submit a vote:
* You need to log in.
* You need to authenticate your e-mail address.
* You need to make at least 8 contributions to the wiki. A contribution is any edit to any page. A good way to get your first few contributions is adding some information about yourself to [[Special:Mypage|your userpage]].';
			$wgOut->addWikiText($perm_msg);
			return false;
		}
		return true;
	}

	/**
	 * Format a link for use elsewhere
	 * @param  Article $article reference to article
	 * @param  string $name link title
	 * @param  string $action sets the ?rating= request variable.
	 * @param  string $id the build id. sets ?build=
	 * @return string
	 */
	public function rateLink(&$article, $name, $action, $id) {
		global $wgUser;
		return '<div class="aedit">[&nbsp;' . Linker::link($article->getTitle(), $name, [], ['action'=>'rate','rating'=>$action,'build'=>$id]) . '&nbsp;]&nbsp;</div>';
	}

	/**
	 * Builds output for a specific rating
	 * @param  array  $rate_results array containing the rating records
	 * @param  string $link links (edit, delete) to include
	 * @return string
	 */
	public function ratePrint(array $rate_results, $link) {
		global $wgUser, $wgParser, $wgOut, $wgExtensionsPath;
		$dbr = wfGetDB(DB_SLAVE);

		$user_name = User::newFromId($rate_results['user_id'])->getName();
		# check for BM status
		$res       = $dbr->query("SELECT * FROM user_groups WHERE ug_user =" . $rate_results['user_id'] . " AND ug_group='buildmaster'");
		$is_bm     = $dbr->numRows($res);

		$parserOptions = ParserOptions::newFromUser($wgUser);
		$parserOptions->setEditSection(false);
		$parserOptions->setTidy(true);
		$wgParser->mShowToc = false;

        //$parserOptions = new Title;

		$number_max = 5;
		$number_cat = 3;
		$tables_max = 168;

		$cate      = array(
			1 => .8,
			.2,
			.0
		);
		$rate      = array(
			1 => $rate_results['rating'][0],
			2 => $rate_results['rating'][1],
			3 => $rate_results['rating'][2]
		);
		$cur_score = ($rate[1] * $cate[1] + $rate[2] * $cate[2] + $rate[3] * $cate[3]);
		$sze_table = array(
			1 => (($rate[1] / $number_max) * $tables_max),
			2 => (($rate[2] / $number_max) * $tables_max),
			3 => (($rate[3] / $number_max) * $tables_max)
		);
		$max_score = (($number_max / 100) * $cate[1]) + (($number_max / 100) * $cate[2]) + (($number_max / 100) * $cate[3]);
		#this is trivial!
		#$max_score = $number_max;
		#$final_scr = ( $cur_score / $max_score ) * 100;

		$final_tbl = ($cur_score / $number_max) * $tables_max;

        $parsedComment = $wgParser->parse($rate_results['comment'], $this->getTitle(), $parserOptions)->mText;

		if ($rate_results['rollback']) {
			$comment = '<b>Removed: </b><s>' . $parsedComment . '</s><br> <b>Reason: </b>' . $rate_results['reason'] . '<br><b>Removed by: </b> ' . User::newFromId($rate_results['admin_id'])->getName();
			$tduser  = $user_name;
		} else {
			$comment = $parsedComment;
			$tduser  = $user_name;
		}

		$timestamp = strtotime($rate_results['timestamp']);
		$timestr   = date('H:i, d M Y', $timestamp) . ' (EST)'; # GMT on test box, EST on main server

		$tduser = $wgParser->parse('[[User:' . $user_name . '|' . $user_name . ']]', $this->getTitle(), $parserOptions)->mText;
		if ($is_bm) {
			$bm_str = '(Build Master)';
		} else {
			$bm_str = '';
		}
		if ($rate[3] > 0) {
			$inno_out = 'X';
		} else {
			$inno_out = 'O';
		}
		$out = '
		<div class="rating"><table border="1" cellpadding="0" cellspacing="3">
		<tr><td class="tdrating"><div style="width:' . $final_tbl . 'px; background-image:url(' . $wgExtensionsPath . 'extensions/PvXRate/images/r1.jpg);"><span>Overall</span></div></td>
		    <td class="tdresult">' . sprintf('%3.1f', round($cur_score * 10) / 10) . '</td>
		    <td class="tdcomment" rowspan="4">
			<table border="0" style="border:0px;">
			<tr><td class="tduser">' . $tduser . '</td><td class="tduser">' . $bm_str . '</td>
                                   <td class="tdedit"><div align="right"> Last edit: ' . $timestr . '&nbsp;</div>' . $link . '</td>
			</tr><tr>
			    <td colspan="3">' . $comment . '</td>
			</tr>
		        </table>
                            </td>
		 </tr><tr>
                            <td class="tdrating"><div style="width:' . $sze_table[1] . 'px; background-image:url(' . $wgExtensionsPath . 'extensions/PvXRate/images/r2.jpg);"><span>Effectiveness</span></div></td>
		     <td class="tdresult">' . $rate[1] . '</td>
		 </tr><tr>
		     <td class="tdrating"><div style="width:' . $sze_table[2] . 'px; background-image:url(' . $wgExtensionsPath . 'extensions/PvXRate/images/r3.jpg);"><span>Universality</span></div></td>
		     <td class="tdresult">' . $rate[2] . '</td>
		 </tr><tr>
		     <td class="tdrating"><div style="width:' . $sze_table[3] . 'px; background-image:url(' . $wgExtensionsPath . 'extensions/PvXRate/images/r4.jpg);"><span>Innovation</span></div></td>
		     <td class="tdresult">' . $inno_out . '</td>
		 </tr>
		 </table>
		 </div><br>';

		return $out;
	}

    /**
     * print ratings for a specific build
     * @param  Article $article reference to Article
     * @param  boolean $show_own Show users own rating or not
     * @param  boolean $show_form Show rating form
     * @param  boolean $show_all Show all current ratings
     * @param  boolean $read_only if true, just call ratePrint
     * @return void, prints to screen
     */
	public function ratePrintAll($article, $show_own, $show_form, $show_all, $read_only) {
		global $wgOut, $wgUser;

		$current = $this->rateRead($article->getID(), false, false);

		if ($current) {
			// Getting the info from database.
			foreach ($current as $array) {
				if ($read_only) {
					$out_all .= $this->ratePrint($array, '');
				} else {
					if ($array['rollback']) {
						if ($array['user_id'] == $this->getUser()->getID()) {
							$link = $this->rateLink($article, 'delete', 'delete', $array['rate_id']);
							$link .= $this->rateLink($article, 'edit', 'edit', $array['rate_id']);
							$out_rmv   = $this->ratePrint($array, $link);
							$show_form = false;
						} elseif ($this->getUser()->isAllowed('vote_rollback')) {
							$link = $this->rateLink($article, 'restore', 'restore', $array['rate_id']);
							$out_rmv .= $this->ratePrint($array, $link);
						} else {
							$out_rmv .= $this->ratePrint($array, '');
						}
					} else {
						if ($array['user_id'] == $this->getUser()->getID()) {
							$link = $this->rateLink($article, 'delete', 'delete', $array['rate_id']);
							$link .= $this->rateLink($article, 'edit', 'edit', $array['rate_id']);
							$out_own   = $this->ratePrint($array, $link);
							$show_form = false;
						} elseif ($this->getUser()->isAllowed('vote_rollback')) {
							$link = $this->rateLink($article, 'remove', 'rollback', $array['rate_id']);
							$out_all .= $this->ratePrint($array, $link);
						} else {
							$out_all .= $this->ratePrint($array, '');
						}
					}
				}
			}

			# $out .=  ('<h2> Rating Totals </h2>'); # included in ratePrintResults now
			$out .= $this->ratePrintResults($article->getID());

			if (($out_own) && ($show_own)) {
				$out .= ('<h2> Your Rating </h2>');
				$out .= ($out_own);
			}

			if (($out_all) && ($show_all)) {
				$out .= ('<h2> Current Ratings </h2>');
				$out .= ($out_all);
			}

			if ($out_rmv) {
				$out .= ('<h2> Removed Ratings </h2>');
				$out .= $out_rmv;
			}

		}

		if (($show_form)) {
			$wgOut->addHtml('<h2> Rate this build </h2>');
			$wgOut->addHtml($this->rateForm(array(
				false
			)));
		}

		$wgOut->addHtml($out);
	}

	/**
	 * Gets retults and returns them for a specific page. Used inside ratePrintAll
	 * @param  int $page_id
	 * @return string
	 */
	public function ratePrintResults($page_id) {

		# names of criteria
		$rate_names = array(
			1 => 'Effectiveness',
			2 => 'Universality',
			3 => 'Innovation'
		);

		# weighting of criteria
		$cate = array(
			1 => .8,
			.2,
			.0
		);

		# build master's additional weighting (set e.g. to 1.5 to get 250% total weight)
		$bm_weight = 1.0; # => 200%

		$dbr = wfGetDB(DB_SLAVE);

		# determine overall rating (equal weighting of all voters, not counting rolled back votes)
		$res = $dbr->query("SELECT count(rating1) AS count, sum( rating1 ) AS r1, sum( rating2 ) AS r2, sum( rating3 ) AS r3
                        FROM rating WHERE rollback != 1 AND page_id = " . $page_id);
		$row = $dbr->fetchObject($res);

		# put into local variables
		$count = $row->count;
		$r1    = $row->r1;
		$r2    = $row->r2;
		$r3    = $row->r3;

		# ask for ratings again, this time only build masters
		$res = $dbr->query("SELECT count(rating1) AS count, sum( rating1 ) AS r1, sum( rating2 ) AS r2, sum( rating3 ) AS r3
                        FROM rating,user_groups WHERE rollback != 1 AND page_id = " . $page_id . "
                        AND user_id=ug_user AND ug_group='buildmaster'");
		$row = $dbr->fetchObject($res);

		# add to the local variables
		$bm_count = $row->count;
		$wcount   = $count + $bm_weight * $row->count;
		$r1 += $bm_weight * $row->r1;
		$r2 += $bm_weight * $row->r2;
		$r3 += $bm_weight * $row->r3;

		# overall rating
		if ($count) {
			$final = array(
				0 => ($r1 * $cate[1] + $r2 * $cate[2] + $r3 * $cate[3]) / $wcount,
				$r1 / $wcount,
				$r2 / $wcount,
				$r3 / $wcount
			);
		} else {
			$final = array(
				0 => 0,
				0,
				0,
				0
			);
		}

		# fill histogram (NOT SCALED FOR BM ATM!!)
		# $rating[x][y] is number of 'y' ratings on criterion 'x'
		$rating = array();
		for ($y = 1; $y <= 3; $y++) { # y=1..3 counts criteria
			for ($i = 0; $i <= 5; $i++) { # i=0..5 counts rating
				$rating[$y][$i] = $dbr->fetchObject($dbr->query("SELECT count(rating" . $y . ") AS count FROM rating
                                     WHERE rating" . $y . " = " . $i . " AND rollback != 1 AND page_id = " . $page_id))->count;
			}
		}

		# overall rating output
		global $wgExtensionsPath;

		if ($bm_count) {
			$out = ('<h2> Rating totals: ' . $count . ' votes, incl. ' . $bm_count . ' from Build Masters (weighted ' . (($bm_weight + 1) * 100) . '%)</h2>');
		} else {
			$out = ('<h2> Rating totals: ' . $count . ' votes</h2>');
		}
		$out .= '<table border="0" cellpadding="0" cellspacing="0"><tr>';
		$out .= '<td><div class="sum"><table border="0" cellpadding="0" cellspacing="3">
            <tr><td class="tdrating"><div style="width:' . round($final[0] * 168 / 5) . 'px;
                    background-image:url(' . $wgExtensionsPath . 'extensions/PvXRate/images/r1.jpg);"><span>Overall</span></div></td>
     <td class="tdresult">' . sprintf('%4.2f', round($final[0] * 100) / 100) . '</td></tr>
     <tr><td class="tdrating"><div style="width:' . round($final[1] * 168 / 5) . 'px;
                    background-image:url(' . $wgExtensionsPath . 'extensions/PvXRate/images/r2.jpg);"><span>Effectiveness</span></div></td>
     <td class="tdresult">' . sprintf('%4.2f', round($final[1] * 100) / 100) . '</td></tr>
            <tr><td class="tdrating"><div style="width:' . round($final[2] * 168 / 5) . 'px;
                    background-image:url(' . $wgExtensionsPath . 'extensions/PvXRate/images/r3.jpg);"><span>Universality</span></div></td>
            <td class="tdresult">' . sprintf('%4.2f', round($final[2] * 100) / 100) . '</td></tr>
            <tr><td class="tdrating"><div style="width:' . round($final[3] * 168 / 5) . 'px;
                    background-image:url(' . $wgExtensionsPath . 'extensions/PvXRate/images/r4.jpg);"><span>Innovation</span></div></td>
     <td class="tdresult">' . sprintf('%4.0f', round($final[3] * 20)) . '%</td></tr>
     </table></div></td>';

		# histograms
		# $rating[c][q] is number of 'q' ratings on criterion 'c'
		for ($c = 1; $c <= 2; $c++) {

			# normalize histo
			for ($q = 0; $q <= 5; $q++) {
				if ($count) {
					$histo[$c][$q] = round($rating[$c][$q] / $count * 77);
				} else {
					$histo[$c][$q] = 0;
				}
			}

			# plot
			$out .= '<td><div class="result"><table border="1" cellpadding="0" cellspacing="3"><tr>
                    <td colspan="6" class="tdresult"><span onmouseover="return overlib(div(\'load' . $rate_names[$c] . '\').innerHTML, WRAP, CENTER, WIDTH, 300, OFFSETY, -25, OFFSETX, -247, VAUTO);" onmouseout="return nd();">' . $rate_names[$c] . '</span></td></tr>
                    <tr><td class="tdrating"><div style="height:' . ($histo[$c][0]) . 'px; width:13px; background-image:url(' . $wgExtensionsPath . 'extensions/PvXRate/images/v' . ($c + 1) . '.jpg);"></div></td>
             		 <td class="tdrating"><div style="height:' . ($histo[$c][1]) . 'px; width:13px; background-image:url(' . $wgExtensionsPath . 'extensions/PvXRate/images/v' . ($c + 1) . '.jpg);"></div></td>
             		 <td class="tdrating"><div style="height:' . ($histo[$c][2]) . 'px; width:13px; background-image:url(' . $wgExtensionsPath . 'extensions/PvXRate/images/v' . ($c + 1) . '.jpg);"></div></td>
             		 <td class="tdrating"><div style="height:' . ($histo[$c][3]) . 'px; width:13px; background-image:url(' . $wgExtensionsPath . 'extensions/PvXRate/images/v' . ($c + 1) . '.jpg);"></div></td>
             		 <td class="tdrating"><div style="height:' . ($histo[$c][4]) . 'px; width:13px; background-image:url(' . $wgExtensionsPath . 'extensions/PvXRate/images/v' . ($c + 1) . '.jpg);"></div></td>
                	 <td class="tdrating"><div style="height:' . ($histo[$c][5]) . 'px; width:13px; background-image:url(' . $wgExtensionsPath . 'extensions/PvXRate/images/v' . ($c + 1) . '.jpg);"></div></td>
	     </tr><tr>
		 <td class="tdresult">0</td>
		 <td class="tdresult">1</td>
		 <td class="tdresult">2</td>
		 <td class="tdresult">3</td>
		 <td class="tdresult">4</td>
		 <td class="tdresult">5</td>
	     </tr></table></div></td><td>';
		}

		$out .= '</tr></table><br>';
		return $out;

	}

    /**
     * Check Users rights
     * @param  Article $article
     * @param  int $build_id
     * @return boolean
     */
	public function rateCheckRights($article, $build_id) {
		global $wgUser;

		$rating_posted = $this->rateRead(false, false, $build_id);
		if (($rating_posted[0]['page_id']) && ($rating_posted[0]['user_id'])) {
			$rating_control = $this->rateRead($rating_posted[0]['page_id'], $rating_posted[0]['user_id'], false);
		}


		if (($rating_control[0]['page_id'] == $article->getID()) && ($rating_control[0]['user_id'] == $this->getUser()->getID())) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Generates the rate form
	 * @param  array  $rate_value Values to populate form with
	 * @return string
	 */
	public function rateForm(array $rate_value) {
		global $wgUser;
		$rate_names = array(
			1 => 'Effectiveness',
			2 => 'Universality',
			3 => 'Innovation'
		);

		$rate_descr = array(
			1 => 'How great is the idea behind this build and how innovative is it?',
			2 => 'Does this build have potential to do more than what it was designed for?',
			3 => 'How powerful is this build, how well does it what it was designed for and how user friendly is it?'
		);

		// ---------- Loading form with values.

		if ($rate_value[0]) {
			$input   = $rate_value[0]['rating'];
			$comment = $rate_value[0]['comment'];
			$submit  = 'Save';
			$update  = $rate_value[0]['rate_id'];
			$action  = '&rating=update';
		} else {
			$input   = false;
			$comment = '';
			$submit  = 'Rate';
			$update  = 0;
			$action  = '';
		}

		// ---------- HEAD
		$out = ('<div class="ratingform"><form method="post" action="' . $this->getTitle()->getFullURL('action=rate' . $action) . '"><table class="rating_table">');

		// ----------- Printing form.
		foreach ($rate_names as $key => $value) {
			$out .= ('<tr><td><span class="rating_cat" onmouseover="return overlib(div(\'load' . $rate_names[$key] . '\').innerHTML, WRAP, CENTER, WIDTH, 300, OFFSETY, -25,  VAUTO);"' . 'onmouseout="return nd();">' . $rate_names[$key] . '</span></td><td>');
			if ($value == 'Innovation') {
				if (($input) && ($input[$key - 1])) {
					$checked = ' checked ';
				} else {
					$checked = '';
				}
				$out .= ('<div class="rating_input">' . '<input name="p' . $key . '" type="checkbox" value="5"' . $checked . '>' . '</div>');
				$out .= ('</td></tr>');
			} else {
				for ($i = 0; $i <= 5; $i++) {
					if (($input) && ($input[$key - 1] == $i)) {
						$checked = ' checked ';
					} else {
						$checked = '';
					}
					$out .= ('<div class="rating_input"' . ' onMouseOver="swapColor(\'p' . $key . $i . '\',\'#f' . (8 - ($i + 3)) . 'f' . (8 - ($i + 3)) . 'f' . (8 - ($i + 3)) . '\');"' . ' onMouseOut="swapColor(\'p' . $key . $i . '\',\'#ffffff\');" id="p' . $key . $i . '">' . '<input name="p' . $key . '" type="radio" value="' . $i . '"' . $checked . '>' . '<span>' . $i . '</span></div>');
				}
			}
			$out .= ('</td></tr>');
		}

		$out .= ('<tr valign="top"><td><span class="rating_cat">Comments</span></td>' . '<td><textarea class="rating_text" name="comment" cols="10" rows="5">' . $comment . '</textarea>');
		$out .= ('<input name="ratingid" type="hidden" value="' . $update . '" />');
		$out .= ('</table><input class="button" type="submit" value="' . $submit . '" /></form></div><br>');
		return $out;
	}

    /**
     * Generate rating rollback form
     * @param  array  $rate_value values to populate form with
     * @return string
     */
	public function rateRollback(array $rate_value) {

		$submit = 'Remove';
		$update = $rate_value[0]['rate_id'];
		$action = '&rating=rollback';

		$out = ('<div class="ratingform"><form method="post" action="' . $this->getTitle()->getFullURL('action=rate' . $action) . '"><table class="rating_table">');
		$out .= ('<tr valign="top"><td><span class="rating_cat">Reason</span></td><td><textarea class="rating_text" name="reason" cols="10" rows="5">' . $comment . '</textarea>');
		$out .= ('<input name="rollback" type="hidden" value=1 /><input name="ratingid" type="hidden" value="' . $update . '" />');
		$out .= ('</table><input class="button" type="submit" value="' . $submit . '" /></form></div><br>');
		return $out;
	}

    /**
     * Generate rating restore form
     * @param  array  $rate_value values to populate form with
     * @return string
     */
	public function rateRestore(array $rate_value) {

		$submit = 'Restore';
		$update = $rate_value[0]['rate_id'];
		$action = '&rating=restore';

		$out = ('<div class="ratingform"><form method="post" action="' . $this->getTitle()->getFullURL('action=rate' . $action) . '"><table class="rating_table">');
		$out .= ('<tr valign="top"><td><span class="rating_cat">Reason</span></td><td><textarea class="rating_text" name="reason" cols="10" rows="5">' . $comment . '</textarea>');
		$out .= ('<input name="restore" type="hidden" value=1 /><input name="ratingid" type="hidden" value="' . $update . '" />');
		$out .= ('</table><input class="button" type="submit" value="' . $submit . '" /></form></div><br>');
		return $out;
	}

	/**
	 * Get rating data array, can be used with other functions.
	 * @param  Article $article
	 * @return array
	 */
	public function rateGet(Article &$article) {
		global $wgUser, $wgRequest;
		$rate_input = array(
			'page_id' => $article->getID(),
			'user_id' => $this->getUser()->getID(),
			'comment' => $wgRequest->getText('comment'),
			'rollback' => $wgRequest->getText('rollback'),
			'restore' => $wgRequest->getText('restore'),
			'reason' => $wgRequest->getText('reason'),
			'rating' => array(
				$wgRequest->getText('p1'),
				$wgRequest->getText('p2'),
				$wgRequest->getText('p3')
			),
			'rate_id' => $wgRequest->getText('ratingid'),
			'error' => false,
			'error_msg' => ''
		);

		if ((($rate_input['rollback'] == 1) || ($rate_input['restore'] == 1)) && (strlen($rate_input['reason']) > 0)) {
			if ($this->getUser()->isAllowed('vote_rollback')) {
				$rate_input['admin_id'] = $this->getUser()->getID();
			} else {
				$rate_input['error']     = true;
				$rate_input['error_msg'] = 'You are not admin.';
			}
			return $rate_input;
			;
		} else {
			# If innovation was not sent, set it to zero
			if (!is_numeric($rate_input['rating'][2])) {
				$rate_input['rating'][2] = 0;
			}
			# Check if what we got are numeric, > then 0 and < then 6.
			if ((!is_numeric($rate_input['rating'][0])) || ($rate_input['rating'][0] > 5) || ($rate_input['rating'][0] < 0) || (!is_numeric($rate_input['rating'][1])) || ($rate_input['rating'][1] > 5) || ($rate_input['rating'][1] < 0)) {
				$rate_input['error']     = true;
				$rate_input['error_msg'] = 'Please try again.';
			} elseif (strlen($rate_input['comment']) < 12) {
				$rate_input['error']     = true;
				$rate_input['error_msg'] = 'Comment is too short..';
			}
			return $rate_input;
		}
	}

	/**
	 * Update rating in database.
	 * @param  array  $input
	 * @return void
	 */
	public function rateUpdate(array $input) {
		$dbw = wfGetDB(DB_MASTER);
		$dbw->begin();
		if ($input['rollback'] || $input['restore']) {
			$dbw->update('rating', array(
				'rollback' => $input['rollback'],
				'reason' => $input['reason'],
				'admin_id' => $input['admin_id']
			), array(
				'rate_id' => $input['rate_id']
			));
		} else {
			$dbw->update('rating', array(
				'comment' => $input['comment'],
				'rating1' => $input['rating'][0],
				'rating2' => $input['rating'][1],
				'rating3' => $input['rating'][2],
				'rollback' => $input['rollback'],
				'reason' => $input['reason'],
				'admin_id' => $input['admin_id']
			), array(
				'rate_id' => $input['rate_id']
			));
		}
		$dbw->commit();
		return;
	}

    /**
     * delete rating from database
     * @param  int $rate_id
     * @param  int $page_id
     * @param  int $user_id
     * @return true
     */
	public function rateDelete($rate_id, $page_id, $user_id) {
		//echo $rate_id . $page_id . $user_id;
		$dbw = wfGetDB(DB_MASTER);
		$dbw->begin();
		$dbw->delete('rating', array(
			'rate_id' => $rate_id,
			'page_id' => $page_id,
			'user_id' => $user_id
		));
		$dbw->commit();
		return true;
	}

	/**
	 * Save rating to database
	 * @param  array  $input
	 * @return true
	 */
	public function rateSave(array $input) {
		$dbw = wfGetDB(DB_MASTER);
		$dbw->begin();
		$dbw->insert('rating', array(
			'page_id' => $input['page_id'],
			'user_id' => $input['user_id'],
			'comment' => $input['comment'],
			'rollback' => $input['rollback'],
			'reason' => $input['reason'],
			'rating1' => $input['rating'][0],
			'rating2' => $input['rating'][1],
			'rating3' => $input['rating'][2]
		), __method__);
		$dbw->commit();
		return true;
	}

	/**
	 * Read ratings from db
	 * @param  int $page_id
	 * @param  int $user_id
	 * @param  int $rate_id
	 * @return array|false
	 */
	public function rateRead($page_id, $user_id, $rate_id) {

		$dbr = wfGetDB(DB_SLAVE);

		if ((is_numeric($rate_id)) && ($rate_id > 1)) {
			$res = $dbr->query("SELECT * FROM `rating` WHERE `rate_id` =" . $rate_id . " LIMIT 0, 1");
		} else {
			if ($user_id) {
				$res = $dbr->query("SELECT * FROM `rating` WHERE `page_id` =" . $page_id . " AND `user_id` = " . $user_id . " LIMIT 0, 1");
			} else {
				$res = $dbr->query("SELECT * FROM `rating` WHERE `page_id` =" . $page_id . " LIMIT 0, 1000");
			}
		}

		$count = $dbr->numRows($res);
		if ($count > 0) {
			# Make list
			$i = 0;
			while ($row = $dbr->fetchObject($res)) {
				$rate_out[$i] = array(
					'page_id' => $row->page_id,
					'user_id' => $row->user_id,
					'comment' => $row->comment,
					'rollback' => $row->rollback,
					'admin_id' => $row->admin_id,
					'reason' => $row->reason,
					'rating' => array(
						$row->rating1,
						$row->rating2,
						$row->rating3
					),
					'rate_id' => $row->rate_id,
					'timestamp' => $row->timestamp
				);
				$i++;
			}
		} else {
			return false;
		}
		return $rate_out;
	}

    /**
     * Returns some discriptive html.
     * @return string
     */
	public function rateOverLib() {
		$out .= '<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>';
		$out .= '
		<div id="loadEffectiveness" style="display: none;">
		    <div style="border:1px; border-color:#CCCCCC; border-collapse:collapse; border-style:solid; width:300px; background-color:#FFFFFF; padding:5px;">
		        <div style="width:300px; font-size:12px; font-family:Arial, Helvetica, sans-serif; font-weight:bold;">Effectiveness</div>
		        <div style="width:300px; font-size:11px; font-family:Arial, Helvetica, sans-serif;">This criterion describes how effective the build does what it was designed for. That is, how much damage does a spiker build deal, a healer build heal or a protector build prevent? How good is the chance to get through the specified area with a running build or to reach and defeat the specified foes with a farming build?</div>
		    </div>
		</div>

		<div id="loadUniversality" style="display: none;">
		    <div style="border:1px; border-color:#CCCCCC; border-collapse:collapse; border-style:solid; width:300px; background-color:#FFFFFF; padding:5px;">
		        <div style="width:300px; font-size:12px; font-family:Arial, Helvetica, sans-serif; font-weight:bold;">Universality</div>
		        <div style="width:300px; font-size:11px; font-family:Arial, Helvetica, sans-serif;">This criterion describes how flexible the build is when used in a situation slightly different from what the build was designed for. This includes the ability to change strategy in case a foe shows unexpected actions, in case an ally does not perform as expected, or when used in a different location than originally intended.</div>
		    </div>
		</div>

		<div id="loadInnovation" style="display: none;">
		    <div style="border:1px; border-color:#CCCCCC; border-collapse:collapse; border-style:solid; width:300px; background-color:#FFFFFF; padding:5px;">
		        <div style="width:300px; font-size:12px; font-family:Arial, Helvetica, sans-serif; font-weight:bold;">Innovation</div>
		        <div style="width:300px; font-size:11px; font-family:Arial, Helvetica, sans-serif;">This criterion describes how new the idea behind this build is. Does it use a new approach for dealing with a known task or even act as a precursor for dealing with a previously unconsidered task? To what extend is it expected to become a prototype for a new class of builds?</div>
		    </div>
		</div>
		';
		return $out;
	}


}

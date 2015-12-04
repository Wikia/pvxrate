<?php
/**
 * Curse Inc.
 * PvX Rate
 * Adds tab to Rate articles, List user ratings, and list recent ratings.
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2015 Curse Inc.
 * @license		All Rights Reserved
 * @package		PvXRate
 * @link		http://www.curse.com/
 *
**/

class SpecialRecentRatings extends SpecialPage {
	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		global $wgRequest, $wgUser, $wgOut;
		parent::__construct(
			'RecentRatings', // name
			null, //'pvxrate', // required user right
			true // display on Special:Specialpages
		);
		$this->wgRequest	= $wgRequest;
		$this->wgUser		= $wgUser;
		$this->output		= $this->getOutput();

		$this->DB = wfGetDB(DB_MASTER);
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @param	string	Sub page passed in the URL.
	 * @return	void	[Outputs to screen]
	 */
	 public function execute($par = null) {
 		global $wgLang;

		$this->output->addModules('ext.pvxrate');
 		$this->output->setPageTitle( wfMessage('recentratings') );


 		$timeprevious = '';

 		foreach (self::GetRatings() as $array) {
 			if ($array['page_title']) {

 				date_default_timezone_set("UTC");

 				$timecorrection = $this->wgUser->getOption('timecorrection');
 				$timecurent     = $wgLang->date(wfTimestamp(TS_MW, $array['timestamp']), true, false, $timecorrection);

 				$out = '* ';

 				if ($timeprevious != $timecurent) {
 					$tc = '===' . $timecurent . '===';
 					$this->output->addWikiText($tc);
 				}
 				$timeprevious = $timecurent;
 				$time         = $wgLang->time(wfTimestamp(TS_MW, $array['timestamp']), true, false, $timecorrection);

 				if ($array['user_name']) {
 					$user_link = '[[User:' . $array['user_name'] . '|' . $array['user_name'] . ']]' . ' ' . '(' . '[[User_talk:' . $array['user_name'] . '|Talk]]' . ' | ' . '[[Special:Contributions/' . $array['user_name'] . '|contribs]])';
 				} else {
 					$user_link = '';
 				}

 				$page_link = '[[Build:' . $array['page_title'] . '|' . $array['page_title'] . ']] - [[Build_talk:' . $array['page_title'] . '|talk]] - [http://www.gwpvx.com/index.php?title=Build:' . str_replace(" ", "_", $array['page_title']) . '&action=rate Rate]';

 				if ($array['admin_id']) {
 					$admin_name = User::newFromId($array['admin_id'])->getName();
 					$admin_link = '[[User:' . $admin_name . '|' . $admin_name . ']]';
 				}

 				$out .= $time;
 				$out .= ' . . ';
 				if ($array['rollback'])
 					$out .= '<font color="red"><b>Rollback</b></font> ';
 				if (!$array['rollback'] && $array['reason'])
 					$out .= '<font color="green"><b>Restore</b></font> ';
 				$out .= $page_link;
 				$out .= '; ';

 				$total = $array['rating'][0] * .8 + $array['rating'][1] * .2 + $array['rating'][2] * .0;
 				if ($total < 3.75)
 					$rating = 'Rating: \'\'\'' . $total . '\'\'\' (\'\'trash\'\')';
 				elseif ($total < 4.75)
 					$rating = 'Rating: \'\'\'' . $total . '\'\'\' (\'\'good\'\')';
 				elseif ($total >= 4.75)
 					$rating = 'Rating: \'\'\'' . $total . '\'\'\' (\'\'great\'\')';

 				if ($array['rollback']) {
 					#<font color="red">
 					$out .= '\'\'\'' . $admin_link . '\'\'\'' . ' removed ' . strtolower($rating) . ' posted by: ' . $user_link;
 				} elseif (!$array['rollback'] && $array['reason']) {
 					$out .= '\'\'\'' . $admin_link . '\'\'\'' . ' restored ' . strtolower($rating) . ' posted by: ' . $user_link;
 				} else {
 					$out .= $rating;
 					$out .= ' . . ';
 					$out .= ' E:' . $array['rating'][0];
 					$out .= ' U:' . $array['rating'][1];
 					$out .= ' I:' . $array['rating'][2];
 					$out .= ' . . ';
 					$out .= $user_link;
 				}

 				$this->output->addWikiText($out);
 			}
 		}
 	}

	/**
	 * Get Ratings from database
	 */
 	public function GetRatings() {
		global $wgPvXRateBuildNamespace;

		$buildNamespace = defined($wgPvXRateBuildNamespace);
        if (!$buildNamespace) {
            wfWarn('The PvXRateBuildNamespace defined in PvX Rate\'s extension.json file ('.$wgPvXRateBuildNamespace.') is not a valid namespace.',2);
        } else {
			$buildNamespace = constant($wgPvXRateBuildNamespace);
		}

		$res = $this->DB->select(
			['rating', 'user', 'page'],
			['user_name', 'rating.user_id', 'page_title', 'comment', 'rollback', 'admin_id', 'reason', 'rating1', 'rating2', 'rating3', 'timestamp'],
			[
				'page.page_namespace' => $buildNamespace
			],
			__METHOD__,
			[
					"ORDER BY"=> "rating.timestamp DESC",
					"LIMIT" => '200'
			],
			[
				'user' => array('LEFT JOIN', array('rating.user_id=user.user_id')),
				'page' => array('LEFT JOIN', array('rating.page_id=page.page_id'))
			]
		);

 		$out = array();
 		while ($row = $this->DB->fetchObject($res)) {
 			$out[] = array(
 				'user_name' => $row->user_name,
 				'comment' => $row->comment,
 				'page_title' => str_replace('_', ' ', $row->page_title),
 				'rollback' => $row->rollback,
 				'admin_id' => $row->admin_id,
 				'reason' => $row->reason,
 				'rating' => array(
 					$row->rating1,
 					$row->rating2,
 					$row->rating3
 				),
 				'timestamp' => $row->timestamp
 			);
 		}

 		return $out;
 	}

	/**
	 * Return the group name for this special page.
	 *
	 * @access protected
	 * @return string
	 */
	protected function getGroupName() {
		return 'pvx'; //Change to display in a different category on Special:SpecialPages.
	}
}

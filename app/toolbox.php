<?php

//! Admin back-end processor
class ToolBox extends Controller {

	function show() {
		$f3=$this->framework;
		if ($f3->get('SESSION.auth') == 'loggedin') {
			$f3->set('inc','index.htm');
		}
		else { 
			$f3->set('navMenu','login');
			$f3->set('inc','login.htm');
		}
	}

	function login() {
		$f3=$this->framework;
		if ($f3->get('POST.user') == $f3->get('user_id') && $f3->get('POST.password') == $f3->get('password')) {
			$f3->set('SESSION.auth','loggedin');
			$f3->set('inc','index.htm');
		} else {
			$f3->set('navMenu','login');
			$f3->set('inc','login.htm');
		}
	}

	function showFeedList() {
		$f3=$this->framework;
		$f3->set('inc','feeds.htm');
	}

	function generateFeed() {
		$f3 = $this->framework;
		$db = $this->db;
		$feedURLs = array();
		foreach($f3->get('POST.feedUrl') as $feedUrl) {
			if (!empty($feedUrl))
				array_push($feedURLs, $feedUrl);
		}

		
		$feed = new DB\SQL\MAPPER($db,'feeds');
		$feed->copyFrom('POST');
		$feed->save();
		$result = $db->exec('SELECT last_insert_rowid();');
		$feed_id = $result[0]['last_insert_rowid()'];

		foreach ($feedURLs as $key => $value) {
			$feedurl = new DB\SQL\MAPPER($db,'feedURLs');
			$feedurl->feed_id = $feed_id;
			$feedurl->feed_url = $value;
			$feedurl->save();
		}
		$f3->set('inc','feeds.htm');

	}


	function listFeeds() {
		$f3 = $this->framework;
		$db = $this->db;
		$jTableResult = array();
		$results = $db->exec("SELECT a.*, group_concat(b.feed_url,' ') as urls FROM feeds as a JOIN feedUrls as b ON a.id = b.feed_id GROUP BY a.id;");
		$jTableResult['aaData'] = $results;
		print json_encode($jTableResult);
		return false;

	}


	function showEditFeed() {
		$f3 = $this->framework;
		$db = $this->db;
		$feed_id = $f3->get('PARAMS.id');
		$feedURLs = array();
		$f3->set('feed', new DB\SQL\MAPPER($db,'feeds'));
		$f3->get('feed')->load(array('id=?',$feed_id));
		$f3->get('feed')->copyTo('POST');

		$f3->set('allowImagesChecked', $f3->get('POST.allowImages') == 1 ? 'checked' : '');
		$f3->set('resizeImagesChecked', $f3->get('POST.resizeImages') == 1 ? 'checked' : '');
		$f3->set('isFacebookChecked', $f3->get('POST.isFacebook') == 1 ? 'checked' : '');

		$f3->set('orientationBothSelected','');
		$f3->set('orientationPortraitSelected','');
		$f3->set('orientationLandscapeSelected','');

		switch($f3->get('POST.orientation')) {
			case 'BOTH':
				$f3->set('orientationBothSelected','selected');
				break;
			case 'LANDSCAPE':
				$f3->set('orientationLandscapeSelected','selected');
				break;
			case 'PORTRAIT':
				$f3->set('orientationPortraitSelected','selected');
				break;
		}

		$results = $db->exec('SELECT feed_url FROM feedURLs where feed_id = ' . $feed_id);
		foreach($results as $row) {
			array_push($feedURLs,$row['feed_url']);
		}
		$f3->set('POST.feedURLs', $feedURLs);
		$f3->set('inc','editFeed.htm');
	}

	function updateFeed() {
		$f3 = $this->framework;
		$db = $this->db;
		$feedURLs = array();

		foreach($f3->get('POST.feedUrl') as $feedUrl) {
			if (!empty($feedUrl))
				array_push($feedURLs, $feedUrl);
		}

		$feed_id = $f3->get('PARAMS.id');
		$feedURLs = array();
		$f3->set('feed', new DB\SQL\MAPPER($db,'feeds'));
		$f3->get('feed')->load(array('id=?',$feed_id));
		$f3->get('feed')->copyFrom('POST');
		$f3->get('feed')->update();
		error_log($db->log());

		$result = $db->exec('SELECT last_insert_rowid();');

		$feed_urls = new DB\SQL\MAPPER($db,'feedURLs');
		$feed_urls->load(array('feed_id=?',$feed_id));

		while($feed_urls->dry() == false){
			$feed_urls->erase();
			$feed_urls->next();
			error_log($db->log());

		}

		foreach ($feedURLs as $key => $value) {
			$feedurl = new DB\SQL\MAPPER($db,'feedURLs');
			$feedurl->feed_id = $feed_id;
			$feedurl->feed_url = $value;
			$feedurl->save();
			error_log($db->log());
		}

		$f3->set('inc','feeds.htm');
	}

	function showPollList() {
		$f3 = $this->framework;
		$f3->set('inc','polls.htm');
	}

	function generatePoll() {
		$f3 = $this->framework;
		$db = $this->db;
		$options = $f3->get('POST.pollOptions');
		error_log($options[0]);
		$feedURLs = array();

		$url = 'feedUrl='.implode(',',$feedURLs);
		
		$feed = new DB\SQL\MAPPER($db,'polls');
		$feed->copyFrom('POST');
		$feed->save();
		$result = $db->exec('SELECT last_insert_rowid();');
		$poll_id = $result[0]['last_insert_rowid()'];
		foreach ($options as $option) {
			$polloption = new DB\SQL\MAPPER($db,'pollOptions');
			$polloption->poll_id = $poll_id;
			$polloption->option_text = $option;
			$polloption->save();
		}
		$f3->set('inc','polls.htm');

	}

	function listPolls() {
		$f3 = $this->framework;
		$db = $this->db;
		$jTableResult = array();
		$results = $db->exec("SELECT a.*, group_concat(b.option_text,'::~::') as options, sum(b.votes) as total_votes FROM polls as a JOIN pollOptions as b ON a.id = b.poll_id GROUP BY a.id;");
		$jTableResult['aaData'] = $results;
		print json_encode($jTableResult);
		return false;
	}

	function pollResultsJSON() {
		$f3 = $this->framework;
		$db = $this->db;
		$poll = $f3->get('PARAMS.id');
		$results = $db->exec("SELECT a.*, b.question from pollOptions as a join polls as b on a.poll_id = b.id  where a.poll_id = " . $poll);
		print json_encode($results);
		return false;
	}
	//! Custom error page
	function error() {
		$f3=$this->framework;
		$log=new Log('error.log');
		$log->write($f3->get('ERROR.text'));
		foreach ($f3->get('ERROR.trace') as $frame)
			if (isset($frame['file'])) {
				// Parse each backtrace stack frame
				$line='';
				$addr=$f3->fixslashes($frame['file']).':'.$frame['line'];
				if (isset($frame['class']))
					$line.=$frame['class'].$frame['type'];
				if (isset($frame['function'])) {
					$line.=$frame['function'];
					if (!preg_match('/{.+}/',$frame['function'])) {
						$line.='(';
						if (isset($frame['args']) && $frame['args'])
							$line.=$f3->csv($frame['args']);
						$line.=')';
					}
				}
				// Write to custom log
				$log->write($addr.' '.$line);
			}
		$f3->set('inc','error.htm');
	}
}
<?php

//! Base controller
class Controller {

	protected
		$framework,
		$db;

	//! HTTP route pre-processor
	function beforeroute() {
		$f3=$this->framework;
		$db=$this->db;
		/*
		$f3->set('navMenu','default');
		if (!$f3->get('SESSION.auth') == "loggedin") {
			if ($f3->get('SESSION.forceLogin') == true) {
				$f3->reroute('/'); 
				$f3->set('SESSION.forceLogin',false);
			} else 
				$f3->set('SESSION.forceLogin',true);

		} else {
			$f3->set('SESSION.forceLogin',true);
		}
		*/
	}

	//! HTTP route post-processor
	function afterroute() {
		// Render HTML layout
		echo Template::instance()->render('layout.htm');
	}

	//! Instantiate class
	function __construct() {
		$f3=Base::instance();

		$db=new DB\SQL($f3->get('db'));
		// Use database-managed sessions
		new DB\SQL\Session($db);
		// Save frequently used variables
		$this->db=$db;
		$this->framework=$f3;
	}

}

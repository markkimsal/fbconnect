<?php

/**
 * Facebook connect
 */
class Cgn_Service_Fbconnect_Main extends Cgn_Service {

	public $usesConfig = TRUE;

	/**
	 * entry point for this application
	 */
	function mainEvent($req, &$t) {
		$this->presenter = 'self';

		Cgn::loadModLibrary('Fbconnect::Facebook');
		Cgn::loadModLibrary('Fbconnect::FacebookRestClient');

		$fbObj = $this->getFb($req);

//		$fbSessionKey    = $req->getSessionVar('fb_session_key');
		$fbUid     = $fbObj->user;
		$fbExpires = $fbObj->session_expires;

		$t['str1'] =  "<br/>";
		if ($fbExpires > 0) {
			$t['str2'] =  "Session still valid for " . sprintf("%d", ((float)$fbExpires - time())/60)." minutes<br/>\n";
		}
		$show_logo =true;
		$t['str3'] = ' 
			<fb:profile-pic uid="'.$fbUid.'" size="square" ' . ($show_logo ? ' facebook-logo="true"' : '') . '></fb:profile-pic>
			<fb:name uid="'.$fbUid.'" useyou="no"></fb:name>
			';


		try {
			$infos = $this->getFb($req)->api_client->users_getInfo($fbUid,
				array('username', 'name'));
//			var_dump($infos);
//			$emails = $this->getFacebookUserEmailHashes($fbUid);
		} catch (Exception $e) {
			echo "Exception ".$e->getMessage()."<br/>\n";
		}


		$uid = $req->getSessionVar('fb_uid');
//		$uid = $fbObj->get_loggedin_user();
		//var_dump($uid);
		/*
		if (!$fbObj->get_loggedin_user()) {
			$fbObj->require_login();
		}
		*/

		/*
		$fbObj->set_cookies($fbUid,
			$fbSessionKey,
			$fbExpires,
			null);

		 */
	}

	/**
	 * Clear fb_uid from session
	 */
	function logoutEvent($req, &$t) {

		Cgn::loadModLibrary('Fbconnect::Facebook');
		Cgn::loadModLibrary('Fbconnect::FacebookRestClient');
		$fbObj = $this->getFb($req);

		$req->clearSessionVar('fb_uid');
		$req->clearSessionVar('fb_expires');
		$req->clearSessionVar('fb_session_key');
		$req->clearSessionVar('fb_secret');
		$req->clearSessionVar('fb_sig');

		$this->presenter = 'redirect';
		if (strlen($_SERVER['HTTP_REFERER'])) 
			$next = urlencode($_SERVER['HTTP_REFERER']);
		else
			$next = urlencode(cgn_appurl('fbconnect'));

		//hit this URL to stop FB Javascript from re-detecting logged-in status
		//http://www.facebook.com/logout.php?app_key=XXX&session_key=XXX&extern=2&next=XXX&locale=en_US
		$t['url'] = 'http://www.facebook.com/logout.php?app_key='.$fbObj->api_key.
			'&session_key='.$fbObj->api_client->session_key.'&extern=2&next='.$next;
		//don't call this any earlier, it also un-sets session_key
		$fbObj->clear_cookie_state();
	}

	/**
	 * event handler
	 */
	function helpEvent($req, &$t) {

	}

	/**
	 * Show a "static" HTML page
	 * From FB wiki:
	 *   Since the channel page is static and does not need to change, 
	 *   we recommend that you configure your Web server to allow browsers 
	 *   to cache the page. Typically, you can set the cache to expire 
	 *   internally after 30 days. 
	 *   http://wiki.developers.facebook.com/index.php/Cross_Domain_Communication_Channel
	 */
	function xdreceiverEvent($req, &$t) {
		$this->presenter = 'self';
		$t['xdcomm'] = '<script src="http://static.ak.connect.facebook.com/js/api_lib/v0.4/XdCommReceiver.debug.js" type="text/javascript"></script>';

		Cgn::loadModLibrary('Fbconnect::Facebook');
		Cgn::loadModLibrary('Fbconnect::FacebookRestClient');
		$apisecret = $this->getConfig('api.secret');

		$session = json_decode($_GET['session']);
		$fb_params = $this->_getXdParams($session);
		$q = Facebook::generate_sig($fb_params, $apisecret);
		if ($session->sig && $q == $session->sig) {
			$fbObj        = $this->getFb($req);
			//HACK to fix Facebook's strange xdreceive handling of 
			//logins and cookies
			$fbObj->api_client->session_key = $session->session_key;
			$fbObj->set_cookies($session->uid,
				$session->session_key,
				$session->expires,
				$session->secret);
		} else {
			//sig does not match
			die('sig does not match');
		}

		$fbExpires    = $session->session_expires;
		$fbUid        = $session->uid;
		$fbSessionKey = $session->session_key;
		$fbSessionKey = $session->secret;

		$req->setSessionVar('fb_uid',          $fbUid);
		$req->setSessionVar('fb_expires',      $fbExpires);
		$req->setSessionVar('fb_session_key',  $fbSessionKey);
		$req->setSessionVar('fb_secret',       $fbSessionSecret);
		$req->setSessionVar('fb_sig',          $session->sig);

		$u = $req->getUser();
		//find a user which has connected before
		if(! $this->_connectFbUser($fbUid, $u)) {
			try {
				$fbInfos = $fbObj->api_client->users_getInfo($fbUid,
				array('username', 'name', 'first_name', 'last_name'));
			} catch (Exception $e) {
				echo "Exception ".$e->getMessage()."<br/>\n";
			}

			//make a new user
			$newUser = $this->_createNewFbUser($fbUid, $u, $fbInfos);
			if (!$newUser) {
				//some error with registration, show template and error.
				//this is highly unlikely
				return FALSE;
			}
			$this->_createNewFbAcct($fbUid, $newUser, $fbObj, $fbInfos);
		}
	}

	/**
	 * Handle multiple "static" file outputs
	 */
	public function output($req, &$t) {
		$apikey = $this->getConfig('api.key');

		if ($this->eventName == 'xdreceiver') {
			echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
			echo "\n";
			echo '<html xmlns="http://www.w3.org/1999/xhtml" > <body>' ."\n";

			foreach ($t as $_t) {
				echo $_t;
			}
			echo' </body> </html>';
		} else {
			echo '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:fb="http://www.facebook.com/2008/fbml">';
			echo '<body>';
			echo '<script src="http://static.ak.connect.facebook.com/js/api_lib/v0.4/FeatureLoader.js.php" type="text/javascript"></script>'."\n";
			echo '<script type="text/javascript">  
				function onConnected(uid) {
//					window.location.href = window.location.href;
				}
				function onNotConnected(uid) {
				}
				FB.init("'.$apikey.'", "'.cgn_appurl('fbconnect', 'main', 'xdreceiver').'",
			{"ifUserConnected":onConnected, "ifUserNotConnected":onNotConnected} ); </script>';
			//echo '<script type="text/javascript">  FB.init("'.$apikey.'", "fbconnect.main.xdreceiver"); </script>';
			$u = $req->getUser();
			if ($req->getSessionVar('fb_uid') < 1 ) {
				echo '<fb:login-button autologoutlink="true"></fb:login-button>';
			} else {
				echo '<a href="'.cgn_appurl('fbconnect', 'main', 'logout').'">Logout</a>';
			}

			foreach ($t as $_t) {
				echo $_t;
			}
			echo '</body>';
			echo '</html>';
		}
	}

	/**
	 * Get the facebook client object for easy access.
	 */
	function getFb($req) {
		static $facebook = null;

		if ($facebook === null) {
			$apikey = $this->getConfig('api.key');
			$apisecret = $this->getConfig('api.secret');
			$facebook = new Facebook($apikey, $apisecret);

			if (!$facebook) {
				error_log('Could not create facebook client.');
			}
		}
		return $facebook;
	}

	/**
	 * If a Facebook user already has an account with this site, then
	 * their email hash will be returned.
	 *
	 * This only works because the site calls facebook.connect.registerUsers
	 * on every registration.
	 */
	function getFacebookUserEmailHashes($fb_uid) {
		$query = 'SELECT email_hashes FROM user WHERE uid=\''.$fb_uid.'\'';
		try {
			$rows = $this->getFb()->api_client->fql_query($query);
		} catch (Exception $e) {
			// probably an expired session
			return null;
		}
		if (is_array($rows) && (count($rows) == 1) && is_array($rows[0]['email_hashes'])) {
			return $rows[0]['email_hashes'];
		} else {
			return null;
		}
	}

	/**
	 * Fix up the passed in GET params to act like
	 * regular cookie params
	 */
	public function _getXdParams($_s) {
		//fix for incorrect FB API
		$fb_params = array();
		$str = '';
		foreach ($_s as $_k => $_v) {
			if ($_k == 'base_domain') continue;
			if ($_k == 'sig') continue;
			if ($_k == 'secret'){ 
				$fb_params['ss'] = $_v;
				continue;
			}
			if ($_k == 'uid'){ 
				$fb_params['user'] = $_v;
				continue;
			}
			$fb_params[$_k] = $_v;
		}
	    ksort($fb_params);
		foreach ($fb_params as $_k => $_v) {
      		$str .= "$_k=$_v";
		}
		return $fb_params;
	}

	protected function _connectFbUser($fbuid, $u) {
		$newUser = $this->_findUserByFb($fbuid);
		if (!$newUser) {
			return FALSE;
		}
		$u->userId = $newUser->cgn_user_id;
		$u->username = $newUser->username;
		$u->password = $newUser->password;
		$u->email    = $newUser->email;
		$u->bindSession();
		return TRUE;
	}

	protected function _findUserByFb($fbuid) {
		$finder = new Cgn_DataItem('fb_uid_link');

		$finder->_cols = array('Tuser.*');
		$finder->hasOne('cgn_user', 'cgn_user_id', 'Tuser', 'cgn_user_id');
		$finder->andWhere('fb_uid', $fbuid);
		$userList = $finder->find();
		if (isset($userList[0]))
			return $userList[0];
		else
			return FALSE;
	}

	protected function _createNewFbUser($fbuid, $u, $fbInfos) {
		$newUser = $this->_createUserByFb($fbuid, $fbInfos);
		if (!$newUser) {
			return FALSE;
		}
		$u->userId   = $newUser->userId;
		$u->username = $newUser->username;
		$u->password = $newUser->password;
		$u->email    = $newUser->email;
		$u->bindSession();
		return $newUser;
	}

	protected function _createUserByFb($fbUid, $fbInfos) {
		$newUser = new Cgn_User();
		$newUser->username  = '';
		$newUser->email     = '';
		$newUser->password  = '';
		$newUser->active_on = time();

		if (!Cgn_User::registerUser($newUser, 'facebook')) {
			trigger_error('user already exists');
			return FALSE;
		}
		$cgnUserId = $newUser->userId;
		if ($cgnUserId < 1) {
			return FALSE;
		}

		$fbLink = new Cgn_DataItem('fb_uid_link');
		$fbLink->load( array('fb_uid = '.$fbUid) );
		$fbLink->set('cgn_user_id', $cgnUserId);
		$fbLink->set('fb_uid',      $fbUid);
		$fbLink->save();

		return $newUser;
	}

	protected function _createNewFbAcct($fbUid, $u, $fbObj, $fbInfos) {

		$firstName = 'Guset';
		$lastName  = 'User';

		if (isset($fbiIfos[0]['name'])) {
			$parts = explode(' ', $fbInfos[0]['name']);
			$firstName = array_shift($parts);
			$lastName  = implode(' ', $parts);
		}
		if (isset($fbInfos[0]['first_name'])) {
			$firstName = $fbInfos[0]['first_name'];
		}
		if (isset($fbInfos[0]['last_name'])) {
			$lastName = $fbInfos[0]['last_name'];
		}

		$cgnUserId = $u->userId;
		$newAcct = new Cgn_DataItem('cgn_account');
		$newAcct->load( array('cgn_user_id = '.$cgnUserId) );
		$newAcct->set('cgn_user_id', $cgnUserId);
		$newAcct->set('firstname',   $firstName);
		$newAcct->set('lastname',    $lastName);
		$newAcct->set('ref_no',      $fbUid);
		$newAcct->save();
	}
}


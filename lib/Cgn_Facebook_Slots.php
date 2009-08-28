<?php

class Cgn_Facebook_Slots {

	public $_configs = array();

	public function getLoginBadge() {
		//die('badge');
		Cgn::loadModLibrary('Fbconnect::Facebook');
		Cgn::loadModLibrary('Fbconnect::FacebookRestClient');

		$apikey = $this->getConfig('api.key');
		$req = Cgn_SystemRequest::getCurrentRequest();
		$u = $req->getUser();

		$this->_addJsToTemplate($apikey, $u);

		$fbObj = $this->getFb($req);

		try {
			$fbUser = $fbObj->get_loggedin_user();
		} catch (Exception $e) {
			echo $e->getMessage() .' ' ;
		}
		$fbUid = $fbObj->user;

		if ($fbUid < 1 && $fbObj->session_expires > 0) {
			//make sure user is not logged in, they might be logged in but
			//our cookies are old
			$fbObj->promote_session();
			$fbUid = $fbObj->user;
		}

		if (!$fbUid) {
			if ($u->isAnonymous()) {
				$u->endSession();
			} else {
				$u->unBindSession();
				$u->endSession();
			}
		}

		if ($fbUid > 1 && $fbObj->session_expires > time()) {
			$this->_connectFbUser($fbUid, $u);
			$show_logo = true;
			$str3 = ' <br style="clear:both;" />
				<div class="fb-badge-img" style="float:right"><fb:profile-pic uid="'.$fbUid.'" size="square" ' . ($show_logo ? ' facebook-logo="true"' : '') . '></fb:profile-pic>
				</div>
				<span class="fb-badge-name">Welcome, <fb:name uid="'.$fbUid.'" useyou="no"></fb:name></span>
				<br/>
				<span class="fb-badge-link"><a href="'.cgn_appurl('fbconnect', 'main', 'logout').'">Sign-out from Facebook.</a></span>
				';
			return $str3;
		}
		//not logged in, show the FBXML button
		return '<br style="clear:both;" /> <div class="fb-badge-button"><fb:login-button size="medium" length="long" autologoutlink="false"></fb:login-button></div>';
//		return '<br style="clear:both;" /> <img src="http://static.ak.fbcdn.net/images/fbconnect/login-buttons/connect_light_large_short.gif"/>';
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

	public function getConfig($k) {
		if (count($this->_configs) < 1) {
			$this->initConfigs();
		}
		return $this->_configs[$k];
	}

	public function initConfigs() {
		$serviceConfig =& Cgn_ObjectStore::getObject('object://defaultConfigHandler');
		$serviceConfig->initModule('fbconnect');

		foreach ($serviceConfig->getModuleKeys('fbconnect') as $k) {
			$this->_configs[$k] = $serviceConfig->getModuleVal('fbconnect', $k);
		}
	}

	/**
	 * Clear the facebook login and reset the redirect login
	 * to facebook logout
	 */
	public function logoutSlot($sig) {
		Cgn::loadModLibrary('Fbconnect::Facebook');
		Cgn::loadModLibrary('Fbconnect::FacebookRestClient');

		$loginMod = $sig->getSource();
		$req = Cgn_SystemRequest::getCurrentRequest();
		$url = $this->clearFbLogin($req);
		$loginMod->redirectUrlLogout = $url;
		return TRUE;
	}

	public function clearFbLogin($req) {
		$req->clearSessionVar('fb_uid');
		$req->clearSessionVar('fb_expires');
		$req->clearSessionVar('fb_session_key');
		$req->clearSessionVar('fb_secret');
		$req->clearSessionVar('fb_sig');

		if (strlen($_SERVER['HTTP_REFERER']))
			$next = urlencode($_SERVER['HTTP_REFERER']);
		else
			$next = urlencode(cgn_appurl('fbconnect'));

		$fbObj = $this->getFb($req);

		//hit this URL to stop FB Javascript from re-detecting logged-in status
		//http://www.facebook.com/logout.php?app_key=XXX&session_key=XXX&extern=2&next=XXX&locale=en_US
		$url = 'http://www.facebook.com/logout.php?app_key='.$fbObj->api_key.
			'&session_key='.$fbObj->api_client->session_key.'&extern=2&next='.$next;
		//don't call this any earlier, it also un-sets session_key
		$fbObj->clear_cookie_state();
		return $url;
	}

	/**
	 * This will add JS to the template which you can hook into when 
	 * a user is recognized as being from Facebook.com
	 *
	 * js functions fb_onConnectedSlot and fb_onNotConnectedSlot will 
	 * be called if they exist and will be passed the UID of the user
	 */
	protected function _addJsToTemplate($apikey, $user) {
		if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')) {
			Cgn_Template::addSiteJs('https://www.connect.facebook.com/js/api_lib/v0.4/FeatureLoader.js.php');
			$xdrUrl = cgn_sappurl('fbconnect', 'main', 'xdreceiver');
		} else {
			Cgn_Template::addSiteJs('http://static.ak.connect.facebook.com/js/api_lib/v0.4/FeatureLoader.js.php');
			$xdrUrl = cgn_appurl('fbconnect', 'main', 'xdreceiver');
		}
		//only add JS that has logged in listeners if the user is anonymous / not logged in
		Cgn_Template::addSiteJs('<script type="text/javascript">
				function onConnected(uid) {
//alert(\'connected\');
					if (document.getElementById(\'fb_login_image\')) {
						if (window.fb_onConnectedSlot !== undefined)
							fb_onConnectedSlot(uid)
						else {
							FB.XFBML.Host.parseDomTree();   /* window.location.reload(); */
							window.location.reload();
							//FB.XFBML.Host.parseDomTree();   /* window.location.reload(); */
						}
					}
				}
				function onNotConnected(uid) {
//alert(\'not connected\');
					if (window.fb_onNotConnectedSlot !== undefined)
						fb_onNotConnectedSlot(uid)
					else  {
						FB.XFBML.Host.parseDomTree();   /* window.location.reload(); */
						//window.location.reload();
					}
				}
			FB.init("'.$apikey.'", "'.$xdrUrl.'"); 
</script>'
);

		if ($user->isAnonymous()) {
			Cgn_Template::addSiteJs('<script type="text/javascript">
            FB.Connect.ifUserConnected(
				onConnected,
				onNotConnected
			); </script>'
			);
		}
/*
		if ($user->isAnonymous()) {
echo "not logged in";
			Cgn_Template::addSiteJs('<script type="text/javascript">

            FB.ensureInit( function() {
                FB.Connect.ifUserConnected(
                    function() {  },
					onNotConnected
                );
                FB.Connect.get_status().waitUntilReady( function( status ) {
                    if( status === FB.ConnectState.connected ) {
						onConnected();
                    }
                } );
            } );
</script>'
			);
		}  else {
echo "logged in";
			Cgn_Template::addSiteJs('<script type="text/javascript">
            FB.ensureInit( function() {

                FB.Connect.get_status().waitUntilReady( function( status ) {
                    if( status === FB.ConnectState.connected ) {
                    } else {
                    alert("method 2: user not connected"); 
						onNotConnected(status);
					}
                } );
            } );
</script>'
			);
		}
*/
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
		$finder->_rsltByPkey = false;
		$userList = $finder->find();
		if (isset($userList[0]))
			return $userList[0];
		else
			return FALSE;
	}

}

<?php

class Cgn_Facebook_Slots {

	public $_configs = array();

	public function getLoginBadge() {
		Cgn::loadModLibrary('Fbconnect::Facebook');
		Cgn::loadModLibrary('Fbconnect::FacebookRestClient');

		$apikey = $this->getConfig('api.key');
		$this->_addJsToTemplate($apikey);

		$req = Cgn_SystemRequest::getCurrentRequest();
		$fbObj = $this->getFb($req);

		$fbUid = $fbObj->user;

		if ($fbUid < 1 && $fbObj->session_expires > 0) {
			//make sure user is not logged in, they might be logged in but
			//our cookies are old
			$fbObj->promote_session();
			$fbUid = $fbObj->user;
		}

		if ($fbUid > 1 && $fbObj->session_expires < time()) {
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
	protected function _addJsToTemplate($apikey) {
		Cgn_Template::addSiteJs('http://static.ak.connect.facebook.com/js/api_lib/v0.4/FeatureLoader.js.php');
		Cgn_Template::addSiteJs('<script type="text/javascript">
				function onConnected(uid) {
					if (document.getElementById(\'fb_login_image\')) {
						if (window.fb_onConnectedSlot !== undefined)
							fb_onConnectedSlot(uid)
						else
							window.location.reload();
					}
				}
				function onNotConnected(uid) {
					if (!document.getElementById(\'fb_login_image\')) {
						if (window.fb_onNotConnectedSlot !== undefined)
							fb_onNotConnectedSlot(uid)
						else 
							window.location.reload();
					}
				}
			FB.init("'.$apikey.'", "'.cgn_appurl('fbconnect', 'main', 'xdreceiver').'",
			{"ifUserConnected":onConnected, "ifUserNotConnected":onNotConnected} ); </script>'
		);
	}
}

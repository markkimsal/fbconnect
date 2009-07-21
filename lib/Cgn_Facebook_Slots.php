<?php

class Cgn_Facebook_Slots {

	public $_configs = array();

	public function getLoginBadge() {
		Cgn::loadModLibrary('Fbconnect::Facebook');
		Cgn::loadModLibrary('Fbconnect::FacebookRestClient');

		$req = Cgn_SystemRequest::getCurrentRequest();
		$fbObj = $this->getFb($req);
		$fbUid = $fbObj->user;
		$show_logo = true;
		$str3 = ' <br style="clear:both;" />
			<fb:profile-pic uid="'.$fbUid.'" size="square" ' . ($show_logo ? ' facebook-logo="true"' : '') . '></fb:profile-pic>
			<fb:name uid="'.$fbUid.'" useyou="no"></fb:name>';
		return $str3;
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

}

<?php 

class LoginHelper extends RuntimeAddon {
	const LOGIN_FAIL = -1;
	const LOGIN_NOTTRIED = 0;
	const LOGIN_SUCCESS = 1;
	
	public static $CONFIG;
	public static $CONFIG_TYPE;
	
	public static function includeAddon() {
		if (!class_exists('UserHelper', false)) {
			throw new Exception("This module [".get_called_class()."] cannot run without module [UserHelper] loaded.");
		}
		if (!(self::$CONFIG['accept_get'] || self::$CONFIG['accept_post'])) {
			throw new Exception("This module [".get_called_class()."] needs one of [accept_get, accept_post] to be true.");
		}
		if (!file_exists(self::$CONFIG['template'])) {
			throw new Exception("This module [".get_called_class()."] needs the file [".self::$CONFIG['template']."].");
		}
	}
	
	private static function handleDataArray(&$arr, &$user, &$pass) {
		if(isset($arr[self::$CONFIG['user_param']]) && isset($arr[self::$CONFIG['pass_param']])) {
			$user = $arr[self::$CONFIG['user_param']];
			$pass = $arr[self::$CONFIG['pass_param']];
			return true;
		}
		return false;
	}
	
	public static function handleData() {
		$try = false;
		$user = null;
		$pass = null;
		
		if(self::$CONFIG['accept_post'] && Runtime::get()->isPost()) {
			$try = self::handleDataArray($_POST, $user, $pass);
		}
		if(self::$CONFIG['accept_get'] && Runtime::get()->isGet()) {
			$try = self::handleDataArray($_GET, $user, $pass);
		}
		if ($try) {
			if (UserHelper::validate($user, $pass)) {
				return self::LOGIN_SUCCESS;
			} else {
				return self::LOGIN_FAIL;
			}
		}
		return self::LOGIN_NOTTRIED;
	}
	
	
	public static function showTemplate($error = false) {
		self::$count = array('getMethod','getUserInputName','getPassInputName', 'getErrorMessage');
		self::$error = $error;
		include self::$CONFIG['template'];
		
		if (sizeof(self::$count)>0) {
			$names = implode(', ', self::$count);
			throw new Exception("This module [".get_called_class()."] requires that [$names] methods get executed in template.");
		}
	}
	
	public static function doLogout() {
		session_destroy();
	}
	
	private static $count = null;
	private static $error = false;
	private static function getMethod() {
		unset(self::$count[0]);
		if (self::$CONFIG['accept_post']) {
			return 'POST';
		}
		return 'GET';
	}
	
	private static function getUserInputName() {
		unset(self::$count[1]);
		return self::$CONFIG['user_param'];
	}
	
	private static function getPassInputName() {
		unset(self::$count[2]);
		return self::$CONFIG['pass_param'];
	}
	
	private static function getErrorMessage() {
		unset(self::$count[3]);
		if (self::$error) {
			return self::$CONFIG['error_message'];
		}
		return null;
	}
	
	
	public static function getRequiredConfigType() {
		return self::$CONFIG_TYPE;
	}
	
}

LoginHelper::$CONFIG_TYPE = new ConfigType(array('template','user_param','pass_param', 'accept_get', 'accept_post', 'error_message'));
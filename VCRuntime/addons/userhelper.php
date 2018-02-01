<?php 

session_start();

class UserHelper extends RuntimeAddon {
	public $id;
	public $name;
	public $desc;
	public $mail;
	
	public static $CONFIG;
	public static $CONFIG_TYPE;

	public function __construct($res) {
		if (gettype($res) === 'array') {
			$this->id   = $res[self::$CONFIG['id_col']];
			$this->name = $res[self::$CONFIG['name_col']];
			$this->desc = $res[self::$CONFIG['desc_col']];
			$this->mail = $res[self::$CONFIG['mail_col']];
		}
	}
	
	private static function getUserAsArray($name) {
		$mysqli = DatabaseHelper::getConnection();
		$config = self::$CONFIG;
		
		$variable = $mysqli->real_escape_string($name);
		$extra_where = "";
		if (isset($config['name_col'])) {
			$extra_where = " and ".$config['extra_where'];
		}
		$query = "select {$config['id_col']}, {$config['name_col']}, {$config['desc_col']}, {$config['mail_col']}, {$config['pass_col']} from {$config['table']} where {$config['name_col']} = '$variable' $extra_where ";
			
		$res = $mysqli->query($query);
		if ($res) {
			return $res->fetch_array();
		}
		return null;
	}
	
	public static function logged() {
		if (isset($_SESSION['user'])) {
			return $_SESSION['user'];
		}
		return null;
	}
	
	public static function validate($name, $pass) {
		$res = self::getUserAsArray($name);
		if ($res && $res[self::$CONFIG['pass_col']] === $pass) {
			$user = new UserHelper($res);
			$_SESSION['user'] = get_object_vars($user);
			return $user;
		}
		return false;
	}
	
	public static function includeAddon() {
		switch (session_status()) {
			case PHP_SESSION_DISABLED:
				throw new Exception("This module [".get_called_class()."] cannot run without sessions enabled.");
				break;
			case PHP_SESSION_NONE:
				session_start();
				break; 
		}
	}
	public static function getRequiredConfigType() {
		return UserHelper::$CONFIG_TYPE;
	}
	
	public static function secure() {
		if(!UserHelper::logged()) {
			header('Location: login.php');
		}
	}
	
}

UserHelper::$CONFIG_TYPE = new ConfigType(array('table', 'id_col', 'pass_col', 'name_col', 'desc_col', 'mail_col'), 'User Configuration');
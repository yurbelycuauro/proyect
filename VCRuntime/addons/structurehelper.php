<?php
class StructureHelper extends RuntimeAddon {

	public static $CONFIG;
	public static $CONFIG_TYPE;
	
	public static function includeAddon() {
		if (!file_exists(self::$CONFIG['main_template'])) {
			throw new Exception("This module [".get_called_class()."] needs the file [".self::$CONFIG['main_template']."].");
		}
		if (!file_exists(self::$CONFIG['top_template'])) {
			throw new Exception("This module [".get_called_class()."] needs the file [".self::$CONFIG['top_template']."].");
		}
		if (!file_exists(self::$CONFIG['bottom_template'])) {
			throw new Exception("This module [".get_called_class()."] needs the file [".self::$CONFIG['bottom_template']."].");
		}
		if (!file_exists(self::$CONFIG['nav_template'])) {
			throw new Exception("This module [".get_called_class()."] needs the file [".self::$CONFIG['nav_template']."].");
		}
	}
	
	public static function execute() {
		include self::$CONFIG['main_template'];
	}
	
	public static function getTopScripts() {
		include self::$CONFIG['top_template'];
	}
	
	public static function getBottomScripts() {
		include self::$CONFIG['bottom_template'];
	}
	
	public static function getNavTemplate() {
		include self::$CONFIG['nav_template'];
	}
	
	public static function getRequiredConfigType() {
		return self::$CONFIG_TYPE;
	}
	
	public static function addListener($name, $optional = true) {
		$run = false;
		if ($optional && function_exists($name)) {
			$run = true;
		}
		if (!$optional) {
			$run = true;
		}
		if ($run) {
			call_user_func($name);
		}
	}
	
}

StructureHelper::$CONFIG_TYPE = new ConfigType(array('main_template', 'top_template', 'bottom_template', 'nav_template'));

<?php
abstract class RuntimeAddon {
	public static $CONFIG;
	public static $CONFIG_TYPE;
	public static function getRequiredConfigType() {
		return null;
	}
	
	public static function add_handler() {
		
	}
	
	public static function saveConfig($type, $config) {
		$type::$CONFIG = $config;
	}
}
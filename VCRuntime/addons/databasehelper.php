<?php

class DatabaseHelper extends RuntimeAddon {
	public static $CONFIG;
	public static $CONFIG_TYPE;

	private static $openConnection = null;

	public static function includeAddon() {
		register_shutdown_function ( array ('DatabaseHelper','shutdown_handler') );
	}

	public static function shutdown_handler() {
		try {
			if (self::$openConnection) {
				self::$openConnection->close ();
			}
		} catch ( Exception $e ) {
		}
	}

	/**
	 * Crea una conexion mysqli usando la configuracion nombrada o 'db' por defecto.
	 * Valida que la configuracion este completa.
	 *
	 * @param string $name
	 * @throws Exception si no existe la configuracion, si faltan parametros en la configuracion, errores de mysql.
	 * @return mysqli
	 */
	public static function getConnection() {
		if (self::$openConnection) {
			return self::$openConnection;
		}
		$db = self::$CONFIG;
		$mysqli = new mysqli ( $db ['host'], $db ['user'], $db ['pass'], $db ['db'] );

		if ($mysqli->connect_errno) {
			throw new Exception ( "[Database Connection: $name] Error {$mysqli->connect_errno}: {$mysqli->connect_error}" );
		}

		$mysqli->set_charset("utf8");

		self::$openConnection = $mysqli;
		return $mysqli;
	}

	public static function getRequiredConfigType() {
		return self::$CONFIG_TYPE;
	}

	public static function applyParamsToQuery($query, $params = null) {
		$mysqli = self::getConnection();
		if ($params != null) {
			foreach ($params as $key => $val) {
				if ($val == null) {
					$val = 'null';
				}
				$query = str_replace(":$key:", $mysqli->real_escape_string($val), $query);
			}
		}
		if (Runtime::$CURRENT->isDebug()) {
			error_log($query);
		}
		return $query;
	}

	private static function executeQuery($query) {
		$mysqli = self::getConnection();
		return $mysqli->query($query);
	}

	public static function fetchAllAsArray($query, $params = null) {
		$result = self::executeQuery(self::applyParamsToQuery($query, $params));
		$resp = array();
		if ($result) {
			while (($row = $result->fetch_array(MYSQLI_ASSOC))!=null) {
				$resp[] = $row;
			}
			$result->close();
		}
		return $resp;
	}

	public static function fetchOneAsArray($query, $params = null) {
		$result = self::executeQuery(self::applyParamsToQuery($query, $params));
		$resp = array();
		if ($result) {
			if (($row = $result->fetch_array(MYSQLI_ASSOC))!=null) {
				$resp = $row;
			}
			$result->close();
		}
		return $resp;
	}

	public static function modifyData($query, $params = null) {
		$resp = new stdClass();
		$sql = self::applyParamsToQuery($query, $params);
		// error_log($sql);
		$result = self::executeQuery($sql);
		$mysqli = self::getConnection();
		if ($result) {
 			$resp->affected_rows = $mysqli->affected_rows;
 			$resp->insert_id = $mysqli->insert_id;
		}
		return $resp;
	}
}

DatabaseHelper::$CONFIG_TYPE = new ConfigType(array ('user','pass','host','db'), 'Database Connection Configuration');

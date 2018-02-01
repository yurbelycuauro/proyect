<?php

include_once 'runtimeaddon.php';
include_once 'configtype.php';

// set display_errors as false;
try {
	ini_set ( 'display_errors', 'Off' );
} catch ( Exception $e ) {
}

/**
 * Clase que maneja todo el runtime, permite crear y cerrar conexiones mysql de forma simple y manejar errores.
 */
class Runtime {
	
	public static $CONFIG_SIMPLE;
	public static $CURRENT;
	
	private $settings = array ();
	private $debug = false;
	private $env = 'dev';
	private $exceptions = array ();
	
	/**
	 * Construye un runtime con configuraciones de forma opciona.
	 *
	 * @param string $settings        	
	 */
	public function __construct(&$settings = null) {
		if ($settings != null) {
			$this->settings = $settings;
		}
		if (isset ( $this->settings ['debug'] )) {
			$this->debug = $this->settings ['debug'];
		}
		if (isset ( $this->settings ['env'] )) {
			$this->env = $this->settings ['env'];
		}
		
		register_shutdown_function ( array (
				$this,
				'shutdown_handler' 
		) );
		set_exception_handler ( array (
				$this,
				'exception_handler' 
		) );
		set_error_handler ( array (
				$this,
				'error_handler' 
		) );
		self::$CURRENT = $this;
	}
	
	/**
	 * Hook que cierra conexiones e imprime excepciones.
	 */
	public function shutdown_handler() {
		if (sizeof ( $this->exceptions ) > 0) {
			if (! $this->debug) {
				echo "<!-- ";
			}
			echo '<pre style="border:red 2px solid; background-color:#FBB;">';
			foreach ( $this->exceptions as &$e ) {
				$class = get_class ( $e );
				echo "$class: {$e->getMessage()}" . PHP_EOL;
				echo $e->getTraceAsString () . PHP_EOL;
			}
			echo "</pre>";
			if (! $this->debug) {
				echo "-->";
			}
		}
	}
	
	/**
	 * handler para excepciones
	 *
	 * @param Exception $e        	
	 */
	public function exception_handler(Exception $e) {
		$this->exceptions [] = $e;
	}
	
	/**
	 * handler para errores
	 *
	 * @param unknown $code        	
	 * @param unknown $error        	
	 * @param unknown $file        	
	 * @param unknown $line        	
	 * @return boolean
	 */
	public function error_handler($code, $error, $file, $line) {
		if ((error_reporting () & $code) === 0)
			return TRUE;
		$this->exception_handler ( new ErrorException ( $error, $code, 0, $file, $line ) );
	}
	
	
	
	/**
	 * Retorna la configuracion si es que existe.
	 *
	 * @param unknown $name        	
	 * @return multitype:
	 */
	public function getConfig($name) {
		return $this->checkConfig ($name, Runtime::$CONFIG_SIMPLE);
	}
	
	/**
	 * Revisa que una configuracion exista y que contenga los parametros definidos.
	 *
	 * @param unknown $name        	
	 * @param string $props        	
	 * @param string $msg        	
	 * @throws Exception
	 * @return multitype:
	 */
	public function checkConfig($name, ConfigType $type) {
		if (! isset ( $this->settings [$name] )) {
			throw new Exception ( "[$msg: $name] not configured." );
		}
		$ret = $this->settings [$name];
		$fail = array ();
		if ($type->props!=null) {
			foreach ( $type->props as $prop ) {
				if (! isset ( $ret [$prop] )) {
					$fail [] = $prop;
				}
			}
		}
		if (sizeof ( $fail ) > 0) {
			$list = implode ( ', ', $fail );
			throw new Exception ( "[$msg: $name] is missing required fields [$list]." );
		}
		return $ret;
	}
	
	/**
	 * Retorna true si el runtime esta en modo debug.
	 *
	 * @return boolean
	 */
	public function isDebug() {
		return $this->debug;
	}
	
	/**
	 * Retorna true si el runtime esta en modo prod.
	 * 
	 * @return boolean
	 */
	public function isProd() {
		return $this->env === 'prod';
	}
	
	/**
	 * Retorna true si el runtime esta en modo dev.
	 * 
	 * @return boolean
	 */
	public function isDev() {
		return $this->env === 'dev';
	}
	
	/**
	 * Incluye el addon, verificando si existe su configuracion de forma completa.
	 * 
	 * @param string $type
	 * @param string $name
	 */
	public function includeAddon($type, $name) {
		$inc = strtolower($type);
		include_once "addons/$inc.php";
		$req = $type::getRequiredConfigType();
		if ($req !=null) {
			$config = $this->checkConfig($name, $req);
			$type::saveConfig($type, $config);
		}
		$type::includeAddon();
	}
	
	public static function get() {
		return self::$CURRENT;
	}
	
	public function isPost() {
		return ($_SERVER['REQUEST_METHOD'] === 'POST');
	
	}
	public function isGet() {
		return ($_SERVER['REQUEST_METHOD'] === 'GET');
	}
	
}

Runtime::$CONFIG_SIMPLE = new ConfigType(null, 'Configuration');

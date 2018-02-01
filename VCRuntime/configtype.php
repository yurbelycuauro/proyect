<?php

/**
 * Define una configuracion con su nombre, parametros requeridos y mensaje para errores
 */
class ConfigType {
	public $props = null;
	public $msg = null;

	public function __construct($props = null, $msg = 'Configuration') {
		$this->props = $props;
		$this->msg = $msg;
	}
}

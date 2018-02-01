<?php 
include_once "conf.env.php";
include_once "VCRuntime/runtime.php";
(@BASE_DIR == 'BASE_DIR') && define('BASE_DIR', realpath(dirname(__FILE__)));

$CONF['user_config'] = array(
	'table' => 'vc_usuario_backoffice',
	'id_col' => 'id',
	'pass_col' => 'clave',
	'name_col' => 'usuario',
	'desc_col' => 'nombre',
	'mail_col' => 'email',
	'id_col' => 'id',
	'extra_where' => "estado = 'O'",
);
$CONF['login_config'] = array(
	'template' => BASE_DIR.'/templates/loginTemplate.php',
	'user_param' => 'user',
	'pass_param' => 'pass',
	'accept_get' => false,
	'accept_post' => true,
	'error_message' => 'Usuario y clave no v&aacute;lidos.',
);
$CONF['structure'] = array(
	'main_template' => BASE_DIR.'/templates/structure.php',
	'top_template' => BASE_DIR.'/templates/structureTopTemplate.php',
	'bottom_template' => BASE_DIR.'/templates/structureBottomTemplate.php',
	'nav_template' => BASE_DIR.'/templates/structureNavTemplate.php',
);

$runtime = new Runtime($CONF);
$runtime->includeAddon('DatabaseHelper', 'db');
$runtime->includeAddon('UserHelper', 'user_config');

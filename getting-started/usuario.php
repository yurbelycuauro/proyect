<?php include_once "conf_svc.php";
UserHelper::secure();

include_once '../VCRuntime/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
require_once '../phpexcel/php-excel.class.php';
$app = new \Slim\Slim();
$app->response->headers->set('Content-Type', 'application/json');

function _validarUsuario($data){
	$usuario=$data[0];
	$clave=$data[1];
	$sql="SELECT * from usuario  WHERE nombre='$usuario' and clave='$clave'";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	if (count($result)!="0"){
		return "true";
	}else{
		return "false";
	}

}
$app->post('/usuario/validarUsuario', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_validarUsuario($data));
});
$app->run();

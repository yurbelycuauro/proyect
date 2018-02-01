<?php include_once "conf_svc.php";
UserHelper::secure();

include_once '../VCRuntime/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();
$app->response->headers->set('Content-Type', 'application/json');

function _obtenerJstrree(){
	echo"paso";
	$sql = "select * from division";
	$resul=DatabaseHelper::fetchAllAsArray($sql);
	print_r($resul);
	return $resul;
	
}


$app->get('jstrree/obtenerJstrree', function() use ($app) {
	echo"pasooo";
	echo json_encode(_obtenerJstrree());
});

$app->run();

<?php include_once "conf_svc.php";
UserHelper::secure();

include_once '../VCRuntime/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
require_once '../phpexcel/php-excel.class.php';
$app = new \Slim\Slim();
$app->response->headers->set('Content-Type', 'application/json');

function _obtenerFichas(){
	$sql="select distinct tft.id_ficha,tft.descripcion_ficha 
		from tabla_ficha_tecnica tft LEFT JOIN tabla_ficha tf
		on tft.id_ficha=tf.id_ficha order by tft.descripcion_ficha desc";
	return DatabaseHelper::fetchAllAsArray($sql);
}

function _obtenerFichasSinDetalles() {
	$sql = "select tft.id_ficha,tft.descripcion_ficha,tft.fecha 
		from tabla_ficha_tecnica tft 
		LEFT JOIN tabla_ficha tf 
			on tft.id_ficha=tf.id_ficha
		where tf.id_ficha is NULL 
		and tft.id_ficha not in (select DISTINCT id_ficha from tabla_ficha_especial)
		and tft.id_ficha not in (select DISTINCT id_ficha from 
		tabla_ficha_cluster_temporal)
		order by tft.id_ficha 	desc";
	$result=DatabaseHelper::fetchAllAsArray($sql);
	$count=count($result);
	return $count;
}

function _obtenerUltimaFichaConDetalle() {
	$sql = "select distinct tft.id_ficha from tabla_ficha_tecnica tft INNER JOIN tabla_ficha tf
			on tft.id_ficha=tf.id_ficha order by tft.id_ficha desc LIMIT 1";
	$result=DatabaseHelper::fetchAllAsArray($sql);
	$count=count($result);
	return $count;

}

function _verificarFicha($id_ficha) {
	$sql = "select DISTINCT id_ficha from tabla_ficha
	where id_ficha=$id_ficha";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	$count=count ($result);
	return $count;
}
function _generar_ficha($data) {
	
	$props = Array();
	$fecha_actual=date('Y-m-d H:i');
	$vFSD=(_obtenerFichasSinDetalles());
	if($vFSD=="0"){
		$vUFCD=(_obtenerUltimaFichaConDetalle());
		if($vUFCD=!"0"){
			$mysqli = new mysqli("localhost", "app77", "app77", "app77");
			/* check connection */
			if (mysqli_connect_errno()) {
			    printf("Error de conexión: %s\n", mysqli_connect_error());
			    exit();
			}

			$query = "insert into tabla_ficha_tecnica value(NULL,'${fecha_actual}','$data','1')";
			$mysqli->query($query);
			$cdf=$mysqli->insert_id;
			$query="update tabla_ficha_tecnica set id_estado=0
					where id_ficha<>$cdf";
			$mysqli->query($query);
			return true;
		}else{
			$sql = "insert into tabla_ficha_tecnica value('','${fecha_actual}','$data','1')";
			$resps[] = DatabaseHelper::modifyData($sql, $props);
			return true;
		}

	}else{
		echo"paso";
		//return "Existen fichas generadas sin detalle";
		$mysqli = new mysqli("localhost", "app77", "app77", "app77");
			/* check connection */
		if (mysqli_connect_errno()) {
			printf("Error de conexión: %s\n", mysqli_connect_error());
			exit();
		}
		$query = "insert into tabla_ficha_tecnica value(NULL,'${fecha_actual}','$data','1')";
		$mysqli->query($query);
		$cdf=$mysqli->insert_id;
		$query="update tabla_ficha_tecnica set id_estado=0
		where id_ficha<>$cdf";
		$mysqli->query($query);
		return true;
	}
	
	
}
function _eliminar_clusterFicha($data){
	$props=Array();
	$cdc=$data['0'];
	$cdf=$data['1'];
	
	$sql= "delete FROM tabla_ficha where id_ficha=$cdf
			and id_cluster=$cdc";
	$resps[] = DatabaseHelper::modifyData($sql, $props);
	$sql1= "delete FROM ficha_cluster where id_ficha=$cdf
			and id_cluster=$cdc";
	$resps[] = DatabaseHelper::modifyData($sql1, $props);

}
function _eliminar_clusterTemporales($data){
	$props=Array();
	$c=$data['0'];
	$f=$data['1'];
	$sql="delete from cluster_temporal where id_cluster_temporal='$c'
		and id_ficha='$f'";
		$resps[] = DatabaseHelper::modifyData($sql, $props);
	$sql1="delete from cluster_temporal_atributo where id_cluster='$c'";
		$resps[] = DatabaseHelper::modifyData($sql1, $props);
	$sql2="delete from ficha_cluster_local_temporal where id_cluster_temporal='$c'
		and id_ficha='$f'";
		$resps[] = DatabaseHelper::modifyData($sql2, $props);
	$sql3="delete from tabla_ficha_cluster_temporal where id_cluster_temporal='$c'
		and id_ficha='$f'";
		$resps[] = DatabaseHelper::modifyData($sql3, $props);
}
function _eliminar_piezaFicha($data){
	$props=Array();
	$cdp=$data['0'];
	$cdf=$data['1'];
	$sql= "delete FROM tabla_ficha where id_ficha=$cdf
			and id_producto=$cdp";
	$resps[] = DatabaseHelper::modifyData($sql, $props);
	$sql1= "delete FROM tabla_ficha_especial where id_ficha=$cdf
	and id_producto=$cdp";
	$resps[] = DatabaseHelper::modifyData($sql1, $props);
	$sql2= "delete FROM ficha_pieza where id_ficha=$cdf
	and id_pieza=$cdp";
	$resps[] = DatabaseHelper::modifyData($sql2, $props);
	$sql3="delete from tabla_ficha_cluster_temporal where id_ficha=$cdf
		and id_producto=$cdp";
	$resps[] = DatabaseHelper::modifyData($sql3, $props);
	

}
function _activar_Ficha($dato){
	$props=Array();
	$_desactivar=(_desactivar_Ficha($dato));
	$sql="update tabla_ficha_tecnica 
	set id_estado=1
	where id_ficha=$dato";
	$resps[] = DatabaseHelper::modifyData($sql, $props);
	return $resps;

}
function _desactivar_Ficha($dato){
	$props=Array();
	$sql="update tabla_ficha_tecnica 
	set id_estado=0
	where id_ficha<>$dato";
	$resps[] = DatabaseHelper::modifyData($sql, $props);
	return $resps;

}
function _eliminar_localFichaEspecial($data){
	$props=Array();
	$cdl=$data['0'];
	$cdf=$data['1'];
	$sql2= "delete FROM tabla_ficha_especial where id_ficha=$cdf
			and id_local=$cdl";
	$resps[] = DatabaseHelper::modifyData($sql2, $props);
}
function obtener_detalles_fichas($data){

	$sql="select tf.id_cluster as id_clu,descripcion_cluster as des, id_producto as id_pro, cantidad_producto from tabla_ficha tf
	INNER JOIN cluster c on tf.id_cluster=c.id_cluster 
	where tf.id_ficha=$data
	order by tf.id_cluster,id_producto";

	$result = DatabaseHelper::fetchAllAsArray($sql);
	$Array_de=Array();
	foreach ($result as $key => $value) {
		$Array_de[$value['des']][$value['id_pro']]=$value['cantidad_producto'];
	}
	//print_r($Array_de);
	$array_final=array();
	foreach ($Array_de as $ke => $value) {
		$array_val=array();
		$i="0";
		//print_r($value);
		foreach ($value as $key => $value) {
			if($i=="0"){
				$array_val[]=$ke;
			}
			$i++;
			$array_val[]=$value;
		}

		$array_final[]=$array_val;
	}
	return $array_final;
}

$app->post('/gestionFicha/eliminarLocalFichaEspecial', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_eliminar_localFichaEspecial($data));
});
$app->post('/gestionFicha/activarFicha', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_activar_Ficha($data));
});
$app->post('/gestionFicha/eliminarPiezaDeFicha', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_eliminar_piezaFicha($data));
});
$app->post('/gestionFicha/eliminarClusterDeFicha', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_eliminar_clusterFicha($data));
});
$app->post('/gestionFicha/eliminarClusterTemporalDeFicha', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_eliminar_clusterTemporales($data));
});
$app->post('/ficha/generarFicha', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_generar_ficha($data));
});
$app->post('/gestionfichas/obtener_detalles_fichas', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(obtener_detalles_fichas($data));
});

$app->get('/gestionfichas/obtener_fichas', function(){
	echo json_encode(_obtenerFichas());
});




$app->run();

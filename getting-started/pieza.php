<?php include_once "conf_svc.php";
UserHelper::secure();

include_once '../VCRuntime/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
require_once '../phpexcel/php-excel.class.php';
$app = new \Slim\Slim();
$app->response->headers->set('Content-Type', 'application/json');

function _matrizPiezas() {
	$sql = "select * from producto ORDER BY  id_producto desc LIMIT 1";
	return DatabaseHelper::fetchAllAsArray($sql);
}

function _matrizCaracterizticaPiezas() {
	$sql = "select * from caracteristica_producto";
	return DatabaseHelper::fetchAllAsArray($sql);
}

function _generarPieza($data) {
	$props = Array();
	$fecha_actual=date('Y-m-d H:i');
	$sql = "insert into producto value('0','$data')";
	$resps[] = DatabaseHelper::modifyData($sql, $props);
}

$app->post('/producto/generarDetalle', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	$resps = Array();
	$props = Array();
	function clean($v) {
		if (!$v) {$v = "null"; $insertar="false";}
		if (!$v === "0") {$v = "null"; $insertar="false";}
		return $v;
	}
	
	$nom_pieza=$data[1];
	$img = array_pop($data);
	$pieza=array_pop($data);
	$cod_pieza=registrar_f(json_decode($nom_pieza));
	if($img!="null"){
		$img=cargar_imagen($img,$cod_pieza);
	}else{
	
	}
	foreach($data as $row) {
		$id_producto = Array();
		$id_caracteristica = Array();
		$updates = Array();
		$estado_caracteristica = Array();
		$valor_caracteristica = Array();
		foreach ($row as $k => $v) {
			$v = clean($v);
			if ($k == "id_p") {
				$id_producto[] = $cod_pieza;
				
			}
			if ($k != "id_p") {
				if ($v !="null") {
					$id_caracteristica[] = $k;
					$updates[] = "id_producto = ${v}";
					$estado_caracteristica[]="1";
					$valor_caracteristica[]="$v";
				}else{
					$id_caracteristica[] = $k;
					$updates[] = "id_producto = ${v}";
					$estado_caracteristica[]="0";
					$valor_caracteristica[]="0";
				}
			}
		}
		foreach ($id_caracteristica as $k => $v) {
			$estado=$estado_caracteristica[$k];
			$valor=$valor_caracteristica[$k];
			if($estado=="1"){
				if($valor=="dm" || $valor=="cm" || $valor=="mm" ){
					$e="0";
					$va="0";
				}else{
					$e="1";
					$va=$valor;
				}
				$sql="insert into producto_detalle (id_producto_detalle,id_producto,id_caracteristica,id_estado_caracteristica_producto,valor) values ('0','$id_producto[0]','$v',$e,'$va')";
				$resps[] = DatabaseHelper::modifyData("$sql", $props);
				
			}else{
				$sql="insert into producto_detalle (id_producto_detalle,id_producto,id_caracteristica,id_estado_caracteristica_producto,valor) values ('0','$id_producto[0]','$v',0,0)";
				$resps[] = DatabaseHelper::modifyData("$sql", $props);
			}
		}
	}
	echo json_encode($resps);


});
function _obtenerPiezaDadoFicha($dato){
	$sql = "select distinct pd.id_producto_detalle, p.nombre_producto,p.id_producto,CONCAT(cp.descripcion_caracteristica,' ', pd.valor) as descripcion_caracteristica
		FROM producto_detalle pd INNER JOIN producto p 
		ON pd.id_producto=p.id_producto INNER JOIN 
		caracteristica_producto cp 
		ON pd.id_caracteristica=cp.id_caracteristica
		INNER JOIN tabla_ficha tf on tf.id_producto=p.id_producto
		where pd.id_estado_caracteristica_producto=1 and tf.id_ficha=$dato
		UNION
		select distinct pd.id_producto_detalle, p.nombre_producto,p.id_producto,CONCAT(cp.descripcion_caracteristica,' ', pd.valor) as descripcion_caracteristica
				FROM producto_detalle pd INNER JOIN producto p 
				ON pd.id_producto=p.id_producto INNER JOIN 
				caracteristica_producto cp 
				ON pd.id_caracteristica=cp.id_caracteristica
				INNER JOIN tabla_ficha_especial tfe on tfe.id_producto=p.id_producto
				where pd.id_estado_caracteristica_producto=1 and tfe.id_ficha=$dato
				ORDER BY nombre_producto,descripcion_caracteristica";
	$resul = DatabaseHelper::fetchAllAsArray($sql);
	$count=count ($resul);
	if($count!="0"){
		$piezas=Array();
		$caracteristicas[]=Array();
		foreach ($resul as $key => $value) {
			if (!isset($comparar)) {
				$comparar=$value["nombre_producto"];
				$pieza=$value["nombre_producto"];
				$piezas[$pieza]=Array();
				$piezas[$pieza]['id_producto']=$value["id_producto"];
				$piezas[$pieza]['nombre_producto']=$value["nombre_producto"];
					
				$ct[]=$value["descripcion_caracteristica"];
			 }else{
				$nom_pieza=$value["nombre_producto"];
				if($comparar==$nom_pieza){
					$ct[]=$value["descripcion_caracteristica"];
				}else{
					$piezas[$pieza]['caracteristica']=$ct;
					$comparar=$value["nombre_producto"];
					$pieza=$value["nombre_producto"];
					$piezas[$pieza]=Array();
					$ct=Array();
					$ct[]=$value["descripcion_caracteristica"];
					$piezas[$pieza]['id_producto']=$value["id_producto"];
					$piezas[$pieza]['nombre_producto']=$value["nombre_producto"];
				}
			}
			
		}
	}
	$piezas[$pieza]['caracteristica']=$ct;
	$piezas[$pieza]['id_producto']=$value["id_producto"];
	$piezas[$pieza]['nombre_producto']=$value["nombre_producto"];
	$res=Array();
	foreach ($piezas as $key => $value) {
		$res[]=$value;
					
	}
	return $res;
}
function registrar_f ($dato){
	$mysqli = new mysqli("localhost", "app77", "app77", "app77");
	/* check connection */
	if (mysqli_connect_errno()) {
		printf("Error de conexión: %s\n", mysqli_connect_error());
		exit();
	}
		$query = "insert into producto value('0','$dato')";
		//$query = "insert into tabla_ficha_tecnica value(NULL,'${fecha_actual}','$ficha','1')";
		$mysqli->query($query);
		$cd=$mysqli->insert_id;
		
	return $cd;
}

function cargar_imagen($baseFromJavascript,$codigo){

	$base_to_php = explode(',', $baseFromJavascript);
	$data = base64_decode($base_to_php[1]);
	$nombre="image".$codigo.".png";
	$filepath = $_SERVER['DOCUMENT_ROOT'].'/proyect/getting-started/intranet/uploads/image'.$codigo.'.png';
	$regis=registar_foto($codigo,$nombre);
	file_put_contents($filepath, $data);
	
	
}

function registar_foto($codigo,$nombre){
	$props=Array();
	$sql = "INSERT into imagenes_ficha_pieza values('0','$nombre','$codigo')";
	$resps[] = DatabaseHelper::modifyData("$sql", $props);
	
}

$app->get('/caracteristicas/matriz', function(){
	echo json_encode(_matrizCaracterizticaPiezas());
});

$app->get('/producto/pieza', function(){
	echo json_encode(_matrizPiezas());
});

$app->get('/gestionpiezas/obtenerPiezasDadoFicha', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_obtenerPiezaDadoFicha($data));
});

$app->post('/pieza/generarPieza', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_generarPieza($data));
});

$app->post('/gestionpiezas/obtenerPiezasDadoFicha', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_obtenerPiezaDadoFicha($data));
});
$app->run();

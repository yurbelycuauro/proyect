<?php include_once "conf_svc.php";
UserHelper::secure();
include_once '../VCRuntime/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
require_once '../phpexcel/php-excel.class.php';
$app = new \Slim\Slim();
$app->response->headers->set('Content-Type', 'text/csv');
$app->response->headers->set('Content-Disposition', 'attachment; filename=prueba.csv');

function _obtenerPiezas() {
	$sql="select pd.id_producto_detalle, p.nombre_producto,p.id_producto,cp.descripcion_caracteristica as caract,pd.valor,
		CONCAT(cp.descripcion_caracteristica,' ', pd.valor) as descripcion_caracteristica,(select img.nombre_imgen from imagenes_ficha_pieza img where img.id_producto=pd.id_producto) as nombre_imgen,
		(select img.id_imagen from imagenes_ficha_pieza img where img.id_producto=pd.id_producto) as id_imagen
				FROM producto_detalle pd
					INNER JOIN producto p ON pd.id_producto=p.id_producto
					INNER JOIN caracteristica_producto cp ON pd.id_caracteristica=cp.id_caracteristica
					
		where pd.id_estado_caracteristica_producto=1 
		ORDER BY p.nombre_producto,cp.descripcion_caracteristica";
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
				$piezas[$pieza]['nombre_imagen']=$value["nombre_imgen"];
				$piezas[$pieza]['url']="intranet/uploads/";
				$piezas[$pieza][$value["caract"]]=$value["valor"];


					
				$ct[]=$value["descripcion_caracteristica"];
			 }else{
				$nom_pieza=$value["nombre_producto"];
				if($comparar==$nom_pieza){
					$ct[]=$value["descripcion_caracteristica"];
					$piezas[$pieza][$value["caract"]]=$value["valor"];
				}else{
					$piezas[$pieza]['caracteristica']=$ct;
					$comparar=$value["nombre_producto"];
					$pieza=$value["nombre_producto"];

					$piezas[$pieza]=Array();
					$ct=Array();
					$ct[]=$value["descripcion_caracteristica"];
					$piezas[$pieza][$value["caract"]]=$value["valor"];
					$piezas[$pieza]['id_producto']=$value["id_producto"];
					$piezas[$pieza]['nombre_producto']=$value["nombre_producto"];
					$piezas[$pieza]['nombre_imagen']=$value["nombre_imgen"];
					$piezas[$pieza]['url']="intranet/uploads/";
				}
			}
			
		}
	}
	$piezas[$pieza]['caracteristica']=$ct;
	$piezas[$pieza][$value["caract"]]=$value["valor"];
	$piezas[$pieza]['id_producto']=$value["id_producto"];
	$piezas[$pieza]['nombre_producto']=$value["nombre_producto"];
	$piezas[$pieza]['nombre_imagen']=$value["nombre_imgen"];
	$piezas[$pieza]['url']="intranet/uploads/";
	$res=Array();
	foreach ($piezas as $key => $value) {
		$res[]=$value;
					
	}
	return $res;
}
$app->get('/gestionpiezas/obtener_piezas', function(){
	echo json_encode(_obtenerPiezas());
});
function _ficha() {
	$sql = "select * from tabla_ficha_tecnica
	where id_estado=1 ORDER BY  id_ficha desc LIMIT 1";
	return DatabaseHelper::fetchAllAsArray($sql);
}

function _verificarFicha($id_ficha) {
	$sql = "select DISTINCT id_ficha from tabla_ficha
	where id_ficha=$id_ficha";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	$count=count ($result);
	return $count;
}

function _generarPiezaFicha($codigo) {

	$props=Array();
	$ficha=array_pop($codigo);
	print_r($codigo);
	foreach ($codigo as $key => $value) {
		if(!isset($value['pieza'])){

		}else{
			$cod=$value['id'];
			$sql="INSERT into ficha_pieza (id_pieza,id_ficha)
			values ('$cod','$ficha') on duplicate key 
			update id_pieza='$cod',id_ficha='$ficha'";
			$resps[] = DatabaseHelper::modifyData($sql, $props);
			
		}
	}
	return $resps;
}
function _eliminarPiezaFicha($codigo) {
	$props=Array();
	$ficha=$codigo[0];
	$codigo=$codigo[1];
	$validarPieza=(_validarRegistroPieza($ficha,$codigo));
		if(count($validarPieza)==0){
			$sql="delete from  ficha_pieza
				where id_pieza='$codigo'and id_ficha='$ficha'";
			$resps[] = DatabaseHelper::modifyData($sql, $props);
			return true;
		}else{
			return "no puede eliminar la pieza,ya se escuentra procesada";
		}
		
	
}
function _validarRegistroPieza($f,$c){
	$sql="select id_ficha from tabla_ficha where id_producto=$c
	and id_ficha=$f
	UNION
	select id_ficha from tabla_ficha_especial where id_producto=$c
		and id_ficha=$f
	UNION
	select id_ficha from tabla_ficha_cluster_temporal where id_producto=$c
		and id_ficha=$f";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	return $result;
}
$app->post('/gestionpiezas/eliminar_piezaficha', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_eliminarPiezaFicha($data));
});
$app->post('/gestionpiezas/generar_piezas_fichas', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_generarPiezaFicha($data));
});


function _eliminarPieza($codigo){
	$props=Array();
	$sql="delete from producto_detalle  where id_producto=$codigo";
	$resps[] = DatabaseHelper::modifyData($sql, $props);
	$sql="delete FROM producto where id_producto=$codigo";
	$resps[] = DatabaseHelper::modifyData($sql, $props);
	return $resps;
}
$app->post('/gestionpiezas/eliminarPiezas', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_eliminarPieza($data));
});





$app->run();

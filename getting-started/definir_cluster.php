<?php include_once "conf_svc.php";
UserHelper::secure();

include_once '../VCRuntime/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
require_once '../phpexcel/php-excel.class.php';
$app = new \Slim\Slim();
$app->response->headers->set('Content-Type', 'application/json');


function _generar_cluster_temporal($data) {
	$des=$data[0];
	$ficha=$data[1];
	$props = Array();
	$fecha_actual=date('Y-m-d H:i');
	$mysqli = new mysqli("localhost", "app77", "app77", "app77");
	/* check connection */
	if (mysqli_connect_errno()) {
		printf("Error de conexión: %s\n", mysqli_connect_error());
		exit();
	}
	$query = "insert into cluster_temporal value(0,'$des','$ficha','1')";
	$mysqli->query($query);
	$cdc=$mysqli->insert_id;
	$query="update cluster_temporal set id_estado=0
	where id_cluster_temporal<>$cdc	";
	$mysqli->query($query);
	return $mysqli->query($query);
	
}
function generar_cluster_temporal($ficha,$des) {
	$props = Array();
	$fecha_actual=date('Y-m-d H:i');
	$mysqli = new mysqli("localhost", "app77", "app77", "app77");
	/* check connection */
	if (mysqli_connect_errno()) {
		printf("Error de conexión: %s\n", mysqli_connect_error());
		exit();
	}
	$query = "insert into cluster_temporal value(0,'$des','$ficha','1')";
	$mysqli->query($query);
	$cdc=$mysqli->insert_id;
	$query="update cluster_temporal set id_estado=0
	where id_cluster_temporal<>$cdc	";
	$mysqli->query($query);
	return $cdc ;
	
}
function _obtenerSignos(){
	$sql="select * from signo";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	return $result;

}
function _obtenerCTActivo(){
	$sql="select * from cluster_temporal where id_estado='1'";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	return $result;
}
function _generar_clusterTemporalAtributo($data){
	$props =array();
	$cluster=(_obtenerCTActivo());
	if(count($cluster)!="0"){
		$ct=$cluster[0]['id_cluster_temporal'];
		$a=$data[0];
		$s=$data[1];
		$v="''$data[2]''";
		$sql="insert into cluster_temporal_atributo
		values ('$ct','$a','$s','$v') on duplicate key 
		update id_cluster='$ct',descripcion_atributo='$a'";
		print_r($sql);
		$resps[] = DatabaseHelper::modifyData("$sql", $props);
		 return $resps;

	}else{
		echo "no se han generado cluster temporales";
	}
}
function generar_clusterTemporalAtributo($data,$ficha,$cluster){
	$props =array();
	foreach ($data as $key => $value) {
		$ct=$cluster;
		$a=$value['id'];
		$s=$value['r'];
		$data=$value['v'];
		$v="''$data''";
		$sql="insert into cluster_temporal_atributo
		values ('$ct','$a','$s','$v') on duplicate key 
		update id_cluster='$ct',descripcion_atributo='$a'";
		$resps[] = DatabaseHelper::modifyData("$sql", $props);
	}
	return true;
}
function _obtenerAtributoCluster(){
	$cluster=(_obtenerCTActivo());
	if(count($cluster)!=0){
		$c=$cluster[0]['id_cluster_temporal'];
		$sql="select * from cluster_temporal_atributo where id_cluster='$c'";
		$result = DatabaseHelper::fetchAllAsArray($sql);
		return $result;
	}else{
		echo "no existen cluster temporales generados";
	}
}

function _eliminar_clusterTemporalAtributo($dato){
	$props=Array();
	$cluster=(_obtenerCTActivo());
	if(count($cluster)!=0){
		$c=$cluster[0]['id_cluster_temporal'];
		$sql="delete from cluster_temporal_atributo where id_cluster='$c'
		and descripcion_atributo='$dato' ";
		$resps[] = DatabaseHelper::modifyData("$sql", $props);
		return $resps;

	}else{
		echo "no existen cluster temporales generados";
	}
	
}
function _obtenerCantidadDeAtributosLocaleTemCompartidos($ficha){
	$sql="select COUNT(*) from
	(select cta.descripcion_atributo,cta.relacion,cta.valor
		from cluster_temporal_atributo cta where cta.id_cluster=17)as comp1
	INNER JOIN 
	(select ct.descripcion_atributo,ct.relacion,ct.valor
		from cluster_temporal_atributo ct where ct.id_cluster=17) as comp2
	on comp1.descripcion_atributo=comp2.descripcion_atributo 
	and comp1.relacion=comp2.relacion and comp1.valor=comp2.valor";
}
function _generar_clusterLocalTemporal($data){
	$ficha=array_pop($data);
	$des=array_pop($data);
	$cluster_tem=generar_cluster_temporal($ficha,$des);
	$cluster_atributos=generar_clusterTemporalAtributo($data,$ficha,$cluster_tem);
	$props=Array();
	$fichas=$data;
	$cluster=(_obtenerCTActivo());
		$c=$cluster[0]['id_cluster_temporal'];
		$sql="select descripcion_atributo,relacion,valor from cluster_temporal_atributo where id_cluster='$cluster_tem'";
		$result = DatabaseHelper::fetchAllAsArray($sql);
		if(count($result)!="0"){
			$atributos=Array();
			foreach ($result as $key => $atributosCluster) {
				$atributo = implode("", $atributosCluster);
				$atributos[]=$atributo;
			}
			$query=implode(" and ", $atributos);
			$sql="select * from locales l
				INNER JOIN cluster c on l.id_cluster=c.id_cluster
				INNER JOIN division d on d.id_division=c.id_division 
					where ".$query." 
					and id_local not in (select id_local from ficha_cluster_local 
					where id_ficha='$ficha')
					and d.id_division=(select DISTINCT cl.id_division 
						from ficha_cluster fc
						INNER JOIN cluster cl on fc.id_cluster=cl.id_cluster
						and fc.id_ficha='$ficha' )
						and l.id_cluster in (select f.id_cluster  from ficha_cluster f   where f.id_ficha= '$ficha')";
			$rest = DatabaseHelper::fetchAllAsArray($sql);
			if(count($rest)!="0"){
				foreach ($rest as $key => $locales) {
					$local=$locales['id_local'];
					$validarLocal=(_validarFichaLocalClusterTemporal($ficha,$local));
					if($validarLocal=="false"){
						$sqll="insert into ficha_cluster_local_temporal 
						value($c,$ficha,$local) 
						on DUPLICATE KEY update id_cluster_temporal='$c', 
						id_ficha=$ficha , id_local=$local";
						$resps[] = DatabaseHelper::modifyData("$sqll", $props);
					}
				}
				$msj="true";
				return $msj;
			}else{
				$msj="No existen locales con esas caracteristicas";
				return $msj;
			}
		}
	
}
function _validarFichaLocalClusterTemporal($f,$l){
	$sql="select * from ficha_cluster_local_temporal
	where id_ficha='$f'and id_local='$l' ";
	$data=DatabaseHelper::fetchAllAsArray($sql);
	if(count($data)!="0"){
		$validar="true";
	}else{
		$validar="false";
	}
	return $validar;

}
function _ficha() {
	$sql = "select * from tabla_ficha_tecnica
	where id_estado=1 ORDER BY  id_ficha desc LIMIT 1";
	return DatabaseHelper::fetchAllAsArray($sql);
}
function _obtener_ClusterTemporales(){
	$ficha=(_ficha());
	if(count($ficha)!="0"){
		$f=$ficha[0]['id_ficha'];
		$sql="select DISTINCT ct.id_cluster_temporal,ct.descripicon_cluster
		from cluster_temporal ct
			INNER JOIN ficha_cluster_local_temporal fclt 
				on ct.id_cluster_temporal=fclt.id_cluster_temporal
				and ct.id_ficha=fclt.id_ficha
		where ct.id_ficha=$f";
		$result = DatabaseHelper::fetchAllAsArray($sql);
		return $result;
	}else{
		echo "no existen fichas generadas o activadas";
	}
}
function _generar_fichaClusterTemporal($data){
	$resps = Array();
	$props = Array();
	$ficha = array_pop($data);
		$resps = Array();
		$props = Array();
		function clean($v) {
			if (!$v) {$v = "null";}
			if (!$v === "0") {$v = "null";}
			return $v;
		}
		foreach($data as $row) {
			$id_local = Array();
			$_productos = Array();
			$updates = Array();
			foreach ($row as $k => $v) {
				$v = clean($v);
				if ($k == "id") {
					$id_local[] = $v;
				}
				if ($k != "id") {
					if ($v !="null") {
						$_productos[$k] = "${v}";
					}else{
						$_productos[$k] = "0";
					}
				}
			}
			foreach ($_productos as $k => $v) {
				$f=$ficha;$c=$id_local[0];
				$p=$k;$v=$v;
				$vEFCT=(_validarFCTE($f,$c,$p));
				if($vEFCT=="false"){
					$resps=(_insertFCTE($f,$c,$p,$v));
				}else{
					$resps=(_updateFCTE($f,$c,$p,$v));
				}
			}
		}
		return json_encode($resps);
	
	return json_encode($resps);
}
function _validarFCTE($f,$l,$p){
	$sql="select * from tabla_ficha_cluster_temporal where id_cluster_temporal=$l
		and id_ficha=$f and id_producto=$p";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	$count=count ($result);
	if($count=="0"){
		return "false";
	}else{
		return "true";
	}

}
function _insertFCTE($f,$l,$p,$v){
	$props=Array();
	$sql="insert into tabla_ficha_cluster_temporal
	(id_tabla,id_cluster_temporal,id_ficha,id_producto,cantidad_producto)
	values ('0','$l','$f','$p','$v')";
	$resps[] = DatabaseHelper::modifyData("$sql", $props);
	return $resps;

}
function _updateFCTE($f,$l,$p,$v){
	$props=Array();
	if($v!="0"){
		$sql="update tabla_ficha_cluster_temporal set cantidad_producto=$v
		where id_ficha=$f and id_cluster_temporal=$l and id_producto=$p";
		$resps[] = DatabaseHelper::modifyData("$sql", $props);
		return $resps;
	}else{
		echo "pieza no actualizada su valor es cero";
	}
	
}
function obtener_detalle_cluster_temporal($codigo){
	$sql="select tft.id_cluster_temporal, ct.descripicon_cluster,id_producto,cantidad_producto
	from tabla_ficha_cluster_temporal tft INNER JOIN cluster_temporal ct
	on tft.id_cluster_temporal=ct.id_cluster_temporal
	where tft.id_ficha=$codigo
	order by tft.id_cluster_temporal,id_producto";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	$Array_de=Array();
	foreach ($result as $key => $value) {
		$Array_de[$value['descripicon_cluster']][$value['id_producto']]=$value['cantidad_producto'];
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
$app->get('/signo/obtenerSignos', function(){
	echo json_encode(_obtenerSignos());
});
$app->get('/atributosCluster/obtener_Atributos_Cluster', function(){
	echo json_encode(_obtenerAtributoCluster());
});
$app->post('/cluster_temporal/generarClusterTemporal', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_generar_cluster_temporal($data));
});
$app->post('/cluster_temporal/generarClusterTemporalAtributo', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_generar_clusterTemporalAtributo($data));
});
$app->post('/cluster_temporal/eliminarClusterTemporalAtributo', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_eliminar_clusterTemporalAtributo($data));
});
$app->post('/cluster_temporal/generarClusterLocalTemporal', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_generar_clusterLocalTemporal($data));
});
$app->post('/definir_cluster_temporal/generarFichaClusterTemporal', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_generar_fichaClusterTemporal($data));
});
$app->post('/cluster_temporal/obtener_detalles_cluster_temp', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(obtener_detalle_cluster_temporal($data));
});
$app->get('/cluster/obtenerClusterTemporales', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_obtener_ClusterTemporales($data));
});
$app->run();

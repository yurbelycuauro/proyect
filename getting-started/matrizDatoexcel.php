<?php include_once "conf_svc.php";
UserHelper::secure();
include_once '../VCRuntime/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
require_once '../phpexcel/php-excel.class.php';
$app = new \Slim\Slim();
$app->response->headers->set('Content-Type', 'text/csv');
$app->response->headers->set('Content-Disposition', 'attachment; filename=prueba.csv');

function _obtenerPieza() {
	$sql = "select * from producto";
	return DatabaseHelper::fetchAllAsArray($sql);
}
$app->get('/piezas/obtenerPieza', function(){
	echo json_encode(_obtenerPieza());
});
function _matriz() {
	$sql = "select * from locales";
	return DatabaseHelper::fetchAllAsArray($sql);
}

$app->get('/locales/obtenerLocales', function(){
	echo json_encode(_matriz());
});
function _ficha() {
	$sql = "select * from tabla_ficha_tecnica ORDER BY  id_ficha desc LIMIT 1";
	return DatabaseHelper::fetchAllAsArray($sql);

}
function _generar_ficha() {
	$props = Array();
	$fecha_actual=date('Y-m-d H:i');
	$sql = "insert into tabla_ficha_tecnica value('','${fecha_actual}')";
	$resps[] = DatabaseHelper::modifyData($sql, $props);
}
$app->post('/ficha/matriz', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	$ultima_ficha =(_ficha());
	$count=count ($ultima_ficha);
	if ($count=="0")
	{
		$ultima_ficha[0]['id_ficha']  = "0";
	}
	$_generar_ficha=(_generar_ficha());
	$codigo=$ultima_ficha[0]['id_ficha']+1;
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
				}
			}
		}
		foreach ($_productos as $k => $v) {
			$sql="INSERT into tabla_ficha (id,id_local,id_producto,cantidad_producto,id_ficha)
				values ('0','$id_local[0]','$k','$v','$codigo')";
				var_dump($sql);
				echo('----');
			$resps[] = DatabaseHelper::modifyData("$sql", $props);
			echo json_encode($resps);
		}
	}
	//$resps[] = DatabaseHelper::modifyData("commit");
	echo json_encode($resps);
});
function _caracteristica_pieza($codigo)
{	
	$sql="select distinct p.nombre_producto,
    	cp.descripcion_caracteristica
		from tabla_ficha tf 
		join producto p on tf.id_producto = p.id_producto 
		join producto_detalle pd on tf.id_producto = pd.id_producto 
		join caracteristica_producto cp on pd.id_caracteristica = cp.id_caracteristica 
		where tf.id_ficha = $codigo
		order by nombre_producto";
	$con = mysqli_connect('localhost', 'app77', 'app77', 'app77');
	$rows = mysqli_query($con,$sql );
	$productos = Array();
    $detalles = Array();
	while ($row = mysqli_fetch_assoc($rows)) {
		$producto = $row["nombre_producto"];
		if (!isset($productos[$producto])) {
			$productos[$producto] = Array();
		}
		$productos[$producto][] = $row["descripcion_caracteristica"];
      	//fputcsv($output, $row);
    }
    return $productos;
    mysqli_close($con);

}
function _cantidadDePiezasPorLocal($codigo)
{	
	$sql="select l.descripcion_local,
		p.nombre_producto,
       	tf.cantidad_producto
  		from tabla_ficha tf 
  		join producto p on tf.id_producto = p.id_producto 
  		join locales l on tf.id_local = l.id_local
 		where tf.id_ficha = $codigo
 		order by l.descripcion_local, p.nombre_producto";
	$con = mysqli_connect('localhost', 'app77', 'app77', 'app77');
	$rows = mysqli_query($con,$sql );
	$local_Piezas = Array();
    while ($row = mysqli_fetch_assoc($rows)) {
    	$local = $row["descripcion_local"];
		if (!isset($local_Piezas[$local])) {
			$local_Piezas[$local] = Array();
		}
		$local_Piezas[$local][] = $row;
      	//fputcsv($output, $row);
    }
   $locales2 = Array();
   foreach ($local_Piezas as $nombre => $registros) {
   		$piezas = Array();
    	foreach ($registros as $nom => $reg) {
    		$local=$reg["descripcion_local"];
    		$locales2[$local]=Array();
    		$nombre_pieza=$reg["nombre_producto"];
    		$cantidad=$reg ["cantidad_producto"];
    		$piezas[$nombre_pieza]=$cantidad;
    		$locales2[$local][]=$piezas;
    	}
    }
    return $locales2; 
    mysqli_close($con);

}
function _caracteristicasPiezas($codigo){
	$sql="select distinct p.nombre_producto,
    	cp.descripcion_caracteristica,pd.id_estado_caracteristica_producto 
		from tabla_ficha tf 
		join producto p on tf.id_producto = p.id_producto 
		join producto_detalle pd on tf.id_producto = pd.id_producto 
		join caracteristica_producto cp on pd.id_caracteristica = cp.id_caracteristica 
		where tf.id_ficha = $codigo
		order by cp.descripcion_caracteristica,p.nombre_producto";
	$con = mysqli_connect('localhost', 'app77', 'app77', 'app77');
	$rows = mysqli_query($con,$sql );
	$caracteristica = Array();
	$row = mysqli_fetch_assoc($rows);
	$comparar=$row["nombre_producto"];
	$i=0;
	$validar=$row["id_estado_caracteristica_producto"];
	$mensaje="No aplica";
	$arr_Caracteristicas=array();
	$arr_Caracteristicas["caracteristica"]=array();
	if($validar=="1"){
		$arr_Caracteristicas["caracteristica"][]=$row["descripcion_caracteristica"];
	}else{
		$arr_Caracteristicas["caracteristica"][]=$mensaje;
	}
	
	$output = fopen('fichero8.csv', 'w');
	$outputBuffer = fopen('fichero8.csv', 'w');
    while ($row = mysqli_fetch_assoc($rows)) {
    	$validar=$row["id_estado_caracteristica_producto"];
    	$i=$i+1;
    	if($i!=1){
    		if($comparar!=$row["nombre_producto"]){
    			if($validar=="1"){
					$arr_Caracteristicas["caracteristica"][]=$row["descripcion_caracteristica"];
				}else{
					$arr_Caracteristicas["caracteristica"][]=$mensaje;
				}
    		}else{
    			$arr_Caracteristicas[]=$arr_Caracteristicas["caracteristica"];
    			$arr_Caracteristicas["caracteristica"]=array();
    			if($validar=="1"){
					$arr_Caracteristicas["caracteristica"][]=$row["descripcion_caracteristica"];
				}else{
					$arr_Caracteristicas["caracteristica"][]=$mensaje;
				}
    		}
    	}else{
    		if($validar=="1"){
				$arr_Caracteristicas["caracteristica"][]=$row["descripcion_caracteristica"];
			}else{
				$arr_Caracteristicas["caracteristica"][]=$mensaje;
			}
    	}
    }
    return $arr_Caracteristicas;

  
}
function _fichacsvTabla($detalles_piezas,$detalles_locales_piezas){
echo"<table border=1>";
echo "<tr>"; 
foreach ($detalles_piezas as $keys => $fuente) {
	echo "<td>";
		echo "<table border=2 >";
			foreach ($fuente as $key => $value) {
				echo"<tr>";
					echo ' '.$value.'<br/>';
				echo "</tr>";
			}
				echo"<tr border=2>";
				 	echo ' '.$keys.'<br/>';
				echo "</tr>";
		echo "</table>";
	echo "</td>";
}
echo "</tr>";
echo '</table>';
echo"<table border=1>";
echo "<tr>"; 
foreach ($detalles_locales_piezas as $key => $fuente) {
	echo"<tr>";
		echo "<td>";
			echo " " . $key . "<br/>";
		echo "</td>";
		echo "<td>";
			echo "<table border=2 >";
			echo "<tr>";
			foreach( $fuente as $key => $value) {
				foreach ($value as $key => $value) {
				echo"<td>";
					 echo ' '.$value.'<br/>';
				echo "</td>";
				}
			}
			echo "</tr>";
			echo"</table>";
		echo"</td>";
	echo"</tr>";
}
echo "</tr>";
echo '</table>';
}
function _fichacsv($codigo){
	$caracteristica=(_caracteristicasPiezas($codigo));
	$detalles_piezas =(_caracteristica_pieza($codigo));
	$detalles_locales_piezas=(_cantidadDePiezasPorLocal($codigo));
	//$output = fopen('fichero8.csv', 'w');
	$outputBuffer = fopen('php://output', 'w');
	
 	/*$tabla=(_fichacsvTabla($detalles_piezas,$detalles_locales_piezas));*/
	$nombrePiezas['piezas']=Array();
	foreach($detalles_piezas as  $index => $val) {
		$nombrePiezas['piezas'][]=$index;
	}
	foreach($caracteristica as $vall) {
		fputcsv($outputBuffer,$vall);
	}
	/*foreach($detalles_piezas as $vall) {
		fputcsv($outputBuffer,$vall);
	}*/
	foreach($nombrePiezas as  $val) {
		fputcsv($outputBuffer,$val);
	}
	foreach($detalles_locales_piezas as $index =>  $val) {
			foreach ($val as $value) {
				$value[]=$index;
			fputcsv($outputBuffer,$value);
		}
	}
	fclose($outputBuffer);
	
	
}

/*function _fichacsv($codigo) {
	$detalles_piezas =(_caracteristica_pieza($codigo));
	$detalles_locales_piezas=(_cantidadDePiezasPorLocal($codigo));
	$sql = "select tf.id_ficha AS id_ficha,
       l.descripcion_local AS descripcion_local,
       p.nombre_producto AS nombre_producto,
       cp.descripcion_caracteristica AS descripcion_caracteristica,
       tf.cantidad_producto AS cantidad_producto
	  from tabla_ficha tf 
	  join locales l on tf.id_local = l.id_local
	  join producto p on tf.id_producto = p.id_producto 
	  join producto_detalle pd on tf.id_producto = pd.id_producto 
	  join caracteristica_producto cp on pd.id_caracteristica = cp.id_caracteristica 
	 where tf.id_ficha = $codigo
	 order by tf.id_ficha, l.descripcion_local, p.nombre_producto, cp.descripcion_caracteristica";
	
	//header('Content-Type: text/csv; charset=utf-8');
    //header('Content-Disposition: attachment; filename=data.csv');
	 var_dump($detalles_piezas);
	 var_dump($detalles_locales_piezas);
    $output = fopen('fichero8.csv', 'w');

    fputcsv($output, array('Local', 'Pieza', 'id_caracteristica','Caracteristica','cantidad','id_ficha'));
    $con = mysqli_connect('localhost', 'app77', 'app77', 'app77');
    $rows = mysqli_query($con,$sql );

    $nombre_productos = Array();
    $locales = Array();
    while ($row = mysqli_fetch_assoc($rows)) {
		$local = $row["descripcion_local"];
		if (!isset($locales[$local])) {
			$locales[$local] = Array();
		}
		$locales[$local][] = $row;
      	//fputcsv($output, $row);
    }
    $locales2 = Array();
    foreach ($locales as $nombre => $registros) {
    	$piezas = Array();
    	foreach ($registros as $row) {
    		$pieza = $row["nombre_producto"];
			if (!isset($piezas[$pieza])) {
				$piezas[$pieza] = Array(
					'caracteristica' => Array(),
					'cantidad' => 0
				);
			}
			$piezas[$pieza]['caracteristica'][] = $row["descripcion_caracteristica"];
			$piezas[$pieza]['cantidad'] = $row["cantidad_producto"];
			$nombre_productos[] = $pieza;
    	}
    	$locales2[$nombre] = $piezas;
    }
    $nombre_productos = array_unique($nombre_productos);
    echo('*******llllll');
    var_dump($nombre_productos);
    // titulos

	var_dump($locales2);
    fclose($output);
    mysqli_close($con);

}*/
function matrizreport() {
	$ultima_ficha =(_ficha());
	$count=count ($ultima_ficha);
	if ($count=="0")
	{
		echo "no existen fichas";
	}else{
		$codigo=$ultima_ficha[0]['id_ficha'];
		$_generar_csv=(_fichacsv($codigo));
	
	}
	// output headers so that the file is downloaded rather than displayed	
}
$app->post('/ficha/matrizreport', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	matrizreport();
});
$app->get('/ficha/matrizreport', function() use ($app) {
	matrizreport();
});

$app->run();

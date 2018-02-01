<?php include_once "conf_svc.php";
UserHelper::secure();
include_once '../VCRuntime/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
require_once '../phpexcel/php-excel.class.php';
$app = new \Slim\Slim();
$app->response->headers->set('Content-Type', 'text/csv');
$app->response->headers->set('Content-Disposition', 'attachment; filename=prueba.csv');

function _obtenerPieza() {
	$ficha=(_ficha());
	$count=count($ficha);
	if($count=="0"){
		echo"No existen fichas debe generar una ficha";
	}else{
		$cod_ficha=$ficha[0]['id_ficha'];
			$sql="SELECT DISTINCT p.id_producto,p.nombre_producto 
				from producto p INNER JOIN ficha_pieza fp 
				on p.id_producto=fp.id_pieza where fp.id_ficha='$cod_ficha'";
				return DatabaseHelper::fetchAllAsArray($sql);
	}
}
$app->get('/piezas/obtenerPieza', function(){
	echo json_encode(_obtenerPieza());
});
function _matriz() {
	$ficha=(_ficha());
	$count=count($ficha);
	if($count=="0"){
		echo"No existen fichas debe generar una ficha";
	}else{
		$cod_ficha=$ficha[0]['id_ficha'];
		$sql="select distinct c.id_cluster,c.descripcion_cluster  
			from cluster c INNER JOIN ficha_cluster fc
			on c.id_cluster=fc.id_cluster where fc.id_ficha='$cod_ficha'";
				return DatabaseHelper::fetchAllAsArray($sql);
	}
}

$app->get('/locales/obtenerLocales', function(){
	echo json_encode(_matriz());
});

function _ficha() {
	$sql = "select * from tabla_ficha_tecnica where id_estado=1 ORDER BY  id_ficha desc LIMIT 1";
	return DatabaseHelper::fetchAllAsArray($sql);
}

function _verificarFicha($id_ficha) {
	$sql = "select DISTINCT id_ficha from tabla_ficha
	where id_ficha=$id_ficha";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	$count=count ($result);
	return $count;
}
function _generar_ficha() {
	$props = Array();
	$fecha_actual=date('Y-m-d H:i');
	$sql = "insert into tabla_ficha_tecnica value('','${fecha_actual}')";
	$resps[] = DatabaseHelper::modifyData($sql, $props);
}

$app->post('/ficha/matriz', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	print_r($data);
	$codigo = array_pop($data);
	$validar=(_verificarFicha($codigo));
		if($validar=="0"){
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
					$sql="INSERT into tabla_ficha (id,id_cluster,id_producto,cantidad_producto,id_ficha)
						values ('0','$id_local[0]','$k','$v','$codigo')";
					$resps[] = DatabaseHelper::modifyData("$sql", $props);
					echo json_encode($resps);
				}
			}
			//$resps[] = DatabaseHelper::modifyData("commit");
			echo json_encode($resps);
		}else{
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
					$f=$codigo;$c=$id_local[0];
					$p=$k;$v=$v;
					$vEFCP=(_validarFCP($f,$c,$p));
					if($vEFCP=="false"){
						$resps=(_insertFCP($f,$c,$p,$v));
					}else{
						$resps=(_updateFCP($f,$c,$p,$v));
					}
				}
			}
			//$resps[] = DatabaseHelper::modifyData("commit");
			echo json_encode($resps);
		}
		
	
	
});
function _validarFCP($f,$c,$p){
	$sql="select * from tabla_ficha where id_cluster=$c
		and id_ficha=$f and id_producto=$p";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	$count=count ($result);
	if($count=="0"){
		return "false";
	}else{
		return "true";
	}

}
function _insertFCP($f,$c,$p,$v){
	$props=Array();
	$sql="insert into tabla_ficha
	(id,id_cluster,id_producto,cantidad_producto,id_ficha)
	values ('0','$c','$p','$v','$f')";
	$resps[] = DatabaseHelper::modifyData("$sql", $props);
	return $resps;

}
function _updateFCP($f,$c,$p,$v){
	$props=Array();
	if($v!="0"){
		$sql="update tabla_ficha set cantidad_producto=$v
		where id_ficha=$f and id_cluster=$c and id_producto=$p";
		$resps[] = DatabaseHelper::modifyData("$sql", $props);
		return $resps;
	}else{
		echo "pieza no actualizada su valor es cero";
	}
	
}
function _generarQueryCPieza($codigo){
	$query="select distinct p.nombre_producto,
    	cp.descripcion_caracteristica
		from tabla_ficha_cluster_temporal tf 
		join producto p on tf.id_producto = p.id_producto 
		join producto_detalle pd on tf.id_producto = pd.id_producto 
		join caracteristica_producto cp on pd.id_caracteristica = cp.id_caracteristica 
		where tf.id_ficha = $codigo";
	return $query;
}
function _caracteristica_pieza($codigo)
{	
	$query=(_generarQueryCPieza($codigo));
	$sql="select distinct p.nombre_producto,
    	cp.descripcion_caracteristica
		from tabla_ficha tf 
		join producto p on tf.id_producto = p.id_producto 
		join producto_detalle pd on tf.id_producto = pd.id_producto 
		join caracteristica_producto cp on pd.id_caracteristica = cp.id_caracteristica 
		where tf.id_ficha = $codigo
		UNION
		select distinct p.nombre_producto,
		cp.descripcion_caracteristica
		from tabla_ficha_especial tfe 
		join producto p on tfe.id_producto = p.id_producto 
		join producto_detalle pd on tfe.id_producto = pd.id_producto 
		join caracteristica_producto cp on pd.id_caracteristica = cp.id_caracteristica 
		where tfe.id_ficha = $codigo
		UNION " .$query. "
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
	$query=(_generarConsultaSQL($codigo));
	$sql="select distinct 
       l._local,l.id_local,
       p.nombre_producto,
       tf.cantidad_producto
  	from locales l 
         inner join cluster c               on l.id_cluster = c.id_cluster
		     INNER JOIN tabla_ficha tf          on c.id_cluster = tf.id_cluster
		     INNER JOIN producto p              on p.id_producto = tf.id_producto
and l.id_local not in (SELECT tfe.id_local from tabla_ficha_especial tfe where tfe.id_ficha=tf.id_ficha)
and l.id_local not in (SELECT fcl.id_local from ficha_cluster_local fcl where fcl.id_ficha=tf.id_ficha)
and l.id_local not in (SELECT tfl.id_local from ficha_cluster_local_temporal tfl where tfl.id_ficha=tf.id_ficha)
		    
	where tf.id_ficha = $codigo 
	UNION
	select distinct 
	       l._local,l.id_local,
	       p.nombre_producto,
	       tfe.cantidad_producto
	from locales l 
		        INNER JOIN tabla_ficha_especial tfe     on l.id_local = tfe.id_local
				INNER JOIN producto p                   on p.id_producto = tfe.id_producto
				     
	where tfe.id_ficha = $codigo 
UNION
	select distinct 
       l._local,l.id_local,
       p.nombre_producto,
       case
        	when tfct.cantidad_producto = 0 then
				 (select tf.cantidad_producto from tabla_ficha tf where 
					tf.id_cluster= (select l.id_cluster from locales l where l.id_local=fclt.id_local ) 
					and tf.id_ficha=fclt.id_ficha
					and tf.id_producto=tfct.id_producto)
										 
          	else tfct.cantidad_producto
          	end as cantidad_producto
    from ficha_cluster_local_temporal fclt
	INNER JOIN tabla_ficha_cluster_temporal tfct 
										on fclt.id_cluster_temporal=tfct.id_cluster_temporal
	INNER JOIN locales l                on l.id_local=fclt.id_local
    INNER JOIN producto p               on p.id_producto=tfct.id_producto
    where tfct.id_ficha=$codigo
    and l.id_local not in (SELECT tfe.id_local from tabla_ficha_especial tfe where tfe.id_ficha=tfct.id_ficha) 
	ORDER BY _local,nombre_producto";
	//print_r($sql);
	//die();
	$con = mysqli_connect('localhost', 'app77', 'app77', 'app77');
	$rows = mysqli_query($con,$sql );
	$local_Piezas = Array();
    while ($row = mysqli_fetch_assoc($rows)) {
    	$local = $row["id_local"];
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
    		$local=$reg["id_local"];
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
function _generarQueryCP($codigo){
	$query="select distinct p.nombre_producto,
	CONCAT(cp.descripcion_caracteristica,' ', pd.valor) 
	as descripcion_caracteristica ,
	pd.id_estado_caracteristica_producto,
	cp.descripcion_caracteristica as dc
		from tabla_ficha_cluster_temporal tf join producto p 
			on tf.id_producto = p.id_producto 
		join producto_detalle pd 
			on tf.id_producto = pd.id_producto 
		join caracteristica_producto cp 
			on pd.id_caracteristica = cp.id_caracteristica
	where tf.id_ficha = $codigo";
	return $query;
}
function _caracteristicasPiezas($codigo){
	$query=(_generarQueryCP($codigo));
	$sql="select distinct p.nombre_producto,
	CONCAT(cp.descripcion_caracteristica,' ', pd.valor) 
	as descripcion_caracteristica ,
	pd.id_estado_caracteristica_producto,
	cp.descripcion_caracteristica as dc
		from tabla_ficha tf join producto p 
			on tf.id_producto = p.id_producto 
		join producto_detalle pd 
			on tf.id_producto = pd.id_producto 
		join caracteristica_producto cp 
			on pd.id_caracteristica = cp.id_caracteristica
	where tf.id_ficha = $codigo  
	UNION
	select distinct p.nombre_producto,
	CONCAT(cp.descripcion_caracteristica,' ', pd.valor)
	as descripcion_caracteristica ,
	pd.id_estado_caracteristica_producto, 
	cp.descripcion_caracteristica as dc
		from tabla_ficha_especial tfe 
		join producto p
			on tfe.id_producto = p.id_producto 
		join producto_detalle pd 
			on tfe.id_producto = pd.id_producto 
		join caracteristica_producto cp 
			on pd.id_caracteristica = cp.id_caracteristica
	where tfe.id_ficha = $codigo
	UNION ".$query."
	order by dc,nombre_producto";
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
    		
    	}
    }
   /*var_dump($arr_Caracteristicas);
   echo('***caracteristica******')*/
   return $arr_Caracteristicas;

  
}

function _fichacsv($codigo){
	$atributos_local=(_generarConsultaSQL($codigo));
	$caracteristica=(_caracteristicasPiezas($codigo));
	$detalles_piezas =(_caracteristica_pieza($codigo));
	$detalles_locales_piezas=(_cantidadDePiezasPorLocal($codigo));
	$neb=(_especiosEnBlancos($codigo));
	$atributos_local=(_generarConsultaSQL($codigo));
	$atributos= explode(",",$atributos_local);
	if(count($neb)!="0"){
		$n=$neb[0]['total'];
		for($i=0; $i<$n; $i++){
			$a[$i]="";
		}
	}
	
	//$output = fopen('fichero8.csv', 'w');
	$outputBuffer = fopen('php://output', 'w');
	
 	$nombrePiezas['piezas']=Array();
	foreach($detalles_piezas as  $index => $val) {
		$nombrePiezas['piezas'][]=$index;
	}
	$exc = Array();
	foreach($caracteristica as $index=> $vall) {
		$exc[] = array_merge($a,$vall);
	}
	foreach ($exc as $key => $value) {
		fputcsv($outputBuffer,$value);
	}

	// iterar por nombre piezas
	$ex=Array();
	foreach($nombrePiezas as $val) {
		$columna_blanco= "";
		$ex[]=array_merge($atributos,$val);
		array_unshift($val, $columna_blanco);
		fputcsv($outputBuffer,$ex[0]);
	}

	$excel = Array();
	// iterar por cada local
	foreach($detalles_locales_piezas as $index => $local) {
		// sacar detalle del local
		$detalle = (_detalleLocal($index,$codigo));
		$detalleLocal=$detalle[0];
		$detallePiezas=$local[0];
		$excel[] = array_merge($detalleLocal, $detallePiezas);
	}
	foreach ($excel as $key => $value) {
		fputcsv($outputBuffer,$value);
	}
	
	fclose($outputBuffer);
	
	
}
function _especiosEnBlancos($ficha){
	$sql="SELECT (COUNT(DISTINCT descripcion_atributo_local))  as total FROM ficha_atributo_local where id_ficha=$ficha";
	$result = DatabaseHelper::fetchAllAsArray($sql);
    return $result;
}
function _detalleLocal($local,$ficha){
   $query=(_generarConsultaSQL($ficha));
   $sql="select ".$query." from locales where id_local=$local";
   $result = DatabaseHelper::fetchAllAsArray($sql);
   return $result; 

}
function _generarConsultaSQL($codigo){
	$sql="select * from ficha_atributo_local where id_ficha=$codigo
	ORDER BY descripcion_atributo_local desc";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	$count=count ($result);
	if($count!=0){
		$query=Array();
		foreach ($result as $key => $value) {
			$query[]=$value['descripcion_atributo_local'];
		}
		$cols = implode(",", $query);
		$texto=str_replace('"','',$cols);
		return $texto;
	}else{
		$props=Array();
		$sql="insert into ficha_atributo_local
			(id_ficha,descripcion_atributo_local)
			value('$codigo','_local') on duplicate key
			update id_ficha='$codigo',
			descripcion_atributo_local='$codigo'";
		$resps[] = DatabaseHelper::modifyData($sql, $props);
		$sql="select * from ficha_atributo_local where id_ficha=$codigo
		ORDER BY descripcion_atributo_local desc";
		$result = DatabaseHelper::fetchAllAsArray($sql);
		$count=count ($result);
		if($count!=0){
			$query=Array();
			foreach ($result as $key => $value) {
				$query[]=$value['descripcion_atributo_local'];
			}
			$cols = implode(",", $query);
			$texto=str_replace('"','',$cols);
			return $texto;
		}
		echo"No se puede generar la consulta no existen atributos";
	}

}
function _ficha_registrada()
{
	$sql="select tft.id_ficha from tabla_ficha_tecnica tft
			INNER JOIN tabla_ficha tf on tft.id_ficha=tf.id_ficha
			where tft.id_estado='1'
		UNION
		select tft.id_ficha from tabla_ficha_tecnica tft
			INNER JOIN tabla_ficha_especial tfe on tft.id_ficha=tfe.id_ficha
			where tft.id_estado='1'
		UNION 
		select tft.id_ficha from tabla_ficha_tecnica tft
			INNER JOIN tabla_ficha_cluster_temporal tfct 
				on tft.id_ficha=tfct.id_ficha
			where tft.id_estado='1'
		ORDER BY  id_ficha desc LIMIT 1";
	return DatabaseHelper::fetchAllAsArray($sql);
}
function matrizreport() {
	$ultima_ficha =(_ficha_registrada());
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
function _generar_fichaEspecial($data){
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
				$vEFCP=(_validarFCPE($f,$c,$p));
				if($vEFCP=="false"){
					$resps=(_insertFCPE($f,$c,$p,$v));
				}else{
					$resps=(_updateFCPE($f,$c,$p,$v));
				}
			}
		}
			echo json_encode($resps);
	
	echo json_encode($resps);
}

function _generar_fichaLocal($data){
	$resps = Array();
	$props = Array();
	$ultima_ficha =(_ficha());
	$count=count ($ultima_ficha);
	if ($count=="0")
	{
		echo"No existen fichas";
	}else{
	$ficha=$ultima_ficha[0]['id_ficha'];
	$validar=(_verificarFicha($ficha));
		if($validar=="0"){
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
					$sql="INSERT into tabla_ficha_local (id,id_local,id_producto,cantidad_producto,id_ficha)
						values ('0','$id_local[0]','$k','$v','$ficha')";
					$resps[] = DatabaseHelper::modifyData("$sql", $props);
					echo json_encode($resps);
				}
			}
		}else{
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
					$vEFCP=(_validarTFL($f,$c,$p));
					if($vEFCP=="false"){
						$resps=(_insertTFL($f,$c,$p,$v));
					}else{
						$resps=(_updateTFL($f,$c,$p,$v));
					}
				}
			}
			//$resps[] = DatabaseHelper::modifyData("commit");
			echo json_encode($resps);

		}
		
	}
	echo json_encode($resps);
}
function _validarFCPE($f,$l,$p){
	$sql="select * from tabla_ficha_especial where id_local=$l
		and id_ficha=$f and id_producto=$p";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	$count=count ($result);
	if($count=="0"){
		return "false";
	}else{
		return "true";
	}

}
function _insertFCPE($f,$l,$p,$v){
	$props=Array();
	$sql="insert into tabla_ficha_especial
	(id,id_local,id_producto,cantidad_producto,id_ficha)
	values ('0','$l','$p','$v','$f')";
	$resps[] = DatabaseHelper::modifyData("$sql", $props);
	return $resps;

}
function _updateFCPE($f,$l,$p,$v){
	$props=Array();
	if($v!="0"){
		$sql="update tabla_ficha_especial set cantidad_producto=$v
		where id_ficha=$f and id_local=$l and id_producto=$p";
		$resps[] = DatabaseHelper::modifyData("$sql", $props);
		return $resps;
	}else{
		echo "pieza no actualizada su valor es cero";
	}
	
}
function _validarTFL($f,$l,$p){
	$sql="select * from tabla_ficha_local where id_local=$l
		and id_ficha=$f and id_producto=$p";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	$count=count ($result);
	if($count=="0"){
		return "false";
	}else{
		return "true";
	}

}
function _insertTFL($f,$l,$p,$v){
	$props=Array();
	$sql="insert into tabla_ficha_local
	(id,id_local,id_producto,cantidad_producto,id_ficha)
	values ('0','$l','$p','$v','$f')";
	$resps[] = DatabaseHelper::modifyData("$sql", $props);
	return $resps;

}
function _updateTFL($f,$l,$p,$v){
	$props=Array();
	if($v!="0"){
		$sql="update tabla_ficha_local set cantidad_producto=$v
		where id_ficha=$f and id_local=$l and id_producto=$p";
		$resps[] = DatabaseHelper::modifyData("$sql", $props);
		return $resps;
	}else{
		echo "pieza no actualizada su valor es cero";
	}
	
}
$app->post('/ficha/matrizFichaLocal', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_generar_fichaLocal($data));
});
$app->post('/ficha/matrizEspecial', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_generar_fichaEspecial($data));
});
$app->post('/ficha/matrizreport', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	matrizreport();
});
$app->get('/ficha/matrizreport', function() use ($app) {
	matrizreport();
});

$app->run();

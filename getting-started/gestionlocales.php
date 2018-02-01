<?php include_once "conf_svc.php";
UserHelper::secure();
include_once '../VCRuntime/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
require_once '../phpexcel/php-excel.class.php';
$app = new \Slim\Slim();
$app->response->headers->set('Content-Type', 'text/csv');
$app->response->headers->set('Content-Disposition', 'attachment; filename=prueba.csv');

function _obtenerDivision() {
	$sql = "select * from division";
	return DatabaseHelper::fetchAllAsArray($sql);
}

function _obtenerClusterPorUltimaFichaGenerada() {
	$ultima_ficha =(_ficha());
	$count=count ($ultima_ficha);
	if ($count=="0")
	{
		echo "No existen ficha debe generar una ficha para continuar";
	}else{
		$ficha=$ultima_ficha[0]['id_ficha'];
			$sql="select c.id_cluster, c.descripcion_cluster,f.descripcion_ficha,f.id_ficha
				from cluster c  INNER JOIN ficha_cluster fc on c.id_cluster=fc.id_cluster
				INNER JOIN tabla_ficha_tecnica f on fc.id_ficha= f.id_ficha
				WHERE fc.id_ficha=$ficha";
			$resul= DatabaseHelper::fetchAllAsArray($sql);
			$total=count($resul);
			/*if($total!="0"){
				$nom_fich=Array ();
				foreach ($resul as $key => $value) {
					$nom_fich["descripcion_cluster"]=$value["descripcion_ficha"];
				}
				array_unshift($resul, $nom_fich);
			}*/
			return $resul;
		
	}
}

function _obtenerClusterDadoFicha($codigo) {
	$sql="select distinct c.id_cluster,c.descripcion_cluster,fc.id_ficha
		from tabla_ficha tf 
		INNER JOIN ficha_cluster fc on tf.id_cluster=fc.id_cluster
									and tf.id_ficha=fc.id_ficha 
		INNER JOIN cluster 			c on c.id_cluster=fc.id_cluster
		where tf.id_ficha=$codigo";
	return DatabaseHelper::fetchAllAsArray($sql);
}
function _obtenerClusterTemporalesDadoFicha($codigo) {
	$sql="select distinct ct.id_cluster_temporal,ct.descripicon_cluster,tfct.id_ficha
		from cluster_temporal ct 
		INNER JOIN tabla_ficha_cluster_temporal tfct
								on ct.id_cluster_temporal=tfct.id_cluster_temporal
		  						and ct.id_ficha=tfct.id_ficha
		where tfct.id_ficha=$codigo";
	return DatabaseHelper::fetchAllAsArray($sql);
}


function _obtenerCluster($codigo) {
	$sql = "select c.id_cluster,c.descripcion_cluster as des_clus ,c.id_division from cluster c where id_division=$codigo";
	return DatabaseHelper::fetchAllAsArray($sql);
}

function _obtenerLocales($codigo) {
	$sql = "select * from locales where id_cluster=$codigo";
	return DatabaseHelper::fetchAllAsArray($sql);
}

function _ficha() {
	$sql = "select * from tabla_ficha_tecnica
	where id_estado=1 ORDER BY  id_ficha desc LIMIT 1 ";
	return DatabaseHelper::fetchAllAsArray($sql);
}

function _verificarFicha($id_ficha) {
	$sql = "select DISTINCT id_ficha from tabla_ficha
	where id_ficha=$id_ficha";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	$count=count ($result);
	return $count;
}
function _verificarLocal_FC($id_ficha,$local) {
	$validar;
	$sql = "select id_cluster from locales where id_local='$local'";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	$count=count ($result);
	if($count!="0"){
		$cluster=$result[0]['id_cluster'];
		$sql="select id_cluster from ficha_cluster
		where id_cluster=$cluster and id_ficha=$id_ficha";
		$res = DatabaseHelper::fetchAllAsArray($sql);
		$ct=count ($res);
		if($ct=="0"){
			$validar="false";
		}else{
			$validar="true"; 
		}
	}else{
		echo"0";
	}
	return $validar;
}

function _vereficarLocal_FCL($ficha,$local){
	$validar;
	$sql="select * from ficha_cluster_local
	where id_local=$local
	and id_ficha=$ficha";
	$res = DatabaseHelper::fetchAllAsArray($sql);
	$ct=count ($res);
	if($ct=="0"){
		$validar="false";
	}else{
		$validar="true";
	}
	return $validar;
}

function _vereficarLocal_FL($ficha,$local){
	$validar;
	$sql="select * from ficha_local
	where id_local=$local and id_ficha=$ficha";
	$res = DatabaseHelper::fetchAllAsArray($sql);
	$ct=count ($res);
	if($ct=="0"){
		$validar="false";
	}else{
		$validar="true";
	}
	return $validar;
}
function _obtenerDivisionRegistrada($codigo){
	$sql="select distinct c.id_division from ficha_cluster fc
	INNER JOIN cluster c
	on fc.id_cluster=c.id_cluster 
	where fc.id_ficha=$codigo";
	$res = DatabaseHelper::fetchAllAsArray($sql);
	return $res;

}
function _obtenerDivisionDadoUnCluster($codigo){
	$sql="select id_division 
	from cluster where id_cluster=$codigo";
	$res = DatabaseHelper::fetchAllAsArray($sql);
	return $res;

}
function _generarFichaCluster($codigo) {
	$ultima_ficha =(_ficha());
	$count=count ($ultima_ficha);
	$props = Array();
	if ($count=="0")
	{
		$msj="No existen ficha debe generar una ficha para continuar";
		return $msj;
	}else{
		$ficha=$ultima_ficha[0]['id_ficha'];
		$divisionRegistrada=(_obtenerDivisionRegistrada($ficha));
		$divisionCluster=(_obtenerDivisionDadoUnCluster($codigo));
		if(count($divisionRegistrada)==0){
			$sql="insert into ficha_cluster (id_ficha,id_cluster)
				values ('$ficha','$codigo')
				on duplicate key update id_ficha='$ficha',id_cluster='$codigo'";
			$resps[] = DatabaseHelper::modifyData("$sql", $props);
			return true;
		}else{
			$dr=$divisionRegistrada[0]['id_division'];
			$dc=$divisionCluster[0]['id_division'];
			if($dr==$dc){
				$sql="insert into ficha_cluster (id_ficha,id_cluster)
				values ('$ficha','$codigo')
				on duplicate key update id_ficha='$ficha',id_cluster='$codigo'";
				$resps[] = DatabaseHelper::modifyData("$sql", $props);
				return true;
			}else{
				return "No puede registrar el cluster pertenece a otra division, Verifique e intente de nuevo"; 
			}
		}
	}
}

function _generarFichaLocal($local){
	$ultima_ficha =(_ficha());
	$count=count ($ultima_ficha);
	$props = Array();
	if ($count=="0")
	{
		echo "No existen ficha debe generar una ficha para continuar";
	}else{
		$ficha=$ultima_ficha[0]['id_ficha'];
		$validar_ficha=(_verificarFicha($ficha));
		$vrc=(_verificarLocal_FC($ficha,$local));
		if($vrc=="true"){
			$vfl=(_vereficarLocal_FL($ficha,$local));
			if($vfl=="false"){
				$sql="insert into ficha_cluster_local
				(id_ficha,id_local)
				values($ficha,$local)
				on duplicate key update
				id_ficha=$ficha , id_local=$local";
			$resps[] = DatabaseHelper::modifyData($sql, $props);
			echo "true";

			}else{
				echo"No se puede registrar el local ya que se encuentra registrado de forma unitaria";
			}

		}else{
			echo "No se puede eliminar el local del cluster ya que el cluster no se encuentra registrado";
		}
		
	}
}



function _obtenerLocalesEspeciales(){
	$ultima_ficha =(_ficha());
	$count=count ($ultima_ficha);
	$props = Array();
	if ($count=="0")
	{
		echo "No existen ficha debe generar una ficha para continuar";
	}else{
		$ficha=$ultima_ficha[0]['id_ficha'];
		$sql="select l.id_local,l._local
		from locales l
			INNER JOIN ficha_cluster_local fcl	on l.id_local=fcl.id_local  
		where fcl.id_ficha=$ficha
		ORDER BY l._local";
		$result = DatabaseHelper::fetchAllAsArray($sql);
		return $result;
	}
}
function _obtenerFichaLocales_Especiales_Unitarios(){
	$ultima_ficha =(_ficha());
	$count=count ($ultima_ficha);
	$props = Array();
	if ($count=="0")
	{
		echo "No existen ficha debe generar una ficha para continuar";
	}else{
		$ficha=$ultima_ficha[0]['id_ficha'];
		$sql="select DISTINCT l.id_local,l._local
		from locales l
			INNER JOIN ficha_cluster_local fcl	on l.id_local=fcl.id_local
			INNER JOIN tabla_ficha_especial tfe on tfe.id_local=fcl.id_local
			and tfe.id_ficha=fcl.id_ficha
		where fcl.id_ficha=$ficha
		UNION
		select  DISTINCT l.id_local,l._local
		from locales l
			INNER JOIN ficha_local fl	on l.id_local=fl.id_local
			INNER JOIN tabla_ficha_especial tfe on tfe.id_local=fl.id_local
			and tfe.id_ficha=fl.id_ficha
		where fl.id_ficha=$ficha
		ORDER BY _local";
		$result = DatabaseHelper::fetchAllAsArray($sql);
		return $result;
	}
}
function _generarFichaLocalUnitarios($local){
	$ultima_ficha =(_ficha());
	$count=count ($ultima_ficha);
	$props = Array();
	if ($count=="0")
	{
		echo "No existen ficha debe generar una ficha para continuar";
	}else{
		$ficha=$ultima_ficha[0]['id_ficha'];
		$vfc=(_verificarLocal_FC($ficha,$local));
		if($vfc=="false"){
			$vfcl=(_vereficarLocal_FCL($ficha,$local));
			if($vfcl=="false"){
				$sql="insert into ficha_local
				(id_ficha,id_local)
				values($ficha,$local)
				on duplicate key update 
				id_ficha=$ficha , id_local=$local";
			$resps[] = DatabaseHelper::modifyData($sql, $props);
			echo "true";
			}else{
				echo "No se puede registrar el local ya que se encuenta registrado como local especial";
			}
		}else{
			echo"No se puede registrar el local ya que se encuentra el cluster registrado";
		}
	}
}
function _obtenerFichaLocal(){
	$ultima_ficha =(_ficha());
	$count=count ($ultima_ficha);
	$props = Array();
	if ($count=="0")
	{
		echo "No existen ficha debe generar una ficha para continuar";
	}else{
		$ficha=$ultima_ficha[0]['id_ficha'];
		$sql="select l.id_local,l._local
		from locales l  INNER JOIN ficha_local fl	on l.id_local=fl.id_local  
		where fl.id_ficha=$ficha
		ORDER BY l._local";
		$result = DatabaseHelper::fetchAllAsArray($sql);
		return $result;
	}
}
function _obtenerAtributosLocal($data){
	$sql="select COLUMN_NAME 
	from information_schema.COLUMNS
	where TABLE_SCHEMA  LIKE 'app77'
	and TABLE_NAME = 'locales'";
	$result = DatabaseHelper::fetchAllAsArray($sql);
	return $result;
}

function _generarAtributoLocal_($dato){

	$props=Array();
	$cdf=$dato[0];
	$dca=$dato[1];
	$sql="insert into ficha_atributo_local
	(id_ficha,descripcion_atributo_local)
	value('$cdf','$dca') on duplicate key
	update id_ficha='$cdf',
	descripcion_atributo_local='$dca' ";
	$resps[] = DatabaseHelper::modifyData($sql, $props);
	echo "true";


}
function _generarAtributoLocal($dato){
	$cdf=array_pop($dato);
	$props=Array();
	foreach ($dato as $key => $value) {
		if(!isset($value['local'])){

		}else{
			$dca=$value['id'];
			$sql="insert into ficha_atributo_local
			(id_ficha,descripcion_atributo_local)
			value('$cdf','$dca') on duplicate key
			update id_ficha='$cdf',
			descripcion_atributo_local='$dca' ";
			$resps[] = DatabaseHelper::modifyData($sql, $props);
			
		}

	}
	echo "true";
}
function _eliminarAtributoLocal($dato){
	$props=Array();
	$cdf=$dato[0];
	$dca=$dato[1];
	$sql="delete from ficha_atributo_local where
	id_ficha=$cdf and descripcion_atributo_local='$dca'";
	$resps[] = DatabaseHelper::modifyData($sql, $props);
	echo "true";


}
function _eliminarFichaCluster($codigo){
	$props=Array();
	$ultima_ficha =(_ficha());
	$count=count ($ultima_ficha);
	if ($count=="0")
	{
		echo "No existen ficha debe generar una ficha para continuar";
	}else{
		$ficha=$ultima_ficha[0]['id_ficha'];
		$vrcf=(_validarRegistroCluster($codigo));
		if(count($vrcf)=="0"){
			$sql="delete from ficha_cluster where id_cluster=$codigo
			and id_ficha=$ficha";
			$resps[] = DatabaseHelper::modifyData($sql, $props);
			echo "true";
		}else{
			echo "No se puede eliminar el cluster, ya que se encuentra registrado 
			en la ficha";

		}
		
	}
}
function _validarRegistroCluster($codigo){
	$props=Array();
	$ultima_ficha =(_ficha());
	$count=count ($ultima_ficha);
	if ($count=="0")
	{
		echo "No existen ficha debe generar una ficha para continuar";
	}else{
		$ficha=$ultima_ficha[0]['id_ficha'];
		$sql="select * from tabla_ficha where id_ficha=$ficha 
		and id_cluster=$codigo";
		$result = DatabaseHelper::fetchAllAsArray($sql);
		return $result;
	}

}
function _eliminarFichaLocal($codigo){
	$props=Array();
	$ultima_ficha =(_ficha());
	$count=count ($ultima_ficha);
	if ($count=="0")
	{
		echo "No existen ficha debe generar una ficha para continuar";
	}else{
		$ficha=$ultima_ficha[0]['id_ficha'];
		$vrfl=(_validarRegistroDeLocal($codigo));
		if(count($vrfl)=="0"){
			$sql="delete from ficha_cluster_local where id_local=$codigo
			and id_ficha=$ficha";
			$resps[] = DatabaseHelper::modifyData($sql, $props);
			$sql1="delete from ficha_local where id_local=$codigo
			and id_ficha=$ficha";
			$resps[] = DatabaseHelper::modifyData($sql1, $props);
			return "true";

		}else{
			return "no se puede eliminar el local, ya se encuentra registrado en la ficha";

		}
		
	}
}

function _validarRegistroDeLocal($codigo){
	$props=Array();
	$ultima_ficha =(_ficha());
	$count=count ($ultima_ficha);
	if ($count=="0")
	{
		echo "No existen ficha debe generar una ficha para continuar";
	}else{
		$ficha=$ultima_ficha[0]['id_ficha'];
		$sql="select * from tabla_ficha_especial where id_ficha=$ficha 
		and id_local=$codigo";
		$result = DatabaseHelper::fetchAllAsArray($sql);
		return $result;
	}

}

$app->get('/gestionlocal/obtener_division', function(){
	echo json_encode(_obtenerDivision());
});

$app->get('/gestionlocal/obtenerclusterPorFicha', function(){
	echo json_encode(_obtenerClusterPorUltimaFichaGenerada());
});
$app->post('/gestionlocal/obtenerFichaLocal', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_obtenerFichaLocal());
	//****tabla:ficha_local
});
$app->post('/gestionfichalocal/generar_ficha_local_unitarios', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_generarFichaLocalUnitarios($data));
});
/*obtine todos los locales especiales y unitarios para eliminar*/
$app->post('/gestionlocal/obtener_ficha_locales', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_obtenerFichaLocales_Especiales_Unitarios());
});
$app->post('/gestionlocal/obtener_locales_especiales', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_obtenerLocalesEspeciales());
});

$app->post('/gestionfichalocal/generar_ficha_cluster_local', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_generarFichaLocal($data));
});

$app->post('/gestionficha/generar_ficha_cluster', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_generarFichaCluster($data));
});

$app->post('/gestionlocal/obtener_locales', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_obtenerLocales($data));
});

$app->post('/gestionlocal/obtenerClusterDadoFicha', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_obtenerClusterDadoFicha($data));
});
$app->post('/gestionlocal/obtenerClusterTemporalesDadoFicha', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_obtenerClusterTemporalesDadoFicha($data));
});

$app->post('/gestionlocal/obtener_cluster', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_obtenerCluster($data));
});
$app->get('/gestionLocales/obtener_atributos_local', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_obtenerAtributosLocal($data));
});
$app->post('/gestionlocal/generar_atributo_local', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_generarAtributoLocal($data));
});
$app->post('/gestionlocal/eliminar_atributo_local', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_eliminarAtributoLocal($data));
});

$app->post('/gestionfichalocal/eliminar_FichaCluster', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_eliminarFichaCluster($data));
});
$app->post('/gestionfichalocal/eliminar_FichaLocal', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_eliminarFichaLocal($data));
});






$app->run();

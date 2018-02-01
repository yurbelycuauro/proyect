<?php include_once "conf_svc.php";
UserHelper::secure();

include_once '../VCRuntime/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
require_once '../phpexcel/php-excel.class.php';
$app = new \Slim\Slim();
$app->response->headers->set('Content-Type', 'application/json');

function _obtenerJstrree(){
	$sql = "select * from division ";
	$resps = DatabaseHelper::fetchAllAsArray($sql);
	$data=array();
	$info_div=array();
	foreach ($resps as $key => $value) {
		$codigo=$value['id_division'];
		$info_div['id']=$codigo;
		$info_div['parent']="#";
		$info_div['text']=$value['descripcion_division'];
		//$info_div['icon']="http://jstree.com/tree.png" ;
		$data[]=$info_div;
		$form=_obtenerFormatos($codigo);
		if(count($form)>=1){
			$info_clust=array();
			foreach ($form as $ke => $val) {
				$cod_clus=$val['id_cluster'];
				$info_clust['id']="cu-".$val['id_cluster'];
				$info_clust['parent']=$codigo;
				$info_clust['text']=$val['descripcion_cluster'];
				//$info_clust['icon']="http://jstree.com/tree.png" ;
				$data[]=$info_clust;
				$locales=_obtenerLocales($cod_clus);
				if(count($locales)>=1){
					$parent=$val['id_cluster'];
					$info=array();
					foreach ($locales as $k => $v) {
						$info['id']="lo-".$v['id_local']."_".$val['id_cluster'];
						$info['parent']="cu-".$val['id_cluster'];
						$info['text']=$v['_local'];
						//$info['icon']="http://jstree.com/tree.png" ;	
						$data[]=$info;
					}

				}
			}
		}
	}
	return $data;
}

function _obtenerFormatos($codigo){
	$sql="select * from cluster where id_division='$codigo' ";
	$resps = DatabaseHelper::fetchAllAsArray($sql);
	return $resps;
}
function _obtenerLocales($codigo){
	$sql="select * from locales where id_cluster='$codigo' ";
	$resps = DatabaseHelper::fetchAllAsArray($sql);
	return $resps;
}
function _generarjstrree($data){
	$arbol=$data[1];
	$cluster_=array();
	foreach ($arbol as $key => $value) {
		$v=$value;
		$r = substr($value, 0, 2);
		switch ($r) {
			case "cu":
					$cl=explode("-", $value);
					$vc=$cl[1];
					$cluster_[]=$vc;
			break;
			case "lo":
					$cl=substr($value,-1);
					$dt1 = explode('-', $value);
					$dat2= explode("_", $dt1[1]);
					$data_c[$cl][]=$dat2[0];
			break;
		}
	}
	$data_c['ficha']=$data[0];
	$registro=gestion_plantilla($data_c);
	return true;
}

function gestion_plantilla($data){
	$props = Array();
	$fecha_actual=date('Y-m-d H:i');
	$mysqli = new mysqli("localhost", "app77", "app77", "app77");
	/* check connection */
	if (mysqli_connect_errno()) {
		printf("Error de conexión: %s\n", mysqli_connect_error());
		exit();
	}
		$ficha=$data['ficha'];
		$query = "insert into tabla_ficha_tecnica value(NULL,'${fecha_actual}','$ficha','1')";
		$mysqli->query($query);
		$cdf=$mysqli->insert_id;
		$query="update tabla_ficha_tecnica set id_estado=0
		where id_ficha<>$cdf";
		$mysqli->query($query);
	
	foreach ($data as $key => $value) {
		if($key!="ficha"){
			$cluster=registrar_cluster_ficha($cdf,$key);
			foreach ($value as $k => $val) {
				$local=registrar_locales_seleccionados($cdf,$val);
			}
			$locales_excluidos=registrar_locales_excluidos($cdf,$value);

		}
	}

		
}

function registrar_cluster_ficha($ficha,$cluster){
	$props=array();
	$sql="insert into ficha_cluster (id_ficha,id_cluster)
	values ('$ficha','$cluster')
	on duplicate key update id_ficha='$ficha',id_cluster='$cluster'";
	$resps[] = DatabaseHelper::modifyData("$sql", $props);
	return true;
}

function registrar_locales_seleccionados($ficha,$local){
	$props=array();
	$sql="insert into ficha_local
	(id_ficha,id_local)
	values($ficha,$local)
	on duplicate key update 
	id_ficha=$ficha , id_local=$local";
	$resps[] = DatabaseHelper::modifyData($sql, $props);
	return "true";
}

function registrar_locales_excluidos($ficha,$data){
	$props=array();
	$sql="select * from locales l inner join ficha_cluster fc
				on l.id_cluster=fc.id_cluster
				and fc.id_ficha='$ficha'
				and l.id_local not in (SELECT flc.id_local from ficha_local flc where flc.id_ficha='$ficha' )";
	$resul= DatabaseHelper::fetchAllAsArray($sql);
	$t=count($resul);
	if($t>0){
		foreach ($resul as $key => $value) {
			$local=$value['id_local'];
			$sql="insert into ficha_cluster_local
				(id_ficha,id_local)
				values($ficha,$local)
				on duplicate key update
				id_ficha=$ficha , id_local=$local";
			$resps[] = DatabaseHelper::modifyData($sql, $props);
		}
	}
	return $data;
}

$app->get('/jstrree/obtenerJstrree', function() use ($app) {
	echo json_encode(_obtenerJstrree());
});

$app->post('/jstrree/generarjstrree', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	echo json_encode(_generarjstrree($data));
});
$app->run();

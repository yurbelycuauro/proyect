<?php include_once "conf_svc.php";
UserHelper::secure();

include_once '../VCRuntime/Slim/Slim.php';
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();
$app->response->headers->set('Content-Type', 'application/json');


function _get_uf($year, $month, $day) {
	$date = date('Y-m-d', strtotime("${year}-${month}-${day}"));
	$from_db = DatabaseHelper::fetchOneAsArray("select v, d from vc_uf where d = ':date:'", array('date'=>$date));

	if (count($from_db) > 0) {
		return $from_db;
	} else {
		$uf_url = "http://api.sbif.cl/api-sbifv3/recursos_api/uf/$year/$month/dias/$day?apikey=2bb2115923d16a1435dbe61ce995fe2c70fc93fb&formato=json";
		$data = file_get_contents($uf_url);
		$json = json_decode($data);
		$ret = Array();
		$n =  $json->UFs[0]->Valor;
		$old = Array('.', ',');
		$new = Array('', '.');
		$ret['v'] = +str_replace($old, $new, $n);
		$ret['d'] = $json->UFs[0]->Fecha;
		DatabaseHelper::modifyData("insert into vc_uf (d, v) values (':d:',:v:)", $ret);
		return $ret;
	}
}

function _resumen_consumo($id, $ano, $mes) {
	$periodo = "${ano}-${mes}-01";
	$id_contrato = "${id}";

	$sql_costos_fijos ="
	select *
		from vc_costos_fijos
		where periodo = ':periodo:'
	";
	$costos_fijos = DatabaseHelper::fetchOneAsArray($sql_costos_fijos, Array('periodo'=>$periodo));

	$tms = DatabaseHelper::fetchAllAsArray("select * from vc_tipo_medidor");
	$detalles = Array();
	foreach($tms as $tm) {
		$detalles[$tm['tipo_medidor']] = Array();
	}


	$id_agua = '1';
	$id_bt1 = '2';
	$id_bt2 = '3';
	$id_bt43 = '4';

	$sql_suma_consumos="
	select
			 m.id_tipo_medidor,
			 tm.tipo_medidor,
			 l.lectura as lectura_mes_actual,
       ll.lectura as lectura_mes_anterior,
   		 l.lectura - ll.lectura as consumo_mes_actual,
			 l.te_1 as te_1,
			 l.pf_pta as pf_pta,
			 l.ph_pta as ph_pta,
       (select sum(lectura) from vc_lectura sl join vc_medidor sm on sl.id_medidor = sm.id where sm.id_tipo_medidor = m.id_tipo_medidor and sl.periodo = ':periodo:') as total_mes_actual,
			 (select sum(lectura) from vc_lectura sl join vc_medidor sm on sl.id_medidor = sm.id where sm.id_tipo_medidor = m.id_tipo_medidor and sl.periodo = date_add(l.periodo,interval -1 month)) as total_mes_anterior
  from vc_lectura l
         join vc_medidor m on l.id_medidor = m.id
				 join vc_tipo_medidor tm on m.id_tipo_medidor = tm.id
         left join vc_lectura ll on l.id_medidor = ll.id_medidor and ll.periodo = date_add(l.periodo,interval -1 month)
 where m.id_contrato = ':id:'
   and l.periodo = ':periodo:'
	";

	$detalle_consumos = DatabaseHelper::fetchAllAsArray($sql_suma_consumos, Array('periodo'=>$periodo, 'id'=>$id_contrato));
  $resumen = Array(
    'Agua' => Array('consumo' => 0, 'facturacion' => 0),
    'BT1' => Array('consumo' => 0, 'facturacion' => 0),
    'BT2' => Array('consumo' => 0, 'facturacion' => 0),
    'BT43' => Array('consumo' => 0, 'facturacion' => 0),
  );
	$process = function($detalle) use ($costos_fijos) {
		switch ($detalle['tipo_medidor']) {

			case 'Agua':
				$porcentaje_consumo = ($detalle['consumo_mes_actual']) / ($detalle['total_mes_actual'] - $detalle['total_mes_anterior']);
				$total_neto_agua = ($costos_fijos['consumo_agua_total'] + $costos_fijos['tratamiento_agua'] + $costos_fijos['recoleccion_agua'] + $costos_fijos['sobreconsumo_agua']) * ($porcentaje_consumo);
				$detalle['porcentaje_consumo'] = ($detalle['consumo_mes_actual']) / ($detalle['total_mes_actual'] - $detalle['total_mes_anterior']);;
				$agua = Array(
					'm3_consumo' => $detalle['consumo_mes_actual'],
					'consumo' => $costos_fijos['consumo_agua_total'] * $porcentaje_consumo,
					'tratamientos_agua' => $costos_fijos['tratamiento_agua'] * $porcentaje_consumo,
					'recoleccion' => $costos_fijos['recoleccion_agua'] * $porcentaje_consumo,
					'sobreconsumo' => $costos_fijos['sobreconsumo_agua'] * $porcentaje_consumo,
					'total_neto' => $total_neto_agua,
          'facturacion_mes_actual' => $total_neto_agua,
				);
				return ($detalle + $agua);
				break;

			case 'BT1':
				$total_neto_bt1 = ($costos_fijos['cargo_fijo_bt1']) + ($detalle['consumo_mes_actual'] * $costos_fijos['valor_energia_bt1']) + ($detalle['consumo_mes_actual'] * $costos_fijos['valor_kw_reliquidacion']) + ($detalle['consumo_mes_actual'] * $costos_fijos['valor_kw_demanda']);
				$bt1 = Array(
					'kwh_consumo' => $detalle['consumo_mes_actual'],
					'consumo' => $detalle['consumo_mes_actual'] * $costos_fijos['valor_energia_bt1'],
					'cuota_reliquidacion' => $detalle['consumo_mes_actual'] * $costos_fijos['valor_kw_reliquidacion'],
					'valor_demanda_prorreateado' => $detalle['consumo_mes_actual'] * $costos_fijos['valor_kw_demanda'],
					'facturacion_mes_actual' => $total_neto_bt1,
				);
				return ($detalle + $bt1);
				break;

			case 'BT2':
  			$total_neto_bt2 = ($costos_fijos['cargo_fijo_bt2']) + ($detalle['consumo_mes_actual'] * $costos_fijos['valor_energia_bt2']) + ($detalle['consumo_mes_actual'] * $costos_fijos['valor_kw_reliquidacion']) + ($detalle['consumo_mes_actual'] * $costos_fijos['valor_kw_demanda']) + ($detalle['te_1'] * $costos_fijos['valor_pte_pta_bt2']);
  			$bt2 = Array(
  				'kwh_consumo' => $detalle['consumo_mes_actual'],
  				'consumo' => $detalle['consumo_mes_actual'] * $costos_fijos['valor_energia_bt2'],
  				'cuota_reliquidacion' => $detalle['consumo_mes_actual'] * $costos_fijos['valor_kw_reliquidacion'],
  				'valor_demanda_prorreateado' => $detalle['consumo_mes_actual'] * $costos_fijos['valor_kw_demanda'],
  				'calculo'=> $detalle['te_1'] * $costos_fijos['valor_pte_pta_bt2'],
  				'facturacion_mes_actual' => $total_neto_bt2,
  			);
				return ($detalle+ $bt2);
				break;

			case 'BT43':
				$total_neto_bt43 = ($costos_fijos['cargo_fijo_bt43']) + ($detalle['consumo_mes_actual'] * $costos_fijos['valor_energia_bt43']) + ($detalle['consumo_mes_actual'] * $costos_fijos['valor_kw_reliquidacion']) + ($detalle['consumo_mes_actual'] * $costos_fijos['valor_kw_demanda']) + ($detalle['pf_pta'] * $costos_fijos['valor_pte_pta_bt43']) + ($detalle['ph_pta'] * $costos_fijos['valor_p_p_pta_bt43']);
				$bt43 = Array(
					'kwh_consumo' => $detalle['consumo_mes_actual'],
					'consumo' => $detalle['consumo_mes_actual'] * $costos_fijos['valor_energia_bt43'],
					'cuota_reliquidacion' => $detalle['consumo_mes_actual'] * $costos_fijos['valor_kw_reliquidacion'],
					'valor_demanda_prorreateado' => $detalle['consumo_mes_actual'] * $costos_fijos['valor_kw_demanda'],
					'total_f_pta'=> $detalle['pf_pta'] * $costos_fijos['valor_pte_pta_bt43'],
					'calculo'=> $detalle['ph_pta'] * $costos_fijos['valor_p_p_pta_bt43'],
					'facturacion_mes_actual' => $total_neto_bt43,
				);
				return ($detalle+ $bt43);
				break;

			default:
				return Array();
				break;
		}
	};
	foreach ($detalle_consumos as $detalle) {
    $procesado = $process($detalle);
    $resumen[$detalle['tipo_medidor']]['consumo'] += $procesado['consumo'];
    $resumen[$detalle['tipo_medidor']]['facturacion'] += $procesado['facturacion_mes_actual'];
		$detalles[$detalle['tipo_medidor']][] = $procesado;
	}

	$ret = Array(
    'resumen' => $resumen,
		'detalle' => $detalles,
		'costos_fijos' => $costos_fijos,
	);

  return $ret;
}

function _multasDeContratoYPeriodo($id, $ano, $mes) {
	$date = "${ano}-${mes}-01";
	$sql = "
	select * from vc_multa
	 where estado_multa = 'Validada'
	   and fecha_multa between date_add(':date:', interval -1 month) and date_add(':date:', interval -1 day)
	   and id_contrato = :id:
	";
	$arr = DatabaseHelper::fetchAllAsArray($sql, Array('date' => $date, 'id' => $id));
	$ret = Array();
	foreach( $arr as $ar) {
		$d = strtotime($ar['fecha_multa']);
		$ar['uf'] = _get_uf(date('Y', $d), date('m', $d), date('d', $d));
		$ar['monto_peso'] = $ar['monto_multa'] * $ar['uf']['v'];
		$ret[] = $ar;
	}
	return $ret;
}

function _contratosPorPeriodo($periodo) {
	$sql = "
	select co.id, cl.rut, cl.dv, co.nombre_fantasia, g.nombre
	  from vc_contrato co
	         join vc_cliente cl on (co.id_cliente = cl.id)
	         join vc_galeria g on (co.id_galeria = g.id)
   where EXTRACT(YEAR_MONTH FROM ':periodo:') between EXTRACT(YEAR_MONTH FROM fecha_inicio_cobro) and EXTRACT(YEAR_MONTH FROM fecha_termino)
	 order by g.nombre, co.nombre_fantasia
	";
	return DatabaseHelper::fetchAllAsArray($sql, Array('periodo' => $periodo));
};

function _extrae_tipo_arriendo($tramo, $ag, $lt) {
	$tipo = 'Por Mt2';
	if ($tramo["tarifa_${ag}_${lt}"] > 0) {
		$tipo_arriendo_fijo = 'Pactado';
		$arriendo_fijo = $tramo["tarifa_${ag}_${lt}"];
	} else if ($tramo["tarifa_${ag}_${lt}"] < 0) {
		$tipo_arriendo_fijo = 'N/A';
		$arriendo_fijo = 0;
	} else {
		$tipo_arriendo_fijo = 'Por Mt2';
		$arriendo_fijo = $tramo["metros_${lt}"]*$tramo["tarifa_tam_${lt}"];
	}
}
function _resumen_arriendo($lista_tramos, $uf) {
	// dias en el mes
	$dias_total_actual = count($lista_tramos);
	$tramos_agrupados = array();

	// por cada tramo
	foreach($lista_tramos as $val) {
		// agregar a un arreglo por id_tramo
		$tramos_agrupados[$val['id_tramo']][] = $val;
	}

	$ret = Array(
		'detalle' => Array(),
		'resumen' => Array(
			'arriendo_total' => 0,
			'gastocomun_total' => 0,
			'arriendo_uf' => 0,
			'gastocomun_uf' => 0,
			'fecha' => 0,
		),
	);
	// por cada grupo de tramos
	foreach($tramos_agrupados as $tramos) {
		// dias del tramo
		$dias = count($tramos);
		// sample el primero, donde empieza el tramo
		$tramo = $tramos[0];

		// calcular arriendo fijo con reglas de negocio
		$arriendo_fijo = 0;
		$tipo_arriendo_fijo = 'Por Mt2';
		if ($tramo['tarifa_arriendo_local'] > 0) {
			$tipo_arriendo_fijo = 'Pactado';
			$arriendo_fijo = $tramo['tarifa_arriendo_local'];
		} else if ($tramo['tarifa_arriendo_local'] < 0) {
			$tipo_arriendo_fijo = 'N/A';
			$arriendo_fijo = 0;
		} else {
			$tipo_arriendo_fijo = 'Por Mt2';
			$arriendo_fijo = $tramo['metros_local']*$tramo['tarifa_tam_local'] + $tramo['metros_terraza']*$tramo['tarifa_tam_terraza'];
		}
		$arriendo_fijo_prop = ($arriendo_fijo / $dias_total_actual) * $dias;

		// costos por tramo
		//$arriendo_local = _extrae_tipo_arriendo($tramo, 'arriendo', 'local');
		//$arriendo_terraza = _extrae_tipo_arriendo($tramo, 'arriendo', 'terraza');
		//$gastocomun_local = _extrae_tipo_arriendo($tramo, 'gastocomun', 'local');
		//$gastocomun_terraza = _extrae_tipo_arriendo($tramo, 'gastocomun', 'terraza');

		// calcular gasto comun con reglas de negocio
		$gastocomun_fijo = 0;
		$tipo_gastocomun_fijo = 'Por Mt2';
		if ($tramo['tarifa_gastocomun_local'] > 0) {
			$tipo_gastocomun_fijo = 'N/A';
			$gastocomun_fijo = $tramo['tarifa_gastocomun_local'];
		} else if ($tramo['tarifa_gastocomun_local'] < 0 ) {
			$tipo_gastocomun_fijo = 'Pactado';
			$gastocomun_fijo = 0;
		} else {
			$tipo_gastocomun_fijo = 'Por Mt2';
			$gastocomun_fijo = $tramo['metros_local']*0.26 + $tramo['metros_terraza']*0.13;
		}
		$gastocomun_fijo_prop = ($gastocomun_fijo / $dias_total_actual) * $dias;


		// agregar data a la respuesta
		$tramo['dias_mes'] = $dias_total_actual;
		$tramo['dias_tramo'] = $dias;
		$tramo['arriendo_uf'] = $arriendo_fijo;
		$tramo['gastocomun_uf'] = $gastocomun_fijo;
		$tramo['arriendo_tipo'] = $tipo_arriendo_fijo;
		$tramo['gastocomun_tipo'] = $tipo_gastocomun_fijo;
		$tramo['arriendo_uf_dias'] = $arriendo_fijo_prop;
		$tramo['gastocomun_uf_dias'] = $gastocomun_fijo_prop;
		$tramo['arriendo_pesos_dias'] = $arriendo_fijo_prop * $uf['v'];
		$tramo['gastocomun_pesos_dias'] = $gastocomun_fijo_prop * $uf['v'];

		$ret['resumen']['arriendo_total'] += $tramo['arriendo_pesos_dias'];
		$ret['resumen']['gastocomun_total'] += $tramo['gastocomun_pesos_dias'];
		$ret['resumen']['arriendo_uf'] += $tramo['arriendo_uf_dias'];
		$ret['resumen']['gastocomun_uf'] += $tramo['gastocomun_uf_dias'];
		$ret['resumen']['metros_local'] = $tramo['metros_local'];
		$ret['resumen']['metros_terraza'] = $tramo['metros_terraza'];
		$ret['resumen']['fecha'] = $tramo['fecha'];
		$ret['detalle'][] = $tramo;
	}

	return $ret;
}

function _resumen_venta($lista_ventas) {
	$ret = Array(
		'detalle' => Array(),
		'resumen' => Array(
			'monto_total' => 0,
			'porcentaje_venta_total' => 0,
		),
	);

	$ventas_agrupadas = array();

	// por cada tramo
	foreach($lista_ventas as $val) {
		// agregar a un arreglo por id_tramo
		$ventas_agrupadas[$val['id_tramo']][] = $val;
	}

	foreach($ventas_agrupadas as $ventas) {
		$resumen_venta['fecha'] = $ventas[0]['fecha'];
		$resumen_venta['id_contrato'] = $ventas[0]['id_contrato'];
		$resumen_venta['id_tramo'] = $ventas[0]['id_tramo'];
		$resumen_venta['tap'] = $ventas[0]['tap'];
		$resumen_venta['monto'] = 0;
		$resumen_venta['porcentaje_venta'] = 0;
		foreach($ventas as $venta) {
			$resumen_venta['monto'] += $venta['monto'];
			$resumen_venta['porcentaje_venta'] += $venta['porcentaje_venta'];
		}
		$ret['resumen']['monto_total'] += $resumen_venta['monto'];
		$ret['resumen']['porcentaje_venta_total'] += $resumen_venta['porcentaje_venta'];
		$ret['detalle'][] = $resumen_venta;
	}

	return $ret;
}

function _facturacion_contrato($id, $ano, $mes) {

	$date = "${ano}-${mes}-01";
	$pdate = date('Y-m-d', strtotime('-1 months', strtotime($date)));
	$sql_arriendo = "
	select d.fecha,
	       c.id as id_contrato,
	       tc.id as id_tramo,
	       coalesce(tc.metros_local,0) as metros_local,
	       coalesce(tc.metros_terraza,0) as metros_terraza,
	       coalesce(tc.tarifa_tam_local,0) as tarifa_tam_local,
	       coalesce(tc.tarifa_tam_terraza,0) as tarifa_tam_terraza,
 				 c.gc_local as tarifa_arriendo_local,

 				 c.gc_terraza as tarifa_gastocomun_local
	  from vc_contrato c
	         join (SELECT date_field AS fecha FROM ( SELECT MAKEDATE(YEAR(':date:'),1) + INTERVAL (MONTH(':date:')-1) MONTH + INTERVAL daynum DAY date_field FROM
					       (SELECT t*10+u daynum FROM (SELECT 0 t UNION SELECT 1 UNION SELECT 2 UNION SELECT 3) A,
								 (SELECT 0 u UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) B ORDER BY daynum ) AA) AAA
	                WHERE MONTH(date_field) = MONTH(':date:') ) d on c.fecha_inicio_cobro <= d.fecha and c.fecha_termino >= d.fecha
	         join vc_tramo_contrato tc on c.id = tc.id_contrato and tc.fecha_inicio = (select max(fecha_inicio) from vc_tramo_contrato where id_contrato = c.id and fecha_inicio <= d.fecha group by id_contrato)
	 where c.id = :id:
	 order by d.fecha
	";
	$sql_variable = "
	select v.fecha, c.id as id_contrato, tc.id as id_tramo, v.monto, tc.tap, ((v.monto/1.19) * (tc.tap/100)) as porcentaje_venta
	  from vc_contrato c
	         join vc_venta v on c.fecha_inicio_cobro <= v.fecha and c.fecha_termino >= v.fecha and v.fecha >= ':date:' and v.fecha < date_add(':date:', INTERVAL 1 month) and c.id = v.id_contrato and v.status = 'O'
	         join vc_tramo_contrato tc on c.id = tc.id_contrato and tc.fecha_inicio = (select max(fecha_inicio) from vc_tramo_contrato where id_contrato = c.id and fecha_inicio <= v.fecha group by id_contrato)
	 where c.id = :id:
	 order by v.fecha
	";

  $sql_adicionales = "
		select *
	  from vc_adicional_contrato ac
	 where ac.id_contrato = :id:
	   and ac.fecha_inicio < date_add(':date:', interval 1 month)
	 order by ac.fecha_inicio desc
	";

	$contrato = DatabaseHelper::fetchOneAsArray("select * from vc_contrato where id = :id:", array('id'=>$id));

	$uf_mes_actual = _get_uf(date('Y', strtotime($date)), date('m', strtotime($date)), 5);
	$uf_mes_anterior = _get_uf(date('Y', strtotime($pdate)), date('m', strtotime($pdate)), 5);

	$arriendo_mes_actual = _resumen_arriendo(DatabaseHelper::fetchAllAsArray($sql_arriendo, array('date'=>$date, 'id'=>$id)), $uf_mes_actual);
	$arriendo_mes_anterior = _resumen_arriendo(DatabaseHelper::fetchAllAsArray($sql_arriendo, array('date'=>$pdate, 'id'=>$id)), $uf_mes_anterior);
	$variable_mes_anterior = _resumen_venta(DatabaseHelper::fetchAllAsArray($sql_variable, array('date'=>$pdate, 'id'=>$id)));

	$adicional = DatabaseHelper::fetchAllAsArray($sql_adicionales, array('date'=>$date, 'id'=>$id));
	if (count($adicional) > 0) {
		$adicional = $adicional[0];
		$adicional['basura_pesos'] = $adicional['basura'] * $uf_mes_actual['v'];
		$adicional['ducto_pesos'] = $adicional['ducto'] * $uf_mes_actual['v'];
		$adicional['bano_pesos'] = $adicional['bano'] * $uf_mes_actual['v'];
		$adicional['bodega_pesos'] = $adicional['bodega'] * $uf_mes_actual['v'];
		$adicional['total_uf'] = $adicional['basura'] + $adicional['ducto'] + $adicional['bano'] + $adicional['bodega'];
		$adicional['total_pesos'] = $adicional['basura_pesos'] + $adicional['ducto_pesos'] + $adicional['bano_pesos'] + $adicional['bodega_pesos'];
	} else {
		$adicional = false;
	}

	$resp= Array();
	$resp['contrato'] = $contrato;
	$resp['uf_mes_actual'] = $uf_mes_actual;
	$resp['uf_mes_anterior'] = $uf_mes_anterior;
	$resp['arriendo_mes_actual'] = $arriendo_mes_actual;
	$resp['arriendo_mes_anterior'] = $arriendo_mes_anterior;
	$resp['variable_mes_anterior'] = $variable_mes_anterior;
	$a_facturar = $variable_mes_anterior['resumen']['porcentaje_venta_total'] - $arriendo_mes_anterior['resumen']['arriendo_total'];
	$fijo_porcentaje = 0;
	if ($a_facturar > 0) {
		$fijo_porcentaje = $a_facturar + $arriendo_mes_actual['resumen']['arriendo_total'];
	} else {
		$fijo_porcentaje = $arriendo_mes_actual['resumen']['arriendo_total'];
	}
	$fondo_promocion = $fijo_porcentaje * $contrato['fondo_promocion'] / 100;
	$gasto_comun = $arriendo_mes_actual['resumen']['gastocomun_total'];
	$resp['resumen'] = Array(
		'a_facturar' => $a_facturar,
		'fijo_porcentaje' => $fijo_porcentaje,
		'fondo_promocion' => $fondo_promocion,
		'gasto_comun' => $gasto_comun,
		'neto' => $fijo_porcentaje + $gasto_comun + $fondo_promocion,
		'adicional' => $adicional,
	);
	return $resp;
}


$app->get('/facturacion/contratos/:periodo', function($periodo) {
	echo(json_encode(_contratosPorPeriodo($periodo)));
});

$app->get('/facturacion/uf/:ano/:mes/:dia', function($ano, $mes, $dia) {
	return _get_uf($ano, $mes, $dia);
});

$app->get('/facturacion/consumos/:id/:ano/:mes/', function($id, $ano, $mes) {
  echo json_encode(_resumen_consumo($id, $ano, $mes));
});

$app->get('/facturacion/reporte_consumo/:periodo', function($periodo) {
	$parts = explode('-', $periodo);
	$ano = $parts[0];
	$mes = $parts[1];
	$contratos = _contratosPorPeriodo($periodo);
	$ret = Array();
	foreach($contratos as $contrato) {
		$ret[$contrato['id']] = _resumen_consumo($contrato['id'], $ano, $mes);
	}
	echo json_encode($ret);
});

$app->get('/facturacion/arriendo/:id/:ano/:mes', function($id, $ano, $mes) {
	echo json_encode(_facturacion_contrato($id, $ano, $mes));
});

$app->get('/facturacion/multa/:id/:ano/:mes', function($id, $ano, $mes) {
	echo json_encode(_multasDeContratoYPeriodo($id, $ano, $mes));
});

$app->get('/facturacion/reporte_arriendo/:periodo', function($periodo) {
	$parts = explode('-', $periodo);
	$ano = $parts[0];
	$mes = $parts[1];
	$contratos = _contratosPorPeriodo($periodo);
	$ret = Array();
	foreach($contratos as $contrato) {
		$ret[$contrato['id']] = _facturacion_contrato($contrato['id'], $ano, $mes);
	}
	echo json_encode($ret);
});

$app->get('/facturacion/reporte_facturacion/:periodo', function($periodo) {
	$parts = explode('-', $periodo);
	$ano = $parts[0];
	$mes = $parts[1];
	$contratos = _contratosPorPeriodo($periodo);
	$ret = Array();
	foreach($contratos as $contrato) {
		$ret[$contrato['id']] = Array();
		$ret[$contrato['id']]['consumo'] = _resumen_consumo($contrato['id'], $ano, $mes);
		$ret[$contrato['id']]['arriendo'] = _facturacion_contrato($contrato['id'], $ano, $mes);
		$ret[$contrato['id']]['multa'] = _multasDeContratoYPeriodo($contrato['id'], $ano, $mes);
	}
	echo json_encode($ret);
});

$app->get('/facturacion/lecturas/:periodo', function($periodo){
	$sql = "
	select t.*,
	       case when t.var_lectura = 0    then null
	            when t.var_lectura < 0.75 then 'Inferior a un 25%'
	            when t.var_lectura > 1.25 then 'Mayor a un 25%'
	       end as warn_lectura,
	       case when t.var_te_1 = 0    then null
	            when t.var_te_1 < 0.75 then 'Inferior a un 25%'
	            when t.var_te_1 > 1.25 then 'Mayor a un 25%'
	       end as warn_te_1,
	       case when t.var_pf_pta = 0    then null
	            when t.var_pf_pta < 0.75 then 'Inferior a un 25%'
	            when t.var_pf_pta > 1.25 then 'Mayor a un 25%'
	       end as warn_pf_pta,
	       case when t.var_ph_pta = 0    then null
	            when t.var_ph_pta < 0.75 then 'Inferior a un 25%'
	            when t.var_ph_pta > 1.25 then 'Mayor a un 25%'
	       end as warn_ph_pta
	  from (
	select c.nombre_fantasia,
	       m.codigo_medidor,
	       m.id,
	       la.lectura as la_lectura,
	       la.te_1    as la_te_1,
	       la.pf_pta  as la_pf_pta,
	       la.ph_pta  as la_ph_pta,
	       lp.lectura as lp_lectura,
	       lp.te_1    as lp_te_1,
	       lp.pf_pta  as lp_pf_pta,
	       lp.ph_pta  as lp_ph_pta,
	       coalesce(coalesce(la.lectura, 0) / coalesce(lp.lectura, 1), la.lectura ) as var_lectura,
	       coalesce(coalesce(la.te_1,    0) / coalesce(lp.te_1,    1), la.te_1    ) as var_te_1,
	       coalesce(coalesce(la.pf_pta,  0) / coalesce(lp.pf_pta,  1), la.pf_pta  ) as var_pf_pta,
	       coalesce(coalesce(la.ph_pta,  0) / coalesce(lp.ph_pta,  1), la.ph_pta  ) as var_ph_pta
	  from vc_medidor m
	         join vc_contrato c on m.id_contrato = c.id
	         left join vc_lectura la on m.id = la.id_medidor and la.periodo = ':periodo:'
	         left join vc_lectura lp on la.id_medidor = lp.id_medidor and date_add(la.periodo, INTERVAL -1 month) = lp.periodo
	         ) t
	 order by t.nombre_fantasia, t.codigo_medidor
	 ";
	 echo json_encode(DatabaseHelper::fetchAllAsArray($sql, array('periodo'=>"${periodo}-01")));
});

function _matriz() {
	$sql = "
	select c.nombre_fantasia, c.id idc, c.estado, m.*
		from vc_contrato c
		join vc_galeria g on c.id_galeria = g.id
		left join vc_matriz_facturacion m on c.id = m.id_contrato
	 order by c.estado desc, g.nombre, c.nombre_fantasia
	";
	return DatabaseHelper::fetchAllAsArray($sql);
}

$app->get('/facturacion/matriz', function(){
	echo json_encode(_matriz());
});

$app->post('/facturacion/matriz', function() use ($app) {
	$data = json_decode($app->request->getBody(), 1);
	$resps = Array();
	$props = Array();
	function clean($v) {
		if (!$v) {$v = "null";}
		if (!$v === "0") {$v = "null";}
		return $v;
	}
	foreach($data as $row) {
		$cols = Array();
		$vals = Array();
		$updates = Array();
		foreach ($row as $k => $v) {
			$v = clean($v);
			if ($k != "id_contrato") {
				$updates[] = "${k} = ${v}";
			}
			$cols[] = $k;
			$vals[] = $v;
		}
		$cols = implode(",", $cols);
		$vals = implode(",", $vals);
		$updates = implode(",", $updates);
		$resps[] = DatabaseHelper::modifyData("insert into vc_matriz_facturacion (${cols}) values (${vals}) on duplicate key update ${updates}", $props);
	}
	//$resps[] = DatabaseHelper::modifyData("commit");
	echo json_encode($resps);
});

$app->run();

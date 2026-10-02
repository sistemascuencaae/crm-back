<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Traits\FormatResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Paciente;
use App\Models\FormOcupacional;


class ReportesAE extends Controller
{
    use FormatResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:admin', ['except' =>
        [
            'proylecma',
        ]]);
    }

    public function porId()
    {
        try {
            $sql =  "
            select * from (select pro.pro_id, pro_codigo, pro_nombre, mar.mar_id, bod_id,
		coalesce((select sal.sal_sinicial-sal.sal_cre1-sal.sal_cre2-sal.sal_cre3-
		sal.sal_cre4-sal.sal_cre5-sal.sal_cre6-sal.sal_cre7-sal.sal_cre8-
		sal.sal_cre9-sal.sal_cre10-sal.sal_cre11-sal.sal_cre12+
		sal.sal_deb1++sal.sal_deb2+sal.sal_deb3+sal.sal_deb4+sal.sal_deb5+
		sal.sal_deb6+sal.sal_deb7+sal.sal_deb8+sal.sal_deb9+
		sal.sal_deb10+sal.sal_deb11+sal.sal_deb12 as canDispo
		from saldoinv sal 
		where sal.sal_periodo=extract('year' from CURRENT_DATE) 
		and sal.bod_id= bod.bod_id and sal.pro_id=pro.pro_id),0) as stock_actual, c2.cla1_nombre as linea, c1.cla1_nombre as sublinea, bod_nombre as bodega, ubi_nombre as ciudad, mar_nombre as marca, lpr_nombre,
		case when (dlpr_precio - round(dlpr_precio,0)) > 0 then round(dlpr_precio,0)+1 else round(coalesce(dlpr_precio,0),0) end as precio,
		case when dlpr_actualizado then 'NUEVO PRECIO' else '' end as observacion 
		from producto pro
		left join marca mar on mar.mar_id = pro.mar_id
		left join clasificacion1 c1 on c1.cla1_id = pro.cla1_id
		left join clasificacion1 c2 on c2.cla1_id = c1.cla1_reporta
		left join dlistapre_reporte dlp on dlp.pro_id = pro.pro_id
left join listapre lpr on lpr.lpr_id = dlp.lpr_id,
		bodega bod
		left join ubicacion ubi on ubi.ubi_id = bod.ubi_id
		where bod_activo = true and coalesce (bod.ubi_id, 0) > 0)tt where stock_actual > 0 and lpr_nombre = 'PROLECMA - EJERCITO CONTADO' or lpr_nombre = 'PROLECMA - EJERCITO CREDITO';
            ";
            $datos = DB::select($sql);
            $resp = array(
                'code'      => 200,
                'status'    => 'success',
                'message'   => 'La información se consiguio sin problemas.',
                'data'  => $datos[0],
            );
        } catch (\Exception $e) {
            $resp = array(
                'code'      => 400,
                'status'    => 'error',
                'message'   => 'Error: la información no se logro conseguir: ',
                'error'     =>  $e,
            );
        }
        return response()->json($resp);
    }


}

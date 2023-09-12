<?php

namespace App\Http\Controllers\crm\auditoria;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteAditoriaController extends Controller
{
    public function cliTabAmortizacion($cuentaanterior)
    {
        try {
        $sql = "SELECT ent.ent_identificacion as cedula, date(cfa.cfa_fecha)as fecha,
        upper((select convertir_numeros_letras(capital + interes))::text) as cuotaletras, v.*
        from public.v_austro_creditos_pagos v
        left join public.ccomproba ccm on ccm.ccm_id = v.cuentaanterior::integer
        left join public.cfactura cfa on cfa.cti_id = ccm.cti_id and cfa.pve_id = ccm.pve_id and cfa_numero = ccm_numero and cfa_periodo = ccm_periodo
        left join public.cliente cli on cli.cli_id = cfa.cli_id
        left join public.entidad ent on ent.ent_id = cli.ent_id where cuentaanterior = ?
        order by cuentaanterior, subcuenta";

        $data = DB::select($sql, [$cuentaanterior]);


            return response()->json(RespuestaApi::returnResultado('success', 'Tabla amortizacion', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Exception', $th->getMessage()));
        }
    }
}

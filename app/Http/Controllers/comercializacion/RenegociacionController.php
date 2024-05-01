<?php

namespace App\Http\Controllers\comercializacion;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\renegociaciones\Pagare;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Exception;

class RenegociacionController extends Controller
{
    // public function getDoctranOpenceo($ddo_doctran)
    // {
    //     try {

    //         $data = DB::select("SELECT * from public.ddocumento doc where ddo_doctran = '$ddo_doctran' order by  doc.ddo_num_pago asc");

    //         return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito', $data));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
    //     }
    // }

    public function addHistorialPagare(Request $request) // Recibe object {ddo_doctran: '2022-FAE-13-503-1938'}
    {
        try {
            $exitoso = null;
            $error = null;

            DB::transaction(function () use ($request, &$exitoso, &$error) {

                $query = null;
                $bandera = 0;

                if ($request->ddo_doctran) {

                    $query = DB::selectOne("SELECT cfa.cfa_id from public.cfactura cfa
                                                inner join public.puntoventa pve on pve.pve_id = cfa.pve_id
                                                inner join public.almacen alm on alm.alm_id = pve.alm_id
                                                inner join public.ctipocom cti on cti.cti_id = cfa.cti_id
                                                where (cfa.cfa_periodo||'-'||cti.cti_sigla||'-'||alm.alm_codigo||'-'||pve.pve_numero||'-'||cfa.cfa_numero) = '$request->ddo_doctran'");

                    if ($query) {
                        $bandera = 1;
                    } else {
                        $error = 'No existe un cfa_id con el ddo_doctran: ' . $request->ddo_doctran;
                        return null;
                    }

                } else {
                    $error = 'No existe el ddo_doctran en el objeto enviado.';
                    return null;
                }

                if ($bandera == 1) {

                    if ($query->cfa_id) {

                        $dataPagare = DB::select("SELECT distinct 
                                                        emp.emp_nombre Empresa,
                                                        ent.ent_identificacion Cedula,
                                                        ddo.ddo_num_pago NumeroPago,
                                                        COALESCE(dir.dir_calle_principal,'')||' y '||COALESCE(dir.dir_calle_secundaria,'') Direccion,
                                                        cfa.cfa_fecha fechaFac, ddo.ddo_fechaven F_Ven,
                                                        ent.ent_apellidos||' '||ent.ent_nombres Nombre, 
                                                        ROUND(COALESCE((select ddt.ddt_interes from ddocumento_tabla ddt where ddt.ddt_id = ddo.ddo_id   and ddt.ddt_tipo=3 ),0),2) Interes, 
                                                        ROUND(COALESCE(ddo.ddo_monto,0),2) Capital,  
                                                        ROUND(COALESCE(ddo.ddo_monto,0)-COALESCE((select ddt.ddt_interes from ddocumento_tabla ddt where ddt.ddt_id = ddo.ddo_id  and ddt.ddt_tipo=3),0),2) Cuota,
                                                        fun_rtnnumero_letras(COALESCE(COALESCE(ddo.ddo_monto,0)-COALESCE((select ddt.ddt_interes from ddocumento_tabla ddt where ddt.ddt_id = ddo.ddo_id and ddt.ddt_tipo=3),0),0) + COALESCE((select ddt.ddt_interes from ddocumento_tabla ddt where ddt.ddt_id = ddo.ddo_id   and ddt.ddt_tipo=3),0)) ValorL, 

                                                        -- ROUND(COALESCE(cfa.cfa_total,0),2) Total,  
                                                        (select SUM(ROUND(COALESCE(ddo.ddo_monto,0),2)) AS valorletra
                                                            from cfactura cfa, ddocumento ddo, ddocumento_tabla ddot, ccomproba ccm, empresa emp, cliente cli, entidad ent, direccion dir, parametro par
                                                            where cfa.cfa_id = $query->cfa_id and
                                                                par.par_abreviacion='INT' and
                                                                par.mod_abreviatura = 'FAC' and
                                                                cli.cli_tipocli = 1 and
                                                                ent.ent_direccion_principal = dir.dir_id and
                                                                cli.ent_id = ent.ent_id and
                                                                cfa.cli_id = cli.cli_id and
                                                                ccm.ccm_id = ddo.ccm_id and
                                                                cfa.pve_id = ccm.pve_id and
                                                                cfa.cti_id = ccm.cti_id and
                                                                cfa.cfa_periodo = ccm.ccm_periodo and
                                                                cfa.cfa_numero = ccm.ccm_numero and
                                                                ddo.ddo_id =ddot.ddt_id and ddot.ddt_tipo = 3 ) as Total,                            

                                                        -- public.fun_rtnnumero_letras(COALESCE(ddo.ddo_monto,0)) TotalL, 
                                                        (SELECT fun_rtnnumero_letras(SUM(ROUND(COALESCE(ddo.ddo_monto,0),2))) valorletra
                                                            from cfactura cfa, ddocumento ddo, ddocumento_tabla ddot, ccomproba ccm, empresa emp, cliente cli, entidad ent, direccion dir, parametro par
                                                            where cfa.cfa_id = $query->cfa_id and
                                                                par.par_abreviacion='INT' and
                                                                par.mod_abreviatura = 'FAC' and
                                                                cli.cli_tipocli = 1 and
                                                                ent.ent_direccion_principal = dir.dir_id and
                                                                cli.ent_id = ent.ent_id and
                                                                cfa.cli_id = cli.cli_id and
                                                                ccm.ccm_id = ddo.ccm_id and
                                                                cfa.pve_id = ccm.pve_id and
                                                                cfa.cti_id = ccm.cti_id and
                                                                cfa.cfa_periodo = ccm.ccm_periodo and
                                                                cfa.cfa_numero = ccm.ccm_numero and
                                                                ddo.ddo_id =ddot.ddt_id and ddot.ddt_tipo = 3) as TotalL, 

                                                        par.par_valor inte,
                                                        entesposa.ent_identificacion as iden_esposa, 
                                                        COALESCE(entesposa.ent_apellidos,'')||' '|| COALESCE(entesposa.ent_nombres,'') as nom_esposa,
                                                        entgarante.ent_identificacion as iden_garante, 
                                                        COALESCE(entgarante.ent_apellidos,'')||' '|| COALESCE(entgarante.ent_nombres,'') as nom_garante,
                                                        entespgarante.ent_identificacion as iden_espgarante, 
                                                        COALESCE(entespgarante.ent_apellidos,'')||' '|| COALESCE(entespgarante.ent_nombres,'') as nom_espgarante,
                                                        cti.cti_sigla,
                                                        alm.alm_codigo,
                                                        pve.pve_numero,
                                                        cfa.cfa_numero, 
                                                        pve.pve_id, 
                                                        ubialm.ubi_nombre,
                                                        ent.ent_email,
                                                        (date(ddo.ddo_fechaven) - date(cfa.cfa_fecha))::text || ' días vista' AS dias_vista,

                                                        -- add columnas Juan para guardar en la tabla crm.historial_pagare
                                                        ent.ent_id,
                                                        cli.cli_id,
                                                        cfa.cfa_id,
                                                        ddo.ddo_doctran

                                                    from cfactura cfa
                                                        LEFT JOIN ccomproba ccmf on cfa.cfa_periodo=ccmf.ccm_periodo and cfa.cti_id=ccmf.cti_id and cfa.pve_id=ccmf.pve_id and cfa.cfa_numero=ccmf.ccm_numero
                                                        LEFT JOIN cliente_garante cligar on cfa.cli_id=cligar.cli_id and cligar.ccm_id=ccmf.ccm_id
                                                        LEFT JOIN cliente esposa on cligar.cli_id_conyuge=esposa.cli_id
                                                        LEFT JOIN entidad entesposa on esposa.ent_id=entesposa.ent_id
                                                        LEFT JOIN cliente garante on cligar.cli_id_gar=garante.cli_id
                                                        LEFT JOIN entidad entgarante on garante.ent_id=entgarante.ent_id
                                                        LEFT JOIN cliente espgarante on cligar.cli_id_conyugegar=espgarante.cli_id
                                                        LEFT JOIN entidad entespgarante on espgarante.ent_id=entespgarante.ent_id, 
                                                        ddocumento ddo, 
                                                        ddocumento_tabla ddot, 
                                                        ccomproba ccm,empresa emp, 
                                                        cliente cli, 
                                                        entidad ent, 
                                                        direccion dir, 
                                                        parametro par, 
                                                        ctipocom cti,
                                                        puntoventa pve, 
                                                        almacen alm left join ubicacion ubialm on ubialm.ubi_id = alm.ubi_id

                                                    where cfa.cfa_id = $query->cfa_id and
                                                        par.par_abreviacion='INT' and
                                                        par.mod_abreviatura = 'FAC' and
                                                        cli.cli_tipocli = 1 and
                                                        ent.ent_direccion_principal = dir.dir_id and
                                                        cli.ent_id = ent.ent_id and
                                                        cfa.cli_id = cli.cli_id and
                                                        ccm.ccm_id = ddo.ccm_id and
                                                        cfa.pve_id = ccm.pve_id and
                                                        cfa.cti_id = ccm.cti_id and
                                                        cfa.cfa_periodo = ccm.ccm_periodo and
                                                        cfa.cfa_numero = ccm.ccm_numero and
                                                        ddo.ddo_id =ddot.ddt_id and ddot.ddt_tipo = 3 and
                                                        cfa.cti_id = cti.cti_id and cfa.pve_id=pve.pve_id and pve.alm_id = alm.alm_id

                                                    order by ddo.ddo_num_pago");

                        if (count($dataPagare) > 0) {

                            $tamanoArray = count($dataPagare);
                            $contador = 0;

                            $codigoUnicoHistorial = Str::uuid();

                            $arrayRegistros = [];

                            // echo "dataa ------>    " . json_encode($dataPagare[0]);

                            // add el código único a cada registro de mi array
                            foreach ($dataPagare as $registro) {

                                // Convierte el objeto stdClass a un array asociativo
                                $registro = json_decode(json_encode($registro), true);

                                $registro['codigo_historial'] = $codigoUnicoHistorial;
                                $arrayRegistros[] = $registro;
                                $contador++;
                            }

                            if ($tamanoArray == $contador) {
                                foreach ($arrayRegistros as $registro) {
                                    $nuevoRegistro = Pagare::create($registro);

                                    // Agregar el nuevo registro al array de registros
                                    $arrayRegistros[] = $nuevoRegistro;
                                }

                                $exitoso = $arrayRegistros;
                                return null;
                            }

                        }

                    } else {
                        $error = 'No existe el cfa_id del doctran: ' . $request->ddo_doctran;
                        return null;
                    }

                }

            });

            if ($exitoso) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $exitoso));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listHistorialPagare($ddo_doctran)
    {
        try {
            // devuelve todas las tuplas de la tabla segun el ddo_doctran
            // $data = Pagare::where('ddo_doctran', $ddo_doctran)->orderBy("id", "asc")
            // ->orderBy('ddo_num_pago', 'asc')->get();

            // devuelve los pagare's agrupados por el codigo_historial y ordenado por el id de la tabla (El primer ID que se creo del grupo)
            $data = Pagare::select('codigo_historial', 'ddo_doctran', 'cfa_id', DB::raw('MAX(created_at) AS created_at'), DB::raw('MIN(id) AS id'))
                ->where('ddo_doctran', $ddo_doctran)
                ->groupBy('codigo_historial', 'ddo_doctran', 'cfa_id')
                ->orderBy('id', 'asc')
                ->get();

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $data->map(function ($item) use ($dateFields) {
                // $this->formatoFechaItem($item, $dateFields);
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);
                return $item;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // Este metodo lo llamo pra imprimir en el front el pagare del CRM
    public function getPagareByCodigoHistorial($codigo_historial)
    {
        try {
            // devuelve todas las tuplas del pagare por codigo_historial
            $data = Pagare::where('codigo_historial', $codigo_historial)->orderBy("id", "asc")
                ->orderBy('numeropago', 'asc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }

    }

}

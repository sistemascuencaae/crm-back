<?php

namespace App\Http\Controllers\db_oracle;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\DB;
use Exception;

class MultiNivelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => [
                // 'multinivel',
            ]
        ]);
    }

    // Version 2.0
    public function multinivel($anio, $mes, $dia)
    {
        $vfecha = sprintf('%s-%s-%s', $anio, $mes, $dia);

        try {
            // Obtener datos de Oracle (solo el registro más reciente por cliente usando ROW_NUMBER)
            $dataOracle = DB::connection('oracle')
                ->select("SELECT
                                FECHA_INGRESO,
                                COD_AGENTE,
                                NOMBRE_AGENTE,
                                COD_CLIENTE,
                                NOMBRE
                            FROM (
                                SELECT
                                    FECHA_INGRESO,
                                    COD_AGENTE,
                                    NOMBRE_AGENTE,
                                    REPLACE(COD_CLIENTE, '-', '') AS COD_CLIENTE, NOMBRE,
                                    ROW_NUMBER() OVER (PARTITION BY COD_CLIENTE ORDER BY FECHA_INGRESO DESC) AS registro
                                FROM VS_CEL_PROSPECTO
                                )
                            WHERE registro = 1");

            $oracleClientes = [];

            foreach ($dataOracle as $item) {
                $oracleClientes[$item->cod_cliente] = $item;
            }

            // Obtener datos de PostgreSQL
            $dataAlm = DB::select("SELECT
                                        identificacion,
                                        nombres,
                                        apellidos,
                                        ccm_id as id_factura,
                                        fecha as fecha_factura,
                                        periodo,
                                        mes,
                                        dia,
                                        factura,
                                        politica,
                                        numero_cuotas,
                                        subtotalmenosdescuentos as subtotal,
                                        forma_pago,
                                        (total - valor_impuesto) as total_menos_valoriva
                                    FROM public.av_cfactura_multinivel_api a
                                    WHERE a.fecha > ?;", [$vfecha]);

            $data = [];

            // Filtrar y mapear en una sola iteración
            foreach ($dataAlm as $item) {

                // Solo incluir si el cliente existe en Oracle
                if (!isset($oracleClientes[$item->identificacion])) {
                    continue; // Descarta clientes que NO están en Oracle
                }

                // Busca en el array de Oracle el registro que corresponde al cliente actual de Dynamo.
                // para luego mergear el cliente de Novasoft y Dynamo.
                $oracleRegistro = $oracleClientes[$item->identificacion];

                $data[] = [
                    // Datos de la DB Oracle
                    'fecha_ingreso' => $oracleRegistro->fecha_ingreso,
                    'identificacion_corredor' => $oracleRegistro->cod_agente,
                    'corredor' => $oracleRegistro->nombre_agente,
                    // 'nombre_cliente' => $oracleRegistro->nombre,

                    // Datos de la DB Postgres
                    'identificacion' => $item->identificacion,
                    'nombres' => $item->nombres,
                    'apellidos' => $item->apellidos,
                    'id_factura' => $item->id_factura,
                    'fecha_factura' => $item->fecha_factura,
                    'periodo' => $item->periodo,
                    'mes' => $item->mes,
                    'dia' => $item->dia,
                    'factura' => $item->factura,
                    'politica' => $item->politica,
                    'numero_cuotas' => $item->numero_cuotas,
                    'subtotal' => $item->subtotal,
                    'forma_pago' => $item->forma_pago,
                    'total_menos_valoriva' => $item->total_menos_valoriva,
                ];
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }



    // Version 1.0
    public function multinivel_nce($anio, $mes, $dia)
    {
        try {
            // Obtener datos de Oracle (solo el registro más reciente por cliente usando ROW_NUMBER)
            $dataOracle = DB::connection('oracle')
                ->select("SELECT
                                FECHA_INGRESO,
                                COD_AGENTE,
                                NOMBRE_AGENTE,
                                COD_CLIENTE,
                                NOMBRE
                            FROM (
                                SELECT
                                    FECHA_INGRESO,
                                    COD_AGENTE,
                                    NOMBRE_AGENTE,
                                    REPLACE(COD_CLIENTE, '-', '') AS COD_CLIENTE, NOMBRE,
                                    ROW_NUMBER() OVER (PARTITION BY COD_CLIENTE ORDER BY FECHA_INGRESO DESC) AS registro
                                FROM VS_CEL_PROSPECTO
                                )
                            WHERE registro = 1");

            $oracleClientes = [];

            foreach ($dataOracle as $item) {
                $oracleClientes[$item->cod_cliente] = $item;
            }

            // Obtener datos de PostgreSQL
            $dataAlm = DB::select("SELECT 
                                        identificacion,
                                        nombres,
                                        apellidos,
                                        fecha as fecha_nota_credito,
                                        periodo,
                                        mes,
                                        dia,
                                        comprobante as nota_credito,
                                        factura as factura_afectada,
                                        periodo_factura,
                                        pol_nombre as politica,
                                        subtotalmenosdescuentos as subtotal,
                                        (total - valor_impuesto) as total_menos_valoriva
                                    FROM public.af_nce_multinivel_api(?,?,?);", [$anio, $mes, $dia]);

            $data = [];

            // Filtrar y mapear en una sola iteración
            foreach ($dataAlm as $item) {
                // Solo incluir si el cliente existe en Oracle
                if (!isset($oracleClientes[$item->identificacion])) {
                    continue; // Descarta clientes que NO están en Oracle
                }

                // Busca en el array de Oracle el registro que corresponde al cliente actual de Dynamo.
                // para luego mergear el cliente de Novasoft y Dynamo.
                $oracleRegistro = $oracleClientes[$item->identificacion];

                $data[] = [
                    // Datos de la DB Oracle
                    'fecha_ingreso' => $oracleRegistro->fecha_ingreso,
                    'identificacion_corredor' => $oracleRegistro->cod_agente,
                    'corredor' => $oracleRegistro->nombre_agente,
                    // 'nombre_cliente' => $oracleRegistro->nombre,

                    // Datos de la DB Postgres
                    'identificacion' => $item->identificacion,
                    'nombres' => $item->nombres,
                    'apellidos' => $item->apellidos,
                    'fecha_nota_credito' => $item->fecha_nota_credito,
                    'periodo' => $item->periodo,
                    'mes' => $item->mes,
                    'dia' => $item->dia,
                    'nota_credito' => $item->nota_credito,
                    'factura_afectada' => $item->factura_afectada,
                    'periodo_factura' => $item->periodo_factura,
                    'politica' => $item->politica,
                    'subtotal' => $item->subtotal,
                    'total_menos_valoriva' => $item->total_menos_valoriva,
                ];
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }



    // --------------------------------------------------------------------------------------------------------------------
    // --------------------------------------------------------------------------------------------------------------------
    // --------------------------------------------------------------------------------------------------------------------

    public function multinivel2($anio, $mes, $dia)
    {
        try {
            // Obtener datos de Oracle (solo el registro más reciente por cliente usando ROW_NUMBER)
            $dataOracle = DB::connection('oracle')
                ->select("SELECT
                                FECHA_INGRESO,
                                COD_AGENTE,
                                NOMBRE_AGENTE,
                                COD_CLIENTE,
                                NOMBRE
                            FROM (
                                SELECT
                                    FECHA_INGRESO,
                                    COD_AGENTE,
                                    NOMBRE_AGENTE,
                                    REPLACE(COD_CLIENTE, '-', '') AS COD_CLIENTE, NOMBRE,
                                    ROW_NUMBER() OVER (PARTITION BY COD_CLIENTE ORDER BY FECHA_INGRESO DESC) AS registro
                                FROM VS_CEL_PROSPECTO
                                )
                            WHERE registro = 1");

            $oracleClientes = [];

            foreach ($dataOracle as $item) {
                $oracleClientes[$item->cod_cliente] = $item;
            }

            // Obtener datos de PostgreSQL
            $dataAlm = DB::select("SELECT
                                        identificacion,
                                        nombres,
                                        apellidos,
                                        ccm_id as id_factura,
                                        fecha as fecha_factura,
                                        periodo,
                                        mes,
                                        dia,
                                        factura,
                                        politica,
                                        numero_cuotas,
                                        subtotalmenosdescuentos as subtotal,
                                        forma_pago,
                                        (total - valor_impuesto) as total_menos_valoriva
                                    FROM public.af_cfactura_multinivel_api(?,?,?);", [$anio, $mes, $dia]);

            $data = [];

            // Filtrar y mapear en una sola iteración
            foreach ($dataAlm as $item) {

                // Solo incluir si el cliente existe en Oracle
                if (!isset($oracleClientes[$item->identificacion])) {
                    continue; // Descarta clientes que NO están en Oracle
                }

                // Busca en el array de Oracle el registro que corresponde al cliente actual de Dynamo.
                // para luego mergear el cliente de Novasoft y Dynamo.
                $oracleRegistro = $oracleClientes[$item->identificacion];

                $data[] = [
                    // Datos de la DB Oracle
                    'fecha_ingreso' => $oracleRegistro->fecha_ingreso,
                    'identificacion_corredor' => $oracleRegistro->cod_agente,
                    'corredor' => $oracleRegistro->nombre_agente,
                    // 'nombre_cliente' => $oracleRegistro->nombre,

                    // Datos de la DB Postgres
                    'identificacion' => $item->identificacion,
                    'nombres' => $item->nombres,
                    'apellidos' => $item->apellidos,
                    'id_factura' => $item->id_factura,
                    'fecha_factura' => $item->fecha_factura,
                    'periodo' => $item->periodo,
                    'mes' => $item->mes,
                    'dia' => $item->dia,
                    'factura' => $item->factura,
                    'politica' => $item->politica,
                    'numero_cuotas' => $item->numero_cuotas,
                    'subtotal' => $item->subtotal,
                    'forma_pago' => $item->forma_pago,
                    'total_menos_valoriva' => $item->total_menos_valoriva,
                ];
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }
}

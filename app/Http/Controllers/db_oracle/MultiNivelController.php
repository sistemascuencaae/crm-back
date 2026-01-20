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

    public function multinivel($anio, $mes, $dia)
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

            // Indexar datos de Oracle usando array nativo (más rápido que colecciones)
            $oracleIndexado = [];
            foreach ($dataOracle as $item) {
                $oracleIndexado[$item->cod_cliente] = $item;
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
                                        forma_pago
                                    FROM dashboard.af_cfactura_periodo_menos1_api(?,?,?);",[$anio,$mes,$dia]);

            // Filtrar y mapear en una sola iteración (evita recorrer dos veces)
            $data = [];
            foreach ($dataAlm as $item) {
                // Solo incluir si el cliente existe en Oracle
                if (!isset($oracleIndexado[$item->identificacion])) {
                    continue;
                }

                $oracleData = $oracleIndexado[$item->identificacion];

                $data[] = [
                    // Datos de la DB Oracle
                    'fecha_ingreso' => $oracleData->fecha_ingreso,
                    'identificacion_corredor' => $oracleData->cod_agente,
                    'corredor' => $oracleData->nombre_agente,
                    // 'nombre_cliente' => $oracleData->nombre,

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
                ];
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }
}

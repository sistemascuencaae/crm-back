<?php

namespace App\Http\Controllers\comercializacion;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\DB;

class CliReiterativoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => []]);
    }


    public function getByIdentificacionCliReitera($identificacion)
    {
        //delete from crm.data_temp_cli_reiterativo where user_id = 1 and ent_identificacion ='0104205497';
        //SELECT * FROM crm.fun_insert_ttemp_clireitera(1, '0104205497');
        //select * from crm.data_temp_cli_reiterativo where user_id = 1 and ent_identificacion ='0104205497';
        //$result = DB::select("SELECT * from crm.data_temp_cli_reiterativo where ent_identificacion = ?;",[$identificacion]);
        $cliente = DB::selectOne("SELECT * from crm.data_temp_cli_reiterativo where ent_identificacion = ? limit 1;", [$identificacion]);
        $data = null;
        if (!$cliente) {
            //DB::delete("DELETE from crm.data_temp_cli_reiterativo where ent_identificacion = ?;", [$identificacion]);
            DB::select("SELECT * FROM crm.fun_insert_ttemp_clireitera(?);", [$identificacion]);
        }

        $data = $this->getDataClienteReiterativo($identificacion);



        return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito', $data));
    }

    public function getDataClienteReiterativo($identificacion)
    {
        // $cliente = null;
        // if (sizeOf($data) > 0) {
        //     $cliente = $data[0]->cliente;
        // }
        $infoCli = DB::selectOne("SELECT * from crm.data_temp_cli_reiterativo where ent_identificacion = ?", [$identificacion]);
        $cliente = $infoCli->cliente;
        $fechaUltCreditoPagado = null;
        $fechaUltimCuotaPagada = null;
        $creditos = DB::select("SELECT cod_comprobante_fp from crm.data_temp_cli_reiterativo where ent_identificacion = ? and interes > 0 GROUP BY 1", [$identificacion]);
        $numeroCreditos = sizeof($creditos);
        $ultiCredPaga = DB::selectOne("SELECT cod_comprobante_fp, MAX(ddo_fecha_emision) AS max_fecha
            FROM crm.data_temp_cli_reiterativo
            WHERE ent_identificacion = ?
            AND interes > 0
            GROUP BY cod_comprobante_fp
	        HAVING COUNT(DISTINCT tipo_vencido) = 1
	        order by 2 desc limit 1", [$identificacion]);
        if ($ultiCredPaga) {
            $fechaUltCreditoPagado = $ultiCredPaga->max_fecha;
        }

        $docUltCuotaPagada = DB::selectOne("SELECT
            cod_comprobante_fp,
            fecha_cobro
            from crm.data_temp_cli_reiterativo
            where ent_identificacion = ?
            AND interes > 0
            and fecha_cobro notnull
            GROUP BY 1,2 order by 2 desc limit 1;", [$identificacion]);
        if ($docUltCuotaPagada) {
            $fechaUltimCuotaPagada = $docUltCuotaPagada->fecha_cobro;
        }

        $atrasoCrePagado = DB::select("SELECT ddo_fecha_emision as fecha_emision, cod_comprobante_fp as compro, secuencia, MAX((COALESCE(ult_fecha_pago, fecha_actual) - fecha_vencimiento)::int) AS dias_atraso
            FROM crm.data_temp_cli_reiterativo
            WHERE ent_identificacion = ?
            AND interes > 0
            AND secuencia <> 999
            AND cod_comprobante_fp IN (
                SELECT cod_comprobante_fp
                FROM crm.data_temp_cli_reiterativo
                WHERE ent_identificacion = ?
                AND interes > 0
                GROUP BY cod_comprobante_fp
                HAVING COUNT(DISTINCT tipo_vencido) = 1
                ORDER BY 1 DESC
                LIMIT 3 ) GROUP BY ddo_fecha_emision, secuencia, cod_comprobante_fp ORDER BY cod_comprobante_fp, secuencia ASC;", [$identificacion, $identificacion]);


        $dataCreActivos = DB::select("SELECT
            ddo_fecha_emision as fecha_emision,
            cod_comprobante_fp as compro,
            tipo_vencido,
            secuencia,
            MAX((COALESCE(ult_fecha_pago, fecha_actual) - fecha_vencimiento)::int) AS dias_atraso
	            FROM crm.data_temp_cli_reiterativo
	            WHERE ent_identificacion = ?
	            AND interes > 0
                AND secuencia <> 999
	            AND tipo_vencido IN ('POR VENCER','VENCIDO' )
            GROUP BY ddo_fecha_emision, cod_comprobante_fp, secuencia, tipo_vencido ORDER BY cod_comprobante_fp, secuencia ASC;", [$identificacion]);


        $data = (object) [
            "cliente" => $cliente,
            "numeroCreditos" => $numeroCreditos,
            "fechaUltCrePagado" => $fechaUltCreditoPagado,
            "fechaUltCuoPagada" => $fechaUltimCuotaPagada,
            "dataUltCrePagados" => $atrasoCrePagado,
            "dataCreditActivos" => $dataCreActivos
        ];
        return $data;
    }

    public function getByIdentificacionCliReitera0($identificacion)
    {
        try {
            $data = [];
            if ($identificacion) {
                $data = DB::select("SELECT
                v1.dias_diferencia_cuotayrec,
                v2.fecha_vencimiento,
                v2.secuencia,
                v3.secuencia_fp,
                v2.cod_comprobante_fp,
                v2.interes,
                v3.cod_comprobante_fp,
                v1.ddo_num_pago,
                v1.cliente,
                v1.ddo_doctran,
                v1.tipo_vencido,
                v2.valor,
                v3.valor_cobro,
                v1.ddo_monto,
                v1.fecha_actual,
                v1.ddo_fecha_emision,
                v1.ddo_fechaven
            from public.av_clientes_reiterativo_por_cuota v1
            inner join public.aav_migracion_cartera_historica_xcuotas v2 on v2.cod_comprobante_fp = v1.ddo_doctran
            inner join public.aav_migracion_cartera_historica_xcuotas_xcobros_masconcepto v3 on v3.cod_comprobante_fp = v1.ddo_doctran
            where
            v1.ent_identificacion = ?", [$identificacion]);
            }

            //echo ('sizeOf($data): '.json_encode(sizeOf($data)));
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }

    

    public function comprobantesCliReiterativo($identificacion, $page, $itemsPerPage)
    {
        try {
            // Establecemos la paginación con los parámetros recibidos
            $data = DB::table('crm.data_temp_cli_reiterativo')
                ->select(
                    'ddo_fecha_emision',
                    'cod_comprobante_fp',
                    'secuencia',
                    'fecha_vencimiento',
                    'fecha_cobro',
                    'cod_comprobante_cobro',
                    'tipo_comprobante_cobro',
                    'ccm_concepto',
                    'tipo_vencido',
                    'dias_atraso',
                    DB::raw("MAX((COALESCE(ult_fecha_pago, fecha_actual) - fecha_vencimiento)::int) AS dias_atraso"),
                )
                ->where('ent_identificacion', $identificacion)
                ->groupBy(
                    'ddo_fecha_emision',
                    'cod_comprobante_fp',
                    'secuencia',
                    'fecha_vencimiento',
                    'fecha_cobro',
                    'cod_comprobante_cobro',
                    'tipo_comprobante_cobro',
                    'ccm_concepto',
                    'tipo_vencido',
                    'dias_atraso'
                )
                ->orderBy('cod_comprobante_fp','asc')
                ->orderBy('secuencia', 'asc')
                ->paginate($itemsPerPage, ['*'], 'page', $page);

            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }
}

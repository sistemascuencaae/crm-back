<?php

namespace App\Http\Controllers\comercializacion;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CliReiterativoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => []]);
    }


    public function getByIdentificacionCliReitera($identificacion)
    {


        try {


            $cliente = DB::selectOne("SELECT * from crm.data_temp_cli_reiterativo where ent_identificacion = ? limit 1;", [$identificacion]);

            if ($cliente) {
                // Convierte 'created_at' a una instancia de Carbon
                $createdAt = Carbon::parse($cliente->created_at);

                // Verifica si han pasado 10 minutos desde la creación
                if ($createdAt->diffInMinutes(Carbon::now()) >= 10) {
                    // Ha pasado una hora o más
                    DB::transaction(function () use ($identificacion) {
                        DB::delete("DELETE from crm.data_temp_cli_reiterativo where ent_identificacion = ?;", [$identificacion]);
                        DB::insert(" INSERT INTO crm.data_temp_cli_reiterativo (
                ent_identificacion, dias_diferencia_cuotayrec, fecha_vencimiento, secuencia, secuencia_fp,
                cod_comprobante_fp, interes, ddo_num_pago, cliente,
                ddo_doctran, tipo_vencido, valor, valor_cobro,
                ddo_monto, fecha_actual, ddo_fecha_emision, ddo_fechaven, fecha_cobro, ult_fecha_pago,cod_comprobante_cobro,tipo_comprobante_cobro, ccm_concepto,dias_atraso, created_at
                )
                SELECT
            	    v1.ent_identificacion,
                    v1.dias_diferencia_cuotayrec,
                    v2.fecha_vencimiento,
                    v2.secuencia,
                    v3.secuencia_fp,
                    v2.cod_comprobante_fp,
                    v2.interes,
                    v1.ddo_num_pago,
                    v1.cliente,
                    v1.ddo_doctran,
                    v1.tipo_vencido,
                    v2.valor,
                    v3.valor_cobro,
                    v1.ddo_monto,
                    v1.fecha_actual,
                    v1.ddo_fecha_emision,
                    v1.ddo_fechaven,
            	    v3.fecha_cobro,
            	    v2.ult_fecha_pago,
            	    v3.cod_comprobante_cobro,
            	    v3.tipo_comprobante_cobro,
            	    v3.ccm_concepto,
            	    v2.dias_atraso,
            	    CURRENT_TIMESTAMP
                FROM crm.av_clientes_reiterativo_por_cuota v1
                INNER JOIN public.aav_migracion_cartera_historica_xcuotas v2
                    ON v2.cod_comprobante_fp = v1.ddo_doctran and v2.interes > 0
                INNER JOIN public.aav_migracion_cartera_historica_xcuotas_xcobros_masconcepto v3
                    ON v3.cod_comprobante_fp = v1.ddo_doctran
                WHERE v1.ent_identificacion = ?;", [$identificacion]);
                    });
                }
            }

            $data = $this->getDataClienteReiterativo($identificacion);

            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
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
            "dataCreditActivos" => $dataCreActivos,

        ];
        return $data;
    }


    public function comproClienReitera(Request $request)
    {
        try {

            $comprobantes = $request->all(); // Asegúrate de que $comprobantes es un array de valores

            // Convertimos el array a una cadena separada por comas
            $comprobantesString = implode(',', array_map(function ($comprobante) {
                return "'" . $comprobante . "'"; // Agregar comillas a cada valor
            }, $comprobantes));


            $data = DB::select("
            SELECT ttemp.name,
                   false as activo,
                   MAX(ttempfae.pro_nombre) as productos,
                   MAX(v1.tipo_nota) as tipo_nota,
                   MAX(v1.comprobante) as comprobante,
                   STRING_AGG(v1.pro_nombre, ', ') as pro_nombre
            FROM (
                SELECT DISTINCT cod_comprobante_fp as name,
                                false as activo
                FROM crm.data_temp_cli_reiterativo
                WHERE cod_comprobante_fp IN ($comprobantesString) -- Aquí usamos la cadena separada por comas
            ) ttemp
            LEFT JOIN av_notascredito_producto v1
                ON ttemp.name ILIKE '%' || v1.factura || '%'
            INNER JOIN (
                SELECT (cfa.cfa_periodo || '-' || cti.cti_sigla || '-' || alm.alm_codigo || '-' || pve.pve_numero || '-' || cfa.cfa_numero) AS comprobante,
                    STRING_AGG(pro.pro_codigo, ', ') AS pro_codigo,
                    STRING_AGG(pro.pro_nombre, ', ') AS pro_nombre
                FROM cfactura cfa
                INNER JOIN puntoventa pve ON pve.pve_id = cfa.pve_id
                INNER JOIN almacen alm ON alm.alm_id = pve.alm_id
                INNER JOIN ctipocom cti ON cti.cti_id = cfa.cti_id AND cti.cti_id = 59
                INNER JOIN dfactura dfa ON dfa.cfa_id = cfa.cfa_id
                INNER JOIN producto pro ON pro.pro_id = dfa.pro_id
                GROUP BY cfa.cfa_periodo, cti.cti_sigla, alm.alm_codigo, pve.pve_numero, cfa.cfa_numero
            ) ttempfae ON ttempfae.comprobante = ttemp.name
            GROUP BY ttemp.name;
        ");
            if (sizeof($data) > 0) {
                $data[0]->activo = true;
            }
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }

    public function comprobantesCliReiterativo($comprobante)
    {

        try {
            $data = DB::select("SELECT
                v1.fecha as fecha_emision,
                v1.cod_comprobante_fp,
                v2.secuencia_fp,
                v1.fecha_vencimiento,
                (select ult_fecha_pago from crm.data_temp_cli_reiterativo v3 where v3.cod_comprobante_cobro = v2.cod_comprobante_cobro and v3.secuencia = v2.secuencia_fp limit 1) as ult_fecha_pago,
                v2.cod_comprobante_cobro,
                v2.tipo_comprobante_cobro,
                v2.ccm_concepto,
                (select tipo_vencido from crm.data_temp_cli_reiterativo v3 where v3.cod_comprobante_cobro = v2.cod_comprobante_cobro limit 1) as estado_cuota,
                (select MAX((COALESCE(ult_fecha_pago, fecha_actual) - fecha_vencimiento)::int) AS dias_atraso from crm.data_temp_cli_reiterativo v3 where v2.cod_comprobante_cobro = v3.cod_comprobante_cobro limit 1) as dias_atraso_maximo,
                (select dias_atraso from crm.data_temp_cli_reiterativo v3 where v3.cod_comprobante_cobro = v2.cod_comprobante_cobro and v3.secuencia = v2.secuencia_fp limit 1) as dias_atraso,
                v2.valor_cobro
                from public.aav_migracion_cartera_historica_xcuotas v1
                inner join public.aav_migracion_cartera_historica_xcuotas_xcobros_masconcepto v2 on v2.cod_comprobante_fp = v1.cod_comprobante_fp and v1.secuencia = v2.secuencia_fp
                where v1.cod_comprobante_fp = ? and v2.secuencia_fp < 999 order by v2.secuencia_fp asc;", [$comprobante]);

            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }

    public function comprobantesCliReiterativo0($identificacion, $comprobante, $page, $itemsPerPage)
    {
        try {
            // Establecemos la paginación con los parámetros recibidos
            $data = DB::table('crm.data_temp_cli_reiterativo')
                ->select(
                    'ddo_fecha_emision',
                    'cod_comprobante_fp',
                    'secuencia',
                    'fecha_vencimiento',
                    'ult_fecha_pago',
                    //'cod_comprobante_cobro',
                    // 'tipo_comprobante_cobro',
                    // 'ccm_concepto',
                    // 'tipo_vencido',
                    // 'dias_atraso',
                    // DB::raw("MAX((COALESCE(ult_fecha_pago, fecha_actual) - fecha_vencimiento)::int) AS dias_atraso"),
                )
                ->where('ent_identificacion', $identificacion)
                ->where('cod_comprobante_fp', $comprobante)
                ->groupBy(
                    'ddo_fecha_emision',
                    'cod_comprobante_fp',
                    'secuencia',
                    'fecha_vencimiento',
                    'ult_fecha_pago',
                    //'cod_comprobante_cobro',
                    // 'tipo_comprobante_cobro',
                    // 'ccm_concepto',
                    // 'tipo_vencido',
                    // 'dias_atraso'
                )
                ->orderBy('cod_comprobante_fp', 'asc')
                ->orderBy('secuencia', 'asc')
                ->paginate($itemsPerPage, ['*'], 'page', $page);

            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }
}

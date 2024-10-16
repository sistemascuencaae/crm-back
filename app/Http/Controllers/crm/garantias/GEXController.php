<?php

namespace App\Http\Controllers\crm\garantias;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\crm\garantias\VentaGex;
use App\Models\crm\garantias\DevolucionGex;
use App\Models\crm\garantias\ReservasGex;
use App\Models\crm\garantias\RubrosReservas;
use App\Models\crm\garantias\RelacionLineasGex;
use App\Models\crm\garantias\ConfigItems;
use App\Models\crm\garantias\ExepcionGex;

class GEXController extends Controller
{
    public function facturaGex(Request $request)
    {
        date_default_timezone_set("America/Guayaquil");

        $fecha = date("Y-m-d");

        $pro_id = intval($request->input('pro_id'));
        $tpr_id = intval($request->input('tpr_id'));
        $precio = round(floatval($request->input('precio')),2);
        $producto = ConfigItems::get()->where('pro_id', $pro_id)->first();

        $data = array();

        $relacion = RelacionLineasGex::get()->where('tpr_id', $tpr_id);

        if (count($relacion) == 0) {
            return response('No hay GEX relacionado para el tipo de producto seleccionado...',400);
        }
        
        foreach ($relacion as $rel){
            $dato = new VentaGex();

            $prodGex = ConfigItems::get()->where('config_id', $rel['config_id'])->first();

            $excepcion = ExepcionGex::select()->where('pro_id', $pro_id)->whereraw("'" . $fecha . "' between fecha_ini and fecha_fin")->first();

            if ($excepcion != null){
                $dato->porc_gex = round(floatval($excepcion->porc_gex),2);
            }else{
                $dato->porc_gex = round(floatval($prodGex->porc_gex),2);
            }

            $dato->pro_id = $prodGex->pro_id;
            //$dato->config_id = $rel['config_id'];
            $dato->meses_gex = $prodGex->meses_garantia;
            //$dato->km_factor = $prodGex->km_factor ? $prodGex->km_factor : 0;
            $dato->valor_gex = round(($precio * $dato->porc_gex) / 100,2);
            //$dato->km_garantia = $producto ? $producto->km_garantia : 0;

            $rubros = RubrosReservas::orderby('capital_sn','asc')->get()->where('estado', 'A');

            if ($rubros->count() <= 0) {
                return response('No hay reservas GEX activas, favor revisar...',400);
            }

            $valor = 0.0;
            $porc = 0.0;

            foreach ($rubros as $r){
                $rubro = new ReservasGex();
                
                if ($r['capital_sn'] == 'N') {
                    $rubro->rr_id = $r['rr_id'];
                    $rubro->descripcion = $r['descripcion'];
                    $rubro->porc_calculo = round(floatval($r['porc_calculo']),2);
                    $rubro->capital_sn = $r['capital_sn'];
                    $rubro->valor = round(($dato->valor_gex * $rubro->porc_calculo) / 100,2);

                    $valor += $rubro->valor;
                    $porc += $rubro->porc_calculo;
                } else {
                    $rubro->rr_id = $r['rr_id'];
                    $rubro->descripcion = $r['descripcion'];
                    $rubro->porc_calculo = round(floatval(100 - $porc),2);
                    $rubro->capital_sn = $r['capital_sn'];
                    $rubro->valor = round($dato->valor_gex - $valor,2);
                }

                array_push($dato->reservas, $rubro);
            }

            array_push($data, $dato);
        }

        return response()->json($data[0]);
    }

    public function devuelveGex(Request $request)
    {
        $ccm_id = intval($request->input('ccm_id'));
        $cfa_id = intval($request->input('cfa_id'));
        $num_fac = intval($request->input('numero_factura'));
        $pro_id = intval($request->input('pro_id'));
        $pro_id_gex = intval($request->input('pro_id_gex'));
        $precio = round(floatval($request->input('precio_gex')),2);

        $data = new DevolucionGex();

        /*$aprobacion = RelacionLineasGex::select()->where('pro_id', $pro_id_gex)->first();

        if ($aprobacion == null) {
            return response('No hay GEX relacionado para el tipo de producto seleccionado...',400);
        }*/

        $data->valor_devolver = $precio;

        $rubros = RubrosReservas::orderby('capital_sn','asc')->get()->where('estado', 'A');

        if ($rubros->count() <= 0) {
            return response('No hay reservas GEX activas, favor revisar...',400);
        }

        $valor = 0.0;
        $porc = 0.0;

        foreach ($rubros as $r){
            $rubro = new ReservasGex();
            
            if ($r['capital_sn'] == 'N') {
                $rubro->rr_id = $r['rr_id'];
                $rubro->descripcion = $r['descripcion'];
                $rubro->porc_calculo = round(floatval($r['porc_calculo']),2);
                $rubro->capital_sn = $r['capital_sn'];
                $rubro->valor = round(($data->valor_devolver * $rubro->porc_calculo) / 100,2);

                $valor += $rubro->valor;
                $porc += $rubro->porc_calculo;
            } else {
                $rubro->rr_id = $r['rr_id'];
                $rubro->descripcion = $r['descripcion'];
                $rubro->porc_calculo = round(floatval(100 - $porc),2);
                $rubro->capital_sn = $r['capital_sn'];
                $rubro->valor = round($data->valor_devolver - $valor,2);
            }

            array_push($data->reservas, $rubro);
        }

        return response()->json($data);
    }
}
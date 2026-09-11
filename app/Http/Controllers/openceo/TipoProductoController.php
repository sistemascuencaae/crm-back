<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Controllers\crm\EmailController;
use App\Http\Resources\RespuestaApi;
use App\Models\openceo\CPedidoProforma;
use App\Models\openceo\TipoProducto;
use Exception;
use Illuminate\Support\Facades\DB;

class TipoProductoController extends Controller
{
    public function getTiposProducto()
    {
        try {
            $nivel1 = TipoProducto::where('tpr_nivel', 1)->where("tpr_id","<>", 209)->with('subTipos')->get();
            $bodegas = DB::select("SELECT * FROM public.bodega where bod_activo = true");
            $tiposPro = $this->buildTree($nivel1);
            $data = (object)[
                "tiposPro" => $tiposPro,
                "bodegas" => $bodegas,

            ];
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    private function buildTree($tipos)
    {
        return $tipos->map(function ($tipo) {
            $subTipos = $tipo->subTipos->map(function ($subTipo) {
                return [
                    'tpr_id' => $subTipo->tpr_id,
                    'tpr_nombre' => $subTipo->tpr_nombre,
                    'tpr_nivel' => $subTipo->tpr_nivel,
                    'tpr_reporta' => $subTipo->tpr_reporta,
                    'subTipos' => $this->buildTree($subTipo->subTipos)
                ];
            });

            return [
                'tpr_id' => $tipo->tpr_id,
                'tpr_nombre' => $tipo->tpr_nombre,
                'tpr_nivel' => $tipo->tpr_nivel,
                'tpr_reporta' => $tipo->tpr_reporta,
                'subTipos' => $subTipos
            ];
        });
    }
}

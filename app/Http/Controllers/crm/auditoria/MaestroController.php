<?php

namespace App\Http\Controllers\crm\auditoria;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Support\Facades\DB;

class MaestroController extends Controller
{
    public function maestroAuditoria()
    {
        $log = new Funciones();
        try {
            $data = DB::selectOne("SELECT actualizar_austro_creditos();"); // esto devuelve "actualizar_austro_creditos = 1" cuando es correcto.

            $log->logInfo(MaestroController::class, 'Se ejecuto correctamente la funcion public.actualizar_austro_creditos');

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con exito.', $data));
        } catch (Exception $e) {
            $log->logError(MaestroController::class, 'Error en la funcion public.actualizar_austro_creditos', $e->getMessage());

            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), ''));
        }
    }
}

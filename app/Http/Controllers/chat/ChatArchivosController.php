<?php

namespace App\Http\Controllers\chat;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatArchivosController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api', [
    //         'except' => [
    //             'listarArchivosConver',
    //             'listarGaleriaConver'
    //         ]
    //     ]);
    // }

    public function listarGaleriaConver($converId, $tipoChat)
    {
        try {
            $data = [];
            if ($tipoChat === 'NORMAL') {
                $data = DB::select("SELECT ga.* from crm.chat_mensajes cm
            inner join crm.chat_mensaje_archivos cma on cma.mensaje_id = cm.id
            inner join crm.galerias ga on ga.id = cma.galeria_id
            where cm.chatconve_id = ? order by ga.id", [$converId]);
            }

            if ($tipoChat === 'GRUPAL') {
                $data = DB::select("SELECT ga.* from crm.chat_mensajes cm
            inner join crm.chat_mensaje_archivos cma on cma.mensaje_id = cm.id
            inner join crm.galerias ga on ga.id = cma.galeria_id
            where cm.chatgrupo_id = ? order by ga.id", [$converId]);
            }
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }
    public function listarArchivosConver($converId, $tipoChat)
    {
        try {

            $data = [];
            if ($tipoChat === 'NORMAL') {
                $data = DB::select("SELECT ar.* from crm.chat_mensajes cm
            inner join crm.archivos ar on ar.id = cm.archivo_id
            where cm.chatconve_id = ? order by ar.id desc", [$converId]);
            }
            if ($tipoChat === 'GRUPAL') {
                $data = DB::select("SELECT ar.* from crm.chat_mensajes cm
                inner join crm.archivos ar on ar.id = cm.archivo_id
                where cm.chatgrupo_id = ? order by 1 desc", [$converId]);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }
}

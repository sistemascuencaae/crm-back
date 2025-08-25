<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\openceo\Bodega;
use App\Models\openceo\Usuario;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class BodegaController extends Controller
{
    public function listBodegas()
    {
        try {
            $data = DB::select("SELECT * FROM public.bodega where bod_activo = true");
            
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listBodegasUsuarios()
    {
        try {
            $data = Bodega::with('usuarios')->get();
            
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listAllUsuActivos(){
        try {
            $data = Usuario::where('usu_activo', true)->get();
            
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }






// PRUEBAS *************************************************************************************
    public function listBodegasDynamo()
    {
        try {
            $data = Bodega::where('bod_activo', true)->orderBy('bod_nombre', 'asc')->get();
            
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listUsuariosByBodIdDynamo($bod_id)
    {
        try {
            $data = Bodega::where('bod_id', $bod_id)->with('usuarios')->first();
            
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editUsuBodegas(Request $request)
    {
        try {
            DB::transaction(function () use ($request) {

                // Elimina los registros anteriores
                DB::table('daccesobod')->where('bod_id', $request->bod_id)->delete();

                // Inserta los nuevos
                $usuarios = $request->input('usuarios', []);

                foreach ($usuarios as $item) {
                    DB::table('daccesobod')->insert([
                        'usu_id' => $item['usu_id'],
                        'bod_id' => $request->bod_id,
                        'locked' => false,
                    ]);
                }
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito.', ''));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), ''));
        }
    }



}

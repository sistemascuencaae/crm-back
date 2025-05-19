<?php

namespace App\Http\Controllers\correo;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\correo\Correo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CorreoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addCorreo(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {

                $correo = Correo::create($request->all());

                $data = Correo::orderBy('id', 'desc')->get();

                // // Especificar las propiedades que representan fechas en tu objeto Correo
                // $dateFields = ['created_at', 'updated_at'];
                // // Utilizar la función map para transformar y obtener una nueva colección
                // $data->map(function ($item) use ($dateFields) {
                //     // $this->formatoFechaItem($item, $dateFields);
                //     $funciones = new Funciones();
                //     $funciones->formatoFechaItem($item, $dateFields);
                //     return $item;
                // });

                return $data;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listCorreos()
    {
        try {
            $correos = Correo::orderBy("id", "desc")->get();

            // Especificar las propiedades que representan fechas en tu objeto Correo
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $correos->map(function ($item) use ($dateFields) {
                // $this->formatoFechaItem($item, $dateFields);
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);
                return $item;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $correos));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editCorreo(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $correo = Correo::findOrFail($id);

                $correo->update($request->all());

                // // Especificar las propiedades que representan fechas en tu objeto correo
                // $dateFields = ['created_at', 'updated_at'];
                // $funciones = new Funciones();
                // $funciones->formatoFechaItem($correo, $dateFields);

                return $correo;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function deleteCorreo(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {

                $correo = Correo::findOrFail($id);

                $correo->delete();

                return $correo;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}
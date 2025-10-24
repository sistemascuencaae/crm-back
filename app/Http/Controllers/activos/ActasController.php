<?php

namespace App\Http\Controllers\activos;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\activos\Acta;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listAllActas()
    {
        try {
            $data = Acta::with('activo', 'user', 'localidad', 'departamento')->orderBy('numero', 'asc')->get();

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $data->map(function ($item) use ($dateFields) {
                // $this->formatoFechaItem($item, $dateFields);
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);
                return $item;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    public function addActa(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {

                $ultimo_acta = Acta::last()->fisrt();
                $ultimo_acta + 1;

// esta mal esto porque tengo que enviar un array de activos y de ahi ir creando la acta con el activo
                Acta::create(array_merge($request->all(), [
                        'numero' => $ultimo_acta,
                    ]));

                $data = Acta::with('activo', 'user', 'localidad', 'departamento')->orderBy('numero', 'asc')->get();

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at'];
                // Utilizar la función map para transformar y obtener una nueva colección
                $data->map(function ($item) use ($dateFields) {
                    // $this->formatoFechaItem($item, $dateFields);
                    $funciones = new Funciones();
                    $funciones->formatoFechaItem($item, $dateFields);
                    return $item;
                });

                return $data;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error: ' . $e->getMessage(), null));
        }
    }
}

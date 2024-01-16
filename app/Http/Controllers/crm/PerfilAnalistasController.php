<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\PerfilAnalistas;
use Exception;
use Illuminate\Http\Request;

class PerfilAnalistasController extends Controller
{
    private $log;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->log = new Funciones();
    }

    public function listAllPerfilAnalistas()
    {
        try {
            $perfil = PerfilAnalistas::get();

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $perfil->map(function ($item) use ($dateFields) {
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);
                return $item;
            });

            $this->log->logInfo(PerfilAnalistasController::class, 'Se listo con exito los perfiles de analistas');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $perfil));
        } catch (Exception $e) {
            $this->log->logError(PerfilAnalistasController::class, 'Error al listar los perfiles de analistas', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addPerfilAnalistas(Request $request)
    {
        try {
            PerfilAnalistas::create($request->all());

            $resultado = PerfilAnalistas::orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $resultado->map(function ($item) use ($dateFields) {
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);
                return $item;
            });

            $this->log->logInfo(PerfilAnalistasController::class, 'Se guardo con exito el perfil de analista');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado));
        } catch (Exception $e) {
            $this->log->logError(PerfilAnalistasController::class, 'Error al guardar el perfil de analista', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editPerfilAnalistas(Request $request, $id)
    {
        try {
            $perfil = PerfilAnalistas::findOrFail($id);

            $perfil->update($request->all());

            $resultado = PerfilAnalistas::where('id', $perfil->id)->first();

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at'];
            $funciones = new Funciones();
            $funciones->formatoFechaItem($resultado, $dateFields);

            $this->log->logInfo(PerfilAnalistasController::class, 'Se actualizo con exito el perfil de analista con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $resultado));
        } catch (Exception $e) {
            $this->log->logError(PerfilAnalistasController::class, 'Error al actualizar el perfil de analista con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deletePerfilAnalistas(Request $request, $id)
    {
        try {
            $perfil = PerfilAnalistas::findOrFail($id);

            $perfil->delete();

            $this->log->logInfo(PerfilAnalistasController::class, 'Se elimino con exito el perfil de analista con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $perfil));
        } catch (Exception $e) {
            $this->log->logError(PerfilAnalistasController::class, 'Error al eliminar el perfil de analista con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}
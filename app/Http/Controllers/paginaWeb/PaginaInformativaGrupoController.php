<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use App\Servicios\PaginaWeb\PaginaInformativaGrupoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Carpetas de los documentos (CRM): listar, crear, renombrar, eliminar y ordenar.
 * Validación ligera aquí; las reglas están en la base.
 */
class PaginaInformativaGrupoController extends Controller
{
    public function __construct(private PaginaInformativaGrupoService $servicio)
    {
    }

    public function listar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Carpetas listadas con éxito', $this->servicio->listar()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar las carpetas');
        }
    }

    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), ['pig_nombre' => 'required|string']);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Escribe el nombre de la carpeta.', $validator->errors()));
        }

        try {
            $pigId = $this->servicio->crear($request->only('pig_nombre') + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta creada con éxito', ['pig_id' => $pigId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al crear la carpeta');
        }
    }

    public function modificar(Request $request, int $pigId)
    {
        $validator = Validator::make($request->all(), ['pig_nombre' => 'required|string']);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Escribe el nombre de la carpeta.', $validator->errors()));
        }

        try {
            $this->servicio->modificar($pigId, $request->only('pig_nombre') + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta guardada con éxito', ['pig_id' => $pigId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la carpeta');
        }
    }

    public function eliminar(int $pigId)
    {
        try {
            $this->servicio->eliminar($pigId);

            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta eliminada con éxito', ['pig_id' => $pigId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar la carpeta');
        }
    }

    // Recibe TODAS las carpetas en el nuevo orden: {ids: [3, 1, 2]}
    public function ordenar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'El nuevo orden no es válido.', $validator->errors()));
        }

        try {
            $this->servicio->ordenar(['ids' => $request->input('ids'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el orden');
        }
    }

    // -----------------------------------------------------------------

    /** Código conocido → su mensaje (arriba, como siempre). Desconocido → mensaje genérico; el detalle queda en el log. */
    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico, PaginaInformativaGrupoService::MENSAJES_ERROR, false);
    }
}
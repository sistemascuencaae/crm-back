<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\PaginaInformativaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Páginas informativas (CRM): listar, ver una y modificar.
 * Las páginas se crean por script; desde el CRM solo se editan.
 * Validación ligera aquí; las reglas de cada página están en la base.
 */
class PaginaInformativaController extends Controller
{
    private const CAMPOS = ['pin_titulo', 'pin_activo', 'pin_contenido'];

    public function __construct(private PaginaInformativaService $servicio)
    {
    }

    public function listar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Páginas listadas con éxito', $this->servicio->listar()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar las páginas');
        }
    }

    public function buscar(string $clave)
    {
        try {
            $pagina = $this->servicio->buscar($clave);

            if ($pagina === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'La página no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Página encontrada', $pagina));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar la página');
        }
    }

    // Acepta solo lo que cambia: todo el contenido, o solo pin_activo desde el interruptor
    public function modificar(Request $request, string $clave)
    {
        $validator = Validator::make($request->all(), [
            'pin_titulo'    => 'sometimes|string',
            'pin_activo'    => 'sometimes|boolean',
            'pin_contenido' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        try {
            $this->servicio->modificar($clave, $request->only(self::CAMPOS) + ['usu_id' => $this->usuarioId()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Página guardada con éxito', ['pin_clave' => $clave]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la página');
        }
    }

    // -----------------------------------------------------------------

    /** Código conocido → su mensaje. Desconocido → mensaje genérico; el detalle queda en el log, no en la respuesta. */
    private function error(QueryException $e, string $mensajeGenerico)
    {
        $mensaje = $this->servicio->mensajeError($e);

        if ($mensaje === null) {
            Log::error($mensajeGenerico, ['error' => $e->getMessage()]);
            $mensaje = $mensajeGenerico;
        }

        return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
    }

    /** Clave primaria del usuario autenticado (usu_id). */
    private function usuarioId(): ?int
    {
        return auth()->id();
    }
}
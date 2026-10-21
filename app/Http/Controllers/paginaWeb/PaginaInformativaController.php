<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use App\Servicios\PaginaWeb\PaginaInformativaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Páginas informativas (CRM): listar, ver una, crear, modificar, eliminar y ordenar.
 * Desde el CRM solo se crean documentos; las fijas (Garantía…) vienen por script, no se eliminan
 * y no van en carpetas. Validación ligera aquí; las reglas están en la base.
 */
class PaginaInformativaController extends Controller
{
    private const CAMPOS = ['pin_titulo', 'pin_activo', 'pig_id', 'pin_contenido'];

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
            'pig_id'        => 'sometimes|nullable|integer',
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

    // Página de documento nueva: título y carpeta (opcional). Nace oculta.
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pin_titulo' => 'required|string',
            'pig_id'     => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Escribe el título de la página.', $validator->errors()));
        }

        try {
            $clave = $this->servicio->crear($request->only('pin_titulo', 'pig_id') + ['usu_id' => $this->usuarioId()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Página creada con éxito', ['pin_clave' => $clave]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al crear la página');
        }
    }

    public function eliminar(string $clave)
    {
        try {
            $this->servicio->eliminar($clave);

            return response()->json(RespuestaApi::returnResultado('success', 'Página eliminada con éxito', ['pin_clave' => $clave]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar la página');
        }
    }

    // Recibe TODOS los documentos de una carpeta en el nuevo orden: {pig_id: 1 (o null), ids: [7, 5, 6]}
    public function ordenar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pig_id' => 'present|nullable|integer',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'El nuevo orden no es válido.', $validator->errors()));
        }

        try {
            $this->servicio->ordenar($request->only('pig_id', 'ids') + ['usu_id' => $this->usuarioId()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el orden');
        }
    }

    // -----------------------------------------------------------------

    /** Código conocido → su mensaje (arriba, como siempre). Desconocido → mensaje genérico; el detalle queda en el log. */
    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico, PaginaInformativaService::MENSAJES_ERROR, false);
    }

    /** Clave primaria del usuario autenticado (usu_id). */
    private function usuarioId(): ?int
    {
        return auth()->id();
    }
}
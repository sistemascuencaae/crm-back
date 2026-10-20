<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\GaleriaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Galerías de imágenes (CRM): listar galerías y, dentro de cada una, subir, modificar,
 * eliminar y ordenar sus imágenes. La imagen se sube antes con POST pagina-web/imagenes,
 * que devuelve su URL. Validación ligera aquí; las reglas están en la base.
 */
class GaleriaController extends Controller
{
    // Lo único que se acepta de una imagen. Los de banner (celular, enlace, mostrar en) son opcionales.
    private const CAMPOS = ['gim_imagen', 'gim_imagen_movil', 'gim_nombre', 'gim_enlace',
                            'gim_nueva_pestana', 'gim_mostrar_en', 'gim_activo'];

    public function __construct(private GaleriaService $servicio)
    {
    }

    public function listar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Galerías listadas con éxito', $this->servicio->listar()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar las galerías');
        }
    }

    public function listarImagenes(int $galId)
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Imágenes listadas con éxito', $this->servicio->listarImagenes($galId)));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar las imágenes');
        }
    }

    // {gim_imagen (URL de la imagen ya subida), gim_nombre}
    // En banners además: gim_imagen_movil, gim_enlace, gim_nueva_pestana, gim_mostrar_en (todos | computadora | celular)
    public function crearImagen(Request $request, int $galId)
    {
        $validator = Validator::make($request->all(), [
            'gim_imagen' => 'required|string',
            'gim_nombre' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Sube una imagen y escribe su nombre.', $validator->errors()));
        }

        try {
            $gimId = $this->servicio->crearImagen(
                $request->only(self::CAMPOS) + ['gal_id' => $galId, 'usu_id' => auth()->id()]
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen agregada con éxito', ['gim_id' => $gimId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al agregar la imagen');
        }
    }

    // Solo lo que cambia (cualquiera de CAMPOS)
    public function modificarImagen(Request $request, int $gimId)
    {
        $validator = Validator::make($request->all(), [
            'gim_imagen' => 'sometimes|string',
            'gim_nombre' => 'sometimes|string',
            'gim_activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        try {
            $this->servicio->modificarImagen(
                $gimId,
                $request->only(self::CAMPOS) + ['usu_id' => auth()->id()]
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen guardada con éxito', ['gim_id' => $gimId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la imagen');
        }
    }

    public function eliminarImagen(int $gimId)
    {
        try {
            $this->servicio->eliminarImagen($gimId);

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen eliminada con éxito', ['gim_id' => $gimId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar la imagen');
        }
    }

    // Recibe TODAS las imágenes de la galería en el nuevo orden: {ids: [7, 5, 6]}
    public function ordenarImagenes(Request $request, int $galId)
    {
        $validator = Validator::make($request->all(), [
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'El nuevo orden no es válido.', $validator->errors()));
        }

        try {
            $this->servicio->ordenarImagenes(['gal_id' => $galId, 'ids' => $request->input('ids'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el orden');
        }
    }

    // -----------------------------------------------------------------

    /** Código conocido → su mensaje. Desconocido → mensaje genérico; el detalle queda en el log. */
    private function error(QueryException $e, string $mensajeGenerico)
    {
        $mensaje = $this->servicio->mensajeError($e);

        if ($mensaje === null) {
            Log::error($mensajeGenerico, ['error' => $e->getMessage()]);
            $mensaje = $mensajeGenerico;
        }

        return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
    }
}
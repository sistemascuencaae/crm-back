<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use App\Servicios\PaginaWeb\GaleriaService;
use App\Servicios\PaginaWeb\ListaPaginaWeb;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Galerías de imágenes (CRM): listar galerías y, dentro de cada una, subir, modificar,
 * eliminar y ordenar sus imágenes; en los banners, también sus grupos programados.
 * La imagen se sube antes con POST pagina-web/imagenes, que devuelve su URL. Validación ligera aquí; las reglas
 * están en la base.
 */
class GaleriaController extends Controller
{
    // Lo único que se acepta de una imagen. La vigencia (desde / hasta, no en los banners), los de banner (celular,
    // enlace, mostrar en) y los de tarjetas (título, subtítulo, color, botón) son opcionales.
    private const CAMPOS = ['gim_imagen', 'gim_imagen_movil', 'gim_nombre', 'gim_enlace',
                            'gim_nueva_pestana', 'gim_mostrar_en', 'gim_activo', 'gim_desde', 'gim_hasta',
                            'gim_titulo', 'gim_subtitulo', 'gim_color', 'gim_boton'];

    // Lo único que se acepta de un grupo de un banner
    private const CAMPOS_GRUPO = ['ggr_nombre', 'ggr_desde', 'ggr_hasta'];

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
    // Opcional fuera de los banners: gim_desde, gim_hasta ("YYYY-MM-DDTHH:MM", hora de Ecuador, o null)
    // En banners además: ggr_id (obligatorio: un grupo de ese banner), gim_imagen_movil, gim_enlace, gim_nueva_pestana,
    //   gim_mostrar_en (todos | computadora | celular). Sin fechas propias (si llegan, la base las ignora)
    // En tarjetas además: gim_titulo, gim_subtitulo, gim_color (rojo | oscuro | claro | gris), gim_boton
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
                $request->only([...self::CAMPOS, 'ggr_id']) + ['gal_id' => $galId, 'usu_id' => auth()->id()]
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen agregada con éxito', ['gim_id' => $gimId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al agregar la imagen');
        }
    }

    // Solo lo que cambia (cualquiera de CAMPOS; una imagen no cambia de grupo)
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

    // DELETE pagina-web/galerias-imagenes/{gimId}   (a la papelera)
    public function eliminarImagen(int $gimId)
    {
        try {
            $this->servicio->eliminarImagen($gimId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen enviada a la papelera', ['gim_id' => $gimId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar la imagen');
        }
    }

    // GET pagina-web/galerias-imagenes-papelera?gal_id=3&busqueda=&pagina=1&tamanio=20   → {registros, total}
    public function papeleraImagenes(Request $request)
    {
        try {
            $imagenes = $this->servicio->papeleraImagenes((int) $request->input('gal_id'), ...ListaPaginaWeb::paginacion($request));

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera', $imagenes));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar la papelera');
        }
    }

    // PUT pagina-web/galerias-imagenes/{gimId}/restaurar
    public function restaurarImagen(int $gimId)
    {
        try {
            $this->servicio->restaurarImagen($gimId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen restaurada con éxito', ['gim_id' => $gimId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al restaurar la imagen');
        }
    }

    // DELETE pagina-web/galerias-imagenes-papelera?gal_id=3   → {cantidad} (vacía también los grupos de la papelera)
    public function vaciarImagenes(Request $request)
    {
        try {
            $cantidad = $this->servicio->vaciarImagenes((int) $request->input('gal_id'), auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera vaciada', ['cantidad' => $cantidad]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al vaciar la papelera');
        }
    }

    // Recibe TODAS las imágenes en el nuevo orden: {ids: [7, 5, 6]}; en los banners, {ggr_id, ids} con todas las de ese grupo
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
            $this->servicio->ordenarImagenes([
                'gal_id' => $galId,
                'ggr_id' => $request->input('ggr_id'),
                'ids'    => $request->input('ids'),
                'usu_id' => auth()->id(),
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el orden');
        }
    }

    // -----------------------------------------------------------------
    // Grupos de los banners
    // -----------------------------------------------------------------

    // GET pagina-web/galerias/{galId}/grupos   → programados por prioridad y "Siempre" al final
    public function listarGrupos(int $galId)
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Grupos listados con éxito', $this->servicio->listarGrupos($galId)));
        } catch (QueryException $e) {
            return $this->errorGrupo($e, 'No se pudieron listar los grupos');
        }
    }

    // POST pagina-web/galerias/{galId}/grupos   {ggr_nombre, ggr_desde, ggr_hasta} ("YYYY-MM-DDTHH:MM", hora de Ecuador)
    public function crearGrupo(Request $request, int $galId)
    {
        try {
            $ggrId = $this->servicio->crearGrupo($request->only(self::CAMPOS_GRUPO) + ['gal_id' => $galId, 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Grupo creado con éxito', ['ggr_id' => $ggrId]));
        } catch (QueryException $e) {
            return $this->errorGrupo($e, 'Error al crear el grupo');
        }
    }

    // PUT pagina-web/galerias-grupos/{ggrId}   (solo lo que cambia: ggr_nombre, ggr_desde, ggr_hasta)
    public function modificarGrupo(Request $request, int $ggrId)
    {
        try {
            $this->servicio->modificarGrupo($ggrId, $request->only(self::CAMPOS_GRUPO) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Grupo guardado con éxito', ['ggr_id' => $ggrId]));
        } catch (QueryException $e) {
            return $this->errorGrupo($e, 'Error al guardar el grupo');
        }
    }

    // PUT pagina-web/galerias/{galId}/grupos-orden   {ids: [4, 2, 3]} (todos los programados; el primero gana)
    public function ordenarGrupos(Request $request, int $galId)
    {
        try {
            $this->servicio->ordenarGrupos(['gal_id' => $galId, 'ids' => $request->input('ids'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->errorGrupo($e, 'Error al guardar el orden');
        }
    }

    // DELETE pagina-web/galerias-grupos/{ggrId}   (a la papelera con sus imágenes)
    public function eliminarGrupo(int $ggrId)
    {
        try {
            $this->servicio->eliminarGrupo($ggrId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Grupo enviado a la papelera', ['ggr_id' => $ggrId]));
        } catch (QueryException $e) {
            return $this->errorGrupo($e, 'Error al eliminar el grupo');
        }
    }

    // PUT pagina-web/galerias-grupos/{ggrId}/restaurar   (vuelve con sus imágenes, al final de la prioridad)
    public function restaurarGrupo(int $ggrId)
    {
        try {
            $this->servicio->restaurarGrupo($ggrId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Grupo restaurado con éxito', ['ggr_id' => $ggrId]));
        } catch (QueryException $e) {
            return $this->errorGrupo($e, 'Error al restaurar el grupo');
        }
    }

    // GET pagina-web/galerias-grupos-papelera?gal_id=5&busqueda=&pagina=1&tamanio=20   → {registros, total}
    // (se vacía junto con las imágenes: DELETE galerias-imagenes-papelera)
    public function papeleraGrupos(Request $request)
    {
        try {
            $grupos = $this->servicio->papeleraGrupos((int) $request->input('gal_id'), ...ListaPaginaWeb::paginacion($request));

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera', $grupos));
        } catch (QueryException $e) {
            return $this->errorGrupo($e, 'No se pudo cargar la papelera');
        }
    }

    // -----------------------------------------------------------------

    /** Código conocido → su mensaje (arriba, como siempre). Desconocido → mensaje genérico; el detalle queda en el log. */
    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico, GaleriaService::MENSAJES_ERROR, false);
    }

    /**
     * Errores de los grupos: con los mensajes comunes (los de GaleriaService cambian CAMPO_LARGO y CAMPO_INVALIDO para
     * el enlace de la imagen) y los de un campo como data {campo: mensaje} (ggr_nombre, ggr_desde, ggr_hasta).
     */
    private function errorGrupo(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico);
    }
}
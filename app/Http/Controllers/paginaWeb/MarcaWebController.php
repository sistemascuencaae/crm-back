<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use App\Servicios\PaginaWeb\MarcaWebService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Marcas de la tienda (CRM): el logo de cada marca y cuáles salen en "Nuestras marcas", en su orden.
 * El logo se sube antes con POST pagina-web/marcas-tienda-logo, que devuelve su URL; se guarda con el PUT.
 * Validación ligera aquí; las reglas están en la base. Los errores de un campo llegan como data {campo: mensaje}.
 */
class MarcaWebController extends Controller
{
    // Lo único que se acepta al modificar
    private const CAMPOS = ['mar_imagen', 'mar_web_destacada'];

    public function __construct(private MarcaWebService $servicio)
    {
    }

    // GET pagina-web/marcas-tienda
    public function listar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Marcas', $this->servicio->listar()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar las marcas');
        }
    }

    // PUT pagina-web/marcas-tienda/{marId}   {mar_imagen} o {mar_web_destacada} (solo lo que cambia)
    public function modificar(Request $request, int $marId)
    {
        try {
            $this->servicio->modificar($marId, $request->only(self::CAMPOS) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Marca guardada con éxito', ['mar_id' => $marId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la marca');
        }
    }

    // PUT pagina-web/marcas-tienda-orden   {ids: [12, 5, 40]} (todas las de Nuestras marcas, en el nuevo orden)
    public function ordenar(Request $request)
    {
        try {
            $this->servicio->ordenar(['ids' => $request->input('ids'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el orden');
        }
    }

    // POST pagina-web/marcas-tienda-logo   multipart: archivo (PNG, WEBP o JPG, máx. 2 MB) → {url}
    // Solo sube el archivo; la URL se guarda después con el PUT.
    public function subirLogo(Request $request)
    {
        // mimes revisa el contenido real del archivo, no solo su nombre. Sin SVG: puede llevar código.
        $validator = Validator::make($request->all(), [
            'archivo' => 'required|file|mimes:png,webp,jpg,jpeg|max:2048',
        ], [
            'archivo.required' => 'Elige una imagen.',
            'archivo.file'     => 'Elige una imagen.',
            'archivo.mimes'    => 'El logo debe ser PNG, WEBP o JPG.',
            'archivo.max'      => 'El logo no puede pesar más de 2 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        return app(ArchivoPaginaWebController::class)->guardar(
            $request->file('archivo'),
            'api/public/pagina-web/imagenes/',
            'Logo subido',
            'marcas'
        );
    }

    // -----------------------------------------------------------------

    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico, MarcaWebService::MENSAJES_ERROR);
    }
}

<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\LimpiadorDocumento;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Documentos de la tienda (ecommerce.documentos) y sus carpetas (ecommerce.documentos_carpetas): términos, políticas,
// garantías... Cada documento es UN texto con formato (HTML del editor del CRM) con un PDF opcional. Reemplaza a las
// páginas informativas. Listar, ver, crear, editar, ordenar, papelera (borrado lógico), auditoría, subir imágenes y
// PDF, y lo que pide la tienda (públicos).
// Las reglas viven en ecommerce.fn_documento_* (database/sql/documentos.sql). El HTML se limpia aquí antes de guardarlo
// (LimpiadorDocumento: lista blanca de etiquetas y estilos, imágenes solo del servidor).
class DocumentosController extends Controller
{
    // Imágenes y PDF: carpeta privada storage/app/documentos; se piden por api/public/documentos/imagenes|pdf/{nombre}.
    private const CARPETA_IMAGENES = 'documentos/imagenes';
    private const CARPETA_PDF = 'documentos/pdf';
    private const NOMBRE_IMAGEN = '/^[A-Za-z0-9]{40}\.(webp|jpg|png)$/';
    private const NOMBRE_PDF = '/^[A-Za-z0-9]{40}\.pdf$/';
    private const TIPOS_IMAGEN = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'];
    private const IMAGEN_MAXIMO_KB = 2048;
    private const PDF_MAXIMO_KB = 10240;

    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se pudo identificar al usuario. Vuelva a iniciar sesión.',
        'REQUIERE_TITULO' => 'Debe ingresar el título del documento.',
        'TITULO_LARGO' => 'El título no puede tener más de 150 caracteres.',
        'DOCUMENTO_DUPLICADO' => 'Ya existe un documento con ese título (si está restaurando, cambie primero el título del otro).',
        'ux_documentos_titulo' => 'Ya existe un documento con ese título.',
        'REQUIERE_RUTA' => 'Debe ingresar la dirección del documento.',
        'RUTA_LARGA' => 'La dirección no puede tener más de 80 caracteres.',
        'RUTA_INVALIDA' => 'La dirección solo puede tener letras minúsculas, números y guiones (sin espacios, tildes ni barras). Ejemplo: terminos-y-condiciones.',
        'RUTA_DUPLICADA' => 'Ya existe un documento con esa dirección (si está restaurando, cambie primero la del otro).',
        'ux_documentos_ruta' => 'Ya existe un documento con esa dirección.',
        'CONTENIDO_LARGO' => 'El documento es demasiado largo (máximo 500.000 caracteres).',
        'CONTENIDO_NO_PERMITIDO' => 'El contenido tiene elementos no permitidos (código o scripts).',
        'PDF_INVALIDO' => 'El PDF no es válido: súbalo de nuevo.',
        'ETIQUETA_PDF_LARGA' => 'El texto del botón del PDF no puede tener más de 40 caracteres.',
        'VALOR_INVALIDO' => 'El valor de "Activo" no es válido.',
        'DOCUMENTO_NO_EXISTE' => 'El documento no existe o está en la papelera.',
        'DOCUMENTO_NO_EN_PAPELERA' => 'El documento no está en la papelera (puede que otro usuario ya lo restauró).',
        'PAPELERA_DOCUMENTO_ACTIVO' => 'No se puede eliminar porque está activo: inactívelo primero.',
        'REQUIERE_DOCUMENTOS' => 'Envíe el nuevo orden de los documentos.',
        'DOCUMENTOS_NO_COINCIDEN' => 'La carpeta cambió mientras la ordenaba (se agregó, movió o eliminó un documento). Vuelva a cargarla.',
        'REQUIERE_NOMBRE_CARPETA' => 'Debe ingresar el nombre de la carpeta.',
        'NOMBRE_CARPETA_LARGO' => 'El nombre de la carpeta no puede tener más de 80 caracteres.',
        'CARPETA_DUPLICADA' => 'Ya existe una carpeta con ese nombre en el mismo lugar.',
        'CARPETA_PADRE_NO_EXISTE' => 'La carpeta donde quiere ponerla no existe o está en la papelera.',
        'CARPETA_CICLO' => 'No se puede mover una carpeta dentro de sí misma ni de una de sus subcarpetas.',
        'CARPETA_NIVEL_MAXIMO' => 'Las carpetas llegan hasta tres niveles (carpeta, subcarpeta y una más dentro).',
        'CARPETA_CON_SUBCARPETAS' => 'La carpeta tiene subcarpetas: elimínelas o muévalas antes.',
        'ux_documentos_carpetas_nombre' => 'Ya existe una carpeta con ese nombre en el mismo lugar.',
        'CARPETA_NO_EXISTE' => 'La carpeta no existe o está en la papelera.',
        'CARPETA_NO_EN_PAPELERA' => 'La carpeta no está en la papelera (puede que otro usuario ya la restauró).',
        'CARPETA_CON_DOCUMENTOS' => 'La carpeta tiene documentos: muévalos a otra carpeta o elimínelos antes.',
        'REQUIERE_CARPETAS' => 'Envíe el nuevo orden de las carpetas.',
        'CARPETAS_NO_COINCIDEN' => 'Las carpetas cambiaron mientras las ordenaba. Vuelva a cargarlas.',
    ];

    private const MENSAJES_FORMULARIO = [
        'doc_titulo.required' => 'Debe ingresar el título del documento.',
        'doc_titulo.max' => 'El título no puede tener más de 150 caracteres.',
        'doc_ruta.required' => 'Debe ingresar la dirección del documento.',
        'doc_ruta.max' => 'La dirección no puede tener más de 80 caracteres.',
        'doc_contenido.string' => 'El contenido del documento no es válido.',
        'doc_contenido.max' => 'El documento es demasiado largo (máximo 500.000 caracteres).',
        'doc_pdf.max' => 'El PDF no es válido: súbalo de nuevo.',
        'doc_pdf_etiqueta.max' => 'El texto del botón del PDF no puede tener más de 40 caracteres.',
        'dca_id.integer' => 'La carpeta no es válida.',
        'doc_activo.boolean' => 'El valor de "Activo" no es válido.',
        'dca_nombre.required' => 'Debe ingresar el nombre de la carpeta.',
        'dca_nombre.max' => 'El nombre de la carpeta no puede tener más de 80 caracteres.',
        'dca_padre_id.integer' => 'La carpeta donde quiere ponerla no es válida.',
        'documentos.required' => 'Envíe el nuevo orden de los documentos.',
        'documentos.array' => 'El orden de los documentos no es válido.',
        'carpetas.required' => 'Envíe el nuevo orden de las carpetas.',
        'carpetas.array' => 'El orden de las carpetas no es válido.',
    ];

    // Lo único que se acepta del formulario del documento.
    private const CAMPOS_PAYLOAD = ['doc_titulo', 'doc_ruta', 'doc_contenido', 'doc_pdf', 'doc_pdf_etiqueta', 'dca_id', 'doc_activo'];

    public function __construct(private LimpiadorDocumento $limpiador)
    {
        $this->middleware('auth:api')->except(['publico', 'publicos', 'verImagen', 'verPdf']);
    }

    // ================= Documentos =================

    // Documentos (fuera de la papelera), paginados -> ecommerce.fn_documento_listar_paginacion.
    // GET ?pagina=&tamanio=&busqueda=&dca_id=   (dca_id: vacío = todos; 0 = sin carpeta; N = esa carpeta)
    public function listar(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));
            $dcaId = $request->query('dca_id');
            $dcaId = $dcaId === null || $dcaId === '' ? null : max((int) $dcaId, 0);

            $registros = DB::select('SELECT * FROM ecommerce.fn_documento_listar_paginacion(?, ?, ?, ?)', [$pagina, $tamanio, $busqueda, $dcaId]);

            return response()->json(RespuestaApi::returnResultado('success', 'Documentos listados con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los documentos', $th->getMessage()));
        }
    }

    // Un documento completo (también de la papelera), para editarlo. Las imágenes van con su dirección completa.
    public function ver(int $id)
    {
        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_documento_buscar(?) AS documento', [$id]);
            if (!$resultado || $resultado->documento === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'El documento no existe.', null));
            }

            $documento = json_decode($resultado->documento);
            $documento->doc_contenido = $this->conImagenesCompletas($documento->doc_contenido);
            $documento->doc_pdf_url = $documento->doc_pdf ? url('api/public/documentos/pdf/' . $documento->doc_pdf) : null;

            return response()->json(RespuestaApi::returnResultado('success', 'Documento cargado con éxito', ['documento' => $documento]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el documento', $th->getMessage()));
        }
    }

    // Crea un documento -> ecommerce.fn_documento_add.
    // Body: { doc_titulo, doc_ruta, doc_contenido?, doc_pdf?, doc_pdf_etiqueta?, dca_id?, doc_activo? }
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), $this->reglas(false), self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_documento_add(?::jsonb) AS doc_id', [json_encode($this->payload($request) + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Documento creado con éxito', ['doc_id' => $resultado->doc_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear el documento');
        }
    }

    // Modifica un documento -> ecommerce.fn_documento_edit. Lo que no viene se queda como está.
    public function editar(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), $this->reglas(true), self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $payload = $this->payload($request) + ['doc_id' => $id];
            $resultado = DB::selectOne('SELECT ecommerce.fn_documento_edit(?::jsonb) AS doc_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Documento guardado con éxito', ['doc_id' => $resultado->doc_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar el documento');
        }
    }

    // Nuevo orden de los documentos de una carpeta -> ecommerce.fn_documentos_ordenar. Body: { dca_id (null = sin carpeta), documentos: [doc_id, ...] }
    public function ordenar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dca_id' => 'nullable|integer|min:1',
            'documentos' => 'present|array',
            'documentos.*' => 'integer|min:1',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            DB::selectOne('SELECT ecommerce.fn_documentos_ordenar(?::jsonb) AS cambios', [json_encode([
                'dca_id' => $request->input('dca_id'),
                'documentos' => array_values($request->input('documentos', [])),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado', null));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al ordenar los documentos');
        }
    }

    public function eliminar(Request $request, int $id)
    {
        return $this->accion('fn_documento_papelera', ['doc_id' => $id], $request, 'Documento enviado a la papelera', 'Error al eliminar el documento');
    }

    public function restaurar(Request $request, int $id)
    {
        return $this->accion('fn_documento_restaurar', ['doc_id' => $id], $request, 'Documento restaurado con éxito', 'Error al restaurar el documento');
    }

    // Documentos en la papelera -> ecommerce.fn_documento_papelera_paginacion.
    public function papelera(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_documento_papelera_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera cargada con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la papelera', $th->getMessage()));
        }
    }

    // Auditoría de UN documento. GET ?doc_id=&pagina=&tamanio=&busqueda=
    public function auditoria(Request $request)
    {
        try {
            $docId = (int) $request->query('doc_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($docId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Documento no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ecommerce.fn_documento_auditoria_resumen(?)', [$docId]);
            $eventos = DB::select('SELECT * FROM ecommerce.fn_documento_auditoria_listar_paginacion(?, ?, ?, ?)', [$docId, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del documento', $th->getMessage()));
        }
    }

    // ================= Carpetas =================

    // Las carpetas en su orden con cuántos documentos tienen (?papelera=1: las de la papelera).
    public function carpetas(Request $request)
    {
        try {
            $registros = DB::select('SELECT * FROM ecommerce.fn_documento_carpeta_listar(?)', [filter_var($request->query('papelera', false), FILTER_VALIDATE_BOOLEAN)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Carpetas listadas con éxito', ['registros' => $registros]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las carpetas', $th->getMessage()));
        }
    }

    // Body: { dca_nombre, dca_padre_id? } (sin padre = primer nivel; hasta tres niveles).
    public function crearCarpeta(Request $request)
    {
        $validator = Validator::make($request->all(), ['dca_nombre' => 'required|string|max:80', 'dca_padre_id' => 'nullable|integer|min:1'], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_documento_carpeta_add(?::jsonb) AS dca_id', [json_encode($request->only(['dca_nombre', 'dca_padre_id']) + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta creada con éxito', ['dca_id' => $resultado->dca_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear la carpeta');
        }
    }

    // Body: { dca_nombre, dca_padre_id? } — si viene dca_padre_id, la carpeta se mueve allí (null = primer nivel).
    public function editarCarpeta(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), ['dca_nombre' => 'required|string|max:80', 'dca_padre_id' => 'sometimes|nullable|integer|min:1'], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_documento_carpeta_edit(?::jsonb) AS dca_id', [json_encode($request->only(['dca_nombre', 'dca_padre_id']) + ['dca_id' => $id, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta guardada con éxito', ['dca_id' => $resultado->dca_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar la carpeta');
        }
    }

    public function eliminarCarpeta(Request $request, int $id)
    {
        return $this->accion('fn_documento_carpeta_papelera', ['dca_id' => $id], $request, 'Carpeta enviada a la papelera', 'Error al eliminar la carpeta');
    }

    public function restaurarCarpeta(Request $request, int $id)
    {
        return $this->accion('fn_documento_carpeta_restaurar', ['dca_id' => $id], $request, 'Carpeta restaurada con éxito', 'Error al restaurar la carpeta');
    }

    // Nuevo orden de las carpetas de UN padre -> ecommerce.fn_documento_carpetas_ordenar.
    // Body: { dca_padre_id (null = primer nivel), carpetas: [dca_id, ...] }
    public function ordenarCarpetas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dca_padre_id' => 'nullable|integer|min:1',
            'carpetas' => 'present|array',
            'carpetas.*' => 'integer|min:1',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            DB::selectOne('SELECT ecommerce.fn_documento_carpetas_ordenar(?::jsonb) AS cambios', [json_encode([
                'dca_padre_id' => $request->input('dca_padre_id'),
                'carpetas' => array_values($request->input('carpetas', [])),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado', null));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al ordenar las carpetas');
        }
    }

    // ================= Imágenes y PDF =================

    // Sube una imagen para el texto del documento: WebP, JPG o PNG de verdad (mimetypes mira el contenido), hasta 2 MB.
    // Devuelve el nombre y la dirección para ponerla en el editor.
    public function subirImagen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'imagen' => 'required|file|mimetypes:image/webp,image/jpeg,image/png|max:' . self::IMAGEN_MAXIMO_KB,
        ], [
            'imagen.required' => 'Elija una imagen.',
            'imagen.file' => 'Elija una imagen.',
            'imagen.mimetypes' => 'Solo se aceptan imágenes WebP, JPG o PNG.',
            'imagen.max' => 'La imagen no puede pesar más de 2 MB.',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        try {
            $extension = array_search($request->file('imagen')->getMimeType(), self::TIPOS_IMAGEN, true) ?: 'webp';
            $nombre = Str::random(40) . '.' . $extension;
            $request->file('imagen')->storeAs(self::CARPETA_IMAGENES, $nombre, 'local');

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen subida', [
                'nombre' => $nombre,
                'url' => url('api/public/documentos/imagenes/' . $nombre),
            ]));
        } catch (\Throwable $th) {
            Log::error('No se pudo subir la imagen del documento', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo subir la imagen.', null));
        }
    }

    // Sube el PDF descargable del documento (hasta 10 MB). Devuelve el nombre (para guardarlo en doc_pdf) y su dirección.
    public function subirPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pdf' => 'required|file|mimetypes:application/pdf|max:' . self::PDF_MAXIMO_KB,
        ], [
            'pdf.required' => 'Elija un PDF.',
            'pdf.file' => 'Elija un PDF.',
            'pdf.mimetypes' => 'Solo se aceptan archivos PDF.',
            'pdf.max' => 'El PDF no puede pesar más de 10 MB.',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        try {
            $nombre = Str::random(40) . '.pdf';
            $request->file('pdf')->storeAs(self::CARPETA_PDF, $nombre, 'local');

            return response()->json(RespuestaApi::returnResultado('success', 'PDF subido', [
                'nombre' => $nombre,
                'url' => url('api/public/documentos/pdf/' . $nombre),
            ]));
        } catch (\Throwable $th) {
            Log::error('No se pudo subir el PDF del documento', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo subir el PDF.', null));
        }
    }

    // ================= Tienda (públicos) =================

    // Un documento activo por su dirección, para la tienda. GET public/documentos/{ruta}
    public function publico(string $ruta)
    {
        try {
            $documento = DB::selectOne('SELECT * FROM ecommerce.fn_documento_buscar_publico(?)', [$ruta]);
            // 404 (como las páginas informativas): la tienda muestra "no disponible".
            if (!$documento) {
                return response()->json(RespuestaApi::returnResultado('error', 'El documento no existe.', null), 404);
            }

            $documento->doc_contenido = $this->conImagenesCompletas($documento->doc_contenido);
            $documento->doc_pdf_url = $documento->doc_pdf ? url('api/public/documentos/pdf/' . $documento->doc_pdf) : null;
            unset($documento->doc_pdf);

            return response()->json(RespuestaApi::returnResultado('success', 'Documento', ['documento' => $documento]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el documento', null));
        }
    }

    // Los documentos activos por carpeta (para el pie de página o un índice). GET public/documentos
    public function publicos()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Documentos', [
                'documentos' => DB::select('SELECT * FROM ecommerce.fn_documento_listar_publicos()'),
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los documentos', null));
        }
    }

    public function verImagen(string $nombre)
    {
        $ruta = self::CARPETA_IMAGENES . '/' . $nombre;
        if (!preg_match(self::NOMBRE_IMAGEN, $nombre, $m) || !Storage::disk('local')->exists($ruta)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($ruta), [
            'Content-Type' => self::TIPOS_IMAGEN[$m[1]],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function verPdf(string $nombre)
    {
        $ruta = self::CARPETA_PDF . '/' . $nombre;
        if (!preg_match(self::NOMBRE_PDF, $nombre) || !Storage::disk('local')->exists($ruta)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($ruta), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="documento.pdf"',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // ================= Ayudas =================

    private function reglas(bool $editar): array
    {
        $requerido = $editar ? 'sometimes|required' : 'required';

        return [
            'doc_titulo' => "$requerido|string|max:150",
            'doc_ruta' => "$requerido|string|max:80",
            'doc_contenido' => 'nullable|string|max:500000',
            'doc_pdf' => 'nullable|string|max:60',
            'doc_pdf_etiqueta' => 'nullable|string|max:40',
            'dca_id' => 'nullable|integer|min:1',
            'doc_activo' => $editar ? 'sometimes|boolean' : 'nullable|boolean',
        ];
    }

    // Lo que se manda a la base: solo los campos permitidos; el contenido, limpio. dca_id vacío = sin carpeta.
    private function payload(Request $request): array
    {
        $payload = $request->only(self::CAMPOS_PAYLOAD);
        if (array_key_exists('doc_contenido', $payload)) {
            $payload['doc_contenido'] = $this->limpiador->limpiar((string) ($payload['doc_contenido'] ?? ''));
        }
        if (array_key_exists('dca_id', $payload) && $payload['dca_id'] === null) {
            $payload['dca_id'] = '';
        }

        return $payload;
    }

    // Las imágenes y los enlaces a PDF de los documentos se guardan con dirección relativa: al entregarlos, con la de
    // este servidor (la tienda está en otro).
    private function conImagenesCompletas(?string $html): string
    {
        return str_replace(
            ['src="' . LimpiadorDocumento::RUTA_IMAGENES, 'href="/api/public/documentos/pdf/'],
            ['src="' . url('api/public/documentos/imagenes') . '/', 'href="' . url('api/public/documentos/pdf') . '/'],
            (string) $html
        );
    }

    // Papelera / restaurar: una función que recibe { <id>, auditoria } y devuelve el id.
    private function accion(string $funcion, array $datos, Request $request, string $exito, string $porDefecto)
    {
        try {
            DB::selectOne("SELECT ecommerce.$funcion(?::jsonb) AS id", [json_encode($datos + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', $exito, $datos));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, $porDefecto);
        }
    }

    // errorInfo[2] trae solo el mensaje de PG (getMessage() incluye el SQL con el payload).
    private function errorTraducido(QueryException $e, string $porDefecto)
    {
        $texto = $e->errorInfo[2] ?? $e->getMessage();

        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($texto, $codigo) !== false) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $porDefecto, $e->getMessage()));
    }

    // Contexto del usuario para la auditoría forense (auditoria.fn_registrar_evento).
    private function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id' => $u->id ?? null,
            'usuario_login' => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => (string) Str::uuid(),
        ];
    }
}

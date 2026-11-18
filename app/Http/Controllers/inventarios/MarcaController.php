<?php

namespace App\Http\Controllers\inventarios;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ArchivosPaginaWeb;
use App\Http\Controllers\paginaWeb\CategoriasTiendaController;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Marcas: reemplaza a la pantalla de Dynamo (mntMarca.xhtml / MntMarcaController), que escribía directo en public.marca.
// Las reglas y la auditoría (auditoria.logs_cambios, módulo MARCAS) viven en las funciones inventarios.fn_marca_*.
class MarcaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se identificó al usuario que realiza el cambio.',
        'CAMPO_REQUERIDO:mar_abreviacion' => 'Debe ingresar la abreviación.',
        'CAMPO_LARGO:mar_abreviacion' => 'La abreviación no puede tener más de 15 caracteres.',
        'CAMPO_REQUERIDO:mar_nombre' => 'Debe ingresar el nombre.',
        'CAMPO_LARGO:mar_nombre' => 'El nombre no puede tener más de 200 caracteres.',
        'CAMPO_INVALIDO:mar_activo' => 'El valor de Activo no es válido.',
        'CAMPO_INVALIDO:mar_web_visible' => 'El valor de En la web no es válido.',
        'CAMPO_INVALIDO:mar_reporta' => 'La marca padre no es válida.',
        'CAMPO_LARGO:mar_imagen' => 'La dirección de la imagen no puede tener más de 200 caracteres.',
        'CAMPO_IMAGEN:mar_imagen' => 'La dirección de la imagen no es válida.',
        'CAMPO_INVALIDO:mar_id' => 'La marca no es válida.',
        'MARCA_NO_EXISTE' => 'La marca no existe.',
        'ABREVIACION_REPETIDA' => 'Ya existe una marca con esa abreviación.',
        'NOMBRE_REPETIDO' => 'Ya existe una marca con ese nombre.',
        'PADRE_NO_EXISTE' => 'La marca padre no existe o está inactiva.',
        'PADRE_CICLO' => 'Una marca no puede quedar debajo de sí misma ni de una de sus submarcas.',
        'PADRE_INACTIVO' => 'No se puede activar porque su marca padre está inactiva.',
        'DESACTIVAR_CON_PRODUCTOS' => 'No se puede desactivar porque tiene productos asignados.',
        'DESACTIVAR_CON_SUBMARCAS' => 'No se puede desactivar porque tiene submarcas activas: desactívelas primero.',
        'CAMPO_INVALIDO:mar_id_origen' => 'La marca a clonar no es válida.',
        'MARCA_ACTIVA' => 'No se puede eliminar porque está activa: desactívela primero.',
        'MARCA_CON_SUBMARCAS' => 'No se puede eliminar porque tiene submarcas.',
        'MARCA_CON_PRODUCTOS' => 'No se puede eliminar porque tiene productos asignados.',
        'PADRE_EN_PAPELERA' => 'No se puede restaurar porque su marca padre está en la papelera: restáurela primero.',
    ];

    private const CAMPOS_CREAR = ['mar_abreviacion', 'mar_nombre', 'mar_activo', 'mar_reporta', 'mar_web_visible', 'mar_imagen'];

    private const REGLAS_CREAR = [
        'mar_abreviacion' => 'required|string|max:15',
        'mar_nombre' => 'required|string|max:200',
        'mar_activo' => 'nullable|boolean',
        'mar_reporta' => 'nullable|integer',
        'mar_web_visible' => 'nullable|boolean',
        'mar_imagen' => 'nullable|string|max:200',
    ];

    // Al editar se cambia todo menos la marca padre (la función la deja como está). mar_imagen vacía = sin imagen.
    private const CAMPOS_EDITAR = ['mar_abreviacion', 'mar_nombre', 'mar_activo', 'mar_web_visible', 'mar_imagen'];

    private const REGLAS_EDITAR = [
        'mar_abreviacion' => 'sometimes|required|string|max:15',
        'mar_nombre' => 'sometimes|required|string|max:200',
        'mar_activo' => 'nullable|boolean',
        'mar_web_visible' => 'nullable|boolean',
        'mar_imagen' => 'nullable|string|max:200',
    ];

    // Árbol de marcas, activas e inactivas, con sus productos.
    public function arbolMarca(Request $request)
    {
        try {
            $busqueda = trim((string) $request->query('busqueda', ''));
            $registros = DB::select('SELECT * FROM inventarios.fn_marca_arbol(?)', [$busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Marcas cargadas con éxito', ['registros' => $registros]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar las marcas', $th->getMessage()));
        }
    }

    public function buscarMarca($marId)
    {
        try {
            $fila = DB::selectOne('SELECT inventarios.fn_marca_buscar(?) AS datos', [(int) $marId]);

            if (!$fila || $fila->datos === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'La marca no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Marca cargada con éxito', json_decode($fila->datos)));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la marca', $th->getMessage()));
        }
    }

    public function crearMarca(Request $request)
    {
        $validator = Validator::make($request->all(), self::REGLAS_CREAR);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $this->conBooleanos($request->only(self::CAMPOS_CREAR));

        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_marca_registrar(?::jsonb) AS mar_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            // Su logo sale en la tienda
            if (!empty($payload['mar_imagen'])) {
                $this->limpiarCacheTienda();
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Marca creada con éxito', ['mar_id' => $resultado->mar_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear la marca');
        }
    }

    public function editarMarca(Request $request, $marId)
    {
        $validator = Validator::make($request->all(), self::REGLAS_EDITAR);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $this->conBooleanos($request->only(self::CAMPOS_EDITAR));

        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_marca_modificar(?::jsonb) AS mar_id', [json_encode($payload + ['mar_id' => (int) $marId, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            // El nombre, el estado, "En la web" y la imagen cambian lo que muestra la tienda
            if (array_intersect(['mar_nombre', 'mar_activo', 'mar_web_visible', 'mar_imagen'], array_keys($payload))) {
                $this->limpiarCacheTienda();
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Marca modificada con éxito', ['mar_id' => $resultado->mar_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al modificar la marca');
        }
    }

    // Crea una marca nueva a partir de otra: los campos del formulario llegan aquí. El padre lo pone la función: siempre
    // es el de la de origen (el que llegue se ignora).
    public function clonarMarca(Request $request, int $marId)
    {
        $validator = Validator::make($request->all(), self::REGLAS_CREAR);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $payload = $this->conBooleanos($request->only(self::CAMPOS_CREAR));
        unset($payload['mar_reporta']);

        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_marca_clonar(?::jsonb) AS mar_id', [json_encode($payload + ['mar_id_origen' => $marId, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            if (!empty($payload['mar_imagen'])) {
                $this->limpiarCacheTienda();
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Marca clonada con éxito', ['mar_id' => $resultado->mar_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al clonar la marca');
        }
    }

    // A la papelera: borrado lógico, como Tipos de producto. Solo inactivas y sin nada que las use. No se vacía.
    public function eliminarMarca(Request $request, $marId)
    {
        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_marca_papelera(?::jsonb) AS mar_id', [json_encode(['mar_id' => (int) $marId, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            $this->limpiarCacheTienda();

            return response()->json(RespuestaApi::returnResultado('success', 'Marca enviada a la papelera', ['mar_id' => $resultado->mar_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al eliminar la marca');
        }
    }

    // Vuelve inactiva.
    public function restaurarMarca(Request $request, $marId)
    {
        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_marca_restaurar(?::jsonb) AS mar_id', [json_encode(['mar_id' => (int) $marId, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Marca restaurada con éxito', ['mar_id' => $resultado->mar_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al restaurar la marca');
        }
    }

    public function papeleraMarca(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM inventarios.fn_marca_papelera_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);
            $total = $registros[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera cargada con éxito', [
                'registros' => $registros,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la papelera', $th->getMessage()));
        }
    }

    // Sube la imagen (solo WebP, máx. 2 MB, como el logo de Página web > Marcas) y devuelve su dirección en data.url.
    // Solo sube el archivo: la dirección se guarda después con crearMarca / editarMarca (mar_imagen).
    public function subirImagenMarca(Request $request)
    {
        if ($error = ArchivosPaginaWeb::errorImagen($request, 'archivo')) {
            return response()->json(RespuestaApi::returnResultado('error', $error, null));
        }

        // Con nombre aleatorio (extensión según el contenido) en la carpeta privada de la tienda; la tienda lo pide por
        // api/public/pagina-web/imagenes/marcas/{nombre} (paginaWeb/ArchivosTiendaController::verImagenMarca).
        try {
            $ruta = $request->file('archivo')->store(ArchivosPaginaWeb::CARPETA . '/marcas', 'local');

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen subida', [
                'url' => url('api/public/pagina-web/imagenes/marcas/' . basename($ruta)),
            ]));
        } catch (\Throwable $th) {
            Log::error('No se pudo subir la imagen de la marca', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo subir el archivo.', null));
        }
    }

    // Auditoría de UNA marca: quién la creó y modificó, y su historial paginado.
    public function auditoriaMarca(Request $request)
    {
        try {
            $marId = (int) $request->query('mar_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($marId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Marca no válida', null));
            }

            $resumen = DB::selectOne('SELECT * FROM inventarios.fn_marca_auditoria_resumen(?)', [$marId]);
            $eventos = DB::select('SELECT * FROM inventarios.fn_marca_auditoria_listar_paginacion(?, ?, ?, ?)', [$marId, $pagina, $tamanio, $busqueda]);
            $total = $eventos[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la marca', $th->getMessage()));
        }
    }

    // El menú de categorías: una categoría cuyos productos son todos de marcas ocultas deja de salir.
    private function limpiarCacheTienda(): void
    {
        CategoriasTiendaController::limpiarCache();
    }

    // La regla acepta 1/0 y "1"/"0", pero la función solo recibe true o false.
    private function conBooleanos(array $payload): array
    {
        foreach (['mar_activo', 'mar_web_visible'] as $campo) {
            if (isset($payload[$campo])) {
                $payload[$campo] = filter_var($payload[$campo], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $payload;
    }

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

    // Contexto del usuario para la auditoría forense: viaja dentro del jsonb y no se guarda como dato de la marca.
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

<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Controllers\paginaWeb\VentanasEmergentesTiendaController;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ArchivosPaginaWeb;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Publicidad del inicio de la tienda (ecommerce.ventanas_emergentes): una imagen con fechas desde / hasta, y
// una predeterminada (opcional) que sale cuando ninguna está en fecha. Listar, subir imagen, crear, editar, papelera
// (borrado lógico), restaurar y auditoría.
// Las reglas viven en ecommerce.fn_ventana_emergente_* (database/sql/ventanas_emergentes.sql); lo que pide la tienda
// está en paginaWeb/VentanasEmergentesTiendaController.
class VentanasEmergentesController extends Controller
{
    private const IMAGEN_MAXIMO_KB = 2048;

    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se pudo identificar al usuario. Vuelva a iniciar sesión.',
        'VENTANA_NOMBRE_REQUERIDO' => 'Debe ingresar el nombre de la publicidad.',
        'VENTANA_NOMBRE_LARGO' => 'El nombre de la publicidad no puede tener más de 100 caracteres.',
        'VENTANA_DUPLICADA' => 'Ya existe una publicidad con ese nombre (si está restaurando, cambie primero el nombre de la otra).',
        'ux_ventanas_emergentes_nombre' => 'Ya existe una publicidad con ese nombre.',
        'VENTANA_IMAGEN_REQUERIDA' => 'Suba la imagen de la publicidad.',
        'VENTANA_IMAGEN_INVALIDA' => 'La imagen no es válida: vuelva a subirla.',
        'VENTANA_PREDETERMINADA_INVALIDA' => 'El valor de "Predeterminada" no es válido.',
        'VENTANA_PREDETERMINADA_INACTIVA' => 'La publicidad predeterminada no se puede desactivar: quítele la marca o envíela a la papelera.',
        'VENTANA_PREDETERMINADA_OCUPADA' => 'Ya hay otra publicidad predeterminada: quítele la marca antes de restaurar esta.',
        'ux_ventanas_emergentes_predeterminada' => 'Ya hay otra publicidad predeterminada.',
        'VENTANA_ACTIVO_INVALIDO' => 'El valor de "Activa" no es válido.',
        'VENTANA_FECHA_DESDE_REQUERIDA' => 'Debe ingresar la fecha desde.',
        'VENTANA_FECHA_HASTA_REQUERIDA' => 'Debe ingresar la fecha hasta.',
        'VENTANA_FECHA_DESDE_INVALIDA' => 'La fecha desde no es válida.',
        'VENTANA_FECHA_HASTA_INVALIDA' => 'La fecha hasta no es válida.',
        'VENTANA_FECHAS_INVERTIDAS' => 'La fecha hasta no puede ser anterior a la fecha desde.',
        'VENTANA_FECHAS_CRUZADAS' => 'Esas fechas se cruzan con otra publicidad activa: la tienda no sabría cuál mostrar.',
        'VENTANA_NO_EXISTE' => 'La publicidad no existe o está en la papelera.',
        'VENTANA_NO_EN_PAPELERA' => 'La publicidad no está en la papelera (puede que otro usuario ya la restauró).',
    ];

    private const MENSAJES_FORMULARIO = [
        'ven_nombre.required' => 'Debe ingresar el nombre de la publicidad.',
        'ven_nombre.max' => 'El nombre de la publicidad no puede tener más de 100 caracteres.',
        'ven_imagen.required' => 'Suba la imagen de la publicidad.',
        'ven_imagen.regex' => 'La imagen no es válida: vuelva a subirla.',
        'ven_predeterminada.boolean' => 'El valor de "Predeterminada" no es válido.',
        'ven_fecha_desde.date_format' => 'La fecha desde no es válida.',
        'ven_fecha_hasta.date_format' => 'La fecha hasta no es válida.',
        'ven_activo.boolean' => 'El valor de "Activa" no es válido.',
    ];

    // Lo único que se acepta del formulario.
    private const CAMPOS_PAYLOAD = [
        'ven_nombre',
        'ven_imagen',
        'ven_predeterminada',
        'ven_fecha_desde',
        'ven_fecha_hasta',
        'ven_activo',
    ];

    public function __construct()
    {
        // Lo público (la publicidad de hoy y las imágenes) está en paginaWeb/VentanasEmergentesTiendaController.
        $this->middleware('auth:api');
    }

    // Ventanas (fuera de la papelera), paginadas, con su estado de hoy y la dirección de su imagen
    // -> ecommerce.fn_ventana_emergente_listar_paginacion. GET ?pagina=&tamanio=&busqueda=
    public function listar(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_ventana_emergente_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Publicidades de inicio listadas con éxito', [
                'registros' => $this->conUrl($registros),
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las publicidades de inicio', $th->getMessage()));
        }
    }

    // Sube la imagen (WebP, JPG o PNG, hasta 2 MB) a ecommerce/ventanas (NAS o local según crm.parametro 'NAS').
    // Devuelve {nombre, url}: se guarda con crear / editar (ven_imagen = nombre).
    public function subirImagen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'imagen' => 'required|file|mimetypes:image/webp,image/jpeg,image/png|max:' . self::IMAGEN_MAXIMO_KB,
        ], [
            'imagen.required' => 'Elija una imagen.',
            'imagen.file' => 'Elija una imagen.',
            'imagen.uploaded' => 'La imagen no se pudo subir: puede pesar hasta 2 MB.',
            'imagen.mimetypes' => 'Solo se aceptan imágenes WebP, JPG o PNG.',
            'imagen.max' => 'La imagen no puede pesar más de 2 MB.',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        $extension = array_search($request->file('imagen')->getMimeType(), VentanasEmergentesTiendaController::TIPOS, true) ?: 'webp';
        $nombre = Str::random(40) . '.' . $extension;
        if (!ArchivosPaginaWeb::guardar($request->file('imagen'), VentanasEmergentesTiendaController::CARPETA, $nombre)) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo subir la imagen.', null));
        }

        return response()->json(RespuestaApi::returnResultado('success', 'Imagen subida', [
            'nombre' => $nombre,
            'url' => VentanasEmergentesTiendaController::urlImagen($nombre),
        ]));
    }

    // Crea una publicidad -> ecommerce.fn_ventana_emergente_add.
    // Body: { ven_nombre, ven_imagen, ven_predeterminada?, ven_fecha_desde?, ven_fecha_hasta?, ven_activo? }
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), $this->reglas(false), self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_ventana_emergente_add(?::jsonb) AS ven_id', [json_encode($request->only(self::CAMPOS_PAYLOAD) + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            VentanasEmergentesTiendaController::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Publicidad de inicio creada con éxito', ['ven_id' => $resultado->ven_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear la publicidad de inicio');
        }
    }

    // Modifica una publicidad -> ecommerce.fn_ventana_emergente_edit. Lo que no viene se queda como está. Si cambió la
    // imagen, la anterior se borra.
    public function editar(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), $this->reglas(true), self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $antes = json_decode(DB::selectOne('SELECT ecommerce.fn_ventana_emergente_buscar(?) AS v', [$id])->v ?? 'null', true);
            $payload = $request->only(self::CAMPOS_PAYLOAD) + ['ven_id' => $id];
            $resultado = DB::selectOne('SELECT ecommerce.fn_ventana_emergente_edit(?::jsonb) AS ven_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            VentanasEmergentesTiendaController::limpiarCache();

            $imagenAnterior = $antes['ven_imagen'] ?? null;
            if ($imagenAnterior && $request->filled('ven_imagen') && $request->input('ven_imagen') !== $imagenAnterior) {
                ArchivosPaginaWeb::borrarRuta(VentanasEmergentesTiendaController::CARPETA . '/' . basename($imagenAnterior));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Publicidad de inicio guardada con éxito', ['ven_id' => $resultado->ven_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar la publicidad de inicio');
        }
    }

    public function eliminar(Request $request, int $id)
    {
        return $this->accion('fn_ventana_emergente_papelera', ['ven_id' => $id], $request, 'Publicidad de inicio enviada a la papelera', 'Error al eliminar la publicidad de inicio');
    }

    public function restaurar(Request $request, int $id)
    {
        return $this->accion('fn_ventana_emergente_restaurar', ['ven_id' => $id], $request, 'Publicidad de inicio restaurada con éxito', 'Error al restaurar la publicidad de inicio');
    }

    // Ventanas en la papelera -> ecommerce.fn_ventana_emergente_papelera_paginacion.
    public function papelera(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_ventana_emergente_papelera_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera cargada con éxito', [
                'registros' => $this->conUrl($registros),
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la papelera', $th->getMessage()));
        }
    }

    // Auditoría de UNA ventana (también de las de la papelera): {resumen, eventos, total, pagina, tamanio}.
    // GET ?ven_id=&pagina=&tamanio=&busqueda=
    public function auditoria(Request $request)
    {
        try {
            $id = (int) $request->query('ven_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Publicidad de inicio no válida', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ecommerce.fn_ventana_emergente_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ecommerce.fn_ventana_emergente_auditoria_listar_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la publicidad de inicio', $th->getMessage()));
        }
    }

    // ================= Internos =================

    // Solo forma y tipos: las reglas (fechas, cruces, predeterminada, duplicados) las decide
    // fn_ventana_emergente_validaciones.
    private function reglas(bool $editar): array
    {
        $requerido = $editar ? 'sometimes|required' : 'required';

        return [
            'ven_nombre' => "$requerido|string|max:100",
            // En lista (no en texto con |): el regex lleva | y Laravel cortaría la regla ahí.
            'ven_imagen' => array_merge(explode('|', $requerido), ['string', 'regex:/^[A-Za-z0-9]{40}\.(webp|jpg|png)$/']),
            'ven_predeterminada' => $editar ? 'sometimes|boolean' : 'nullable|boolean',
            'ven_fecha_desde' => 'nullable|date_format:Y-m-d',
            'ven_fecha_hasta' => 'nullable|date_format:Y-m-d',
            'ven_activo' => $editar ? 'sometimes|boolean' : 'nullable|boolean',
        ];
    }

    // Cada fila con la dirección pública de su imagen (imagen_url).
    private function conUrl(array $registros): array
    {
        foreach ($registros as $r) {
            $r->imagen_url = VentanasEmergentesTiendaController::urlImagen($r->ven_imagen ?? null);
        }

        return $registros;
    }

    // Papelera / restaurar: una función que recibe { ven_id, auditoria } y devuelve el id.
    private function accion(string $funcion, array $datos, Request $request, string $exito, string $porDefecto)
    {
        try {
            DB::selectOne("SELECT ecommerce.$funcion(?::jsonb) AS id", [json_encode($datos + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            VentanasEmergentesTiendaController::limpiarCache();

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
            if (preg_match('/\b' . preg_quote($codigo, '/') . '\b/', $texto)) {
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

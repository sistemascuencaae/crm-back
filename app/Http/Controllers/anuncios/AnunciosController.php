<?php

namespace App\Http\Controllers\anuncios;

use App\Http\Controllers\Controller;
use App\Events\AnuncioEvent;
use App\Http\Resources\RespuestaApi;
use App\Models\anuncios\Anuncio;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AnunciosController extends Controller
{
    private const CARPETA = "anuncios";

    // tamano maximo por imagen en KB //
    private const MAX_KB_IMAGEN = 10240;

    private const MENSAJES_ERROR = [
        'TITULO_REQUERIDO' => 'El título es obligatorio.',
        'FECHAS_REQUERIDAS' => 'Las fechas de inicio y fin son obligatorias.',
        'FECHA_FIN_MENOR' => 'La fecha fin no puede ser anterior a la fecha inicio.',
        'TIPO_DESTINO_INVALIDO' => 'Tipo de destino no válido.',
        'ANUNCIO_NO_ENCONTRADO' => 'No se encontró el anuncio.',
    ];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!Anuncio::moduloActivo()) {
                return response()->json(
                    RespuestaApi::returnResultado('error', 'El módulo de anuncios no está habilitado', null)
                );
            }
            return $next($request);
        });
    }

    public function listAllAnuncios(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select(
                'SELECT * FROM crm.fn_anuncio_listar_paginacion(?, ?, ?, ?)',
                [$pagina, $tamanio, $busqueda, $this->ahora()]
            );

            $total = $registros[0]->total_registros ?? 0;

            foreach ($registros as $registro) {
                // imagenes y destinos vuelven de PostgreSQL como TEXTO (jsonb):
                // sin esto el frontend recibiria una cadena y
                // anuncio.imagenes.length seria undefined.
                $registro->imagenes = $registro->imagenes ? json_decode($registro->imagenes, true) : [];
                $registro->destinos = $registro->destinos ? json_decode($registro->destinos, true) : [];

                // Los tipos los daba Eloquent con $casts; con DB::select hay que
                // ponerlos a mano o los checkbox del modal de edicion reciben
                // texto y quedan siempre marcados.
                $registro->activo = (bool) $registro->activo;
                $registro->ver_todos = (bool) $registro->ver_todos;
                $registro->orden = (int) $registro->orden;
                $registro->total_imagenes = (int) $registro->total_imagenes;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'registros' => $registros,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function addAnuncio(Request $request)
    {
        $validacion = $this->validarCabecera($request);
        if ($validacion !== null) {
            return response()->json(RespuestaApi::returnResultado('error', $validacion, null));
        }

        $rutasSubidas = [];

        try {
            $anuncioId = (int) DB::selectOne("SELECT nextval('crm.anuncios_id_seq') AS id")->id;

            // Los archivos se suben ANTES del insert. La cabecera ya se valido
            // arriba, asi que el caso normal no deja huerfanos; si la funcion
            // igual falla, el catch los borra.
            $imagenes = $this->subirImagenes($anuncioId, $request->file("imagenes"), $request->input("alts", []));
            $rutasSubidas = array_column($imagenes, 'ruta');

            $payload = $this->payloadCabecera($request) + [
                'id' => $anuncioId,
                'created_by' => auth()->id(),
                'destinos' => $this->comoArreglo($request->input("destinos")),
                'imagenes' => $imagenes,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ];

            DB::selectOne('SELECT crm.fn_anuncio_crear(?::jsonb) AS id', [json_encode($payload)]);

            $data = $this->foto($anuncioId);

            // Se avisa DESPUES del commit: si se dispara dentro y la operacion
            // falla, los clientes consultarian un anuncio que nunca existio.
            if (!empty($data['activo'])) {
                event(new AnuncioEvent());
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (QueryException $e) {
            $this->borrarArchivos($rutasSubidas);
            return $this->respuestaError($e, 'Error al crear el anuncio');
        } catch (Exception $e) {
            $this->borrarArchivos($rutasSubidas);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editAnuncio(Request $request, $id)
    {
        $validacion = $this->validarCabecera($request);
        if ($validacion !== null) {
            return response()->json(RespuestaApi::returnResultado('error', $validacion, null));
        }

        $rutasSubidas = [];

        try {
            $anuncioId = (int) $id;

            $imagenes = $this->subirImagenes($anuncioId, $request->file("imagenes"), $request->input("alts", []));
            $rutasSubidas = array_column($imagenes, 'ruta');

            $payload = $this->payloadCabecera($request) + [
                'id' => $anuncioId,
                'imagenes' => $imagenes,
                'imagenes_eliminar' => $this->comoArreglo($request->input("imagenes_eliminar")),
                'imagenes_orden' => $this->comoArreglo($request->input("imagenes_orden")),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ];

            // los destinos solo se reemplazan si vinieron en la peticion
            if ($request->has("destinos")) {
                $payload['destinos'] = $this->comoArreglo($request->input("destinos"));
            }

            $resultado = DB::selectOne('SELECT crm.fn_anuncio_modificar(?::jsonb) AS resultado', [json_encode($payload)]);
            $resultado = json_decode($resultado->resultado, true) ?: [];

            // Los archivos de las imagenes dadas de baja se borran DESPUES de
            // que la funcion confirmo: al reves, un fallo dejaria registros
            // apuntando a archivos que ya no existen.
            $this->borrarArchivos($resultado['rutas_eliminadas'] ?? []);

            $data = $this->foto($anuncioId);

            // Se avisa tambien al editar: si se agregan destinatarios nuevos, a
            // esos les tiene que llegar en el momento. Reenviarlo a quien ya lo
            // vio no molesta (el aviso no lleva datos, el cliente reconsulta), y
            // si el anuncio se desactivo la reconsulta se lo quita a todos.
            event(new AnuncioEvent());

            return response()->json(RespuestaApi::returnResultado('success', 'Se edito con éxito', $data));
        } catch (QueryException $e) {
            $this->borrarArchivos($rutasSubidas);
            return $this->respuestaError($e, 'Error al editar el anuncio');
        } catch (Exception $e) {
            $this->borrarArchivos($rutasSubidas);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function deleteAnuncio(Request $request, $id)
    {
        try {
            $resultado = DB::selectOne(
                'SELECT crm.fn_anuncio_eliminar(?, ?::jsonb) AS resultado',
                [(int) $id, json_encode($this->contextoAuditoriaForense($request))]
            );
            $resultado = json_decode($resultado->resultado, true) ?: [];

            $this->borrarArchivos($resultado['rutas_eliminadas'] ?? []);

            // Se borra la carpeta aparte de los archivos: un anuncio viejo puede
            // tener imagenes guardadas antes de que existiera la carpeta por
            // anuncio, y esas no estan dentro.
            $this->disco()->deleteDirectory(self::CARPETA . "/" . (int) $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $resultado));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar el anuncio');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function anuncioAuditoria(Request $request, $id)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $resumen = DB::selectOne('SELECT * FROM crm.fn_anuncio_auditoria_resumen(?)', [(int) $id]);
            $eventos = DB::select(
                'SELECT * FROM crm.fn_anuncio_auditoria_listar_paginacion(?, ?, ?, ?)',
                [(int) $id, $pagina, $tamanio, $busqueda]
            );

            $total = $eventos[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría', $e->getMessage()));
        }
    }

    private function disco()
    {
        $parametro = DB::table('crm.parametro')->where('abreviacion', 'NAS')->first();

        return ($parametro && $parametro->nas == true)
            ? Storage::disk('nas')
            : Storage::disk('local');
    }

    private function subirImagenes($anuncioId, $archivos, $alts): array
    {
        if (empty($archivos)) {
            return [];
        }

        $disco = $this->disco();
        $carpeta = self::CARPETA . "/" . $anuncioId;
        $imagenes = [];

        foreach ($archivos as $indice => $archivo) {

            if (!$archivo->isValid()) {
                throw new Exception("Archivo invalido en la posicion " . $indice);
            }
            if (strpos((string) $archivo->getMimeType(), "image/") !== 0) {
                throw new Exception("Solo se permiten imagenes. Recibido: " . $archivo->getMimeType());
            }
            if ($archivo->getSize() > self::MAX_KB_IMAGEN * 1024) {
                throw new Exception("La imagen supera los " . (self::MAX_KB_IMAGEN / 1024) . " MB");
            }

            $nombreLimpio = str_replace(' ', '-', $archivo->getClientOriginalName());
            // uniqid ademas de la carpeta por anuncio: putFileAs sobrescribe
            // sin avisar, y dos "banner.jpg" el mismo dia se pisaban
            $nombre = Carbon::now()->format('Y-m-d') . '-' . uniqid() . '-' . $nombreLimpio;

            $imagenes[] = [
                'ruta' => $disco->putFileAs($carpeta, $archivo, $nombre),
                'alt' => $alts[$indice] ?? null,
                'orden' => null, // lo numera la funcion, continuando las existentes
            ];
        }

        return $imagenes;
    }

    private function borrarArchivos($rutas): void
    {
        if (empty($rutas)) {
            return;
        }

        $disco = $this->disco();

        foreach ($rutas as $ruta) {
            try {
                $disco->delete($ruta);
            } catch (Exception $e) {
                // el registro ya se borro: un archivo huerfano no justifica
                // devolver error al usuario
            }
        }
    }

    private function foto($anuncioId): array
    {
        $fila = DB::selectOne('SELECT crm.fn_anuncio_foto(?) AS datos', [(int) $anuncioId]);

        return ($fila && $fila->datos) ? (json_decode($fila->datos, true) ?: []) : [];
    }

    private function payloadCabecera(Request $request): array
    {
        return [
            'titulo' => $request->input("titulo"),
            'descripcion' => $request->input("descripcion"),
            'fecha_inicio' => $request->input("fecha_inicio"),
            'fecha_fin' => $request->input("fecha_fin"),
            'activo' => $request->boolean("activo", true),
            'ver_todos' => $request->boolean("ver_todos", false),
            'orden' => (int) $request->input("orden", 0),
            'ahora' => $this->ahora(),
        ];
    }

    private function ahora(): string
    {
        date_default_timezone_set("America/Guayaquil");

        return Carbon::now()->format('Y-m-d H:i:s');
    }

    private function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id' => $u->id ?? null,
            'usuario_login' => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
        ];
    }

    private function respuestaError(QueryException $e, string $porDefecto)
    {
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($e->getMessage(), $codigo) !== false) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $porDefecto, $e->getMessage()));
    }

    private function comoArreglo($valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }
        if (is_string($valor) && $valor !== '') {
            $decodificado = json_decode($valor, true);
            return is_array($decodificado) ? $decodificado : [];
        }
        return [];
    }

    private function validarCabecera(Request $request): ?string
    {
        if (!$request->filled("titulo")) {
            return "El título es obligatorio";
        }
        if (!$request->filled("fecha_inicio") || !$request->filled("fecha_fin")) {
            return "Las fechas de inicio y fin son obligatorias";
        }
        if (strtotime($request->input("fecha_fin")) < strtotime($request->input("fecha_inicio"))) {
            return "La fecha fin no puede ser anterior a la fecha inicio";
        }
        return null;
    }
}

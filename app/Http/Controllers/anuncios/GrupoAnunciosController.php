<?php

namespace App\Http\Controllers\anuncios;

use App\Events\AnuncioEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// 19-nov: CRUD de Grupo Anuncios (grupos de agencias que se usan como destino de los anuncios).
// Las reglas y la auditoria viven en las funciones crm.fn_cgrupo_anuncios_*; aqui solo se arma el pedido.
class GrupoAnunciosController extends Controller
{
    private const MENSAJES_ERROR = [
        'NOMBRE_REQUERIDO' => 'El nombre es obligatorio.',
        'NOMBRE_MUY_LARGO' => 'El nombre no puede tener más de 100 caracteres.',
        'GRUPO_DUPLICADO' => 'Ya existe un grupo con ese nombre.',
        'AGENCIAS_REQUERIDAS' => 'Marque al menos una agencia.',
        'AGENCIA_NO_EXISTE' => 'Una de las agencias marcadas no existe.',
        'GRUPO_NO_EXISTE' => 'El grupo no existe.',
    ];

    // Version 1.0
    public function listGrupoAnuncios(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select(
                'SELECT * FROM crm.fn_cgrupo_anuncios_listar_paginacion(?, ?, ?)',
                [$pagina, $tamanio, $busqueda]
            );

            $total = $registros[0]->total_registros ?? 0;

            foreach ($registros as $registro) {
                $registro->activo = (bool) $registro->activo;
                $registro->total_agencias = (int) $registro->total_agencias;
                // jsonb llega como texto
                $registro->agencias = $registro->agencias ? json_decode($registro->agencias, true) : [];
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'registros' => $registros,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar los grupos', $e->getMessage()));
        }
    }

    // Version 1.0
    // Agencias para los checks: las activas mas las del grupo (?id=) aunque esten inactivas.
    public function listAgenciasGrupoAnuncios(Request $request)
    {
        try {
            $id = (int) $request->query('id', 0);

            $agencias = DB::select(
                'SELECT * FROM crm.fn_cgrupo_anuncios_agencias_listar(?)',
                [$id > 0 ? $id : null]
            );

            foreach ($agencias as $agencia) {
                $agencia->activo = (bool) $agencia->activo;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $agencias));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar las agencias', $e->getMessage()));
        }
    }

    // Version 1.0
    // Grupos activos para el combo de destinos del anuncio.
    public function listGrupoAnunciosActivos()
    {
        try {
            $grupos = DB::select('SELECT * FROM crm.fn_cgrupo_anuncios_activos_listar()');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $grupos));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar los grupos', $e->getMessage()));
        }
    }

    // Version 1.0
    public function addGrupoAnuncios(Request $request)
    {
        try {
            $payload = $this->payload($request);

            $fila = DB::selectOne('SELECT crm.fn_cgrupo_anuncios_crear(?::jsonb) AS id', [json_encode($payload)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', ['id' => (int) $fila->id]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al crear el grupo');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear el grupo', $e->getMessage()));
        }
    }

    // Version 1.0
    public function editGrupoAnuncios(Request $request, $id)
    {
        try {
            $payload = $this->payload($request) + [
                'id' => (int) $id,
                'ahora' => $this->ahora(),
            ];

            $fila = DB::selectOne('SELECT crm.fn_cgrupo_anuncios_modificar(?::jsonb) AS resultado', [json_encode($payload)]);
            $resultado = json_decode($fila->resultado, true) ?: [];

            // Si un anuncio vigente usa el grupo, se manda el mismo aviso que al editar un anuncio: los conectados
            // reconsultan y el servidor decide (a una agencia nueva le llega; a una quitada se le va).
            if (!empty($resultado['cambio']) && !empty($resultado['en_uso'])) {
                event(new AnuncioEvent());
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se edito con éxito', $resultado));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al editar el grupo');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al editar el grupo', $e->getMessage()));
        }
    }

    // Version 1.0
    public function grupoAnunciosAuditoria(Request $request, $id)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $resumen = DB::selectOne('SELECT * FROM crm.fn_cgrupo_anuncios_auditoria_resumen(?)', [(int) $id]);
            $eventos = DB::select(
                'SELECT * FROM crm.fn_cgrupo_anuncios_auditoria_listar_paginacion(?, ?, ?, ?)',
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

    private function payload(Request $request): array
    {
        $agencias = $request->input('agencias', []);

        return [
            'nombre' => $request->input('nombre'),
            'activo' => $request->boolean('activo', true),
            'agencias' => is_array($agencias) ? array_values(array_map('intval', $agencias)) : [],
            'auditoria' => $this->contextoAuditoriaForense($request),
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
            'request_id' => (string) Str::uuid(),
        ];
    }

    // Busca cada codigo como PALABRA ENTERA (un codigo puede estar dentro de otro).
    private function respuestaError(QueryException $e, string $porDefecto)
    {
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (preg_match('/\b' . preg_quote($codigo, '/') . '\b/', $e->getMessage())) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $porDefecto, $e->getMessage()));
    }
}

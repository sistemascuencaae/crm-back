<?php

namespace App\Http\Controllers\anuncios;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\anuncios\Anuncio;
use App\Models\anuncios\AnuncioDestino;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class AnunciosUsuarioController extends Controller
{
    /**
     * Configuracion del modulo para el frontend.
     *
     * Existe porque el frontend NO conoce crm.parametro: esa bandera solo la
     * leia el backend, y por eso el modulo tenia el interruptor repartido entre
     * la base de datos y codigo comentado en Angular, que habia que mantener en
     * sincronia a mano. Con esto **la base de datos es la unica que manda**:
     * apagar el modulo es un UPDATE, sin recompilar ni tocar nada.
     *
     * Va en un endpoint aparte y no dentro de listAnunciosUsuario porque
     * RespuestaApi tiene un solo campo 'data': meter la config ahi obligaria a
     * envolver la respuesta en {anuncios, config} y romper el contrato que el
     * frontend ya consume.
     *
     * OJO: este metodo NO puede cortar por modulo apagado como los de abajo.
     * Es justamente el que informa si esta apagado.
     */
    public function config()
    {
        // Ante cualquier fallo se responde todo en false y con exito: el
        // default seguro es no mostrar nada, y el frontend no se rompe.
        $configuracion = [
            'modulo_activo' => false,
            'marca_agua'    => false,
        ];

        try {
            // UNA sola consulta a crm.parametro, resuelta entera en el modelo
            $configuracion = Anuncio::configuracion();
        } catch (Exception $e) {
            // se deja la configuracion en false
        }

        return response()->json(
            RespuestaApi::returnResultado('success', 'Se obtuvo la configuración', $configuracion)
        );
    }

    public function listAnunciosUsuario()
    {
        try {
            // El frontend llama esto en cada inicio de sesion. Con el modulo
            // apagado se responde vacio y con exito: nunca se toca
            // crm.anuncios, asi que funciona igual si las tablas no existen.
            if (!Anuncio::moduloActivo()) {
                return response()->json(RespuestaApi::returnResultado('success', 'Módulo no habilitado', []));
            }

            $userId = auth()->id();

            // El "ahora" se calcula en PHP y se manda como parametro en vez de
            // usar NOW() de PostgreSQL. Las columnas son timestamp sin zona y
            // NOW() devuelve timestamptz: la comparacion dependeria del
            // TimeZone de la sesion del servidor. Asi la vigencia siempre se
            // evalua en America/Guayaquil, sin importar como este la base.
            date_default_timezone_set("America/Guayaquil");
            $ahora = Carbon::now()->format('Y-m-d H:i:s');

            $ids = DB::select("SELECT a.id, (v.id IS NOT NULL) AS visto
                                FROM crm.anuncios a
                                JOIN crm.users u ON u.id = ?
                                LEFT JOIN crm.anuncios_vistos v
                                    ON v.anuncio_id = a.id AND v.user_id = u.id
                                WHERE a.activo = true
                                    AND ? BETWEEN a.fecha_inicio AND a.fecha_fin
                                    AND (
                                        a.ver_todos = true
                                        OR EXISTS (
                                            SELECT 1
                                            FROM crm.anuncios_destinos d
                                            WHERE d.anuncio_id = a.id
                                                AND (
                                                    (d.tipo = ? AND d.destino_id = u.dep_id)
                                                    OR (d.tipo = ? AND d.destino_id = u.profile_id)
                                                    OR (d.tipo = ? AND d.destino_id = u.id)
                                                    )
                                            )
                                        )
                                ORDER BY (v.id IS NOT NULL), a.orden, a.id
                            ", [$userId, $ahora,
                                AnuncioDestino::TIPO_DEPARTAMENTO,
                                AnuncioDestino::TIPO_PERFIL,
                                AnuncioDestino::TIPO_USUARIO,
                                ]);

            // se resuelve en dos pasos: la consulta cruda define QUE y en que
            // orden, y Eloquent trae las imagenes sin repetir filas
            $orden = array_column($ids, 'id');
            $vistos = array_column($ids, 'visto', 'id');

            $anuncios = Anuncio::with("imagenes")
                                    ->whereIn("id", $orden)
                                    ->get()
                                    ->sortBy(fn($a) => array_search($a->id, $orden))
                                    ->values()
                                    ->map(function ($anuncio) use ($vistos) {
                                        $anuncio->visto = (bool) ($vistos[$anuncio->id] ?? false);
                                        return $anuncio;
                                    });

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $anuncios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    /**
     * Marca un anuncio como visto. Es permanente por usuario: no oculta el
     * anuncio, solo hace que deje de salir primero y apaga el badge.
     */
    public function marcarVisto($anuncio_id)
    {
        try {
            if (!Anuncio::moduloActivo()) {
                return response()->json(RespuestaApi::returnResultado('success', 'Módulo no habilitado', true));
            }

            date_default_timezone_set("America/Guayaquil");
            $ahora = Carbon::now()->format('Y-m-d H:i:s');

            // ON CONFLICT contra el UNIQUE (anuncio_id, user_id): permite
            // insertar a ciegas sin consultar antes si ya estaba marcado
            DB::insert("INSERT INTO crm.anuncios_vistos (anuncio_id, user_id, created_at, updated_at)
                        VALUES (?, ?, ?, ?)
                        ON CONFLICT (anuncio_id, user_id) DO NOTHING
                        ", [$anuncio_id, auth()->id(), $ahora, $ahora]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se marco como visto', true));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    /** Marca varios de una sola vez, al abrir el modal de inicio de sesion. */
    public function marcarVistosVarios(\Illuminate\Http\Request $request)
    {
        try {
            if (!Anuncio::moduloActivo()) {
                return response()->json(RespuestaApi::returnResultado('success', 'Módulo no habilitado', true));
            }

            date_default_timezone_set("America/Guayaquil");
            $ahora = Carbon::now()->format('Y-m-d H:i:s');
            $userId = auth()->id();

            $ids = $request->input("anuncios", []);
            if (is_string($ids)) {
                $ids = json_decode($ids, true) ?: [];
            }

            DB::transaction(function () use ($ids, $userId, $ahora) {
                foreach ($ids as $anuncioId) {

                    DB::insert("INSERT INTO crm.anuncios_vistos (anuncio_id, user_id, created_at, updated_at)
                                VALUES (?, ?, ?, ?)
                                ON CONFLICT (anuncio_id, user_id) DO NOTHING
                                ", [(int) $anuncioId, $userId, $ahora, $ahora]);
                }
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se marcaron como vistos', true));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}

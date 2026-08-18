<?php

namespace App\Http\Controllers\anuncios;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\anuncios\Anuncio;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
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
     * Es justamente el que informa si esta apagado. Tampoco pasa por funcion:
     * no es dato del modulo y Anuncio::parametro() es a proposito el unico
     * lector de crm.parametro en todo el backend.
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

    /**
     * Anuncios vigentes que le tocan al usuario, paginados.
     *
     * La MISMA funcion atiende a los dos consumidores:
     *   modal de inicio de sesion -> solo_no_vistos = true,  tamanio = 3
     *   campanita                 -> solo_no_vistos = false, tamanio = 5 (scroll)
     *
     * Devuelve dos totales y ninguno sobra: total_registros para el scroll y el
     * encabezado, y total_pendientes para el badge de la campana, que NO se
     * puede calcular desde una pagina (con 5 filas en memoria contaria 5 como
     * maximo y mentiria).
     */
    public function listAnunciosUsuario(Request $request)
    {
        try {
            // El frontend llama esto en cada inicio de sesion. Con el modulo
            // apagado se responde vacio y con exito: nunca se toca
            // crm.anuncios, asi que funciona igual si las tablas no existen.
            if (!Anuncio::moduloActivo()) {
                return response()->json(RespuestaApi::returnResultado('success', 'Módulo no habilitado', [
                    'registros'        => [],
                    'total'            => 0,
                    'total_pendientes' => 0,
                    'pagina'           => 1,
                    'tamanio'          => 0,
                ]));
            }

            $pagina       = max((int) $request->query('pagina', 1), 1);
            $tamanio      = max((int) $request->query('tamanio', 5), 1);
            $soloNoVistos = $request->boolean('solo_no_vistos', false);

            $registros = DB::select(
                'SELECT * FROM crm.fn_anuncio_usuario_listar_paginacion(?, ?, ?, ?, ?)',
                [auth()->id(), $pagina, $tamanio, $soloNoVistos, $this->ahora()]
            );

            foreach ($registros as $registro) {
                // el jsonb de imagenes llega como texto desde PDO
                $registro->imagenes = $registro->imagenes ? json_decode($registro->imagenes, true) : [];

                // 'visto' se compara con === false en el header (etiqueta
                // "Nuevo"), asi que tiene que ser booleano de verdad
                $registro->visto     = (bool) $registro->visto;
                $registro->activo    = (bool) $registro->activo;
                $registro->ver_todos = (bool) $registro->ver_todos;
                $registro->orden     = (int) $registro->orden;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'registros'        => $registros,
                'total'            => (int) ($registros[0]->total_registros ?? 0),
                'total_pendientes' => (int) ($registros[0]->total_pendientes ?? 0),
                'pagina'           => $pagina,
                'tamanio'          => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    /**
     * Marca como vistos los anuncios que de verdad se mostraron.
     *
     * Es permanente por usuario: no oculta el anuncio, solo apaga su badge.
     * La funcion hace un solo INSERT multi-fila con ON CONFLICT; antes esto era
     * un INSERT por anuncio dentro de un bucle de PHP.
     *
     * No se audita en logs_cambios a proposito: crm.anuncios_vistos ya es el
     * registro de quien vio que y cuando.
     */
    public function marcarVistosVarios(Request $request)
    {
        try {
            if (!Anuncio::moduloActivo()) {
                return response()->json(RespuestaApi::returnResultado('success', 'Módulo no habilitado', true));
            }

            $ids = $request->input("anuncios", []);
            if (is_string($ids)) {
                $ids = json_decode($ids, true) ?: [];
            }

            DB::selectOne(
                'SELECT crm.fn_anuncio_marcar_vistos(?::jsonb, ?, ?) AS insertados',
                [json_encode(array_values((array) $ids)), auth()->id(), $this->ahora()]
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Se marcaron como vistos', true));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    /**
     * El "ahora" se calcula en PHP y se manda como parametro en vez de usar
     * NOW() de PostgreSQL. Las columnas son timestamp sin zona y NOW() devuelve
     * timestamptz: la comparacion dependeria del TimeZone de la sesion del
     * servidor. Asi la vigencia siempre se evalua en America/Guayaquil, sin
     * importar como este la base.
     */
    private function ahora(): string
    {
        date_default_timezone_set("America/Guayaquil");

        return Carbon::now()->format('Y-m-d H:i:s');
    }
}

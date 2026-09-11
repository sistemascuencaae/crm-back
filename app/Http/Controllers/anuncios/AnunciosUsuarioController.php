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
    public function config()
    {
        // Ante cualquier fallo se responde todo en false y con exito: el
        // default seguro es no mostrar nada, y el frontend no se rompe.
        $configuracion = [
            'modulo_activo' => false,
            'marca_agua' => false,
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

    public function listAnunciosUsuario(Request $request)
    {
        try {
            if (!Anuncio::moduloActivo()) {
                return response()->json(RespuestaApi::returnResultado('success', 'Módulo no habilitado', [
                    'registros' => [],
                    'total' => 0,
                    'total_pendientes' => 0,
                    'pagina' => 1,
                    'tamanio' => 0,
                ]));
            }

            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 5), 1);
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
                $registro->visto = (bool) $registro->visto;
                $registro->activo = (bool) $registro->activo;
                $registro->ver_todos = (bool) $registro->ver_todos;
                $registro->orden = (int) $registro->orden;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'total_pendientes' => (int) ($registros[0]->total_pendientes ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

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

    private function ahora(): string
    {
        date_default_timezone_set("America/Guayaquil");

        return Carbon::now()->format('Y-m-d H:i:s');
    }
}

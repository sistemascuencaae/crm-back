<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\openceo\Bodega;
use App\Models\openceo\Usuario;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class BodegaController extends Controller
{
    public function listBodegas()
    {
        try {
            $data = DB::select("SELECT * FROM public.bodega where bod_activo = true");

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listAllUsuActivos()
    {
        try {
            $data = Usuario::where('usu_activo', true)->orderBy('usu_nombre', 'asc')->get(['usu_id', 'usu_alias', 'usu_nombre', 'usu_apellido']);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }


    // Por Bodegas *************************************************************************************

    public function listBodegasDynamo()
    {
        try {
            $data = Bodega::where('bod_activo', true)->orderBy('bod_nombre', 'asc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listUsuariosByBodIdDynamo($bod_id)
    {
        try {
            $data = Bodega::where('bod_id', $bod_id)->with('usuarios')->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editUsuBodegas(Request $request)
    {
        try {
            DB::transaction(function () use ($request) {
                // Elimina los registros anteriores
                DB::table('daccesobod')->where('bod_id', $request->bod_id)->delete();

                $usuarios = $request->input('usuarios', []);

                if (empty($usuarios)) {
                    return;
                }

                // Preparar datos para insert masivo
                $dataToInsert = array_map(function ($item) use ($request) {
                    return [
                        'usu_id' => $item['usu_id'],
                        'bod_id' => $request->bod_id,
                        'locked' => false,
                    ];
                }, $usuarios);

                // Insert masivo
                DB::table('daccesobod')->insert($dataToInsert);

                // Calcular bodegas relacionadas UNA SOLA VEZ (fuera del loop)
                $bodegaData = null;
                if ($request->bod_id) {
                    $bodConsigUser = DB::selectOne("SELECT
                        b.bod_id as bod_add1,
                        b.bod_codigo as bod_codigo_1,
                        b2.bod_id as bod_add2,
                        b3.bod_id as bod_add3
                    FROM public.bodega b
                    LEFT JOIN public.bodega b2
                        ON CAST(b2.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 100)
                    LEFT JOIN public.bodega b3
                        ON CAST(b3.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 200)
                    WHERE b.bod_id = ?
                    LIMIT 1", [$request->bod_id]);

                    if ($bodConsigUser) {
                        // Calcular código base (restar 200 o 100 si aplica)
                        $codigoBodegaBase = $bodConsigUser->bod_codigo_1;

                        if ($codigoBodegaBase >= 200) {
                            $codigoBodegaBase = $codigoBodegaBase - 200;
                        } else if ($codigoBodegaBase >= 100) {
                            $codigoBodegaBase = $codigoBodegaBase - 100;
                        }

                        $bodegaData = DB::selectOne("SELECT
                            b.bod_id as bod_add1,
                            b2.bod_id as bod_add2,
                            b3.bod_id as bod_add3
                        FROM public.bodega b
                        LEFT JOIN public.bodega b2
                            ON CAST(b2.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 100)
                        LEFT JOIN public.bodega b3
                            ON CAST(b3.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 200)
                        WHERE b.bod_codigo = ?
                        LIMIT 1", [$codigoBodegaBase]);
                    }
                }

                // Cargar todos los usuarios de una vez
                $aliases = array_column($usuarios, 'usu_alias');
                $usuariosModelos = User::whereIn('usu_alias', $aliases)->get()->keyBy('usu_alias');

                // Actualizar usuarios
                if ($bodegaData) {
                    foreach ($usuarios as $item) {
                        $usuario = $usuariosModelos->get($item['usu_alias']);

                        if ($usuario) {
                            $usuario->bod_id = $bodegaData->bod_add1;
                            $usuario->bod_id_dos = $bodegaData->bod_add2;
                            $usuario->bod_id_tres = $bodegaData->bod_add3;
                            $usuario->save();
                        }
                    }
                }
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito.', ''));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), ''));
        }
    }

    // Por Usuarios *************************************************************************************

    public function listUsuariosBodegasDynamo()
    {
        try {
            $data = Usuario::with('bodegas')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listBodegasByUsuIdDynamo($usu_id)
    {
        try {
            $data = DB::select("SELECT d.usu_id, b.bod_id, b.bod_nombre 
                                        FROM daccesobod d 
                                        JOIN bodega b ON b.bod_id = d.bod_id 
                                        WHERE d.usu_id = ?;", [$usu_id]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editBodUsuario(Request $request)
    {
        try {
            DB::transaction(function () use ($request) {
                // Elimina los registros anteriores
                DB::table('daccesobod')->where('usu_id', $request->usu_id)->delete();

                $bodegas = $request->input('bodegas', []);

                if (empty($bodegas)) {
                    // Si no hay bodegas, poner a null los campos del usuario
                    $usuarioDynamo = DB::SelectOne("SELECT u.usu_id, u.usu_alias FROM public.usuario u where u.usu_id = ?", [$request->usu_id]);

                    if ($usuarioDynamo) {
                        $usuario = User::where('usu_alias', $usuarioDynamo->usu_alias)->first();

                        if ($usuario) {
                            $usuario->bod_id = 1;
                            $usuario->bod_id_dos = 101;
                            $usuario->bod_id_tres = null;
                            $usuario->save();
                        }
                    }

                    return;
                }

                // Preparar datos para insert masivo
                $dataToInsert = array_map(function ($item) use ($request) {
                    return [
                        'usu_id' => $request->usu_id,
                        'bod_id' => $item['bod_id'],
                        'locked' => false,
                    ];
                }, $bodegas);

                // Insert masivo
                DB::table('daccesobod')->insert($dataToInsert);

                // Actualizar bodegas en crm.users solo con la última bodega de la lista
                $ultimaBodega = $bodegas[count($bodegas) - 1];

                $usuarioDynamo = DB::SelectOne("SELECT u.usu_id, u.usu_alias FROM public.usuario u where u.usu_id = ?;", [$request->usu_id]);

                if (!$usuarioDynamo) {
                    return;
                }

                $usuario = User::where('usu_alias', $usuarioDynamo->usu_alias)->first();

                if ($usuario && $ultimaBodega['bod_id']) {
                    // Calcular bodegas relacionadas
                    $bodConsigUser = DB::selectOne("SELECT
                        b.bod_id as bod_add1,
                        b.bod_codigo as bod_codigo_1,
                        b2.bod_id as bod_add2,
                        b3.bod_id as bod_add3
                    FROM public.bodega b
                    LEFT JOIN public.bodega b2
                        ON CAST(b2.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 100)
                    LEFT JOIN public.bodega b3
                        ON CAST(b3.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 200)
                    WHERE b.bod_id = ?
                    LIMIT 1", [$ultimaBodega['bod_id']]);

                    if ($bodConsigUser) {
                        // Calcular código base (restar 200 o 100 si aplica)
                        $codigoBodegaBase = $bodConsigUser->bod_codigo_1;

                        if ($codigoBodegaBase >= 200) {
                            $codigoBodegaBase = $codigoBodegaBase - 200;
                        } else if ($codigoBodegaBase >= 100) {
                            $codigoBodegaBase = $codigoBodegaBase - 100;
                        }

                        $bodegaData = DB::selectOne("SELECT
                            b.bod_id as bod_add1,
                            b2.bod_id as bod_add2,
                            b3.bod_id as bod_add3
                        FROM public.bodega b
                        LEFT JOIN public.bodega b2
                            ON CAST(b2.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 100)
                        LEFT JOIN public.bodega b3
                            ON CAST(b3.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 200)
                        WHERE b.bod_codigo = ?
                        LIMIT 1", [$codigoBodegaBase]);

                        if ($bodegaData) {
                            $usuario->bod_id = $bodegaData->bod_add1;
                            $usuario->bod_id_dos = $bodegaData->bod_add2;
                            $usuario->bod_id_tres = $bodegaData->bod_add3;
                            $usuario->save();
                        }
                    }
                }
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito.', ''));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), ''));
        }
    }
}

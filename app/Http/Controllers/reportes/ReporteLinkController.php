<?php

namespace App\Http\Controllers\reportes;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\reportes\ReporteLink;
use App\Models\reportes\ReporteLinkUsuario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Exception;

class ReporteLinkController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:api', ['except' => ['listAllClientesDynamo']]);
    }

    public function listAllReporteLink()
    {
        try {
            $data = ReporteLink::selectRaw("*, (CASE WHEN crm.reporte_link.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado_reporte")
                ->with('departamento')
                ->orderBy('id', 'desc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

    public function addReporteLink(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {
                ReporteLink::create($request->all());

                $resp = ReporteLink::selectRaw("*, (CASE WHEN crm.reporte_link.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado_reporte")
                    ->with('departamento')
                    ->orderBy('id', 'desc')->get();
                return $resp;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editReporteLink(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $reporte = ReporteLink::find($id);

                $reporte->update($request->all());

                $resp = ReporteLink::selectRaw("*, (CASE WHEN crm.reporte_link.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado_reporte")
                    ->with('departamento')
                    ->orderBy('id', 'desc')->get();

                return $resp;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function deleteReporteLink($id)
    {
        try {
            $data = DB::transaction(function () use ($id) {
                $reporte = ReporteLink::find($id);

                $reporte->delete();

                $resp = ReporteLink::selectRaw("*, (CASE WHEN crm.reporte_link.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado_reporte")
                    ->with('departamento')
                    ->orderBy('id', 'desc')->get();

                return $resp;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // listado de reportes al que tiene permiso el usuario
    public function listReporteLinkByUserId($id)
    {
        try {
            $data = ReporteLinkUsuario::where('user_id', $id)
                ->whereHas('reporteLink', function ($query) {
                    $query->where('estado', true);
                })
                ->with('reporteLink.departamento')
                ->get();


            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

    // listado de los usuarios activos del CRM excluyendo algunos perfiles.
    public function listUsuActivosReportes()
    {
        try {
            // profile_id 3, es el perfil FABRICA DE CREDITO (Analista) en el crm.profiles
            // profile_id 6, es el perfil BODEGA en el crm.profiles
            // profile_id 38, es el perfil VENDEDOR en el crm.profiles
            // profile_id 39, es el perfil CAJERA FACTURADORA en el crm.profiles
            // $data = User::where('estado', true)->whereNotIn('profile_id', [3, 6, 38, 39])->orderBy('name', 'asc')->get(['id', 'usu_alias', 'name', 'surname']);
            $data = User::whereNotIn('profile_id', [3, 6, 38, 39])->orderBy('name', 'asc')->get(['id', 'usu_alias', 'name', 'surname']);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listReportesByUsuId($user_id)
    {
        try {
            $data = DB::select("SELECT u.id AS user_id,
                                    r.id AS id,
                                    r.nombre AS nombre_reporte
                                FROM crm.reporte_link r
                                JOIN crm.reporte_link_usuario ru ON ru.reporte_link_id = r.id
                                JOIN crm.users u ON u.id = ru.user_id
                                WHERE u.id = ?;", [$user_id]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listAllReportes()
    {
        try {
            $data = ReporteLink::orderBy('nombre', 'asc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editReporteUsuario(Request $request)
    {
        try {
            // Validar que el usuario exista
            $usuario = User::find($request->user_id);
            if (!$usuario) {
                return response()->json(RespuestaApi::returnResultado('error', 'El usuario no existe', ''));
            }

            DB::transaction(function () use ($request, $usuario) {
                // Eliminar registros anteriores
                ReporteLinkUsuario::where('user_id', $usuario->id)->delete();

                $reportes = $request->input('reportes', []);

                if (!empty($reportes)) {
                    // Preparar datos para inserción masiva
                    $dataToInsert = array_map(function ($item) use ($usuario) {
                        return [
                            'user_id' => $usuario->id,
                            'reporte_link_id' => $item['id'],
                        ];
                    }, $reportes);

                    // Inserción masiva
                    ReporteLinkUsuario::insert($dataToInsert);
                }
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito.', ''));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), ''));
        }
    }
}

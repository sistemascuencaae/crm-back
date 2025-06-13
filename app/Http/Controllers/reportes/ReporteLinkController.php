<?php

namespace App\Http\Controllers\reportes;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\reportes\ReporteLink;
use App\Models\reportes\ReporteLinkUsuario;
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
                                    ->with('reporteLink')
                                    ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

}
<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\openceo\Politica;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PoliticaController extends Controller
{
    public function listAllPoliticas()
    {
        try {
            $data = Politica::orderBy('pol_tipocli', 'asc')
                ->orderBy('pol_pordefecto', 'desc')
                ->orderBy('pol_activo', 'desc')
                ->orderBy('pol_nombre', 'asc')
                ->get(['pol_id', 'pol_nombre', 'pol_agrupador', 'por_porcentaje_comision', 'pol_activo', 'pol_tipocli', 'pol_pordefecto']);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function addPolitica(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {
                $nombre = strtoupper(trim($request->pol_nombre ?? ''));

                $reg = Politica::create([
                    'pol_nombre' => $nombre,
                    'pol_valordesde' => $request->pol_valordesde ?? 0,
                    'pol_valorhasta' => $request->pol_valorhasta ?? 0,
                    'pol_por_desc' => $request->pol_por_desc ?? 0,
                    'pol_por_financia' => $request->pol_por_financia ?? 0,
                    'pol_por_pagconta' => $request->pol_por_pagconta ?? 0,
                    'pol_por_propago' => $request->pol_por_propago ?? 0,
                    'pol_diasplazo' => $request->pol_diasplazo ?? 1,
                    'pol_nropagos' => $request->pol_nropagos ?? 1,
                    'pol_lineacredito' => $request->pol_lineacredito ?? 0,
                    'pol_activo' => $request->pol_activo,
                    'pol_tipocli' => $request->pol_tipocli ?? 1,
                    'locked' => false,
                    'pol_pordefecto' => $request->pol_pordefecto,
                    'pol_diasgracia' => $request->pol_diasgracia,
                    'pol_cuotasgratis' => $request->pol_cuotasgratis ?? 0,
                    'pol_agrupador' => $request->pol_agrupador ?? 2,
                    'por_porcentaje_comision' => $request->por_porcentaje_comision ?? 0,
                ]);

                $registro = Politica::where('pol_id', $reg->pol_id)
                    ->get(['pol_id', 'pol_nombre', 'pol_agrupador', 'por_porcentaje_comision']);

                return $registro;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se creo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editPolitica(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $politica = Politica::findOrFail($id);

                $nombre = strtoupper(trim($request->pol_nombre ?? ''));

                $politica->update([
                    'pol_nombre' => $nombre !== '' ? $nombre : $politica->pol_nombre,
                    'pol_valordesde' => $request->pol_valordesde ?? $politica->pol_valordesde,
                    'pol_valorhasta' => $request->pol_valorhasta ?? $politica->pol_valorhasta,
                    'pol_por_desc' => $request->pol_por_desc ?? $politica->pol_por_desc,
                    'pol_por_financia' => $request->pol_por_financia ?? $politica->pol_por_financia,
                    'pol_por_pagconta' => $request->pol_por_pagconta ?? $politica->pol_por_pagconta,
                    'pol_por_propago' => $request->pol_por_propago ?? $politica->pol_por_propago,
                    'pol_diasplazo' => $request->pol_diasplazo ?? $politica->pol_diasplazo,
                    'pol_nropagos' => $request->pol_nropagos ?? $politica->pol_nropagos,
                    'pol_lineacredito' => $request->pol_lineacredito ?? $politica->pol_lineacredito,
                    'pol_activo' => $request->pol_activo ?? $politica->pol_activo,
                    'pol_tipocli' => $request->pol_tipocli ?? $politica->pol_tipocli,
                    'locked' => false,
                    'pol_pordefecto' => $request->pol_pordefecto ?? $politica->pol_pordefecto,
                    'pol_diasgracia' => $request->pol_diasgracia ?? $politica->pol_diasgracia,
                    'pol_cuotasgratis' => $request->pol_cuotasgratis ?? $politica->pol_cuotasgratis,
                    'pol_agrupador' => $request->pol_agrupador ?? $politica->pol_agrupador,
                    'por_porcentaje_comision' => $request->por_porcentaje_comision ?? $politica->por_porcentaje_comision,
                ]);

                $registro = Politica::where('pol_id', $politica->pol_id)
                    ->get(['pol_id', 'pol_nombre', 'pol_agrupador', 'por_porcentaje_comision']);

                return $registro;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}

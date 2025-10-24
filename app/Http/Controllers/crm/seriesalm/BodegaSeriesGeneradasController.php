<?php

namespace App\Http\Controllers\crm\seriesalm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\seriesalm\BodegaSeriesGeneradas;
use Exception;

class BodegaSeriesGeneradasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listBodegaSeriesGeneradas()
    {
        try {
            $data = BodegaSeriesGeneradas::whereNotIn('bod_id', [16, 50, 60, 61, 181, 200, 209, 211, 225, 204, 208, 240, 241, 242, 243, 244, 210, 205])
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}

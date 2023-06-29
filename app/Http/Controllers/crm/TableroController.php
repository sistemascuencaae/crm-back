<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Tablero;
use Exception;
use Illuminate\Http\Request;

class TableroController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function store(Request $request)
    {
        try {
            $tablero = Tablero::create($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo el tablero con éxito', $tablero));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function index()
    {
        $tableros = Tablero::orderBy("id", "desc")->get();

        return response()->json([
            "tableros" => $tableros,
        ]);
    }

    public function edit(Request $request, $id)
    {
        try {
            $tablero = Tablero::findOrFail($id);

            $tablero->update($request->all());

            return response()->json(["tablero" => $tablero]);
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}
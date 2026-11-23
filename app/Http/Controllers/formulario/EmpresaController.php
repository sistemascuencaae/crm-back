<?php

namespace App\Http\Controllers\formulario;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Empresa;
use Exception;

// Migrado de ecommerce-back: solo el combo que usa el formulario institucional (/formulario)
class EmpresaController extends Controller
{
    // Para la lista del combo box del formulario
    public function listAllEmpresas()
    {
        try {
            $data = Empresa::where('estado', true)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}

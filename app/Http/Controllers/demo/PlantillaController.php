<?php

namespace App\Http\Controllers\demo;

use App\Http\Controllers\Controller;
use App\Models\crm\Caso;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class PlantillaController extends Controller
{
    // Devuelve las variables de un caso para rellenar una plantilla de documento.
    public function casoVariables(int $id): JsonResponse
    {
        $caso = Caso::with(['clienteCrm', 'user'])
            ->findOrFail($id);

        $cliente = $caso->clienteCrm;

        return response()->json([
            // Caso
            'numero_caso' => (string) $caso->id,
            'fecha_inicio' => $caso->fecha_inicio ? Carbon::parse($caso->fecha_inicio)->format('d/m/Y') : '',
            'fecha_vencimiento' => $caso->fecha_vencimiento ? Carbon::parse($caso->fecha_vencimiento)->format('d/m/Y') : '',
            'descripcion_caso' => $caso->descripcion ?? '',
            'estado_caso' => $caso->estado ?? '',
            'prioridad_caso' => $caso->prioridadNombre ?? $caso->prioridad ?? '',
            'agente_caso' => $caso->user ? trim($caso->user->name . ' ' . $caso->user->surname) : '',
            // Cliente
            'nombre_cliente' => $cliente ? $cliente->nombres : ($caso->nombre ?? ''),
            'identificacion_cliente' => $cliente ? $cliente->identificacion : ($caso->identificacion ?? ''),
            'email_cliente' => $cliente ? $cliente->email : ($caso->email ?? ''),
            'celular_cliente' => $caso->celulares ?? '',
            'direccion_cliente' => $cliente ? $cliente->direccion : ($caso->direccion ?? ''),
            // Sistema
            'fecha_hoy' => Carbon::now()->format('d/m/Y'),
            'hora_hoy' => Carbon::now()->format('H:i'),
        ]);
    }
}

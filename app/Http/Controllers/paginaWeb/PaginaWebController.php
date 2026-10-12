<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Servicios\PaginaWeb\PaginaWebService;
use Illuminate\Http\Request;

// Base de los controladores de la página web: lo que comparten todos
abstract class PaginaWebController extends Controller
{
    public function __construct(protected PaginaWebService $paginaWeb)
    {
    }

    // ok:true -> 200 (o el código indicado); ok:false -> el código que diga la función, o 422
    protected function responder(array $resultado, int $codigoOk = 200)
    {
        if ($resultado['ok'] ?? false) {
            return response()->json($resultado, $codigoOk);
        }

        return response()->json($resultado, $resultado['codigo'] ?? 422);
    }

    // El id del usuario que hace el cambio, para la auditoría (crm.users.id, igual que ConsultasService)
    protected function usuarioId(): ?int
    {
        return auth('api')->user()->id ?? null;
    }

    // Convierte una lista que llega en la petición en una lista de enteros
    protected function listaDeIds(Request $request, string $campo): array
    {
        return array_values(array_map('intval', (array) $request->input($campo, [])));
    }
}

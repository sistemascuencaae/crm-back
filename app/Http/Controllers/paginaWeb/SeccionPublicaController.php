<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Servicios\PaginaWeb\PaginaWebService;

class SeccionPublicaController extends Controller
{
    // GET public/pagina-web/secciones
    public function index(PaginaWebService $paginaWeb)
    {
        $resultado = $paginaWeb->listarSeccionesPublicas();

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 500);
    }
}

<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Servicios\PaginaWeb\SucursalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ciudades de la página "Nuestras tiendas" (CRM).
 * Solo deja pasar los campos permitidos y agrega el usuario autenticado;
 * las validaciones están en la base (fn_sucursal_ciudad_validaciones).
 */
class SucursalCiudadController extends Controller
{
    private const CAMPOS = ['sci_nombre', 'sci_activo'];

    public function __construct(private SucursalService $servicio)
    {
    }

    public function crear(Request $request): JsonResponse
    {
        $datos = array_merge($request->only(self::CAMPOS), ['usu_id' => $this->usuarioId()]);

        return $this->responder($this->servicio->crearCiudad($datos), 201);
    }

    public function modificar(Request $request, int $sciId): JsonResponse
    {
        // only() trae solo los campos que llegaron: permite modificar uno solo
        $datos = array_merge($request->only(self::CAMPOS), ['usu_id' => $this->usuarioId()]);

        return $this->responder($this->servicio->modificarCiudad($sciId, $datos));
    }

    public function eliminar(int $sciId): JsonResponse
    {
        return $this->responder($this->servicio->eliminarCiudad($sciId));
    }

    /** Body: {"ids": [3, 1, 2]} con todas las ciudades en el nuevo orden. */
    public function ordenar(Request $request): JsonResponse
    {
        $datos = [
            'ids'    => $request->input('ids'),
            'usu_id' => $this->usuarioId(),
        ];

        return $this->responder($this->servicio->ordenarCiudades($datos));
    }

    /** ok:true → código de éxito; ok:false → 422 con el mensaje y los errores de la base. */
    private function responder(array $resultado, int $codigoExito = 200): JsonResponse
    {
        return response()->json($resultado, !empty($resultado['ok']) ? $codigoExito : 422);
    }

    /** Clave primaria del usuario autenticado (usu_id). Si en SeccionController lo obtienes de otra forma, usa esa. */
    private function usuarioId(): ?int
    {
        return auth()->id();
    }
}
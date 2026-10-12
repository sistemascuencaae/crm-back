<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Servicios\PaginaWeb\SucursalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sucursales de la página "Nuestras tiendas" (CRM).
 * Solo deja pasar los campos permitidos y agrega el usuario autenticado;
 * las validaciones están en la base (fn_sucursal_validaciones).
 */
class SucursalController extends Controller
{
    private const CAMPOS = [
        'sci_id',
        'suc_nombre',
        'suc_direccion',
        'suc_horario',
        'suc_telefono',
        'suc_latitud',
        'suc_longitud',
        'suc_activo',
    ];

    public function __construct(private SucursalService $servicio)
    {
    }

    /** Ciudades con sus sucursales, incluidas las inactivas. */
    public function listar(): JsonResponse
    {
        return $this->responder($this->servicio->listarAdmin());
    }

    public function crear(Request $request): JsonResponse
    {
        $datos = array_merge($request->only(self::CAMPOS), ['usu_id' => $this->usuarioId()]);

        return $this->responder($this->servicio->crear($datos), 201);
    }

    public function modificar(Request $request, int $sucId): JsonResponse
    {
        // only() trae solo los campos que llegaron: permite modificar uno solo.
        // Para borrar un campo opcional, el CRM envía null (ej.: "suc_telefono": null).
        $datos = array_merge($request->only(self::CAMPOS), ['usu_id' => $this->usuarioId()]);

        return $this->responder($this->servicio->modificar($sucId, $datos));
    }

    public function eliminar(int $sucId): JsonResponse
    {
        return $this->responder($this->servicio->eliminar($sucId));
    }

    /** Body: {"sci_id": 1, "ids": [5, 3, 4]} con todas las sucursales de esa ciudad en el nuevo orden. */
    public function ordenar(Request $request): JsonResponse
    {
        $datos = [
            'sci_id' => $request->input('sci_id'),
            'ids'    => $request->input('ids'),
            'usu_id' => $this->usuarioId(),
        ];

        return $this->responder($this->servicio->ordenar($datos));
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
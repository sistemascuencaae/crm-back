<?php

namespace App\Http\Traits;

use App\Models\Access;

// Version 1.0
// El permiso de un menú (crm.menu.name) para el perfil del usuario del token: crm.access con la acción en 1, el mismo que
// valida el front (como UserController::tienePermisoUsuarios). Listar pide ejecutar; guardar pide edit y, si es uno nuevo
// (sin id), además create.
// 11-nov-2026 (reestructura de Contabilidad): viene de los métodos privados tienePermiso, sinPermisoParaGuardar y sinValor de
// ventas/BancosEmisoresController, con la misma lógica y los mismos mensajes. Lo usan contabilidad/EmisorController,
// SistemaLiquidacionController y BancoController.
trait PermisoMenuTrait
{
    // Version 1.0
    // El permiso del menú (EMISORES, SISTEMAS-LIQUIDACION o BANCOS) para el perfil del usuario logueado: crm.access por
    // crm.menu.name.
    private function tienePermiso(string $menu, string $accion): bool
    {
        $usuario = auth('api')->user();

        return $usuario !== null && Access::where('profile_id', $usuario->profile_id)
            ->where($accion, 1)
            ->whereHas('menu', function ($query) use ($menu) {
                $query->where('name', $menu);
            })
            ->exists();
    }

    // Version 1.0
    // Guardar pide edit; uno nuevo (sin id), además create. null = puede; si no, el mensaje para el usuario.
    private function sinPermisoParaGuardar(string $menu, $id, string $plural): ?string
    {
        if (!$this->tienePermiso($menu, 'edit')) {
            return 'Su perfil no tiene permiso para guardar ' . $plural . '.';
        }
        if ($this->sinValor($id) && !$this->tienePermiso($menu, 'create')) {
            return 'Su perfil no tiene permiso para agregar ' . $plural . '.';
        }

        return null;
    }

    // Version 1.0
    // Sin id = uno nuevo (null o texto vacío; otra cosa la revisa el Validator).
    private function sinValor($valor): bool
    {
        return $valor === null || (is_string($valor) && trim($valor) === '');
    }
}

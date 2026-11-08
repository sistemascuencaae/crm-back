<?php

namespace App\Http\Controllers\crm\series;

use Illuminate\Support\Facades\DB;

// 07-nov: el autor de GEX y series sale del token (el usuario de crm.users que actúa), no del body.
//   - Alta (fecha_modifica en null): usuario_crea = el que actúa y usuario_modifica = null.
//   - Edición: usuario_modifica = el que actúa; usuario_crea y fecha_crea se conservan de la fila guardada.
//     Si la fila todavía no existe (la grabación la crea), el que actúa queda como creador y fecha_crea = la fecha de esta grabación.
//   - Sin token (hoy ninguna ruta de GEX y series lo permite): como antes, lo que manda el body.
// Lo usan los graba* de crm/garantias y crm/series.
trait AutorGexSeriesTrait
{
    /**
     * @param  array   $body           Lo que mandó el front: $request->all() o un elemento del arreglo que llegó.
     * @param  string  $tabla          Tabla con usuario_crea y fecha_crea, por ejemplo 'gex.comisiones'.
     * @param  array   $llave          Llave de la fila, por ejemplo ['comi_id' => 5].
     * @param  mixed   $fechaCrea      fecha_crea que ya calculó el método (la de ahora en el alta; la del body al editar).
     * @param  mixed   $fechaModifica  fecha_modifica que ya calculó el método: null en el alta.
     * @return array   ['usuario_crea' => ..., 'fecha_crea' => ..., 'usuario_modifica' => ...]
     */
    protected function autorGexSeries(array $body, string $tabla, array $llave, $fechaCrea, $fechaModifica): array
    {
        $usuarioId = auth('api')->id();

        if (!$usuarioId) {
            return [
                'usuario_crea' => $body['usuario_crea'] ?? null,
                'fecha_crea' => $fechaCrea,
                'usuario_modifica' => $body['usuario_modifica'] ?? null,
            ];
        }

        if ($fechaModifica === null) {
            return ['usuario_crea' => $usuarioId, 'fecha_crea' => $fechaCrea, 'usuario_modifica' => null];
        }

        $guardada = DB::table($tabla)->where($llave)->first(['usuario_crea', 'fecha_crea']);

        if (!$guardada) {
            return ['usuario_crea' => $usuarioId, 'fecha_crea' => $fechaModifica, 'usuario_modifica' => null];
        }

        return ['usuario_crea' => $guardada->usuario_crea, 'fecha_crea' => $guardada->fecha_crea, 'usuario_modifica' => $usuarioId];
    }
}

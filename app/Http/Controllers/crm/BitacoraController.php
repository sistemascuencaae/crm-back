<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Support\Facades\DB;

class BitacoraController extends Controller
{
    public function listBitacoraByCasoId($caso_id)
    {
        $log = new Funciones();
        try {
            $bitacora = DB::select("SELECT adi.*,
                                            ur.name || ' ' || ur.surname AS name
                                    FROM crm.audits adi
                                    LEFT JOIN crm.users ur ON ur.id = adi.user_id
                                    WHERE adi.caso_id = " . $caso_id . "
                                    ORDER BY adi.id DESC;");

            // Recopilar todos los tab_id necesarios
            $tableroIds = [];
            foreach ($bitacora as $registro) {
                if (!empty($registro->old_values)) {
                    $oldValues = json_decode($registro->old_values, true);
                    if (isset($oldValues['estadodos']['tab_id'])) {
                        $tableroIds[] = $oldValues['estadodos']['tab_id'];
                    }
                }
                if (!empty($registro->new_values)) {
                    $newValues = json_decode($registro->new_values, true);
                    if (isset($newValues['estadodos']['tab_id'])) {
                        $tableroIds[] = $newValues['estadodos']['tab_id'];
                    }
                }
            }

            // Obtener información de tableros en una sola consulta
            $tableroIds = array_unique($tableroIds);
            $tableros = [];
            if (!empty($tableroIds)) {
                $tablerosData = DB::table('crm.tablero')
                    ->whereIn('id', $tableroIds)
                    ->get();

                foreach ($tablerosData as $tablero) {
                    $tableros[$tablero->id] = [
                        'id' => $tablero->id,
                        'nombre' => $tablero->nombre,
                        'dep_id' => $tablero->dep_id,
                        'descripcion' => $tablero->descripcion,
                        'estado' => $tablero->estado,
                        'created_at' => $tablero->created_at,
                        'updated_at' => $tablero->updated_at,
                    ];
                }
            }

            // Agregar el objeto tablero dentro de estadodos para que se muestre el tablero correcto
            foreach ($bitacora as $registro) {
                // Procesar old_values
                if (!empty($registro->old_values)) {
                    $oldValues = json_decode($registro->old_values, true);
                    if (isset($oldValues['estadodos']['tab_id']) && isset($tableros[$oldValues['estadodos']['tab_id']])) {
                        $oldValues['estadodos']['tablero'] = $tableros[$oldValues['estadodos']['tab_id']];
                        $registro->old_values = json_encode($oldValues);
                    }
                }

                // Procesar new_values
                if (!empty($registro->new_values)) {
                    $newValues = json_decode($registro->new_values, true);
                    if (isset($newValues['estadodos']['tab_id']) && isset($tableros[$newValues['estadodos']['tab_id']])) {
                        $newValues['estadodos']['tablero'] = $tableros[$newValues['estadodos']['tab_id']];
                        $registro->new_values = json_encode($newValues);
                    }
                }
            }

            $log->logInfo(BitacoraController::class, 'Se listo con exito la bitacora del caso: # ' . $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $bitacora));
        } catch (Exception $e) {
            $log->logError(BitacoraController::class, 'Error al listar la bitacora del caso: #' . $caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}

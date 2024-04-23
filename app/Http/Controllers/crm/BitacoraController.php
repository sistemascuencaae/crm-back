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

            // CONSULTA ANTERIOR, EL ERROR ERA CUANDO YA NO EXISTE EL REGISTRO YA NO TRAIA LOS REGISTROS DE LA TABLA AUDITS

            // $bitacora = DB::select("select adi.*,ur.name,gal.titulo, fas.nombre as fase_actual_nombre, tab.nombre as tablero_actual_nombre, fas1.nombre as fase_anterior_nombre, tab1.nombre as tablero_anterior_nombre,
            //                         u1.name as usuario_actual, u2.name as usuario_anterior

            //                         from crm.audits adi

            //                         left join crm.archivos arc on arc.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Archivo'
            //                         left join crm.galerias gal on gal.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Galeria'
            //                         left join crm.nota n on n.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Nota'
            //                         left join crm.caso caso on caso.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Caso'
            //                         left join crm.comentarios c on c.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Comentarios'
            //                         left join crm.dtipo_actividad dta on dta.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\DTipoActividad'
            //                         left join crm.solicitud_credito sc on sc.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\credito\SolicitudCredito'
            //                         left join crm.requerimientos_caso rq on rq.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\RequerimientoCaso'
            //                         left join crm.caso cas on cas.id = arc.caso_id or cas.id = gal.caso_id or cas.id = n.caso_id or cas.id = caso.id

            //                         or cas.id = c.caso_id
            //                         or cas.id = dta.caso_id
            //                         or cas.id = sc.caso_id
            //                         or cas.id = rq.caso_id

            //                         left join crm.fase fas on fas.id = cas.fas_id
            //                         left join crm.tablero tab on tab.id = fas.tab_id
            //                         left join crm.fase fas1 on fas1.id = cas.fase_anterior_id
            //                         left join crm.tablero tab1 on tab1.id = fas1.tab_id
            //                         left join crm.users u1 on u1.id = cas.user_id -- Esto es correcto No modificcar
            //                         left join crm.users u2 on u2.id = cas.user_anterior_id -- Esto es correcto No modificcar
            //                         left join crm.users ur on ur.id = adi.user_id

            //                         where cas.id = " . $caso_id . "
            //                         order By 1 DESC");



            $bitacora = DB::select("SELECT adi.*, 
                                            ur.name || ' ' || ur.surname AS name, 
                                            gal.titulo, 
                                            fas.nombre AS fase_actual_nombre, 
                                            tab.nombre AS tablero_actual_nombre, 
                                            fas1.nombre AS fase_anterior_nombre, 
                                            tab1.nombre AS tablero_anterior_nombre,
                                            u1.name || ' ' || u1.surname AS usuario_actual, 
                                            u2.name || ' ' || u2.surname AS usuario_anterior

                                    FROM crm.audits adi

                                    LEFT JOIN crm.archivos arc ON arc.id = adi.auditable_id AND adi.auditable_type = 'App\Models\crm\Archivo'
                                    LEFT JOIN crm.galerias gal ON gal.id = adi.auditable_id AND adi.auditable_type = 'App\Models\crm\Galeria'
                                    LEFT JOIN crm.nota n ON n.id = adi.auditable_id AND adi.auditable_type = 'App\Models\crm\Nota'
                                    LEFT JOIN crm.caso caso ON caso.id = adi.caso_id AND adi.auditable_type = 'App\Models\crm\Caso'
                                    LEFT JOIN crm.comentarios c ON c.id = adi.auditable_id AND adi.auditable_type = 'App\Models\crm\Comentarios'
                                    LEFT JOIN crm.dtipo_actividad dta ON dta.id = adi.auditable_id AND adi.auditable_type = 'App\Models\crm\DTipoActividad'
                                    LEFT JOIN crm.solicitud_credito sc ON sc.id = adi.auditable_id AND adi.auditable_type = 'App\Models\crm\credito\SolicitudCredito'
                                    LEFT JOIN crm.requerimientos_caso rq ON rq.id = adi.auditable_id AND adi.auditable_type = 'App\Models\crm\RequerimientoCaso'

                                    LEFT JOIN crm.caso cas ON cas.id = adi.caso_id
                                    LEFT JOIN crm.fase fas ON fas.id = cas.fas_id
                                    LEFT JOIN crm.tablero tab ON tab.id = fas.tab_id
                                    LEFT JOIN crm.fase fas1 ON fas1.id = cas.fase_anterior_id
                                    LEFT JOIN crm.tablero tab1 ON tab1.id = fas1.tab_id
                                    LEFT JOIN crm.users u1 ON u1.id = cas.user_id -- Esto es correcto No modificcar
                                    LEFT JOIN crm.users u2 ON u2.id = cas.user_anterior_id -- Esto es correcto No modificcar
                                    LEFT JOIN crm.users ur ON ur.id = adi.user_id

                                    WHERE adi.caso_id = " . $caso_id . "ORDER BY adi.id DESC;");

            $log->logInfo(BitacoraController::class, 'Se listo con exito la bitacora del caso: # ' . $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $bitacora));
        } catch (Exception $e) {
            $log->logError(BitacoraController::class, 'Error al listar la bitacora del caso: #' . $caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}

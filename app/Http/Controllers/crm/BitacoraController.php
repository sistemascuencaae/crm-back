<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BitacoraController extends Controller
{
    public function listBitacoraByCasoId($caso_id)
    {
        // $bitacora = DB::select('select * from public.audits');
        try {
            //     $bitacora = DB::select("select adi.*,ur.name,gal.titulo  from public.audits adi
            // left join crm.archivos arc on arc.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Archivo'
            // left join crm.galerias gal on gal.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Galeria'
            // left join crm.comentarios c on c.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Comentarios'
            // left join crm.etiquetas e on e.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Etiqueta'
            // left join crm.nota n on n.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Nota'
            // left join crm.caso cas on cas.id = arc.caso_id or cas.id = gal.caso_id or cas.id = c.caso_id or cas.id = e.caso_id or cas.id = n.caso_id
            // left join public.users ur on ur.id = adi.user_id
            // where cas.id = " . $caso_id . "
            // order By 1 DESC");

            $bitacora = DB::select("select adi.*,ur.name,gal.titulo, fas.nombre as fase_actual_nombre, tab.nombre as tablero_actual_nombre, fas1.nombre as fase_anterior_nombre, tab1.nombre as tablero_anterior_nombre from public.audits adi
        left join crm.archivos arc on arc.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Archivo'
        left join crm.galerias gal on gal.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Galeria'
        left join crm.comentarios c on c.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Comentarios'
        left join crm.etiquetas e on e.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Etiqueta'
        left join crm.nota n on n.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Nota'
        left join crm.caso caso on caso.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Caso'
        left join crm.caso cas on cas.id = arc.caso_id or cas.id = gal.caso_id or cas.id = c.caso_id or cas.id = e.caso_id or cas.id = n.caso_id or cas.id = caso.id
        left join crm.fase fas on fas.id = cas.fas_id
        left join crm.tablero tab on tab.id = fas.tab_id 
        left join crm.fase fas1 on fas1.id = cas.fase_anterior_id 
        left join crm.tablero tab1 on tab1.id = fas1.tab_id 
        left join public.users ur on ur.id = adi.user_id
        where cas.id = " . $caso_id . "
        order By 1 DESC");

            // return response()->json([
            //     "bitacora" => $bitacora,
            // ]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $bitacora));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}
<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BitacoraController extends Controller
{
    public function index($tar_id)
    {
        // $bitacora = DB::select('select * from public.audits');

        $bitacora = DB::select("select adi.*,ur.name  from public.audits adi
        left join crm.archivos arc on arc.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Archivo'
        left join crm.galerias gal on gal.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Galeria'
        left join crm.comentarios c on c.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Comentarios'
        left join crm.etiquetas e on e.id = adi.auditable_id and adi.auditable_type = 'App\Models\crm\Etiqueta'
        left join crm.tarea tar on tar.id = arc.tar_id or tar.id = gal.tar_id or tar.id = c.tarea_id or tar.id = e.tar_id
        left join public.users ur on ur.id = adi.user_id 
        where tar.id = " . $tar_id . ";");

        return response()->json([
            "bitacora" => $bitacora,
        ]);
    }
}
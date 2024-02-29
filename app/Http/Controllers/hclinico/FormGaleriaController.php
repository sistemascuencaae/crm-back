<?php

namespace App\Http\Controllers\hclinico;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Galeria;
use Illuminate\Support\Facades\Storage;
use App\Models\hclinico\FormGaleria;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormGaleriaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => [
                'listConversaciones',
                'listarMensajes',
                'getImagenesSmg'
            ]
        ]);
    }
    public function imagenesFormulario($formId)
    {
        try {
             //$data = FormGaleria::with("imagenes")->where('form_id',$formId)->first();

             $data = DB::select("SELECT ga.* from hclinico.form_galeria fg
                    inner join crm.galerias ga on ga.id = fg.galeria_id
                    where fg.form_id = ?",[$formId]);
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }
    public function addGaleriaForm(Request $request, $formId)
    {


        try {
            if ($request->hasFile("imagen_file")) {
                $imagen = $request->file("imagen_file");
                $titulo = $imagen->getClientOriginalName();

                // Fecha actual
                $fechaActual = Carbon::now();

                // Formatear la fecha en formato deseado
                // $fechaFormateada = $fechaActual->format('Y-m-d H-i-s');

                // Reemplazar los dos puntos por un guion medio (NO permite windows guardar con los : , por eso se le pone el - )
                $fecha_actual = str_replace(':', '-', $fechaActual);

                $parametro = DB::table('crm.parametro')
                ->where('abreviacion', 'NAS')
                ->first();

                if ($parametro->nas == true) {
                    $path = Storage::disk('nas')->putFileAs($formId . "/galerias", $imagen, $formId . '-' . $fecha_actual . '-' . $titulo);
                } else {
                    $path = Storage::disk('local')->putFileAs($formId . "/galerias", $imagen, $formId . '-' . $fecha_actual . '-' . $titulo);
                }

                $request->request->add(["imagen" => $path]); // Aquí obtenemos la ruta de la imagen en la que se encuentra
            }

            $galeria = Galeria::create($request->all());
            $ormGaleria = FormGaleria::create([
                "galeria_id" => $galeria->id,
                "form_id" => $formId
            ]);


            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $galeria));
        } catch (Exception $e) {


            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editGaleriaForm(Request $request, $formId)
    {


        try {
            if ($request->hasFile("imagen_file")) {
                $imagen = $request->file("imagen_file");
                $titulo = $imagen->getClientOriginalName();

                // Fecha actual
                $fechaActual = Carbon::now();

                // Formatear la fecha en formato deseado
                // $fechaFormateada = $fechaActual->format('Y-m-d H-i-s');

                // Reemplazar los dos puntos por un guion medio (NO permite windows guardar con los : , por eso se le pone el - )
                $fecha_actual = str_replace(':', '-', $fechaActual);

                $parametro = DB::table('crm.parametro')
                ->where('abreviacion', 'NAS')
                ->first();

                if ($parametro->nas == true) {
                    $path = Storage::disk('nas')->putFileAs($formId . "/galerias", $imagen, $formId . '-' . $fecha_actual . '-' . $titulo);
                } else {
                    $path = Storage::disk('local')->putFileAs($formId . "/galerias", $imagen, $formId . '-' . $fecha_actual . '-' . $titulo);
                }

                $request->request->add(["imagen" => $path]); // Aquí obtenemos la ruta de la imagen en la que se encuentra
            }

            // $galeria = Galeria::create($request->all());
            // $ormGaleria = FormGaleria::create([
            //     "galeria_id" => $galeria->id,
            //     "form_id" => $formId
            // ]);
            $galeria = Galeria::find($request->input('id'));
            $galeria->update($request->all());
            //$galeria = Galeria::updated($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $galeria));
        } catch (Exception $e) {


            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}

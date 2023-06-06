<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Models\crm\Archivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use TheSeer\Tokenizer\Exception;
use Tymon\JWTAuth\Providers\JWT\Provider;

class ArchivoController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

    public function store(Request $request)
    {

        $file = $request->file("archivo");
        $titulo = $file->getClientOriginalName();
        
        $count = count($request->file());
        
        if ($count > 0) {
            foreach ($request->file() as $item) {
                echo (json_encode($count));
                echo (json_encode($file));
                echo (json_encode($titulo));
                //acá almacenas cada archivo, un pequeño ejemplo:
                //   $item->storeAs('files/', 'nombre-del-archivo');
                // $path = Storage::putFile("archivos", $request->file("archivo")); //se va a guardar dentro de la CARPETA CATEGORIAS
                // $request->request->add(["archivo" => $path]); //Aqui obtenemos la ruta de la imagen en la que se encuentra

                // //tú coloca la lógica que necesites para almacenar cada archivo :) 
                // $archivo = Archivo::create([
                //     "titulo" => $titulo,
                //     "archivo" => $path,
                // ]);
            }
        }

        // return response()->json(["archivo" => $archivo,]);

        // -*-*-*-*-*-*-*-*-***********************************************************************
        // $file = $request->file("archivo");
        // $count = count($request->file());
        // echo (json_encode($count));

        // $titulo = $file->getClientOriginalName();

        // $path = Storage::putFile("archivos", $request->file("archivo")); //se va a guardar dentro de la CARPETA CATEGORIAS
        // $request->request->add(["archivo" => $path]); //Aqui obtenemos la ruta de la imagen en la que se encuentra


        // $archivo = Archivo::create([
        //     "titulo" => $titulo,
        //     "archivo" => $path,
        // ]);
        // // }

        // return response()->json(["archivo" => $archivo,]);

        // //*-*-*-*-*-*-*-*-*-*-*-*-*-********************************************************
        // if ($request->hasFile("archivo_file")) {
        //     $path = Storage::putFile("archivos", $request->file("archivo_file")); //se va a guardar dentro de la CARPETA CATEGORIAS
        //     $request->request->add(["archivo" => $path]); //Aqui obtenemos la ruta de la imagen en la que se encuentra
        // }

        // $arch = Archivo::create($request->all());

        // return response()->json(["archivo" => $arch,]);

        //*-*-*-*-*-*-*-*-*-*-*-*-*-********************************************************
        // dd($request); // dd para envia toda la info
        // try {
        //     DB::beginTransaction();

        //     $reg = new Archivo;

        //     $reg->titulo = $request->get('titulo');

        //     if ($request->hasFile('archivo')) {

        //         $archivo = $request->file('archivo');
        //         // $archivo->move(public_path() . '/Archivos/', $archivo->getClientOriginalName());
        //         // $reg->documento = $archivo->getClientOriginalName();

        //         $path = Storage::putFile("archivos", $request->file("archivo")); //se va a guardar dentro de la CARPETA CATEGORIAS
        //         $request->request->add(["archivo" => $path]); //Aqui obtenemos la ruta de la imagen en la que se encuentra
        //     }
        //     $reg->save;
        //     $save = Archivo::create($request->all());


        //     DB::commit();
        //     return response()->json(["archivo" => $save]);
        // } catch (Exception $e) {
        //     DB::rollBack();
        // }

        //*-*-*-*-*-*-*-*-*-*-*-*-*-********************************************************
        //othert coed

        // if ($request->hasFile("archivo")) {

        //     $file = $request->file("archivo");

        //     $titulo = "pdf_" . time() . "." . $file->guessExtension();

        //     $ruta = storage_path("pdf/" . $titulo);

        //     if ($file->guessExtension() == "pdf") {
        //         copy($file, $ruta);
        //         $archivo = Archivo::create($request->all());

        //         return response()->json(["imagen" => $archivo,]);
        //     } else {
        //         dd('NO ES PDF');
        //     }
        // }

        //*-*-*-*-*-*-*-*-*-*-*-*-*-********************************************************

        // $max_size = (int) ini_get('tamaño max') * 10240;

        // $files = $request->file('files');

        // foreach ($files as $file) {
        //     if (Storage::putFilesAs('/public/', $file, $file->getClientOriginalName())) {
        //         $archivo = Archivo::create($request->all());
        //     }
        // }

        // return response()->json(["imagen" => $archivo,]);
    }

    public function index(Request $request)
    {

    }

    public function edit(Request $request, $id)
    {

    }

    public function destroy($id)
    {

    }
}
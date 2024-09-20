<?php

namespace App\Http\Controllers\MigracionNovasoft;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\Storage;

class MigracionController extends Controller
{

    private $funciones;

    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => [
                'aav_migracion_cartera',
                'aav_migracion_cliente',
                'aav_migracion_referencias_cliente',
                'aav_migracion_CobrosxSecretaria_PeriodoyMes_Actual',
                'aav_migracion_rutaje',
                'aav_migracion_cartera_historica',
                'af_migracion_cartera_historica_api',
                'af_migracion_cartera_historica_xcuotas_api',
                'af_migracion_cartera_historica_xcuotas_xcobros_api',
                'imagenes_base64',
            ]
        ]);

        $this->funciones = new Funciones();
    }

    public function aav_migracion_cartera()
    {
        try {
            $data = DB::table('public.aav_migracion_cartera')->get();

            // Generar el archivo .txt
            $archivo = $this->funciones->descargarTxt($data, 'almespana_cartera.txt');

            return $archivo;
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function aav_migracion_cliente()
    {
        try {
            $data = DB::table('public.aav_migracion_cliente')->get();

            // Generar el archivo .txt
            $archivo = $this->funciones->descargarTxt($data, 'almespana_clientes.txt');

            return $archivo;
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function aav_migracion_referencias_cliente()
    {
        try {
            $data = DB::table('public.aav_migracion_referencias_cliente')->get();

            // Generar el archivo .txt
            $archivo = $this->funciones->descargarTxt($data, 'almespana_referencias_clientes.txt');

            return $archivo;
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function aav_migracion_CobrosxSecretaria_PeriodoyMes_Actual()
    {
        try {
            $data = DB::table('public.aav_migracion_cobrosxsecretaria_periodoymes_actual')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function aav_migracion_rutaje()
    {
        try {
            $data = DB::table('public.aav_migracion_rutaje')
                ->select('ent_id', 'ent_identificacion', 'ent_nombres', 'ent_apellidos', 'emp_abreviacion', 'cedula_cobrador', 'nombre_cobrador', 'fecha_reporte')
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function aav_migracion_cartera_historica()
    {
        try {
            $data = DB::table('public.aav_migracion_cartera_historica')
                ->get();

            // Generar el archivo .txt
            $archivo = $this->funciones->descargarTxt($data, 'almespana_cartera_historica.txt');

            return $archivo;
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function af_migracion_cartera_historica_api($anio, $mes, $dia)
    {
        try {
            // Select directo a una funcion de la base de datos
            $data = DB::select('SELECT * FROM dashboard.af_migracion_cartera_historica_api(?, ?, ?)', [$anio, $mes, $dia]);

            // $archivo = $this->funciones->descargarTxt($data, 'af_migracion_cartera_historica_api.txt');
            // return $archivo;

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function af_migracion_cartera_historica_xcuotas_api($anio, $mes, $dia)
    {
        try {
            // Select directo a una funcion de la base de datos
            $data = DB::select('SELECT * FROM dashboard.af_migracion_cartera_historica_xcuotas_api(?, ?, ?)', [$anio, $mes, $dia]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function af_migracion_cartera_historica_xcuotas_xcobros_api($anio, $mes, $dia)
    {
        try {
            // Select directo a una funcion de la base de datos
            $data = DB::select('SELECT * FROM dashboard.af_migracion_cartera_historica_xcuotas_xcobros_api(?, ?, ?)', [$anio, $mes, $dia]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // devuelve la N fotos de cada SKU
    // public function imagenes_base64()
    // {
    //     try {

    //         // para obtener el archivo txt, nos vamos al sguiente link y transformamos a json el excel 
    //         // https://tableconvert.com/es/excel-to-json
    //         // luego lo copiasmos todo el json y en cada imagen tenemos que poner como atributo "Imagen n" (n = numero de fotos)

    //         // Ruta del archivo en el NAS
    //         $jsonPath = "migracion_productos/productos_json.txt";

    //         // Verificar si el archivo existe en el NAS
    //         if (!Storage::disk('nas')->exists($jsonPath)) {
    //             return response()->json(['message' => 'No existe ningún archivo'], 404);
    //         }

    //         // Obtener el contenido del archivo
    //         $jsonContent = Storage::disk('nas')->get($jsonPath);
    //         $productos = json_decode($jsonContent, true); // Decodificar el JSON a un array asociativo

    //         if ($productos === null) {
    //             return response()->json(['message' => 'Error al decodificar el JSON'], 500);
    //         }

    //         // Array para almacenar productos con imágenes en base64
    //         $productosConImagenes = [];

    //         // Recorrer los productos y buscar URLs en las columnas de imágenes
    //         foreach ($productos as $producto) {
    //             $sku = isset($producto['SKU']) ? $producto['SKU'] : 'SKU no disponible'; // Obtener el SKU o asignar valor por defecto
    //             $imagenesProducto = [];

    //             for ($i = 1; $i <= 15; $i++) {
    //                 $key = "Imagen $i";

    //                 // Asegurarse de que la URL no tenga espacios y sea válida
    //                 if (isset($producto[$key])) {
    //                     $urlImagen = trim($producto[$key]); // Eliminar espacios en blanco alrededor de la URL

    //                     if (filter_var($urlImagen, FILTER_VALIDATE_URL)) {
    //                         try {
    //                             // Obtener el contenido de la imagen desde el enlace
    //                             $imagenContenido = @file_get_contents($urlImagen);

    //                             if ($imagenContenido === false) {
    //                                 continue; // Saltar si no se puede obtener la imagen
    //                             }

    //                             // Codificar el contenido de la imagen en base64
    //                             $base64Imagen = base64_encode($imagenContenido);

    //                             // Obtener el tipo MIME de la imagen
    //                             $infoImagen = @getimagesize($urlImagen);
    //                             $tipoMime = $infoImagen['mime'];

    //                             // Formatear en "data:image/jpeg;base64, ..."
    //                             $base64ImagenConFormato = 'data:' . $tipoMime . ';base64,' . $base64Imagen;

    //                             // Agregar la imagen al array de imágenes del producto
    //                             $imagenesProducto[] = [
    //                                 'url' => $urlImagen,
    //                                 'base64' => $base64ImagenConFormato
    //                             ];
    //                         } catch (Exception $e) {
    //                             // Si ocurre algún error al convertir la imagen, continuar con la siguiente
    //                             continue;
    //                         }
    //                     }
    //                 }
    //             }

    //             // Si el producto tiene imágenes, agregarlo al array final
    //             if (!empty($imagenesProducto)) {
    //                 $productosConImagenes[] = [
    //                     'SKU' => $sku,
    //                     'imagenes' => $imagenesProducto
    //                 ];
    //             }
    //         }

    //         // Verificar si se encontraron productos con imágenes
    //         if (count($productosConImagenes) > 0) {
    //             return response()->json($productosConImagenes, 200);
    //         } else {
    //             return response()->json(['message' => 'No se encontraron imágenes válidas en el archivo'], 404);
    //         }
    //     } catch (Exception $e) {
    //         return response()->json(['message' => 'Error al procesar el archivo: ' . $e->getMessage()], 500);
    //     }
    // }

    // este devuelve solo la primera foto del archivoy no las n imagenes
    // public function imagenes_base64()
    // {
    //     try {
    //         // Ruta del archivo JSON en el NAS
    //         $jsonPath = "migracion_productos/productos_json.txt";

    //         // Verificar si el archivo JSON existe en el NAS
    //         if (!Storage::disk('nas')->exists($jsonPath)) {
    //             return response()->json(['message' => 'El archivo JSON no existe'], 404);
    //         }

    //         // Obtener el contenido del archivo JSON
    //         $jsonContent = Storage::disk('nas')->get($jsonPath);
    //         $productos = json_decode($jsonContent, true); // Decodificar el JSON a un array asociativo

    //         if ($productos === null) {
    //             return response()->json(['message' => 'Error al decodificar el JSON'], 500);
    //         }

    //         // Array para almacenar productos con imágenes en base64
    //         $productosConImagenes = [];

    //         // Recorrer los productos y buscar URLs en las columnas de imágenes
    //         foreach ($productos as $producto) {
    //             $sku = isset($producto['SKU']) ? $producto['SKU'] : 'SKU no disponible'; // Obtener el SKU o asignar valor por defecto
    //             $imagenesProducto = [];

    //             for ($i = 1; $i <= 15; $i++) {
    //                 $key = "Imagen $i";
    //                 if (isset($producto[$key]) && filter_var($producto[$key], FILTER_VALIDATE_URL)) {
    //                     try {
    //                         // Obtener el contenido de la imagen desde el enlace
    //                         $imagenContenido = @file_get_contents($producto[$key]);

    //                         if ($imagenContenido === false) {
    //                             continue; // Saltar si no se puede obtener la imagen
    //                         }

    //                         // Codificar el contenido de la imagen en base64
    //                         $base64Imagen = base64_encode($imagenContenido);

    //                         // Obtener el tipo MIME de la imagen
    //                         $infoImagen = @getimagesize($producto[$key]);
    //                         $tipoMime = $infoImagen['mime'];

    //                         // Formatear en "data:image/jpeg;base64, ..."
    //                         $base64ImagenConFormato = 'data:' . $tipoMime . ';base64,' . $base64Imagen;

    //                         // Agregar la imagen al array de imágenes del producto
    //                         $imagenesProducto[] = [
    //                             'url' => $producto[$key],
    //                             'base64' => $base64ImagenConFormato
    //                         ];
    //                     } catch (Exception $e) {
    //                         // Si ocurre algún error al convertir la imagen, continuar con la siguiente
    //                         continue;
    //                     }
    //                 }
    //             }

    //             // Si el producto tiene imágenes, agregarlo al array final
    //             if (!empty($imagenesProducto)) {
    //                 $productosConImagenes[] = [
    //                     'SKU' => $sku,
    //                     'imagenes' => $imagenesProducto
    //                 ];
    //             }
    //         }

    //         // Verificar si se encontraron productos con imágenes
    //         if (count($productosConImagenes) > 0) {
    //             return response()->json($productosConImagenes, 200);
    //         } else {
    //             return response()->json(['message' => 'No se encontraron imágenes válidas en el archivo'], 404);
    //         }
    //     } catch (Exception $e) {
    //         return response()->json(['message' => 'Error al procesar el archivo: ' . $e->getMessage()], 500);
    //     }
    // }


    public function imagenes_base64()
    {
        try {
            // Obtener los productos y sus imágenes de la base de datos
            $data = DB::SELECT("SELECT pro_id, pro_codigo, url_imagen FROM dashboard.at_producto_imagen");

            if (empty($data)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontraron productos', ''));
            }

            // Array para almacenar productos con imágenes en base64
            $productosConImagenes = [];

            // Recorrer los productos y buscar URLs de imágenes
            foreach ($data as $producto) {
                $ProId = $producto->pro_id; // Obtener el código del producto
                $codigoProducto = $producto->pro_codigo; // Obtener el código del producto
                $urlImagen = $producto->url_imagen; // Obtener la URL de la imagen

                // Validar si la URL de la imagen es válida
                if (filter_var($urlImagen, FILTER_VALIDATE_URL)) {
                    try {
                        // Obtener el contenido de la imagen desde el enlace
                        $imagenContenido = @file_get_contents($urlImagen);

                        if ($imagenContenido === false) {
                            continue; // Saltar si no se puede obtener la imagen
                        }

                        // Codificar el contenido de la imagen en base64
                        $base64Imagen = base64_encode($imagenContenido);

                        // Obtener el tipo MIME de la imagen
                        $infoImagen = @getimagesize($urlImagen);
                        $tipoMime = $infoImagen['mime'];

                        // Formatear en "data:image/jpeg;base64, ..."
                        $base64ImagenConFormato = 'data:' . $tipoMime . ';base64,' . $base64Imagen;


                        // Si el producto no está en el array, agregarlo con la primera imagen
                        $productosConImagenes[$codigoProducto] = [
                            'pro_id' => $ProId,
                            'pro_codigo' => $codigoProducto,
                            'base64' => $base64ImagenConFormato
                        ];

                    } catch (Exception $e) {
                        // Si ocurre algún error al convertir la imagen, continuar con la siguiente
                        continue;
                    }
                }
            }

            // Verificar si se encontraron productos con imágenes
            if (!empty($productosConImagenes)) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', array_values($productosConImagenes)));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontraron imágenes válidas', ''));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}

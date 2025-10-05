<?php

namespace App\Http\Controllers\demo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\demo\Demo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DemoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // ! metodo con validaciones y actualiza si ya existe un cliente
    public function addDatos(Request $request)
    {
        try {
            // Validar que el archivo esté presente
            $request->validate([
                'archivo' => 'required|file|mimes:xlsx,xls,csv'
            ]);

            $data = DB::transaction(function () use ($request) {
                $archivo = $request->file('archivo');

                // Cargar el archivo Excel
                $spreadsheet = IOFactory::load($archivo->getRealPath());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();

                // Validar que exista la primera fila (encabezados)
                if (empty($rows) || count($rows) < 1) {
                    throw new Exception('El archivo Excel está vacío');
                }

                // Definir las columnas requeridas
                $columnasRequeridas = ['identificacion', 'nombre', 'apellido'];
                $encabezados = array_map('strtolower', array_map('trim', $rows[0]));

                // Validar que todas las columnas requeridas existan
                $columnasFaltantes = [];
                foreach ($columnasRequeridas as $columna) {
                    if (!in_array(strtolower($columna), $encabezados)) {
                        $columnasFaltantes[] = $columna;
                    }
                }

                if (!empty($columnasFaltantes)) {
                    throw new Exception('Faltan las siguientes columnas en el archivo Excel: ' . implode(', ', $columnasFaltantes));
                }

                // Obtener índices de las columnas
                $indices = [];
                foreach ($columnasRequeridas as $columna) {
                    $indices[$columna] = array_search(strtolower($columna), $encabezados);
                }

                // PASO 2: Validar que no haya celdas vacías o con solo espacios
                $erroresVacios = [];
                foreach (array_slice($rows, 1) as $index => $row) {
                    $lineaExcel = $index + 2;
                    $camposVacios = [];

                    foreach ($columnasRequeridas as $columna) {
                        $valor = $row[$indices[$columna]] ?? null;
                        // Verificar si está vacío o solo tiene espacios en blanco
                        if ($valor === null || trim($valor) === '') {
                            $camposVacios[] = $columna;
                        }
                    }

                    if (!empty($camposVacios)) {
                        $erroresVacios[] = "Línea $lineaExcel: campos vacíos en [" . implode(', ', $camposVacios) . "]";
                    }
                }

                // Si hay campos vacíos, lanzar excepción
                if (!empty($erroresVacios)) {
                    throw new Exception('Se encontraron campos vacíos: ' . implode(' | ', $erroresVacios));
                }

                // PASO 3: Validar identificaciones duplicadas en el archivo
                $identificaciones = [];
                $duplicados = [];
                foreach (array_slice($rows, 1) as $index => $row) {
                    $lineaExcel = $index + 2; // +2 porque array_slice quita la primera fila y el index empieza en 0
                    $identificacion = trim($row[$indices['identificacion']]);

                    if (!empty($identificacion)) {
                        if (isset($identificaciones[$identificacion])) {
                            // Si ya existe, agregar ambas líneas a duplicados
                            if (!isset($duplicados[$identificacion])) {
                                $duplicados[$identificacion] = [$identificaciones[$identificacion]];
                            }
                            $duplicados[$identificacion][] = $lineaExcel;
                        } else {
                            $identificaciones[$identificacion] = $lineaExcel;
                        }
                    }
                }

                // Si hay duplicados, lanzar excepción con detalles
                if (!empty($duplicados)) {
                    $mensajeError = 'Se encontraron identificaciones duplicadas: ';
                    $detalles = [];
                    foreach ($duplicados as $identificacion => $lineas) {
                        $detalles[] = "Identificación '$identificacion' en las líneas: " . implode(', ', $lineas);
                    }
                    throw new Exception($mensajeError . implode(' | ', $detalles));
                }

                // Saltar la primera fila (encabezados) y procesar datos
                $registrosCreados = 0;
                $registrosActualizados = 0;

                foreach (array_slice($rows, 1) as $row) {
                    $identificacion = trim($row[$indices['identificacion']]);
                    $nombre = trim($row[$indices['nombre']]);
                    $apellido = trim($row[$indices['apellido']]);

                    // Buscar si ya existe un registro con esta identificación
                    $registroExistente = Demo::where('identificacion', $identificacion)->first();

                    if ($registroExistente) {
                        // Actualizar el registro existente
                        $registroExistente->update([
                            'nombre' => $nombre,
                            'apellido' => $apellido,
                        ]);
                        $registrosActualizados++;
                    } else {
                        // Crear nuevo registro
                        Demo::create([
                            'identificacion' => $identificacion,
                            'nombre' => $nombre,
                            'apellido' => $apellido,
                        ]);
                        $registrosCreados++;
                    }
                }

                $data = Demo::orderBy('id', 'desc')->get();

                return [
                    'registros' => $data,
                    'registrosCreados' => $registrosCreados,
                    'registrosActualizados' => $registrosActualizados,
                    'total' => $registrosCreados + $registrosActualizados
                ];
            });

            $mensaje = "Se procesaron {$data['total']} registros: {$data['registrosCreados']} creados, {$data['registrosActualizados']} actualizados";
            return response()->json(RespuestaApi::returnResultado('success', $mensaje, $data['registros']));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error: ' . $e->getMessage(), null));
        }
    }
}

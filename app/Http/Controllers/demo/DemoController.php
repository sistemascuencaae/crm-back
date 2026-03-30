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




    public function diferenciasFechasDynamoNovasoft()
    {
        try {
            // Obtener datos de PostgreSQL (Dynamo)
            $dataPostgres = DB::select("SELECT ddo_doctran, cuota, ddo_fechaven, cli_codigo, cliente, ddo_monto, saldo, estado_cuota FROM public.av_carterapagare_mes_anterior");

            // Obtener datos de Oracle (Novasoft)
            $dataOracle = DB::connection('oracle')
                ->select("SELECT ddo_doctran, cuota, ddo_fechaven, cli_codigo, cliente, ddo_monto, saldo, estado_cuota FROM stock.vt_cartera_espana_01");

            // Indexar PostgreSQL por ddo_doctran + cuota
            $pgMap = [];
            foreach ($dataPostgres as $row) {
                $key = trim($row->ddo_doctran) . '|' . trim($row->cuota);
                $pgMap[$key] = $row;
            }

            // Comparar Oracle contra PostgreSQL
            $diferencias = [];
            foreach ($dataOracle as $oraRow) {
                $key = trim($oraRow->ddo_doctran) . '|' . trim($oraRow->cuota);

                if (isset($pgMap[$key])) {
                    $pgRow = $pgMap[$key];
                    $fechaPg = substr(trim($pgRow->ddo_fechaven), 0, 10);
                    $fechaOra = substr(trim($oraRow->ddo_fechaven), 0, 10);

                    if ($fechaPg !== $fechaOra) {
                        $diferencias[] = [
                            'ddo_doctran' => trim($oraRow->ddo_doctran),
                            'cuota' => trim($oraRow->cuota),
                            'cli_codigo' => trim($oraRow->cli_codigo),
                            'cliente' => trim($oraRow->cliente),
                            'fecha_dynamo' => $fechaPg,
                            'fecha_novasoft' => $fechaOra,
                            // 'ddo_monto' => $oraRow->ddo_monto,
                            // 'saldo_dynamo' => $pgRow->saldo,
                            // 'saldo_novasoft' => $oraRow->saldo,
                            // 'estado_dynamo' => trim($pgRow->estado_cuota),
                            // 'estado_novasoft' => trim($oraRow->estado_cuota),
                        ];
                    }
                }
            }

            // Generar archivo Excel
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ];

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('1. Diferencias Fechas Venc');

            $encabezados = [
                'FACTURA',
                'CUOTA',
                'CLI_CODIGO',
                'CLIENTE',
                'FECHA VENC. DYNAMO',
                'FECHA VENC. NOVASOFT',
                // 'MONTO',
                // 'SALDO DYNAMO',
                // 'SALDO NOVASOFT',
                // 'ESTADO DYNAMO',
                // 'ESTADO NOVASOFT',
            ];

            foreach ($encabezados as $col => $encabezado) {
                $sheet->setCellValue([$col + 1, 1], $encabezado);
            }
            $sheet->getStyle([1, 1, count($encabezados), 1])->applyFromArray($headerStyle);

            $fila = 2;
            foreach ($diferencias as $dif) {
                $col = 1;
                foreach ($dif as $valor) {
                    $sheet->setCellValue([$col, $fila], $valor);
                    $col++;
                }
                $fila++;
            }

            foreach (range(1, count($encabezados)) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }

            // Segunda hoja: solo facturas (distinct)
            $sheetFacturas = $spreadsheet->createSheet();
            $sheetFacturas->setTitle('2. Resumen Solo Facturas');
            $sheetFacturas->setCellValue([1, 1], 'FACTURA');
            $sheetFacturas->getStyle([1, 1, 1, 1])->applyFromArray($headerStyle);

            $facturasUnicas = array_unique(array_column($diferencias, 'ddo_doctran'));
            sort($facturasUnicas);
            $filaFact = 2;
            foreach ($facturasUnicas as $factura) {
                $sheetFacturas->setCellValue([1, $filaFact], $factura);
                $filaFact++;
            }
            $sheetFacturas->getColumnDimensionByColumn(1)->setAutoSize(true);

            // Tercera hoja: cambios de fecha Novasoft
            if (!empty($facturasUnicas)) {
                $bindings = implode(',', array_fill(0, count($facturasUnicas), '?'));
                $dataCambiosFecha = DB::select(
                    "SELECT fecha, tipo_comprobante_fp, cod_comprobante_fp, valor_ws, saldo_ws, valor_fpc, saldo_fpc, valor_co, saldo_co, cod_persona, accion
                     FROM crm.aav_cambios_de_fecha_novasoft_materializada
                     WHERE cod_comprobante_fp IN ($bindings)",
                    array_values($facturasUnicas)
                );

                $sheetCambios = $spreadsheet->createSheet();
                $sheetCambios->setTitle('3. Formato CambioFecha Novasoft');

                // Definición de columnas: nombre de encabezado y tipo (text o number)
                $columnasCambios = [
                    'fecha' => ['label' => 'FECHA', 'tipo' => 'text'],
                    'tipo_comprobante_fp' => ['label' => 'TIPO_COMPROBANTE_FP', 'tipo' => 'text'],
                    'cod_comprobante_fp' => ['label' => 'COD_COMPROBANTE_FP', 'tipo' => 'text'],
                    'valor_ws' => ['label' => 'VALOR_WS', 'tipo' => 'number'],
                    'saldo_ws' => ['label' => 'SALDO_WS', 'tipo' => 'number'],
                    'valor_fpc' => ['label' => 'VALOR_FPC', 'tipo' => 'number'],
                    'saldo_fpc' => ['label' => 'SALDO_FPC', 'tipo' => 'number'],
                    'valor_co' => ['label' => 'VALOR_CO', 'tipo' => 'number'],
                    'saldo_co' => ['label' => 'SALDO_CO', 'tipo' => 'number'],
                    'cod_persona' => ['label' => 'COD_PERSONA', 'tipo' => 'text'],
                    'accion' => ['label' => 'ACCION', 'tipo' => 'text'],
                ];

                // Encabezados
                $colIdx = 1;
                foreach ($columnasCambios as $config) {
                    $sheetCambios->setCellValue([$colIdx, 1], $config['label']);
                    $colIdx++;
                }
                $sheetCambios->getStyle([1, 1, count($columnasCambios), 1])->applyFromArray($headerStyle);

                // Datos con formato
                $filaCambios = 2;
                foreach ($dataCambiosFecha as $row) {
                    $colIdx = 1;
                    foreach ($columnasCambios as $campo => $config) {
                        $valor = $row->$campo ?? '';
                        if ($config['tipo'] === 'text') {
                            $sheetCambios->setCellValueExplicit([$colIdx, $filaCambios], $valor, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        } else {
                            $sheetCambios->setCellValue([$colIdx, $filaCambios], is_numeric($valor) ? (float) $valor : $valor);
                            $sheetCambios->getStyle([$colIdx, $filaCambios])->getNumberFormat()
                                ->setFormatCode('0.00');
                        }
                        $colIdx++;
                    }
                    $filaCambios++;
                }

                foreach (range(1, count($columnasCambios)) as $col) {
                    $sheetCambios->getColumnDimensionByColumn($col)->setAutoSize(true);
                }

                // Cuarta hoja: facturas sin cambios de fecha (están en hoja 2 pero no en hoja 3)
                $facturasConCambios = array_unique(array_map(function ($row) {
                    return trim($row->cod_comprobante_fp);
                }, $dataCambiosFecha));

                $facturasSinCambios = array_diff($facturasUnicas, $facturasConCambios);
                sort($facturasSinCambios);

                $sheetSinCambios = $spreadsheet->createSheet();
                $sheetSinCambios->setTitle('4. Diferencias hojas 2 y 3');
                $sheetSinCambios->setCellValue([1, 1], 'FACTURA');
                $sheetSinCambios->getStyle([1, 1, 1, 1])->applyFromArray($headerStyle);

                $filaSC = 2;
                foreach ($facturasSinCambios as $factura) {
                    $sheetSinCambios->setCellValue([1, $filaSC], $factura);
                    $filaSC++;
                }
                $sheetSinCambios->getColumnDimensionByColumn(1)->setAutoSize(true);
            }

            // Guardar en archivo temporal y descargar
            $fileName = 'diferencias_dynamo_novasoft_' . date('Y_m_d_His') . '.xlsx';
            $tempPath = storage_path('app/public/' . $fileName);
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($tempPath);

            return response()->download($tempPath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.xml',
            ])->deleteFileAfterSend(true);
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }
}

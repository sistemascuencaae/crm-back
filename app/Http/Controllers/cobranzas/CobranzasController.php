<?php

namespace App\Http\Controllers\cobranzas;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CobranzasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function diferenciasFechasDynamoNovasoft()
    {
        try {
            // Obtener datos de PostgreSQL (Dynamo)
            $dataPostgres = DB::select("SELECT ddo_doctran, cuota, ddo_fechaven FROM public.av_carterapagare_mes_anterior");

            // Obtener datos de Oracle (Novasoft)
            $dataOracle = DB::connection('oracle')
                ->select("SELECT ddo_doctran, cuota, ddo_fechaven FROM stock.vt_cartera_espana_01");

            // Indexar PostgreSQL por ddo_doctran + cuota
            $pgMap = [];
            foreach ($dataPostgres as $row) {
                $key = trim($row->ddo_doctran) . '|' . trim($row->cuota);
                $pgMap[$key] = $row;
            }

            // Comparar Oracle contra PostgreSQL para obtener facturas con diferencias
            $facturasUnicas = [];
            foreach ($dataOracle as $oraRow) {
                $key = trim($oraRow->ddo_doctran) . '|' . trim($oraRow->cuota);

                if (isset($pgMap[$key])) {
                    $fechaPg = substr(trim($pgMap[$key]->ddo_fechaven), 0, 10);
                    $fechaOra = substr(trim($oraRow->ddo_fechaven), 0, 10);

                    if ($fechaPg !== $fechaOra) {
                        $facturasUnicas[trim($oraRow->ddo_doctran)] = true;
                    }
                }
            }

            $facturasUnicas = array_keys($facturasUnicas);
            sort($facturasUnicas);

            if (empty($facturasUnicas)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontraron diferencias de fechas entre Dynamo y Novasoft', ''));
            }

            // Consultar cambios de fecha Novasoft filtrados por facturas con diferencias
            $bindings = implode(',', array_fill(0, count($facturasUnicas), '?'));
            $dataCambiosFecha = DB::select(
                "SELECT fecha, tipo_comprobante_fp, cod_comprobante_fp, valor_ws, saldo_ws, valor_fpc, saldo_fpc, valor_co, saldo_co, cod_persona, accion
                    FROM crm.aav_cambios_de_fecha_novasoft_materializada
                    WHERE cod_comprobante_fp IN ($bindings)",
                array_values($facturasUnicas)
            );

            // Generar archivo Excel
            $spreadsheet = new Spreadsheet();
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ];

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Formato CambioFecha Novasoft');

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
                $sheet->setCellValue([$colIdx, 1], $config['label']);
                $colIdx++;
            }
            $sheet->getStyle([1, 1, count($columnasCambios), 1])->applyFromArray($headerStyle);

            // Datos con formato
            $fila = 2;
            foreach ($dataCambiosFecha as $row) {
                $colIdx = 1;
                foreach ($columnasCambios as $campo => $config) {
                    $valor = $row->$campo ?? '';
                    if ($config['tipo'] === 'text') {
                        $sheet->setCellValueExplicit([$colIdx, $fila], $valor, DataType::TYPE_STRING);
                    } else {
                        $sheet->setCellValue([$colIdx, $fila], is_numeric($valor) ? (float) $valor : $valor);
                        $sheet->getStyle([$colIdx, $fila])->getNumberFormat()
                            ->setFormatCode('0.00');
                    }
                    $colIdx++;
                }
                $fila++;
            }

            foreach (range(1, count($columnasCambios)) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
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

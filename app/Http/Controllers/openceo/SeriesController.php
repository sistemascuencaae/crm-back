<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SeriesController extends Controller
{
    private const MAX_FILAS = 5000;

    public function listTiposProductoDynamo()
    {
        try {
            $data = DB::select("SELECT tpr_id, tpr_nombre
                                FROM public.tipo_producto
                                WHERE tpr_activo = true
                                    AND tpr_reporta <> 209
                                ORDER BY tpr_nombre ASC");

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    public function listTiposConParametrosDynamo()
    {
        try {
            $data = DB::select("SELECT DISTINCT tp.tpr_id, tp.tpr_nombre
                                FROM public.tipo_producto tp
                                INNER JOIN public.parametros_tipo_producto ptp ON ptp.tpr_id = tp.tpr_id
                                    AND ptp.estado = true
                                WHERE tp.tpr_activo = true
                                    AND tp.tpr_reporta <> 209
                                ORDER BY tp.tpr_nombre ASC");

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    public function listProductosActivosByTprId($tpr_id)
    {
        try {
            if (!is_numeric($tpr_id) || (int) $tpr_id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'tpr_id invalido.', []), 422);
            }

            $data = DB::select("SELECT p.pro_id, CONCAT(p.pro_codigo, ' - ', p.pro_nombre) AS producto
                                FROM public.producto p
                                WHERE p.pro_activo = true
                                    AND p.tpr_id = ?
                                ORDER BY p.pro_codigo ASC", [(int) $tpr_id]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    public function listLotesSeries()
    {
        try {
            $data = DB::select("SELECT
                                    s.codigo_lote,
                                    concat(u.usu_alias, ' - ', u.name, ' ', u.surname) AS usuario,
                                    tp.tpr_nombre,
                                    s.observacion,
                                    COUNT(*) AS total_registros,
                                    TO_CHAR(MIN(s.created_at), 'DD/MM/YYYY HH24:MI') AS fecha
                                FROM public.series s
                                    LEFT JOIN crm.users u ON u.id = s.user_id
                                    LEFT JOIN public.tipo_producto tp ON tp.tpr_id = s.tpr_id
                                GROUP BY s.codigo_lote, u.usu_alias, u.name, u.surname, tp.tpr_nombre, s.observacion
                                ORDER BY MIN(s.created_at) DESC");

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    // Devuelve el array de columnas en el orden del parametros_tipo_producto
    private function columnasDelTipoProducto(int $tprId): array
    {
        $row = DB::select("SELECT string_to_array(replace(replace(replace(valor, '[', ''), ']', ''), '''', ''), ',') AS columnas_array
                            FROM public.parametros_tipo_producto
                            WHERE tpr_id = ?
                            ORDER BY estado DESC, id DESC
                            LIMIT 1", [$tprId]);

        if (empty($row)) {
            return [];
        }

        return $this->pgArrayToPhp($row[0]->columnas_array ?? '{}');
    }

    public function getSeriesByLote($codigo_lote)
    {
        try {
            $codigoLote = trim((string) $codigo_lote);

            if ($codigoLote === '' || !preg_match('/^[0-9a-fA-F\-]{36}$/', $codigoLote)) {
                return response()->json(RespuestaApi::returnResultado('error', 'codigo_lote invalido.', []), 422);
            }

            // tpr_id del lote (todas las filas comparten el mismo).
            $cab = DB::select("SELECT s.id, s.tpr_id, tp.tpr_nombre, s.observacion
                                FROM public.series s
                                LEFT JOIN public.tipo_producto tp ON tp.tpr_id = s.tpr_id
                                WHERE s.codigo_lote = ?
                                LIMIT 1", [$codigoLote]);

            if (empty($cab)) {
                return response()->json(RespuestaApi::returnResultado('error', 'El lote no existe.', []), 404);
            }

            $tprId = (int) $cab[0]->tpr_id;
            $columnas = $this->columnasDelTipoProducto($tprId);
            $totalCol = count($columnas);

            // Encabezado: campo1..campoN -> nombre real del parametro.
            $encabezado = [];
            for ($i = 0; $i < $totalCol; $i++) {
                $encabezado[] = [
                    'campo' => 'campo' . ($i + 1),
                    'titulo' => $columnas[$i],
                ];
            }

            // // Traer las filas del lote con el nombre del producto.
            // $filas = DB::select("SELECT s.*, CONCAT(p.pro_codigo, ' - ', p.pro_nombre) AS producto_nombre
            //                     FROM public.series s
            //                     LEFT JOIN public.producto p ON p.pro_id = s.pro_id
            //                     WHERE s.codigo_lote = ?
            //                     ORDER BY s.id ASC", [$codigoLote]);

            // Traer las filas del lote con el nombre del producto.
            $filas = DB::select("SELECT
                                    s.*, CONCAT(p.pro_codigo, ' - ', p.pro_nombre) AS producto_nombre,
                                    CASE
                                        WHEN cf.cfa_id IS NOT NULL
                                        THEN CONCAT(cf.cfa_periodo, '-', cti.cti_sigla, '-', alm.alm_codigo, '-', pve.pve_numero, '-', cf.cfa_numero) ELSE NULL END AS factura
                                FROM public.series s
                                    LEFT JOIN public.producto p ON p.pro_id = s.pro_id
                                    LEFT join cfactura cf on s.cfa_id = cf.cfa_id
                                    LEFT join ccomproba ccm ON ccm.pve_id = cf.pve_id 
                                        AND ccm.ccm_periodo = cf.cfa_periodo 
                                        AND ccm.cti_id = cf.cti_id 
                                        AND ccm.ccm_numero = cf.cfa_numero
                                    LEFT JOIN puntoventa pve ON pve.pve_id = cf.pve_id
                                    LEFT JOIN almacen alm ON alm.alm_id = pve.alm_id
                                    LEFT JOIN ctipocom cti ON cti.cti_id = cf.cti_id
                                WHERE s.codigo_lote = ?
                                ORDER BY s.id ASC;", [$codigoLote]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito.', [
                'encabezado' => $encabezado,
                'filas' => $filas,
                'lote' => [
                    'codigo_lote' => $codigoLote,
                    'tpr_id' => $tprId,
                    'tpr_nombre' => $cab[0]->tpr_nombre,
                    'observacion' => $cab[0]->observacion,
                ],
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }


    public function downloadLoteSeries($codigo_lote)
    {
        try {
            $codigoLote = trim((string) $codigo_lote);

            if ($codigoLote === '' || !preg_match('/^[0-9a-fA-F\-]{36}$/', $codigoLote)) {
                return response()->json(RespuestaApi::returnResultado('error', 'codigo_lote invalido.', []), 422);
            }

            $cab = DB::select("SELECT tpr_id FROM public.series WHERE codigo_lote = ? LIMIT 1", [$codigoLote]);

            if (empty($cab)) {
                return response()->json(RespuestaApi::returnResultado('error', 'El lote no existe.', []), 404);
            }

            $tprId = (int) $cab[0]->tpr_id;
            $columnas = $this->columnasDelTipoProducto($tprId);
            $totalCol = count($columnas);

            $filas = DB::select("SELECT s.*, CONCAT(p.pro_codigo, ' - ', p.pro_nombre) AS producto_nombre
                                FROM public.series s
                                LEFT JOIN public.producto p ON p.pro_id = s.pro_id
                                WHERE s.codigo_lote = ?
                                ORDER BY s.id ASC", [$codigoLote]);

            $spreadsheet = new Spreadsheet();
            $hoja = $spreadsheet->getActiveSheet();

            // PhpSpreadsheet: setCellValue([col, row], valor).
            // Encabezados: columnas del parametro + Producto + Fecha.
            $colIdx = 1;
            for ($i = 0; $i < $totalCol; $i++) {
                $hoja->setCellValue([$colIdx++, 1], (string) $columnas[$i]);
            }

            $hoja->setCellValue([$colIdx++, 1], 'Producto');
            $hoja->setCellValue([$colIdx, 1], 'Fecha');

            // Filas de datos.
            $fila = 2;
            foreach ($filas as $r) {
                $c = 1;
                for ($i = 0; $i < $totalCol; $i++) {
                    $key = 'campo' . ($i + 1);
                    $hoja->setCellValue([$c++, $fila], (string) ($r->$key ?? ''));
                }
                $hoja->setCellValue([$c++, $fila], (string) ($r->producto_nombre ?? ''));
                $hoja->setCellValue([$c, $fila], (string) ($r->created_at ?? ''));
                $fila++;
            }

            $nombreArchivo = 'lote_' . substr($codigoLote, 0, 8) . '.xlsx';
            $writer = new Xlsx($spreadsheet);

            // Generar el binario en un buffer DENTRO del try para que cualquier
            // error quede capturado (streamDownload se ejecuta fuera del try/catch).
            ob_start();
            $writer->save('php://output');
            $contenido = ob_get_clean();

            // Liberar memoria del spreadsheet.
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);

            return response($contenido, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
                'Content-Length' => strlen($contenido),
            ]);
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    public function deleteLoteSeries($codigo_lote)
    {
        try {
            $codigoLote = trim((string) $codigo_lote);

            // Validacion basica del formato UUID (proteccion contra valores arbitrarios).
            if ($codigoLote === '' || !preg_match('/^[0-9a-fA-F\-]{36}$/', $codigoLote)) {
                return response()->json(RespuestaApi::returnResultado('error', 'codigo_lote invalido.', []), 422);
            }

            // Verificar que el lote exista antes de intentar borrar.
            $existe = DB::select("SELECT 1 AS ok FROM public.series WHERE codigo_lote = ? LIMIT 1", [$codigoLote]);

            if (empty($existe)) {
                return response()->json(RespuestaApi::returnResultado('error', 'El lote no existe o ya fue eliminado.', []), 404);
            }

            // Buscar facturas relacionadas
            $facturasRelacionadas = DB::select("SELECT
                                                    CONCAT(cf.cfa_periodo, '-', cti.cti_sigla, '-', alm.alm_codigo, '-', pve.pve_numero, '-', cf.cfa_numero) AS factura,
                                                    CONCAT_WS(
                                                        ' || ',
                                                        NULLIF(sr.campo1, ''), NULLIF(sr.campo2, ''), NULLIF(sr.campo3, ''),
                                                        NULLIF(sr.campo4, ''), NULLIF(sr.campo5, ''), NULLIF(sr.campo6, ''),
                                                        NULLIF(sr.campo7, ''), NULLIF(sr.campo8, ''), NULLIF(sr.campo9, ''),
                                                        NULLIF(sr.campo10, ''), NULLIF(sr.campo11, ''), NULLIF(sr.campo12, ''),
                                                        NULLIF(sr.campo13, ''), NULLIF(sr.campo14, ''), NULLIF(sr.campo15, ''),
                                                        NULLIF(sr.campo16, ''), NULLIF(sr.campo17, ''), NULLIF(sr.campo18, ''),
                                                        NULLIF(sr.campo19, ''), NULLIF(sr.campo20, ''), NULLIF(sr.campo21, ''),
                                                        NULLIF(sr.campo22, ''), NULLIF(sr.campo23, ''), NULLIF(sr.campo24, ''),
                                                        NULLIF(sr.campo25, ''), NULLIF(sr.campo26, ''), NULLIF(sr.campo27, ''),
                                                        NULLIF(sr.campo28, ''), NULLIF(sr.campo29, ''), NULLIF(sr.campo30, '')
                                                    ) AS registro_afectado
                                                FROM cfactura cf
                                                    JOIN ccomproba ccm ON ccm.pve_id = cf.pve_id
                                                        AND ccm.ccm_periodo = cf.cfa_periodo
                                                        AND ccm.cti_id = cf.cti_id
                                                        AND ccm.ccm_numero = cf.cfa_numero
                                                    JOIN puntoventa pve ON pve.pve_id = cf.pve_id
                                                    JOIN almacen alm ON alm.alm_id = pve.alm_id
                                                    JOIN ctipocom cti ON cti.cti_id = cf.cti_id
                                                    JOIN series sr ON sr.cfa_id = cf.cfa_id
                                                WHERE sr.codigo_lote = ?
                                                    AND sr.cfa_id IS NOT NULL", [$codigoLote]);

            // Si existen facturas relacionadas, bloquear eliminacion
            if (!empty($facturasRelacionadas)) {
                return response()->json(
                    RespuestaApi::returnResultado(
                        'error',
                        'No se puede eliminar el lote porque existen registros asociadas a facturas',
                        [
                            'total_facturas' => count($facturasRelacionadas),
                            'facturas' => $facturasRelacionadas
                        ]
                    ),
                    409
                );
            }

            // Borrado dentro de transaccion. delete() devuelve el numero de filas afectadas.
            $eliminados = 0;
            DB::transaction(function () use ($codigoLote, &$eliminados) {
                $eliminados = DB::table('series')->where('codigo_lote', $codigoLote)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', "Se elimino el lote ($eliminados registros).", [
                'eliminados'  => $eliminados,
                'codigo_lote' => $codigoLote,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    public function validarColumnasProducto(Request $request)
    {
        try {
            $tprId = $request->input('tpr_id');
            $headers = $request->input('headers');

            // Validacion de inputs basicos.
            if (!is_numeric($tprId) || (int) $tprId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'tpr_id es requerido.', []), 422);
            }

            if (!is_array($headers) || empty($headers)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Los headers del Excel son requeridos.', []), 422);
            }

            // 1) Buscar el parametro ACTIVO definido para este tpr_id y extraer su array.
            $parametroInfo = DB::select("SELECT string_to_array(replace(replace(replace(valor, '[', ''), ']', ''), '''', ''), ',') AS columnas_array
                                        FROM public.parametros_tipo_producto
                                        WHERE tpr_id = ? AND estado = true
                                        ORDER BY id DESC
                                        LIMIT 1", [(int) $tprId]);

            // Si no hay parametro ACTIVO para este tipo, no se puede validar nada.
            if (empty($parametroInfo)) {
                return response()->json(RespuestaApi::returnResultado(
                    'error',
                    'El parametro para este tipo de producto no existe. Por favor comuniquese con el administrador.',
                    ['sin_parametro' => true]
                ), 422);
            }

            // Convertir el array nativo de PostgreSQL ("{a,b,c}") a array PHP limpio.
            $columnasParamRaw = $parametroInfo[0]->columnas_array ?? '{}';
            $ordenColumnas = $this->pgArrayToPhp($columnasParamRaw);

            // Normalizar (trim) cada header recibido para que las comparaciones sean fiables.
            $headersClean = array_values(array_map(fn($h) => trim((string) $h), $headers));
            $totalEsperado = count($ordenColumnas);
            $totalRecibido = count($headersClean);

            // 2) La cantidad de columnas debe coincidir exacto con el parametro.
            if ($totalEsperado > 0 && $totalRecibido !== $totalEsperado) {
                return response()->json(RespuestaApi::returnResultado(
                    'error',
                    "El parametro espera $totalEsperado columna(s) pero el Excel tiene $totalRecibido. Corrige el archivo y vuelve a subirlo.",
                    [
                        'cantidad_incorrecta' => true,
                        'total_esperado'     => $totalEsperado,
                        'total_recibido'     => $totalRecibido,
                    ]
                ), 422);
            }

            // 3) Validar nombres: para cada header recibido verificar que exista
            //    en $ordenColumnas (case-insensitive). Antes esto se hacia con
            //    la funcion SQL crm.validar_columnas_producto, pero esa funcion
            //    no esta presente en todos los entornos (faltaba en produccion
            //    y rompia la validacion). Hacerlo en PHP elimina la dependencia
            //    de un objeto de BD que puede no estar deployado.
            $ordenLowerSet = array_flip(array_map(
                fn($c) => strtolower(trim((string) $c)),
                $ordenColumnas
            ));

            $resultado = [];
            $noExisten = [];
            foreach ($headersClean as $h) {
                $existe = isset($ordenLowerSet[strtolower($h)]);
                $resultado[] = (object) ['nombre' => $h, 'estado' => $existe ? 'OK' : 'NO EXISTE'];
                if (!$existe) {
                    $noExisten[] = $h;
                }
            }

            if (!empty($noExisten)) {
                return response()->json(RespuestaApi::returnResultado(
                    'error',
                    'Las siguientes columnas no existen para el tipo de producto seleccionado: ' . implode(', ', $noExisten),
                    [
                        'validacion' => $resultado,
                        'columnasNoExisten' => $noExisten,
                    ]
                ), 422);
            }

            // Exito: devolvemos tambien el orden correcto para que el frontend reordene el preview.
            return response()->json(RespuestaApi::returnResultado('success', 'Todas las columnas son validas.', [
                'validacion' => $resultado,
                'orden_columnas' => $ordenColumnas,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    public function chequearCampo1Existentes(Request $request)
    {
        try {
            $campo1 = $request->input('campo1');

            if (!is_array($campo1)) {
                return response()->json(RespuestaApi::returnResultado('error', 'El campo "campo1" debe ser un array.', []), 422);
            }

            // Normalizar: trim, descartar vacios y deduplicar antes de consultar.
            $valores = [];
            foreach ($campo1 as $v) {
                $s = trim((string) $v);
                if ($s !== '') {
                    $valores[$s] = true;
                }
            }
            $valores = array_keys($valores);

            // Si no quedo nada que consultar, salida temprana.
            if (empty($valores)) {
                return response()->json(RespuestaApi::returnResultado('success', 'Sin valores a chequear.', [
                    'existentes' => [],
                ]));
            }

            // Consultar en chunks para no exceder el limite de parametros de Postgres.
            $existentes = [];
            foreach (array_chunk($valores, 1000) as $chunk) {
                $ph = rtrim(str_repeat('?,', count($chunk)), ',');

                $rows = DB::select("SELECT DISTINCT campo1 FROM public.series WHERE campo1 IN ($ph)", $chunk);

                foreach ($rows as $r) {
                    $existentes[] = (string) $r->campo1;
                }
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se chequeo con exito.', [
                'existentes' => array_values(array_unique($existentes)),
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    public function downloadPlantillaSeries($tpr_id)
    {
        try {
            if (!is_numeric($tpr_id) || (int) $tpr_id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'tpr_id invalido.', []), 422);
            }
            $tprId = (int) $tpr_id;

            // Obtener nombre del tipo + las columnas del parametro ACTIVO.
            // LEFT JOIN para distinguir "tipo no existe" de "tipo sin parametro".
            $cab = DB::select(
                "SELECT tp.tpr_nombre,
                            string_to_array(replace(replace(replace(ptp.valor, '[', ''), ']', ''), '''', ''), ',') AS columnas_array
                            FROM public.tipo_producto tp
                            LEFT JOIN public.parametros_tipo_producto ptp ON ptp.tpr_id = tp.tpr_id
                                AND ptp.estado = true
                            WHERE tp.tpr_id = ?
                            ORDER BY ptp.id DESC
                            LIMIT 1",
                [$tprId]
            );

            if (empty($cab)) {
                return response()->json(RespuestaApi::returnResultado('error', 'El tipo de producto no existe.', []), 404);
            }

            if (empty($cab[0]->columnas_array)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Este tipo no tiene parametros activos definidos.', []), 422);
            }

            $tprNombre = (string) $cab[0]->tpr_nombre;
            $columnas = $this->pgArrayToPhp((string) $cab[0]->columnas_array);
            $totalCol = count($columnas);

            if ($totalCol === 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'El parametro de este tipo esta vacio.', []), 422);
            }

            // Construir el spreadsheet.
            $spreadsheet = new Spreadsheet();
            $hoja = $spreadsheet->getActiveSheet();
            $hoja->setTitle('Plantilla');

            // Letra de la ultima columna (ej. totalCol=19 -> 'S') para los ranges.
            $ultLetra = Coordinate::stringFromColumnIndex($totalCol);

            // 1) Escribir headers en fila 1.
            for ($i = 0; $i < $totalCol; $i++) {
                $hoja->setCellValue([$i + 1, 1], (string) $columnas[$i]);
            }

            // 2) Formato TEXTO en TODO el rango util de la plantilla.
            $ultimaFila = self::MAX_FILAS + 1;
            $hoja->getStyle("A1:{$ultLetra}{$ultimaFila}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);

            // 3) Estilo del header: negrita + fondo gris claro + centrado.
            $estiloHeader = $hoja->getStyle("A1:{$ultLetra}1");
            $estiloHeader->getFont()->setBold(true)->setSize(11);
            $estiloHeader->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
            $estiloHeader->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // 4) Congelar la fila de headers para que siga visible al hacer scroll.
            $hoja->freezePane('A2');

            // 5) Ancho automatico por columna.
            for ($i = 1; $i <= $totalCol; $i++) {
                $letra = Coordinate::stringFromColumnIndex($i);
                $hoja->getColumnDimension($letra)->setAutoSize(true);
            }

            // Generar el binario en buffer (mismo patron que downloadLoteSeries).
            $writer = new Xlsx($spreadsheet);
            ob_start();
            $writer->save('php://output');
            $contenido = ob_get_clean();
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);

            // Nombre seguro: reemplazar cualquier caracter raro por "_".
            $nombreLimpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', $tprNombre);
            $nombreArchivo = 'plantilla_' . ($nombreLimpio !== '' ? $nombreLimpio : ('tpr_' . $tprId)) . '.xlsx';

            return response($contenido, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
                'Content-Length' => strlen($contenido),
            ]);
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    // Convierte el literal de array nativo de PostgreSQL a array PHP.
    // Ejemplos: "{a,b,c}" o "{\"a\",\"b,c\"}" -> ['a','b','c'] / ['a','b,c']
    private function pgArrayToPhp(string $pgArray): array
    {
        $s = trim($pgArray);
        if ($s === '' || $s === '{}') return [];

        // Quitar las llaves de inicio/fin del literal pg.
        if ($s[0] === '{') $s = substr($s, 1);
        if (substr($s, -1) === '}') $s = substr($s, 0, -1);

        // Parsear como CSV (respeta comillas dobles y backslash como escape).
        $values = str_getcsv($s, ',', '"', '\\');

        // Trim de cada valor y descartar vacios.
        return array_values(array_filter(
            array_map(fn($v) => trim((string) $v), $values),
            fn($v) => $v !== ''
        ));
    }

    public function previewExcelSeries(Request $request)
    {
        try {
            // No permitir array de archivos: solo UN archivo en el campo "archivo".
            $rawArchivo = $request->file('archivo');
            if (is_array($rawArchivo)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Solo se permite UN archivo en el campo "archivo".', []), 422);
            }

            // Validacion estandar de Laravel: tipo de archivo y tamano maximo (10 MB).
            $validator = Validator::make(
                $request->all(),
                ['archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240'],
                [
                    'archivo.required' => 'Debes adjuntar un archivo en el campo "archivo".',
                    'archivo.file' => 'El campo "archivo" debe ser un archivo valido.',
                    'archivo.mimes' => 'El archivo debe ser .xlsx, .xls o .csv.',
                    'archivo.max' => 'El archivo no puede exceder 10 MB.',
                ]
            );

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first('archivo'), ['errores' => $validator->errors()->all()]), 422);
            }

            $archivo = $request->file('archivo');

            // Verificacion adicional: que el archivo se haya subido sin corrupcion.
            if (!$archivo->isValid()) {
                return response()->json(RespuestaApi::returnResultado('error', 'El archivo no se subio correctamente. Vuelve a intentarlo.', []), 422);
            }

            // Archivos de 0 bytes no son procesables.
            if ($archivo->getSize() === 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'El archivo esta vacio.', []), 422);
            }

            // Cargar el archivo con PhpSpreadsheet y convertir la hoja activa a array PHP.
            // Parametros de toArray:
            //   - null: valor para celdas vacias.
            //   - true: incluir celdas vacias (necesario para que cuadre el indice).
            //   - false: NO formatear (importante para que las fechas no se reordenen segun locale).
            //   - false: NO usar referencias de celda (devuelve indices 0..N).
            $spreadsheet = IOFactory::load($archivo->getRealPath());
            $hoja = $spreadsheet->getActiveSheet();
            $filas = $hoja->toArray(null, true, false, false);

            // Debe haber al menos encabezado (fila 1) + 1 fila de datos.
            if (count($filas) < 2) {
                return response()->json(RespuestaApi::returnResultado('error', 'El archivo no contiene filas de datos.', []), 422);
            }

            // Extraer la fila de encabezado del array (queda $filas con solo los datos).
            $encabezadoRaw = array_shift($filas);
            $totalColumnas = count($encabezadoRaw);

            // Construir el array de encabezado: [{campo: 'campo1', titulo: 'Marca'}, ...]
            // Si el titulo viene vacio se pone "Columna N" como fallback.
            $encabezado = [];
            for ($i = 0; $i < $totalColumnas; $i++) {
                $titulo = trim((string) ($encabezadoRaw[$i] ?? ''));
                $encabezado[] = [
                    'campo'  => 'campo' . ($i + 1),
                    'titulo' => $titulo !== '' ? $titulo : 'Columna ' . ($i + 1),
                ];
            }

            // Procesar cada fila de datos: saltar las completamente vacias y
            // RECHAZAR el archivo si excede el limite (en vez de truncar en
            // silencio, que es peligroso porque el usuario no se entera de
            // que se perdieron datos).
            $datos = [];
            $excedeLimite = false;
            foreach ($filas as $fila) {
                if ($this->filaVacia($fila)) {
                    continue;
                }
                // Si ya tenemos MAX_FILAS Y aparece otra fila no vacia,
                // el archivo excede el tope -> marcar y salir.
                if (count($datos) >= self::MAX_FILAS) {
                    $excedeLimite = true;
                    break;
                }
                $row = [];
                for ($i = 0; $i < $totalColumnas; $i++) {
                    $valor = $fila[$i] ?? null;
                    // Convertir cada valor a string limpio, manteniendo nulls intactos.
                    $row['campo' . ($i + 1)] = $valor === null ? null : trim((string) $valor);
                }
                $datos[] = $row;
            }

            if ($excedeLimite) {
                return response()->json(RespuestaApi::returnResultado(
                    'error',
                    'El archivo excede el limite de ' . self::MAX_FILAS . ' filas con datos. Divide el archivo en varios y vuelve a subirlos.',
                    []
                ), 422);
            }

            // Si despues de filtrar filas vacias no queda nada, error.
            if (empty($datos)) {
                return response()->json(RespuestaApi::returnResultado('error', 'El archivo no contiene filas con datos.', []), 422);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Preview generado correctamente.', [
                'encabezado' => $encabezado,
                'filas' => $datos,
                'totalFilas' => count($datos),
                'totalColumnas' => $totalColumnas,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    public function addSeriesProductos(Request $request)
    {
        try {
            // 1) Validaciones de inputs
            $filas = $request->input('filas');

            if (!is_array($filas) || empty($filas)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No hay filas para guardar.', []), 422);
            }

            // Limite por lote
            if (count($filas) > self::MAX_FILAS) {
                return response()->json(RespuestaApi::returnResultado('error', 'El lote excede el limite de ' . self::MAX_FILAS . ' filas.', []), 422);
            }

            $userId = auth()->id();
            if (!$userId) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se pudo identificar al usuario autenticado.', []), 401);
            }

            // Observacion es OBLIGATORIA y maximo 255 caracteres (igual que en BD).
            $observacion = trim((string) $request->input('observacion', ''));

            if ($observacion === '') {
                return response()->json(RespuestaApi::returnResultado('error', 'La observacion es requerida.', []), 422);
            }

            if (mb_strlen($observacion) > 255) {
                return response()->json(RespuestaApi::returnResultado('error', 'La observacion no puede exceder 255 caracteres.', []), 422);
            }

            // tpr_id es OBLIGATORIO y debe ser numerico positivo.
            $tprId = $request->input('tpr_id');
            if (!is_numeric($tprId) || (int) $tprId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'tpr_id es requerido.', []), 422);
            }
            $tprId = (int) $tprId;

            // 2) Defense-in-depth: validar pro_id vs tpr_id
            // El frontend filtra productos por tpr_id, pero un usuario malicioso podria
            // manipular el JSON. Aqui validamos contra la BD que todos los pro_id
            // realmente pertenezcan al tipo seleccionado.

            // Recolectar pro_ids UNICOS de todas las filas (sin duplicados).
            $proIdsRecibidos = [];
            foreach ($filas as $fila) {
                if (!is_array($fila)) continue;
                $pid = $fila['pro_id'] ?? null;
                if (is_numeric($pid) && (int) $pid > 0) {
                    $proIdsRecibidos[(int) $pid] = true;
                }
            }

            $proIdsRecibidos = array_keys($proIdsRecibidos);

            if (!empty($proIdsRecibidos)) {
                // UNA sola query para validar todos los IDs (independiente del numero de filas).
                $placeholders = rtrim(str_repeat('?,', count($proIdsRecibidos)), ',');
                $rowsValidos = DB::select("SELECT pro_id FROM public.producto
                                            WHERE pro_activo = true AND tpr_id = ? AND pro_id IN ($placeholders)", array_merge([$tprId], $proIdsRecibidos));

                $proIdsValidos = array_map(fn($r) => (int) $r->pro_id, $rowsValidos);
                $proIdsInvalidos = array_values(array_diff($proIdsRecibidos, $proIdsValidos));

                // Si hay alguno que no esta en la BD para ese tipo -> rechazar todo.
                if (!empty($proIdsInvalidos)) {
                    return response()->json(RespuestaApi::returnResultado(
                        'error',
                        'Hay productos que no pertenecen al tipo seleccionado o estan inactivos: ' . implode(', ', $proIdsInvalidos),
                        ['proIdsInvalidos' => $proIdsInvalidos]
                    ), 422);
                }
            }

            // 3) Generar identificadores del lote
            $codigoLote = (string) Str::uuid();

            // Forzar timezone de Ecuador para que created_at/updated_at queden consistentes.
            date_default_timezone_set('America/Guayaquil');
            $now = Carbon::now()->toDateTimeString();

            // 4) Construir registros a insertar
            $insertar = [];
            $filasSinProducto = 0;
            foreach ($filas as $fila) {
                if (!is_array($fila)) {
                    continue;
                }

                // Filas sin pro_id valido se descartan (se contabilizan para el mensaje final).
                $proId = $fila['pro_id'] ?? null;
                if (!is_numeric($proId) || (int) $proId <= 0) {
                    $filasSinProducto++;
                    continue;
                }

                // Datos comunes para cada registro del lote.
                $registro = [
                    'codigo_lote' => $codigoLote,
                    'user_id' => $userId,
                    'tpr_id' => $tprId,
                    'pro_id' => (int) $proId,
                    'observacion' => $observacion,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Copiar los campos campo1..campoN dinamicamente (whitelist con regex).
                // Esto evita inyectar columnas arbitrarias desde el frontend.
                $tieneValor = false;
                foreach ($fila as $key => $valor) {
                    if (!is_string($key) || !preg_match('/^campo\d+$/', $key)) {
                        continue;
                    }
                    if ($valor !== null && $valor !== '') {
                        $registro[$key] = (string) $valor;
                        $tieneValor = true;
                    }
                }

                // Solo agregar la fila si tiene al menos un campo con valor.
                if ($tieneValor) {
                    $insertar[] = $registro;
                }
            }

            // Si despues de filtrar no hay nada que insertar, error con mensaje claro.
            if (empty($insertar)) {
                $msg = $filasSinProducto > 0
                    ? 'Ninguna fila tiene producto asignado (pro_id).'
                    : 'Todas las filas estan vacias.';
                return response()->json(RespuestaApi::returnResultado('error', $msg, []), 422);
            }

            // 4.5) Unicidad de campo1 (global en toda la tabla series)
            $omitidosDuplicados = []; // valores de campo1 omitidos por duplicado
            $omitidosSinCampo1 = 0; // filas omitidas por campo1 vacio
            $vistosEnArchivo = []; // set para deduplicar dentro del archivo

            // a) Recolectar los campo1 NO vacios de las filas a insertar.
            $campo1Recibidos = [];
            foreach ($insertar as $reg) {
                $c1 = isset($reg['campo1']) ? trim((string) $reg['campo1']) : '';
                if ($c1 !== '') {
                    $campo1Recibidos[$c1] = true;
                }
            }
            $campo1Recibidos = array_keys($campo1Recibidos);

            // b) Consultar cuales de esos campo1 YA existen en public.series.
            // En chunks para no exceder el limite de parametros de Postgres.
            $existentesEnBd = [];
            foreach (array_chunk($campo1Recibidos, 1000) as $chunk) {
                $ph = rtrim(str_repeat('?,', count($chunk)), ',');
                $rows = DB::select("SELECT DISTINCT campo1 FROM public.series WHERE campo1 IN ($ph)", $chunk);

                foreach ($rows as $r) {
                    $existentesEnBd[(string) $r->campo1] = true;
                }
            }

            // c) Filtrar: omitir filas con campo1 vacio (NOT NULL en BD) y las
            // que ya existen en BD o se repiten dentro de este mismo archivo.
            $insertarFiltrado = [];
            foreach ($insertar as $reg) {
                $c1 = isset($reg['campo1']) ? trim((string) $reg['campo1']) : '';
                if ($c1 === '') {
                    $omitidosSinCampo1++;
                    continue;
                }
                if (isset($existentesEnBd[$c1]) || isset($vistosEnArchivo[$c1])) {
                    $omitidosDuplicados[] = $c1;
                    continue;
                }
                $vistosEnArchivo[$c1] = true;
                $insertarFiltrado[]   = $reg;
            }
            $insertar = $insertarFiltrado;
            $omitidosDuplicados = array_values(array_unique($omitidosDuplicados));
            $totalDuplicados = count($omitidosDuplicados);

            // Si tras filtrar no queda nada, error explicando el por que.
            if (empty($insertar)) {
                $partes = [];
                if ($totalDuplicados > 0)   $partes[] = "$totalDuplicados con valor ya existente";
                if ($omitidosSinCampo1 > 0) $partes[] = "$omitidosSinCampo1 con valor vacio";
                $detalle = empty($partes) ? '' : ' (' . implode(', ', $partes) . ')';
                return response()->json(RespuestaApi::returnResultado(
                    'error',
                    'No se guardo nada: todas las filas fueron descartadas' . $detalle . '.',
                    [
                        'omitidosDuplicados' => $omitidosDuplicados,
                        'totalDuplicados' => $totalDuplicados,
                        'omitidosSinCampo1' => $omitidosSinCampo1,
                    ]
                ), 422);
            }

            // 5) Insertar en transaccion de 500 filas
            $insertados = 0;
            DB::transaction(function () use ($insertar, &$insertados) {
                foreach (array_chunk($insertar, 500) as $chunk) {
                    $insertados += DB::table('series')->insertOrIgnore($chunk);
                }
            });

            // Mensaje final: cuantos se guardaron y cuantos/por que se omitieron.
            $mensaje = "Se guardaron $insertados registros correctamente.";
            if ($totalDuplicados > 0) {
                $mensaje .= " Se omitieron $totalDuplicados por valor ya existente.";
            }
            if ($omitidosSinCampo1 > 0) {
                $mensaje .= " Se omitieron $omitidosSinCampo1 por valor vacio.";
            }

            // Devolver el codigo_lote para que el frontend pueda referenciarlo si quiere.
            return response()->json(RespuestaApi::returnResultado('success', $mensaje, [
                'insertados' => $insertados,
                'codigo_lote' => $codigoLote,
                'user_id' => $userId,
                'omitidosDuplicados' => $omitidosDuplicados,
                'totalDuplicados' => $totalDuplicados,
                'omitidosSinCampo1' => $omitidosSinCampo1,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    // Determina si una fila esta completamente vacia (todos sus valores null o solo espacios).
    // Se usa para descartar filas inutiles del Excel.
    private function filaVacia(array $valores): bool
    {
        foreach ($valores as $v) {
            if ($v !== null && trim((string) $v) !== '') {
                return false;
            }
        }
        return true;
    }
}

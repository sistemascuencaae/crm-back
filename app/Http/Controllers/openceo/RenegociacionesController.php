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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class RenegociacionesController extends Controller
{
    // Nombres EXACTOS que deben tener los encabezados del Excel (fila 1).
    // Se comparan en MAYUSCULAS, asi que en el archivo da igual el case.
    private const COL_FACTURA = 'NUMERO_FACTURA';
    private const COL_CUOTA = 'NUMERO_CUOTA';
    private const COL_FECHA = 'FECHA_CAMBIO';

    // Solo letras A-Z, digitos y guion (despues de trim + upper).
    private const PATRON_FACTURA = '/^[A-Z0-9\-]+$/';
    // Solo digitos.
    private const PATRON_CUOTA = '/^\d+$/';
    // dd/mm/yyyy con dia y mes de 1 o 2 digitos y anio de 4.
    private const PATRON_FECHA = '#^(\d{1,2})/(\d{1,2})/(\d{4})$#';
    // yyyy-mm-dd (ISO 8601) - alternativa para evitar problemas de locale en Excel.
    private const PATRON_FECHA_ISO = '#^(\d{4})-(\d{1,2})-(\d{1,2})$#';

    private const MAX_FILAS = 1000;

    // Renegociación MASIVA desde un archivo Excel.
    public function renegociacionesJuanNarvaezMasivo(Request $request)
    {
        try {
            // 1. Validación de que solo acepte UN archivo no mayor a 10MB
            $rawArchivo = $request->file('archivo');
            if (is_array($rawArchivo)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Solo se permite UN archivo en el campo "archivo".', []), 422);
            }

            // 2. Validación estandar del header Accept: application/json
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

            // 3. Validación que el archivo subio sin corrupcion
            $archivo = $request->file('archivo');

            if (!$archivo->isValid()) {
                return response()->json(RespuestaApi::returnResultado('error', 'El archivo no se subio correctamente. Vuelve a intentarlo.', []), 422);
            }

            // 4. Validación que no este vacio (0 bytes)
            if ($archivo->getSize() === 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'El archivo esta vacio.', []), 422);
            }

            // -------------------------------------------------------------
            // 1) Cargar archivo Excel y extraer filas
            // -------------------------------------------------------------
            $rutaArchivo = $archivo->getRealPath();
            $spreadsheet = IOFactory::load($rutaArchivo);
            $hoja = $spreadsheet->getActiveSheet();
            // formatData=false -> Esto evita que Excel reordene dia/mes en la lectura segun la configuracion regional.
            $filas = $hoja->toArray(null, true, false, false);

            if (count($filas) < 2) {
                return response()->json(RespuestaApi::returnResultado('error', 'El archivo no contiene filas de datos.', []));
            }

            // -------------------------------------------------------------
            // 2) Mapear columnas por nombre de encabezado
            // -------------------------------------------------------------
            $encabezado = array_map(fn($v) => strtoupper(trim((string) $v)), array_shift($filas));

            $idxFactura = array_search(self::COL_FACTURA, $encabezado, true);
            $idxCuota = array_search(self::COL_CUOTA, $encabezado, true);
            $idxFecha = array_search(self::COL_FECHA, $encabezado, true);

            $faltantes = [];
            if ($idxFactura === false) $faltantes[] = self::COL_FACTURA;
            if ($idxCuota === false) $faltantes[] = self::COL_CUOTA;
            if ($idxFecha === false) $faltantes[] = self::COL_FECHA;

            if ($faltantes) {
                return response()->json(RespuestaApi::returnResultado('error', 'Faltan columnas obligatorias en el archivo: ' . implode(', ', $faltantes), []));
            }

            if (count($filas) > self::MAX_FILAS) {
                return response()->json(RespuestaApi::returnResultado('error', 'El archivo excede el limite de ' . self::MAX_FILAS . ' filas por lote.', []));
            }

            // -------------------------------------------------------------
            // 3) Pasada 1: limpiar (trim/upper) y validar formato fila a fila
            // -------------------------------------------------------------
            $items = [];
            $errores = [];
            $vistos = [];

            foreach ($filas as $i => $fila) {
                $numeroFila = $i + 2; // +1 por offset 0, +1 por encabezado

                $rawFactura = $fila[$idxFactura] ?? null;
                $rawCuota = $fila[$idxCuota] ?? null;
                $rawFecha = $fila[$idxFecha] ?? null;

                if ($this->filaVacia([$rawFactura, $rawCuota, $rawFecha])) {
                    continue; // ignorar filas totalmente vacias
                }

                $motivos = [];

                // NUMERO_FACTURA: trim + UPPER + validar caracteres
                $factura = strtoupper(trim((string) $rawFactura));

                if ($factura === '') {
                    $motivos[] = self::COL_FACTURA . ' esta vacio.';
                } elseif (!preg_match(self::PATRON_FACTURA, $factura)) {
                    $motivos[] = self::COL_FACTURA . ' contiene caracteres no permitidos ' . '(solo letras A-Z, digitos y guiones): "' . $factura . '"';
                }

                // NUMERO_CUOTA: trim + solo digitos
                $cuotaStr = trim((string) $rawCuota);
                $cuota = null;
                if ($cuotaStr === '') {
                    $motivos[] = self::COL_CUOTA . ' esta vacio.';
                } elseif (!preg_match(self::PATRON_CUOTA, $cuotaStr)) {
                    $motivos[] = self::COL_CUOTA . ' debe ser un entero positivo (recibido: "' . $cuotaStr . '").';
                } else {
                    $cuota = (int) $cuotaStr;
                    if ($cuota <= 0) {
                        $motivos[] = self::COL_CUOTA . ' debe ser mayor a cero.';
                    }
                }

                // FECHA_CAMBIO: dd/mm/yyyy + futura
                $fechaCarbon = null;
                if ($rawFecha === null || trim((string) $rawFecha) === '') {
                    $motivos[] = self::COL_FECHA . ' esta vacio.';
                } else {
                    $fechaCarbon = $this->parsearFecha($rawFecha);

                    if (!$fechaCarbon) {
                        $motivos[] = self::COL_FECHA . ' no tiene formato dd/mm/yyyy ' . '(recibido: "' . trim((string) $rawFecha) . '").';
                    } elseif (!$fechaCarbon->isFuture()) {
                        $motivos[] = self::COL_FECHA . ' debe ser posterior a hoy (recibido: ' . $fechaCarbon->format('d/m/Y') . ').';
                    }
                }

                // Duplicado dentro del mismo lote
                if ($factura !== '' && $cuota !== null) {
                    $clave = $factura . '|' . $cuota;
                    if (isset($vistos[$clave])) {
                        $motivos[] = "El par {$factura} / cuota {$cuota} esta " . "duplicado en el archivo (ya aparecio en la fila {$vistos[$clave]}).";
                    } else {
                        $vistos[$clave] = $numeroFila;
                    }
                }

                if ($motivos) {
                    $errores[] = [
                        'fila' => $numeroFila,
                        'numero_factura' => $factura,
                        'numero_cuota' => $cuotaStr,
                        'fecha_cambio' => is_scalar($rawFecha) ? trim((string) $rawFecha) : null,
                        'motivos' => $motivos,
                    ];
                    continue;
                }

                $items[] = [
                    'fila' => $numeroFila,
                    'numero_factura' => $factura,
                    'numero_cuota' => $cuota,
                    'fecha_cambio' => $fechaCarbon,
                ];
            }

            if (!$items && !$errores) {
                return response()->json(RespuestaApi::returnResultado('error', 'El archivo no contiene filas con datos.', []));
            }

            // -------------------------------------------------------------
            // 4) Pasada 2: validar contra BD (existencia y no-cancelado) Una sola consulta para todos los doctran's.
            // -------------------------------------------------------------
            if ($items) {
                $cuotasBD = $this->cargarCuotasDesdeBD($items);

                foreach ($items as &$it) {
                    $clave = $it['numero_factura'] . '|' . $it['numero_cuota'];

                    if (!isset($cuotasBD[$clave])) {
                        $errores[] = [
                            'fila' => $it['fila'],
                            'numero_factura' => $it['numero_factura'],
                            'numero_cuota' => $it['numero_cuota'],
                            'fecha_cambio' => $it['fecha_cambio']->format('d/m/Y'),
                            'motivos' => ["No existe la cuota {$it['numero_cuota']} de la factura {$it['numero_factura']}.",],
                        ];
                        continue;
                    }

                    $cuotaBD = $cuotasBD[$clave];

                    if ($cuotaBD->ddo_cancelado) {
                        $errores[] = [
                            'fila' => $it['fila'],
                            'numero_factura' => $it['numero_factura'],
                            'numero_cuota' => $it['numero_cuota'],
                            'fecha_cambio' => $it['fecha_cambio']->format('d/m/Y'),
                            'motivos' => ["La cuota {$it['numero_cuota']} de la factura {$it['numero_factura']} ya esta cancelada.",],
                        ];
                        continue;
                    }

                    $it['ddo'] = $cuotaBD;
                }
                unset($it);
            }

            // -------------------------------------------------------------
            // 5) Si hay aunque sea 1 inconsistencia -> NO se aplica nada
            // -------------------------------------------------------------
            if ($errores) {
                return response()->json([
                    'code' => 422,
                    'status' => 'error',
                    'message' => 'Se encontraron ' . count($errores) . ' inconsistencias. No se aplico ningun cambio.',
                    'data' => [
                        'totalFilas' => count($items) + count($errores),
                        'errores' => $errores,
                    ],
                    'icon' => 'error',
                    'color' => '#ff4000',
                ], 422);
            }

            // -------------------------------------------------------------
            // 6) Pasada 3: aplicar todo dentro de UNA transaccion
            // -------------------------------------------------------------
            $codigoLote = (string) Str::uuid(); // Genera un id alfanumerico
            $userId = auth()->id(); // recupera el id del usuario

            $resumen = [];
            DB::transaction(function () use ($items, &$resumen, $codigoLote, $userId) {
                $resumen = $this->aplicarRenegociacionBatch($items, [
                    'codigo_lote' => $codigoLote,
                    'user_id' => $userId,
                ]);
            });

            // -------------------------------------------------------------
            // 7) Obtiene los nuevos valores de las facturas actualizadas
            // -------------------------------------------------------------
            $doctranesTocados = array_values(array_unique(array_column($items, 'numero_factura')));
            $cuotasPorFactura = $this->cuotasFinalesAgrupadas($doctranesTocados);

            $facturas = [];
            foreach ($resumen as $r) {
                $facturas[] = [
                    'numero_factura' => $r['numero_factura'],
                    'numero_cuota_modificada' => $r['numero_cuota'],
                    'fecha_cambio' => $r['fecha_cambio'],
                    'cuotas_actualizadas' => $r['cuotas_actualizadas'],
                    'cuotas' => $cuotasPorFactura[$r['numero_factura']] ?? [],
                ];
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Renegociación masiva aplicada con exito.',
                    [
                        'codigo_lote' => $codigoLote,
                        'user_id' => $userId,
                        'totalFilas' => count($items),
                        'filasProcesadas' => count($items),
                        'facturas' => $facturas,
                    ]));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    // Aplica la renegociacion masiva en lote.
    private function aplicarRenegociacionBatch(array $items, array $contextoBase): array
    {
        $tz = 'America/Guayaquil';
        $ahora = Carbon::now($tz);
        $horaCambio = $ahora->format('H:i:s.v');
        $now = $ahora->toDateTimeString();

        // 1) Una sola query trae todas las cuotas de todos los doctranes
        $doctranes = array_values(array_unique(array_column($items, 'numero_factura')));
        if (!$doctranes) {
            return [];
        }

        $placeholders = rtrim(str_repeat('?,', count($doctranes)), ',');
        $allRows = DB::select("SELECT ddo_id, ddo_transacc, ccm_id, ddo_num_pago, ddo_debcre,
                                    ddo_monto, ddo_fechaven, cli_id, ddo_cancelado,
                                    ddo_monto_cancelado, ddo_agente, locked, ddo_fecha_emision,
                                    ddo_doctran, dco_id, ddo_numfac, ddo_emisor, ddo_nrocuenta,
                                    ddo_observacion
                                FROM public.ddocumento
                                WHERE ddo_doctran IN ($placeholders)
                                ORDER BY ddo_doctran, ddo_num_pago ASC", $doctranes);

        // 2) Agrupar por doctran
        $cuotasPorDoctran = [];
        foreach ($allRows as $row) {
            $cuotasPorDoctran[$row->ddo_doctran][] = $row;
        }

        // 3) Construir filas de historial y de UPDATE
        $historyRows = [];
        $updates = [];
        $resumen = [];

        foreach ($items as $it) {
            $cuotasFactura = $cuotasPorDoctran[$it['numero_factura']] ?? [];
            $contexto = array_merge($contextoBase, [
                'factura_cambio' => $it['numero_factura'],
                'cuota_cambio' => $it['numero_cuota'],
                'fecha_cambio' => $it['fecha_cambio'],
            ]);

            // Localizar cuota a cambiar y posteriores (ya vienen ordenadas por num_pago ASC)
            $cuota_cambiar = null;
            $posteriores = [];
            foreach ($cuotasFactura as $cuota) {
                $numPago = (int) $cuota->ddo_num_pago;
                if ($numPago === $it['numero_cuota']) {
                    $cuota_cambiar = $cuota;
                } elseif ($numPago > $it['numero_cuota']) {
                    $posteriores[] = $cuota;
                }
            }

            if (!$cuota_cambiar) {
                continue; // no deberia pasar tras la pasada 2
            }

            // Snapshot + UPDATE para la cuota_cambiar
            $historyRows[] = $this->snapshotARow($cuota_cambiar, $contexto, $now);
            $updates[] = [
                'ddo_id' => (int) $cuota_cambiar->ddo_id,
                'fechaven' => $it['fecha_cambio']->format('Y-m-d H:i:s.v'),
            ];

            // Snapshot + UPDATE para cada propagada
            foreach ($posteriores as $i => $cuota) {
                $fechaCuota = $it['fecha_cambio']->copy()
                    ->addMonthsNoOverflow($i + 1)
                    ->setTimeFromTimeString($horaCambio);

                $historyRows[] = $this->snapshotARow($cuota, $contexto, $now);
                $updates[] = [
                    'ddo_id' => (int) $cuota->ddo_id,
                    'fechaven' => $fechaCuota->format('Y-m-d H:i:s.v'),
                ];
            }

            $resumen[] = [
                'numero_factura' => $it['numero_factura'],
                'numero_cuota' => $it['numero_cuota'],
                'fecha_cambio' => $it['fecha_cambio']->format('d/m/Y'),
                'cuotas_actualizadas' => count($posteriores) + 1,
            ];
        }

        // 4) INSERT del historial.
        // 24 columnas x 500 filas = 12.000 parametros (limite Postgres = 65.535).
        if ($historyRows) {
            foreach (array_chunk($historyRows, 500) as $chunk) {
                DB::table('crm.ddocumento_historial')->insert($chunk);
            }
        }

        // 5) 2 parametros por fila x 1000 filas = 2.000 parametros.
        if ($updates) {
            foreach (array_chunk($updates, 1000) as $chunk) {
                $this->updateFechavenBatch($chunk);
            }
        }

        return $resumen;
    }

    // Aplica un UPDATE atomico sobre N cuotas usando la sintaxis de Postgres:
    private function updateFechavenBatch(array $updates): void
    {
        if (!$updates) {
            return;
        }

        $valuesSql = [];
        $bindings = [];
        foreach ($updates as $u) {
            $valuesSql[] = '(?::int, ?::timestamp)';
            $bindings[] = $u['ddo_id'];
            $bindings[] = $u['fechaven'];
        }

        $sql = "UPDATE public.ddocumento AS d
                SET ddo_fechaven = v.fechaven
                    FROM (VALUES " . implode(', ', $valuesSql) . ") AS v(ddo_id, fechaven)
                WHERE d.ddo_id = v.ddo_id";

        DB::update($sql, $bindings);
    }

    // Recoje los datos para poder guardar en el historial
    private function snapshotARow(object $ddo, array $contexto, string $now): array
    {
        return [
            'codigo_lote' => $contexto['codigo_lote'],
            'user_id' => $contexto['user_id'],

            // ddocumento (valores ANTES del UPDATE)
            'ddo_id' => $ddo->ddo_id,
            'ddo_transacc' => $ddo->ddo_transacc,
            'ccm_id' => $ddo->ccm_id,
            'ddo_num_pago' => $ddo->ddo_num_pago,
            'ddo_debcre' => $ddo->ddo_debcre,
            'ddo_monto' => $ddo->ddo_monto,
            'ddo_fechaven' => $ddo->ddo_fechaven,
            'cli_id' => $ddo->cli_id,
            'ddo_cancelado' => $ddo->ddo_cancelado,
            'ddo_monto_cancelado' => $ddo->ddo_monto_cancelado,
            'ddo_agente' => $ddo->ddo_agente,
            'locked' => $ddo->locked,
            'ddo_fecha_emision' => $ddo->ddo_fecha_emision,
            'ddo_doctran' => $ddo->ddo_doctran,
            'dco_id' => $ddo->dco_id,
            'ddo_numfac' => $ddo->ddo_numfac,
            'ddo_emisor' => $ddo->ddo_emisor,
            'ddo_nrocuenta' => $ddo->ddo_nrocuenta,
            'ddo_observacion' => $ddo->ddo_observacion,

            // datos enviados desde el Excel
            'factura_cambio' => $contexto['factura_cambio'],
            'cuota_cambio' => $contexto['cuota_cambio'],
            'fecha_cambio' => $contexto['fecha_cambio']->format('Y-m-d H:i:s'),

            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    // Devuelve todas las cuotas de los doctranes
    private function cuotasFinalesAgrupadas(array $doctranes): array
    {
        if (!$doctranes) {
            return [];
        }

        $placeholders = rtrim(str_repeat('?,', count($doctranes)), ',');
        $rows = DB::select("SELECT ddo_doctran,
                                TO_CHAR(ddo_fecha_emision, 'DD/MM/YYYY') AS f_emision,
                                TO_CHAR(ddo_fechaven, 'DD/MM/YYYY') AS f_ven,
                                ddo_num_pago,
                                ddo_debcre,
                                ddo_monto,
                                ddo_monto_cancelado
                            FROM public.ddocumento
                            WHERE ddo_doctran IN ($placeholders)
                            ORDER BY ddo_doctran, ddo_num_pago ASC", $doctranes);

        $agrupado = [];
        foreach ($rows as $r) {
            $agrupado[$r->ddo_doctran][] = [
                'f_emision' => $r->f_emision,
                'f_ven' => $r->f_ven,
                'no_pago' => (int) $r->ddo_num_pago,
                'dc' => (int) $r->ddo_debcre,
                'monto' => (float) $r->ddo_monto,
                'monto_cancel' => (float) $r->ddo_monto_cancelado,
            ];
        }
        return $agrupado;
    }

    // Devuelve un map ['DOCTRAN|NUMPAGO' => objeto] de todas las facturas que se cargo en el excel.
    private function cargarCuotasDesdeBD(array $items): array
    {
        $doctranes = array_values(array_unique(array_column($items, 'numero_factura')));
        if (!$doctranes) {
            return [];
        }

        $placeholders = rtrim(str_repeat('?,', count($doctranes)), ',');
        $cuotas = DB::select("SELECT ddo_id, ddo_doctran, ddo_num_pago, ddo_fechaven, ddo_cancelado
                                FROM public.ddocumento
                                WHERE ddo_doctran IN ($placeholders)", $doctranes);

        $map = [];
        foreach ($cuotas as $c) {
            $map[$c->ddo_doctran . '|' . $c->ddo_num_pago] = $c;
        }
        return $map;
    }

    // Parsea fecha desde celda Excel. Devuelve null si no se puede interpretar como fecha valida.
    private function parsearFecha($valor): ?Carbon
    {
        if ($valor instanceof \DateTime) {
            return Carbon::instance($valor)->startOfDay();
        }

        if (is_numeric($valor)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $valor);
                return Carbon::instance($dt)->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        }

        $str = trim((string) $valor);
        if ($str === '') {
            return null;
        }

        // Intento 1: formato d/m/yyyy (lo principal que enviara el usuario)
        if (preg_match(self::PATRON_FECHA, $str, $m)) {
            $dia = (int) $m[1];
            $mes = (int) $m[2];
            $anio = (int) $m[3];
            if (checkdate($mes, $dia, $anio)) {
                return Carbon::create($anio, $mes, $dia, 0, 0, 0);
            }
            return null;
        }

        // Intento 2: formato ISO yyyy-mm-dd (alternativa segura ante problemas de auto-conversion de Excel por locale)
        if (preg_match(self::PATRON_FECHA_ISO, $str, $m)) {
            $anio = (int) $m[1];
            $mes = (int) $m[2];
            $dia = (int) $m[3];
            if (checkdate($mes, $dia, $anio)) {
                return Carbon::create($anio, $mes, $dia, 0, 0, 0);
            }
            return null;
        }

        return null;
    }

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

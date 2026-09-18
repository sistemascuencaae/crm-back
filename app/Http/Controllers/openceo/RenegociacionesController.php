<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\renegociaciones\RenegociacionSolicitud;
use App\Services\RenegociacionService;
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
            // 3.5) Detectar facturas DUPLICADAS (misma factura en >1 fila).
            //
            // POR QUÉ: cada fila propaga la nueva fecha a todas las cuotas
            // POSTERIORES (cuota X+1 = fecha + 1 mes, X+2 = +2 meses, etc).
            // Si la misma factura aparece dos veces, las propagaciones se
            // SOLAPAN y generan UPDATE duplicado al mismo ddo_id con valores
            // distintos. Postgres no garantiza cuál gana → resultado
            // INDETERMINADO (a veces bien, a veces mal).
            //
            // Regla: por cada factura, una sola fila en el Excel (la cuota
            // más temprana que se quiera cambiar). Las cuotas siguientes se
            // recalculan automáticamente por la propagación.
            // -------------------------------------------------------------
            $facturasPorFila = [];
            foreach ($items as $idx => $it) {
                $facturasPorFila[$it['numero_factura']][] = [
                    'idx' => $idx,
                    'fila' => $it['fila'],
                    'numero_cuota' => $it['numero_cuota'],
                    'fecha_cambio' => $it['fecha_cambio']->format('d/m/Y'),
                ];
            }

            $idxsAQuitar = [];
            foreach ($facturasPorFila as $factura => $apariciones) {
                if (count($apariciones) > 1) {
                    // Construir lista legible de filas afectadas
                    $detalleFilas = array_map(
                        fn($a) => "fila {$a['fila']} (cuota {$a['numero_cuota']} → {$a['fecha_cambio']})",
                        $apariciones
                    );
                    $resumen = implode(', ', $detalleFilas);

                    // Marcar TODAS las apariciones como error y quitarlas de $items
                    foreach ($apariciones as $a) {
                        $errores[] = [
                            'fila' => $a['fila'],
                            'numero_factura' => $factura,
                            'numero_cuota' => $a['numero_cuota'],
                            'fecha_cambio' => $a['fecha_cambio'],
                            'motivos' => [
                                "La factura {$factura} aparece " . count($apariciones)
                                    . " veces en el archivo: {$resumen}. "
                                    . "Cada factura debe aparecer SOLO UNA VEZ — sube la cuota "
                                    . "más temprana que quieras cambiar; las posteriores se "
                                    . "recalculan automáticamente por la propagación."
                            ],
                        ];
                        $idxsAQuitar[] = $a['idx'];
                    }
                }
            }

            if ($idxsAQuitar) {
                $items = array_values(array_filter($items, fn($_, $k) => !in_array($k, $idxsAQuitar, true), ARRAY_FILTER_USE_BOTH));
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

            return response()->json(RespuestaApi::returnResultado(
                'success',
                'Renegociación masiva aplicada con exito.',
                [
                    'codigo_lote' => $codigoLote,
                    'user_id' => $userId,
                    'totalFilas' => count($items),
                    'filasProcesadas' => count($items),
                    'facturas' => $facturas,
                ]
            ));
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

    // =====================================================================
    // RENEGOCIACIÓN CON SOLICITUD + APROBACIÓN (workflow 2 usuarios)
    // ---------------------------------------------------------------------
    // Usuario A (Cartera) crea la solicitud; Usuario B (Sistemas) aprueba y
    // ejecuta (aprobar = ejecutar). Cada línea se aplica en su propia
    // transacción reutilizando RenegociacionService::aplicarLinea (misma
    // propagación +1 mes que el masivo). NO toca el método masivo por Excel.
    // =====================================================================

    // Helper: ejecuta una función de la BD que devuelve jsonb y la decodifica a array.
    private function llamarFn(string $sql, array $bindings = []): array
    {
        $row = DB::selectOne("SELECT {$sql} AS r", $bindings);
        return $row && $row->r !== null ? (json_decode($row->r, true) ?? []) : [];
    }

    // Auditoría de UNA solicitud: resumen (creó/modificó) + eventos paginados de crm.audits
    // con el detalle de cuotas (antes -> después) por evento. Espejo de clienteAuditoria.
    public function auditoriaSolicitud(Request $request, $id)
    {
        try {
            $id = (int) $id;
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $resumen = DB::selectOne('SELECT * FROM crm.fn_renegociacion_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM crm.fn_renegociacion_auditoria_listar(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda !== '' ? $busqueda : null]);
            $total = $eventos[0]->total_registros ?? 0;

            // cuotas viene como jsonb -> array para el front.
            foreach ($eventos as $e) {
                $e->cuotas = $e->cuotas ? json_decode($e->cuotas, true) : [];
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría de la solicitud.', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    // Cédula -> nombre del cliente + facturas renegociables (las que tienen cuotas en ddocumento).
    public function facturasPorCedula($cedula)
    {
        try {
            $cedula = trim((string) $cedula);
            if ($cedula === '') {
                return response()->json(RespuestaApi::returnResultado('error', 'Debes enviar una identificación.', []), 422);
            }

            $data = $this->llamarFn('crm.fn_renegociacion_facturas_por_cedula(?)', [$cedula]);
            $msg = !empty($data['facturas'])
                ? 'Cliente y facturas encontradas.'
                : 'No se encontraron facturas con cuotas para esa identificación.';

            return response()->json(RespuestaApi::returnResultado('success', $msg, $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    // doctran (numero_factura) -> fecha de la factura + productos (informativo) + cuotas.
    public function productosPorFactura($doctran)
    {
        try {
            $doctran = strtoupper(trim((string) $doctran));
            if ($doctran === '') {
                return response()->json(RespuestaApi::returnResultado('error', 'Debes enviar el número de factura.', []), 422);
            }

            $data = $this->llamarFn('crm.fn_renegociacion_productos_por_factura(?)', [$doctran]);

            return response()->json(RespuestaApi::returnResultado('success', 'Datos de la factura.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    // Reglas de crear/editar solicitud, espejo del form add-edit-renegociacion:
    // todo required EXCEPTO observacion_cartera. motivo_cambio limitado a las 5
    // opciones del ng-select; tipo_factura solo exige presencia (en edición el
    // form admite valores legados fuera de la lista actual).
    private static function reglasSolicitud()
    {
        return [
            'identificacion' => 'required|string|max:20',
            'nombre_cliente' => 'required|string|max:255',
            'numero_factura' => 'required|string|max:100',
            'fecha_factura' => 'required|date',
            'codigo_producto' => 'required|string|max:100',
            'descripcion_producto' => 'required|string|max:255',
            'numero_cuota' => 'required|integer|min:1',
            'fecha_cambio' => 'required|date',
            'motivo_cambio' => 'required|string|max:60|in:Moto no entregada,Moto entregada,servicio tecnico,Error en fecha de pagaré,Regularizacion',
            'tipo_factura' => 'required|string|max:40',
            'observacion_cartera' => 'nullable|string',
        ];
    }

    private static function mensajesSolicitud()
    {
        return [
            'identificacion.required' => 'La identificación es obligatoria.',
            'nombre_cliente.required' => 'El nombre del cliente es obligatorio.',
            'numero_factura.required' => 'El número de factura es obligatorio.',
            'fecha_factura.required' => 'La fecha de la factura es obligatoria.',
            'fecha_factura.date' => 'La fecha de la factura no es válida.',
            'codigo_producto.required' => 'El producto es obligatorio.',
            'descripcion_producto.required' => 'La descripción del producto es obligatoria.',
            'numero_cuota.required' => 'El número de cuota es obligatorio.',
            'numero_cuota.integer' => 'El número de cuota debe ser un entero.',
            'numero_cuota.min' => 'El número de cuota debe ser mayor a cero.',
            'fecha_cambio.required' => 'La fecha a cambiar es obligatoria.',
            'fecha_cambio.date' => 'La fecha a cambiar no es válida.',
            'motivo_cambio.required' => 'El motivo del cambio es obligatorio.',
            'motivo_cambio.in' => 'El motivo del cambio no es una opción válida.',
            'tipo_factura.required' => 'El tipo de factura es obligatorio.',
        ];
    }

    // Usuario A: crea la solicitud (aprobado=false, ejecutado=false). No aplica nada todavía.
    public function crearSolicitud(Request $request)
    {
        $validator = Validator::make($request->all(), self::reglasSolicitud(), self::mensajesSolicitud());

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), ['errores' => $validator->errors()->all()]), 422);
        }

        try {
            $usuario = auth()->user();

            // Toda la lógica (validar cuota, derivar tipo, insertar) vive en la función BD.
            // Los últimos 6 parámetros son el contexto del usuario para la auditoría
            // forense (auditoria.logs_cambios vía fn_registrar_evento).
            $data = $this->llamarFn(
                'crm.fn_renegociacion_solicitud_crear(?,?,?,?::date,?,?,?,?::date,?,?,?,?,?::bigint,?::varchar,?::varchar,?::inet,?::text,?::uuid)',
                [
                    trim($request->input('identificacion')),
                    $request->input('nombre_cliente'),
                    strtoupper(trim($request->input('numero_factura'))),
                    $request->input('fecha_factura'),
                    $request->input('codigo_producto'),
                    $request->input('descripcion_producto'),
                    (int) $request->input('numero_cuota'),
                    $request->input('fecha_cambio'),
                    $request->input('motivo_cambio'),
                    $request->filled('tipo_factura') ? trim($request->input('tipo_factura')) : null,
                    $request->input('observacion_cartera'),
                    RenegociacionService::etiquetaUsuario($usuario),
                    $usuario->id ?? null,
                    $usuario->usu_alias ?? null,
                    trim(($usuario->surname ?? '') . ' ' . ($usuario->name ?? '')) ?: null,
                    $request->ip(),
                    $request->userAgent(),
                    (string) Str::uuid(),
                ]
            );

            if (!($data['ok'] ?? false)) {
                return response()->json(RespuestaApi::returnResultado('error', $data['message'] ?? 'No se pudo crear la solicitud.', ['errores' => $data['motivos'] ?? []]), $data['code'] ?? 422);
            }

            return response()->json(RespuestaApi::returnResultado('success', $data['message'], $data['data']));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    // Listado de solicitudes con filtros de estado y rango de fechas (FECHA SOLICITUD = created_at).
    public function listarSolicitudes(Request $request)
    {
        try {
            $query = RenegociacionSolicitud::query();

            // estado: pendiente | aprobado | ejecutado | no_ejecutado
            switch ($request->input('estado')) {
                case 'pendiente':
                    $query->where('aprobado', false);
                    break;
                case 'aprobado':
                    $query->where('aprobado', true);
                    break;
                case 'ejecutado':
                    $query->where('ejecutado', true);
                    break;
                case 'no_ejecutado':
                    $query->where('aprobado', true)->where('ejecutado', false);
                    break;
            }

            if ($request->filled('desde')) {
                $query->whereDate('created_at', '>=', $request->input('desde'));
            }
            if ($request->filled('hasta')) {
                $query->whereDate('created_at', '<=', $request->input('hasta'));
            }
            if ($request->filled('numero_factura')) {
                $query->where('numero_factura', strtoupper(trim($request->input('numero_factura'))));
            }
            if ($request->filled('identificacion')) {
                $query->where('identificacion', trim($request->input('identificacion')));
            }

            $solicitudes = $query->orderBy('created_at', 'desc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Listado de solicitudes.', $solicitudes));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    // Listado PAGINADO (Usuario A): pendientes primero. Usa la función
    // crm.fn_renegociacion_solicitud_listar_paginado (COUNT(*) OVER() + LIMIT/OFFSET).
    // Devuelve { registros, total }. Tamaños permitidos: 10/20/30/50/100.
    public function listarSolicitudesPaginado(Request $request)
    {
        try {
            $pagina = max(1, (int) $request->input('pagina', 1));
            $tamanio = (int) $request->input('tamanio', 10);
            if (!in_array($tamanio, [10, 20, 30, 50, 100], true)) {
                $tamanio = 10;
            }
            $busqueda = trim((string) $request->input('busqueda', ''));
            $estado = trim((string) $request->input('estado', ''));
            $desde = trim((string) $request->input('desde', ''));
            $hasta = trim((string) $request->input('hasta', ''));

            $rows = DB::select(
                'SELECT * FROM crm.fn_renegociacion_solicitud_listar_paginado(?, ?, ?, ?, ?, ?)',
                [
                    $pagina,
                    $tamanio,
                    $busqueda !== '' ? $busqueda : null,
                    $estado !== '' ? $estado : null,
                    $desde !== '' ? $desde : null,
                    $hasta !== '' ? $hasta : null,
                ]
            );

            $total = $rows ? (int) $rows[0]->total_registros : 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Listado de solicitudes.', [
                'registros' => $rows,
                'total' => $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    // Usuario A: edita una solicitud SOLO mientras está pendiente (aprobado=false).
    // No toca updated_by (ese queda para quien aprueba/ejecuta = Usuario B).
    public function editarSolicitud(Request $request, $id)
    {
        $validator = Validator::make($request->all(), self::reglasSolicitud(), self::mensajesSolicitud());

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), ['errores' => $validator->errors()->all()]), 422);
        }

        try {
            $usuario = auth()->user();

            // Validar pendiente + cuota + derivar tipo + actualizar: todo en la función BD.
            // Los últimos 6 parámetros son el contexto del usuario para la auditoría
            // forense (auditoria.logs_cambios vía fn_registrar_evento).
            $data = $this->llamarFn(
                'crm.fn_renegociacion_solicitud_editar(?,?,?,?,?::date,?,?,?,?::date,?,?,?,?::bigint,?::varchar,?::varchar,?::inet,?::text,?::uuid)',
                [
                    (int) $id,
                    trim($request->input('identificacion')),
                    $request->input('nombre_cliente'),
                    strtoupper(trim($request->input('numero_factura'))),
                    $request->input('fecha_factura'),
                    $request->input('codigo_producto'),
                    $request->input('descripcion_producto'),
                    (int) $request->input('numero_cuota'),
                    $request->input('fecha_cambio'),
                    $request->input('motivo_cambio'),
                    $request->filled('tipo_factura') ? trim($request->input('tipo_factura')) : null,
                    $request->input('observacion_cartera'),
                    $usuario->id ?? null,
                    $usuario->usu_alias ?? null,
                    trim(($usuario->surname ?? '') . ' ' . ($usuario->name ?? '')) ?: null,
                    $request->ip(),
                    $request->userAgent(),
                    (string) Str::uuid(),
                ]
            );

            if (!($data['ok'] ?? false)) {
                return response()->json(RespuestaApi::returnResultado('error', $data['message'] ?? 'No se pudo editar la solicitud.', ['errores' => $data['motivos'] ?? []]), $data['code'] ?? 422);
            }

            return response()->json(RespuestaApi::returnResultado('success', $data['message'], $data['data']));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    // PREVIEW para el modal de aprobación: cómo quedarían las cuotas (vence actual ->
    // vence nueva) si se ejecuta esta solicitud. NO escribe nada. Incluye validación
    // (existe / no cancelada) para avisar antes de confirmar.
    public function previsualizarSolicitud($id)
    {
        try {
            $data = $this->llamarFn('crm.fn_renegociacion_solicitud_previsualizar(?)', [(int) $id]);

            if (!($data['ok'] ?? false)) {
                return response()->json(RespuestaApi::returnResultado('error', $data['message'] ?? 'No existe la solicitud.', []), $data['code'] ?? 422);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Previsualización de la renegociación.', [
                'solicitud' => $data['solicitud'],
                'cuotas' => $data['cuotas'],
                'valido' => $data['valido'],
                'motivos' => $data['motivos'],
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    // Usuario B: aprobar = ejecutar. Recibe un array de líneas; cada aprobada se aplica
    // en su propia transacción (si una falla, las demás siguen y el error va a observacion_sistemas).
    public function ejecutarSolicitudes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'solicitudes' => 'required|array|min:1',
            'solicitudes.*.id' => 'required|integer',
            'solicitudes.*.aprobado' => 'required|boolean',
            'solicitudes.*.observacion_sistemas' => 'nullable|string',
        ], [
            'solicitudes.required' => 'Debes enviar al menos una solicitud.',
            'solicitudes.array' => 'El campo solicitudes debe ser un arreglo.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), ['errores' => $validator->errors()->all()]), 422);
        }

        try {
            $usuario = auth()->user();
            $etiqueta = RenegociacionService::etiquetaUsuario($usuario);
            $userId = auth()->id();
            // request_id único para todo el lote de esta llamada: enlaza en la
            // auditoría forense todas las líneas ejecutadas en esta misma acción.
            $requestId = (string) Str::uuid();

            $resultados = [];

            // Cada línea se ejecuta en su propia función BD: maneja idempotencia,
            // no-aprobada, validación, propagación+snapshot y captura de errores
            // (la propagación se revierte sola si falla; la fila queda con el error).
            // Los últimos 6 parámetros son el contexto del usuario para la auditoría
            // forense (auditoria.logs_cambios vía fn_registrar_evento; solo se
            // registra evento 'EJECUTADO' cuando la línea se aplica con éxito).
            foreach ($request->input('solicitudes') as $linea) {
                $id = (int) ($linea['id'] ?? 0);
                $aprobado = (bool) ($linea['aprobado'] ?? false);
                $obsSistemas = $linea['observacion_sistemas'] ?? null;
                $codigoLote = (string) Str::uuid();

                $res = $this->llamarFn(
                    'crm.fn_renegociacion_solicitud_ejecutar(?,?,?,?,?,?::uuid,?::bigint,?::varchar,?::varchar,?::inet,?::text,?::uuid)',
                    [
                        $id, $aprobado ? 'true' : 'false', $obsSistemas, $etiqueta, $userId, $codigoLote,
                        $usuario->id ?? null,
                        $usuario->usu_alias ?? null,
                        trim(($usuario->surname ?? '') . ' ' . ($usuario->name ?? '')) ?: null,
                        $request->ip(),
                        $request->userAgent(),
                        $requestId,
                    ]
                );
                $resultados[] = $res;
            }

            $okCount = count(array_filter($resultados, fn($r) => $r['ok'] ?? false));

            return response()->json(RespuestaApi::returnResultado(
                'success',
                "Proceso finalizado: {$okCount} de " . count($resultados) . ' líneas correctas.',
                ['resultados' => $resultados]
            ));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    // PREVIEW de REVERSIÓN: cómo quedarían las cuotas si se revierte una solicitud
    // ya ejecutada (fecha aplicada -> fecha original). NO escribe nada. Incluye la
    // regla de seguridad: si alguna cuota cambió de fecha después de esta
    // renegociación, marca conflicto y valido=false.
    public function previsualizarReversion($id)
    {
        try {
            $usuario = auth()->user();

            // Permiso (delete del menú) + validación + análisis de reversión: todo en la función BD.
            $data = $this->llamarFn(
                'crm.fn_renegociacion_solicitud_previsualizar_reversion(?,?)',
                [(int) $id, $usuario->profile_id ?? null]
            );

            if (!($data['ok'] ?? false)) {
                return response()->json(RespuestaApi::returnResultado('error', $data['message'] ?? 'No se pudo previsualizar la reversión.', []), $data['code'] ?? 422);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Previsualización de la reversión.', [
                'solicitud' => $data['solicitud'],
                'cuotas' => $data['cuotas'],
                'valido' => $data['valido'],
                'motivos' => $data['motivos'],
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    // Revierte una solicitud ejecutada: restaura las cuotas a su fecha original
    // (solo si sigue siendo el último cambio). Limitado por permiso "eliminar" del
    // menú de aprobación. Deja la solicitud en estado pendiente con la marca de
    // quién/cuándo revirtió en observacion_sistemas.
    public function revertirSolicitud(Request $request, $id)
    {
        try {
            $usuario = auth()->user();
            $etiqueta = RenegociacionService::etiquetaUsuario($usuario);

            // Permiso + regla de seguridad + restaurar fechas + marca de auditoría: todo en la función BD.
            // Los últimos 6 parámetros son el contexto del usuario para la auditoría
            // forense (auditoria.logs_cambios vía fn_registrar_evento, evento 'REVERTIDO').
            $data = $this->llamarFn(
                'crm.fn_renegociacion_solicitud_revertir(?,?,?,?::bigint,?::varchar,?::varchar,?::inet,?::text,?::uuid)',
                [
                    (int) $id, $usuario->profile_id ?? null, $etiqueta,
                    $usuario->id ?? null,
                    $usuario->usu_alias ?? null,
                    trim(($usuario->surname ?? '') . ' ' . ($usuario->name ?? '')) ?: null,
                    $request->ip(),
                    $request->userAgent(),
                    (string) Str::uuid(),
                ]
            );

            if (!($data['ok'] ?? false)) {
                return response()->json(RespuestaApi::returnResultado('error', $data['message'] ?? 'No se pudo revertir.', []), $data['code'] ?? 422);
            }

            return response()->json(RespuestaApi::returnResultado('success', $data['message'], [
                'id' => $data['id'] ?? (int) $id,
                'cuotas_revertidas' => $data['cuotas_revertidas'] ?? 0,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }
}

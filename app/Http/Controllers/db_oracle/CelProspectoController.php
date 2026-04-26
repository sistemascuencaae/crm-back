<?php

namespace App\Http\Controllers\db_oracle;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Exception;

class CelProspectoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => []
        ]);
    }

    public function listVsCelProspecto()
    {
        try {
            $data = DB::connection('oracle')->select("SELECT * FROM VS_CEL_PROSPECTO");

            if (empty($data)) {
                return response()->json(RespuestaApi::returnResultado('success', 'Sin datos en Oracle, no se sincroniza.', null));
            }

            $tableName = 'crm.vs_cel_prospecto';
            $oracleView = 'VS_CEL_PROSPECTO';

            $schemaMap = $this->getOracleSchemaMap($oracleView, array_keys((array)$data[0]));

            $this->ensureTable($tableName, $schemaMap);

            $now = now();
            $rows = array_map(function ($row) use ($now, $schemaMap) {
                $arr = [];
                foreach ((array)$row as $key => $value) {
                    $col = strtolower($key);
                    $kind = $schemaMap[$col]['kind'] ?? 'text';
                    if ($kind !== 'text' && $value === '') {
                        $value = null;
                    }
                    $arr[$col] = $value;
                }
                $arr['created_at'] = $now;
                $arr['updated_at'] = $now;
                return $arr;
            }, $data);

            DB::connection('pgsql')->transaction(function () use ($tableName, $rows) {
                DB::connection('pgsql')->table($tableName)->truncate();
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::connection('pgsql')->table($tableName)->insert($chunk);
                }
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se sincronizó con éxito. ' . count($rows) . ' registros.', null));
        } catch (\Throwable $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }

    private function getOracleSchemaMap(string $oracleTable, array $fallbackColumns): array
    {
        $meta = DB::connection('oracle')->select(
            "SELECT column_name, data_type, data_precision, data_scale
             FROM all_tab_columns
             WHERE table_name = ?
             ORDER BY column_id",
            [strtoupper($oracleTable)]
        );

        $map = [];
        foreach ($meta as $row) {
            $r = array_change_key_case((array)$row, CASE_LOWER);
            $col = strtolower($r['column_name']);
            $map[$col] = $this->mapOracleToKind($r['data_type'], $r['data_precision'] ?? null, $r['data_scale'] ?? null);
        }

        foreach ($fallbackColumns as $orig) {
            $col = strtolower($orig);
            if (!isset($map[$col])) {
                $map[$col] = ['kind' => 'text'];
            }
        }

        return $map;
    }

    private function mapOracleToKind(string $dataType, $precision, $scale): array
    {
        $t = strtoupper(trim($dataType));
        $precision = $precision !== null ? (int)$precision : null;
        $scale = $scale !== null ? (int)$scale : null;

        if ($t === 'DATE') {
            return ['kind' => 'timestamp'];
        }
        if (str_contains($t, 'TIMESTAMP')) {
            return str_contains($t, 'WITH TIME ZONE')
                ? ['kind' => 'timestamptz']
                : ['kind' => 'timestamp'];
        }
        if ($t === 'NUMBER') {
            if ($scale !== null && $scale > 0) {
                return ['kind' => 'numeric', 'precision' => $precision ?? 38, 'scale' => $scale];
            }
            if ($precision !== null && $precision <= 18) {
                return ['kind' => 'bigint'];
            }
            return ['kind' => 'numeric', 'precision' => $precision ?? 38, 'scale' => 0];
        }
        if (in_array($t, ['FLOAT', 'BINARY_FLOAT', 'BINARY_DOUBLE'])) {
            return ['kind' => 'double'];
        }
        return ['kind' => 'text'];
    }

    private function ensureTable(string $tableName, array $schemaMap): void
    {
        [$schema, $tabla] = str_contains($tableName, '.')
            ? explode('.', $tableName, 2)
            : ['public', $tableName];

        $existe = DB::connection('pgsql')->selectOne(
            "SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
            [$schema, $tabla]
        );

        if ($existe) {
            $reservadas = ['id', 'created_at', 'updated_at'];
            $rows = DB::connection('pgsql')->select(
                "SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ?",
                [$schema, $tabla]
            );

            $actuales = [];
            foreach ($rows as $r) {
                if (in_array($r->column_name, $reservadas)) {
                    continue;
                }
                $actuales[$r->column_name] = $this->pgDataTypeToKind($r->data_type);
            }

            $esperadas = [];
            foreach ($schemaMap as $col => $info) {
                $esperadas[$col] = $info['kind'];
            }

            ksort($actuales);
            ksort($esperadas);

            if ($actuales === $esperadas) {
                return;
            }

            Schema::connection('pgsql')->drop($tableName);
        }

        Schema::connection('pgsql')->create($tableName, function (Blueprint $table) use ($schemaMap) {
            $table->id();
            foreach ($schemaMap as $col => $info) {
                $this->addBlueprintColumn($table, $col, $info);
            }
            $table->timestamps();
        });
    }

    private function pgDataTypeToKind(string $dataType): string
    {
        $dataType = strtolower($dataType);
        return match (true) {
            $dataType === 'text' => 'text',
            $dataType === 'timestamp without time zone' => 'timestamp',
            $dataType === 'timestamp with time zone' => 'timestamptz',
            $dataType === 'bigint' => 'bigint',
            $dataType === 'numeric' => 'numeric',
            $dataType === 'double precision' => 'double',
            default => 'other',
        };
    }

    private function addBlueprintColumn(Blueprint $table, string $name, array $info): void
    {
        switch ($info['kind']) {
            case 'timestamp':
                $table->timestamp($name)->nullable();
                break;
            case 'timestamptz':
                $table->timestampTz($name)->nullable();
                break;
            case 'bigint':
                $table->bigInteger($name)->nullable();
                break;
            case 'numeric':
                $table->decimal($name, $info['precision'] ?? 38, $info['scale'] ?? 10)->nullable();
                break;
            case 'double':
                $table->double($name)->nullable();
                break;
            default:
                $table->text($name)->nullable();
        }
    }
}

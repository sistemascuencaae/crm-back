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
            $columns = array_map('strtolower', array_keys((array)$data[0]));

            $this->ensureTable($tableName, $columns);

            $now = now();
            $rows = array_map(function ($row) use ($now) {
                $arr = [];
                foreach ((array)$row as $key => $value) {
                    $arr[strtolower($key)] = $value;
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
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }

    private function ensureTable(string $tableName, array $columns): void
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
                "SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ?",
                [$schema, $tabla]
            );
            $actuales = array_values(array_diff(
                array_map(fn($r) => $r->column_name, $rows),
                $reservadas
            ));
            $esperadas = $columns;
            sort($actuales);
            sort($esperadas);

            if ($actuales === $esperadas) {
                return;
            }

            Schema::connection('pgsql')->drop($tableName);
        }

        Schema::connection('pgsql')->create($tableName, function (Blueprint $table) use ($columns) {
            $table->id();
            foreach ($columns as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamps();
        });
    }
}

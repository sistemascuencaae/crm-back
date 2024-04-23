<?php

namespace App\Models\crm\series;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Facades\DB;

class Inventario extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.cinventario';

    protected $primaryKey = 'numero';

    public $timestamps = false;

    protected $fillable = [
        "numero",
        "fecha",
        "estado",
        "bod_id",
        "responsable",
        "usuario_crea",
        "fecha_crea",
        "usuario_modifica",
        "fecha_modifica",
    ];
    
    public function detalle()
    {
        return $this->hasMany(InventarioDet::class, "numero");
    }
}

class InventarioDet extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.dinventario';

    protected $primaryKey = 'numero, linea';

    public $timestamps = false;

    protected $fillable = [
        "numero",
        "linea",
        "pro_id",
        "serie",
        "tipo",
        "procesado",
    ];
}
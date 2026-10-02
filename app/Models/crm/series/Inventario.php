<?php

namespace App\Models\crm\series;

use App\Models\openceo\Bodega;
use App\Models\openceo\Producto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

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

    public function bodega()
    {
        return $this->belongsTo(Bodega::class, "bod_id");
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

    public function producto()
    {
        return $this->belongsTo(Producto::class, "pro_id");
    }
}
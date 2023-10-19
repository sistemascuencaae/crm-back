<?php

namespace App\Models\crm\garantias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Facades\DB;

class ConfigItems extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.producto_config';

    protected $primaryKey = 'pro_id';

    public $timestamps = false;

    protected $fillable = [
        "pro_id",
        "tipo_servicio",
        "porc_gex",
        "meses_garantia",
        "usuario_crea",
        "fecha_crea",
        "usuario_modifica",
        "fecha_modifica",
    ];
    
    public function partes()
    {
        return $this->hasMany(ConfigItemsPartes::class, "pro_id");
    }
}

class ConfigItemsPartes extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.producto_partes';

    protected $primaryKey = 'pro_id';

    public $timestamps = false;

    protected $fillable = [
        "pro_id",
        "parte_id",
        "meses_garantia",
    ];
}
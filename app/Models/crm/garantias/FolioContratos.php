<?php

namespace App\Models\crm\garantias;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class FolioContratos extends Model implements Auditable
{
    use AuditableTrait;

    use HasFactory;

    protected $table = 'gex.folios_contratos';

    protected $primaryKey = 'alm_id';

    public $timestamps = false;

    protected $fillable = [
        "alm_id",
        "folio",
    ];
}
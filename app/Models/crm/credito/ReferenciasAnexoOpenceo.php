<?php

namespace App\Models\crm\credito;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferenciasAnexoOpenceo extends Model
{
    // use AuditableTrait;

    use HasFactory;

    protected $table = 'public.referencias_anexo';

    protected $primaryKey = 'refane_id';

    protected $fillable = [
        "refane_id",
        "refane_nombre",
        "refane_descripcion",
        "refane_direccion",
        "refane_documento_url",
        "refane_email",
        "refane_numero_telefono",
        "refane_numero_telefono2",
        "refane_numero_telefono3",
        "ctn_id",
        "pane_id_trf",
        "locked",
        "refane_activo",
    ];


}

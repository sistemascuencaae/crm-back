<?php

namespace App\Models\fileManager;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FmAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false; // Solo tiene created_at — Eloquent default updated_at no aplica

    protected $table = 'crm.fm_audit_log';

    protected $fillable = [
        'user_id',
        'accion',
        'entidad_tipo',
        'entidad_id',
        'datos_antes',
        'datos_despues',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'datos_antes' => 'array',
        'datos_despues' => 'array',
        'created_at' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

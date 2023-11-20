<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User;

class Audits extends Model
{
    use HasFactory;

    protected $table = 'crm.audits';
    protected $fillable = ["accion", "estado_caso", "estado_caso_id"];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

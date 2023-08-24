<?php

namespace App\Models\crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User;

class Audits extends Model
{
    use HasFactory;

    protected $table = 'public.audits';
    protected $fillable = ["accion"];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

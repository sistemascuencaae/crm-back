<?php

namespace App\Models\crm;

use App\Models\crm\TipoEstado;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parentesco extends Model
{
    use HasFactory;

    protected $table = 'crm.parentesco';

    protected $fillable = ["nombre"];
}
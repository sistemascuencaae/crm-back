<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DivMovil extends Model
{
    use SoftDeletes;
    protected $table = 'crm.div_movil';
    protected $primaryKey = 'div_id';
    protected $fillable = [
        "dato1",
        "dato2",
        "dato3",
        "dato4",
        "dato5",
        "dato6",
        "dato7",
        "dato8"
    ];


    public function setCreatedAtAtribute($value)
    {
        date_default_timezone_set("America/Lima");
        $this->attributes["created_at"] = Carbon::now();
    }

    public function setUpdateAtAtribute($value)
    {
        date_default_timezone_set("America/Lima");
        $this->attributes["updated_at"] = Carbon::now();
    }
}

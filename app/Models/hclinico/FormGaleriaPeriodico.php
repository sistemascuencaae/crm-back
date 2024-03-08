<?php

namespace App\Models\hclinico;

use App\Models\crm\Galeria;
use App\Models\FormOcupacional;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormGaleriaPeriodico extends Model
{
    use HasFactory;
    protected $table = 'hclinico.form_galeria_periodico';

    protected $fillable = [
    "id",
	"galeria_id",
	"form_id",
    ];

    public function formOcupacional()
    {
        return $this->hasMany(FormOcupacional::class, "form_id","fo_id");
    }
    public function imagenes()
    {
        return $this->hasMany(Galeria::class, "id", "galeria_id");
    }

}

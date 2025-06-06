<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TarifarioGrupo extends Model
{
    protected $table = 'emp_tarifario_grupos';
    protected $primaryKey = 'ta_id';
    public $timestamps = false;
    protected $fillable = ['ta_ubigeo', 'ta_id_departamento', 'ta_id_provincia', 'ta_id_distrito', 'ta_id_grupo', 'ta_id_tarifa', 'ta_id_tarifa_reparto', 'fhCrea', 'userCrea'];
}

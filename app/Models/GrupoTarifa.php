<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrupoTarifa extends Model
{
    protected $table = 'emp_tarifario_grupo_tarifa';
    protected $primaryKey = 'gt_id';
    public $timestamps = false;
    protected $fillable = ['gt_tarifas', 'fhCrea', 'userCrea'];
}

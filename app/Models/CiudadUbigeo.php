<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CiudadUbigeo extends Model
{
    protected $table = 'emp_ciudad_ubigeo';
    protected $primaryKey = 'ubi_id';
    public $timestamps = false;
    protected $fillable = ['ubi_departamento', 'ubi_depid', 'ubi_provincia', 'ubi_provid', 'ubi_distrito', 'ubi_distid', 'ubi_identificador'];
}

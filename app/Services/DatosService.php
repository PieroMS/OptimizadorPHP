<?php

namespace App\Services;

use App\Models\CiudadUbigeo;
use App\Models\GrupoTarifa;
use App\Models\TarifarioGrupo;
use App\Models\Terminal;
use Illuminate\Support\Facades\Log;

class DatosService
{
    public function obtenerDatos()
    {
      $terminales = Terminal::select('ter_id', 'ter_nombre', 'ter_ubigeo')
          ->where('ter_nombre', '!=', '...')
          ->get();

      $datos = [];

      foreach ($terminales as $terminal) {
        $datos[$terminal->ter_id] = [
          'name' => $terminal->ter_nombre,
          'distritos' => $this->obtenerDistritos($terminal->ter_ubigeo),
        ];
      }

      return $datos;
    }

    public function obtenerDistritos($ubigeo_id)
    {
      $ubigeo = CiudadUbigeo::select('ubi_depid', 'ubi_provid')
          ->where('ubi_id', $ubigeo_id)
          ->first();

      if (!$ubigeo) {
          return [];
      }

      $distritos = CiudadUbigeo::select('ubi_distrito', 'ubi_id')
          ->where('ubi_distrito', '!=', '')
          ->where('ubi_depid', $ubigeo->ubi_depid)
          ->where('ubi_provid', $ubigeo->ubi_provid)
          // ->whereIn('ubi_distid', function($query) use ($ubigeo) {
          //     $query->select('ta_id_distrito')
          //       ->from('emp_tarifario_grupos')
          //       ->where('ta_id_departamento', $ubigeo->ubi_depid)
          //       ->where('ta_id_provincia', $ubigeo->ubi_provid);
          // })
          ->get();

      $resultado = [];
      
      foreach ($distritos as $dist) {
          $resultado[] = [
            'ubi_distrito' => $dist->ubi_distrito,
            'ubi_id' => $dist->ubi_id,
            'tarifa_recojo' => $this->obtenerTarifa($dist->ubi_id, 'recojo'),
            'tarifa_reparto' => $this->obtenerTarifa($dist->ubi_id, 'reparto'),
          ];
      }

      return $resultado;
    }

    public function obtenerTarifa($ubi_id, $tipo)
    {
      $campo = $tipo === 'recojo' ? 'ta_id_tarifa_reparto' : 'ta_id_tarifa';
      
      $grupo = TarifarioGrupo::select($campo)
        ->where('ta_id_distrito', $ubi_id)
        ->first();

      if (!$grupo || !$grupo->$campo) {
          return [];
        }

      $gt_id = (int) $grupo->$campo;

      $tarifa = GrupoTarifa::select('gt_tarifas')
          ->where('gt_id', $gt_id)
          ->first();

      return $tarifa ? json_decode($tarifa->gt_tarifas, true) : [];
    }
}

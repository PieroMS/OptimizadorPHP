<?php

namespace App\Services;

use App\Models\CiudadUbigeo;
use App\Models\GrupoTarifa;
use App\Models\TarifarioGrupo;
use App\Models\Terminal;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DatosService
{
    public function obtenerDatos()
    {
        return Cache::remember('terminales_datos', 3600, function () {
            $terminales = Terminal::select('ter_id', 'ter_nombre', 'ter_ubigeo')
                ->where('ter_nombre', '!=', '...')
                ->get();
                
            $datos = [];
            
            $ubigeos_ids = $terminales->pluck('ter_ubigeo')->filter();
            $ubigeos = CiudadUbigeo::select('ubi_id', 'ubi_depid', 'ubi_provid')
                ->whereIn('ubi_id', $ubigeos_ids)
                ->get()
                ->keyBy('ubi_id');
            
            foreach ($terminales as $terminal) {
                $datos[$terminal->ter_id] = [
                    'name' => $terminal->ter_nombre,
                    'distritos' => $this->obtenerDistritosOptimizado($terminal->ter_id, $ubigeos)
                ];
            }
            
            return $datos;
        });
    }

    public function obtenerDistritosOptimizado($terminal_id, $ubigeos = null)
    {
        $cache_key = "distritos_terminal_{$terminal_id}";
        
        return Cache::remember($cache_key, 1800, function () use ($terminal_id, $ubigeos) {
            
            if (!$ubigeos) {
                $terminal = Terminal::select('ter_ubigeo')->where('ter_id', $terminal_id)->first();
                if (!$terminal || !$terminal->ter_ubigeo) {
                    return [];
                }
                
                $ubigeo = CiudadUbigeo::select('ubi_depid', 'ubi_provid')
                    ->where('ubi_id', $terminal->ter_ubigeo)
                    ->first();
            } else {
                $terminal = Terminal::select('ter_ubigeo')->where('ter_id', $terminal_id)->first();
                if (!$terminal || !$terminal->ter_ubigeo) {
                    return [];
                }
                $ubigeo = $ubigeos->get($terminal->ter_ubigeo);
            }
            
            if (!$ubigeo) {
                return [];
            }

            $distritos = DB::select("
                SELECT DISTINCT 
                    cu.ubi_distrito, 
                    cu.ubi_id,
                    tg.ta_id_tarifa,
                    tg.ta_id_tarifa_reparto
                FROM emp_ciudad_ubigeo cu
                INNER JOIN emp_tarifario_grupos tg ON tg.ta_ubigeo = cu.ubi_id
                WHERE cu.ubi_depid = ? 
                    AND cu.ubi_provid = ? 
                    AND cu.ubi_distrito != ''
                    AND cu.ubi_distrito IS NOT NULL
            ", [$ubigeo->ubi_depid, $ubigeo->ubi_provid]);

            if (empty($distritos)) {
                $distritos = DB::select("
                    SELECT DISTINCT 
                        cu.ubi_distrito, 
                        cu.ubi_id,
                        NULL as ta_id_tarifa,
                        NULL as ta_id_tarifa_reparto
                    FROM emp_ciudad_ubigeo cu
                    WHERE cu.ubi_depid = ? 
                        AND cu.ubi_provid = ? 
                        AND cu.ubi_distrito != ''
                        AND cu.ubi_distrito IS NOT NULL
                ", [$ubigeo->ubi_depid, $ubigeo->ubi_provid]);
            }

            if (empty($distritos)) {
                return [];
            }

            $gt_ids = [];
            foreach ($distritos as $dist) {
                if ($dist->ta_id_tarifa) $gt_ids[] = $dist->ta_id_tarifa;
                if ($dist->ta_id_tarifa_reparto) $gt_ids[] = $dist->ta_id_tarifa_reparto;
            }
            
            $gt_ids = array_unique(array_filter($gt_ids));
            $tarifas = [];
            
            if (!empty($gt_ids)) {
                $tarifas_db = GrupoTarifa::select('gt_id', 'gt_tarifas')
                    ->whereIn('gt_id', $gt_ids)
                    ->get()
                    ->keyBy('gt_id');
                    
                foreach ($tarifas_db as $tarifa) {
                    $tarifas[$tarifa->gt_id] = json_decode($tarifa->gt_tarifas, true) ?: [];
                }
            }

            $resultado = [];
            foreach ($distritos as $dist) {
                $resultado[] = [
                    'ubi_distrito' => $dist->ubi_distrito,
                    'ubi_id' => $dist->ubi_id,
                    'tarifa_recojo' => $this->obtenerTarifaRapida($dist->ubi_id, 'recojo', $dist, $tarifas),
                    'tarifa_reparto' => $this->obtenerTarifaRapida($dist->ubi_id, 'reparto', $dist, $tarifas),
                ];
            }
            
            return $resultado;
        });
    }

    private function obtenerTarifaRapida($ubi_id, $tipo, $distrito_data, $tarifas_precargadas)
    {
        if (isset($distrito_data->ta_id_tarifa) || isset($distrito_data->ta_id_tarifa_reparto)) {
            $pos = strpos($ubi_id, '1501');
            $gt_id = null;
            
            if ($pos !== false) {
                if ($tipo === 'recojo') {
                    $gt_id = $distrito_data->ta_id_tarifa;
                } elseif ($tipo === 'reparto') {
                    $gt_id = $distrito_data->ta_id_tarifa_reparto;
                }
            } else {
                $gt_id = $distrito_data->ta_id_tarifa;
            }
            
            if ($gt_id && isset($tarifas_precargadas[$gt_id])) {
                return $tarifas_precargadas[$gt_id];
            }
        }
        
        $cache_key = "tarifa_{$ubi_id}_{$tipo}";
        return Cache::remember($cache_key, 1800, function () use ($ubi_id, $tipo) {
            return $this->obtenerTarifa($ubi_id, $tipo);
        });
    }

    public function obtenerTarifa($ubi_id, $tipo)
    {
        $grupo = TarifarioGrupo::select('ta_id_tarifa', 'ta_id_tarifa_reparto')
            ->where('ta_ubigeo', $ubi_id)
            ->first();
        
        if (!$grupo) {
            $ubi_str = (string)$ubi_id;
            if (strlen($ubi_str) >= 6) {
                $dep_code = (int)substr($ubi_str, 0, 2);
                $prov_code = (int)substr($ubi_str, 2, 2);
                $dist_code = (int)substr($ubi_str, 4, 2);
                
                $grupo = TarifarioGrupo::select('ta_id_tarifa', 'ta_id_tarifa_reparto')
                    ->where('ta_id_departamento', $dep_code)
                    ->where(function($query) use ($prov_code, $dist_code) {
                        $query->where('ta_id_provincia', $prov_code)
                              ->orWhere('ta_id_distrito', $dist_code);
                    })
                    ->first();
                    
                if (!$grupo) {
                    $grupo = TarifarioGrupo::select('ta_id_tarifa', 'ta_id_tarifa_reparto')
                        ->where('ta_id_departamento', $dep_code)
                        ->first();
                }
            }
        }
        
        if (!$grupo) {
            return [];
        }

        $pos = strpos($ubi_id, '1501');
        $gt_id = null;
        
        if ($pos !== false) {
            if ($tipo === 'recojo') {
                $gt_id = $grupo->ta_id_tarifa;
            } elseif ($tipo === 'reparto') {
                $gt_id = $grupo->ta_id_tarifa_reparto;
            }
        } else {
            $gt_id = $grupo->ta_id_tarifa;
        }

        if (!$gt_id) {
            return [];
        }

        $tarifa = GrupoTarifa::select('gt_tarifas')
            ->where('gt_id', $gt_id)
            ->first();

        if (!$tarifa) {
            return [];
        }

        return json_decode($tarifa->gt_tarifas, true) ?: [];
    }

    public function limpiarCache($terminal_id = null)
    {
        if ($terminal_id) {
            Cache::forget("distritos_terminal_{$terminal_id}");
        } else {
            Cache::forget('terminales_datos');
            Cache::flush();
        }
    }

    public function debugTerminal($terminal_id)
    {
        $terminal = Terminal::where('ter_id', $terminal_id)->first();

        $ubigeo = CiudadUbigeo::where('ubi_id', $terminal->ter_ubigeo)->first();

        $tarifarios = TarifarioGrupo::where('ta_id_departamento', $ubigeo->ubi_depid)
            ->where('ta_id_provincia', $ubigeo->ubi_provid)
            ->take(5)
            ->get();

        return [
            'terminal' => $terminal,
            'ubigeo' => $ubigeo,
            'tarifarios_count' => $tarifarios->count(),
            'tarifarios' => $tarifarios
        ];
    }
}
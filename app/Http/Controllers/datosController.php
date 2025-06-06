<?php

namespace App\Http\Controllers;

use App\Services\DatosService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Datos",
 *     description="API Endpoints para la devolucion de datos"
 * )
 */
class datosController extends Controller
{
    protected $datosService;

    public function __construct(DatosService $datosService)
    {
        $this->datosService = $datosService;
    }

    /**
     * @OA\Get(
     *     path="/api/datos",
     *     tags={"Datos"},
     *     summary="Obtener los datos generales",
     *     description="Obtiene una lista de todos los datos necesarios",
     *     operationId="getData",
     *     @OA\Response(
     *         response=200,
     *         description="Lista obtenida correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="clients", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor"
     *     )
     * )
     */
    public function index()
    {
        $datos = $this->datosService->obtenerDatos();
        return response()->json($datos);
    }
}

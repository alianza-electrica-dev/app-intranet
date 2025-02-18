<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CotizacionController extends Controller
{
    public function store(Request $request, $company, $cliente)
    {
        if (!$this->validarSesion($company)) {
            return response()->json(['error' => 'No has iniciado sesión con esta empresa o la sesión ha expirado.'], 401);
        }

        $validatedData = $request->validate([
            'productos' => 'required|array',
            'productos.*.codigonum' => 'required|string',
            'productos.*.codigosat' => 'required|string',
            'productos.*.codigo' => 'required|string',
            'productos.*.descripcion' => 'required|string',
            'productos.*.precio_interno' => 'required|numeric',
            'productos.*.precio' => 'required|numeric',
            'productos.*.cantidad' => 'required|integer',
            'productos.*.precio_lista' => 'required|numeric',
            'productos.*.moneda' => 'required|string',
            'productos.*.factor' => 'required|numeric',
            'productos.*.precio_venta' => 'required|numeric',
            'productos.*.precio_unitario' => 'required|numeric',
            'productos.*.tipo_entrega' => 'required|string',
            'productos.*.marca' => 'required|string',
            'productos.*.certificaciones' => 'nullable|string',
            'productos.*.unidad_medida' => 'required|string',
            'productos.*.costo_promedio' => 'required|numeric',
            'productos.*.costo_reposicion' => 'required|numeric',
            'productos.*.margen_contado' => 'required|numeric',
            'productos.*.margen_credito' => 'required|numeric',
            'productos.*.existencia_almacen' => 'required|array',
            'productos.*.existencia_proveedor' => 'required|array',
            'productos.*.existencia_recibir' => 'required|array',
            'productos.*.producto_comprometido' => 'required|array',
        ]);

        return response()->json($this->formatResponse($validatedData), 200);
    }

    private function formatResponse(array $validatedData): array
{
    $response = [];
    foreach ($validatedData['productos'] as $producto) {
        $response[] = [
            'numero_producto' => $producto['codigonum'],
            'Codigo SAT' => $producto['codigosat'],
            'cardcode' => $producto['codigo'],
            'descripcion' => $producto['descripcion'],
            'tipo_entrega' => $producto['tipo_entrega'],
            'cantidad' => $producto['cantidad'],
            'unidad_medida' => $producto['unidad_medida'],
            'precio_lista' => $producto['precio_lista'],
            'importe' => $producto['precio_venta'] * $producto['cantidad'],
            'fecha de Documento' => date('Y-m-d'),
            'ubicacion' => 'Alfredo del Mazo No. 9 Int 2 Col. Ex Hacienda del Pedregal I y II, Cd. López Mateos, Atizapán de Zaragoza 
            Mex. C.P. 52916',
            'Web' => 'fgelectrical.com'
            /*
            Falta pro agregar:
            Tipo de cambio
            ____________________
            cliente
            no.cliente
            moneda: USD
            Domicilio c.p
            RFC
            ____________________
            Vendedor asignado
            email
            telefono
            celular
            ____________________
            Vendedor cotiza
            email
            telefono
            celular           
            
            */
        ];
    }
    return $response;
}


    private function validarSesion($company): bool
    {
        $loggedCompany = session('companyDb');
        return $loggedCompany && strtoupper($company) === str_replace('SBO_', '', strtoupper($loggedCompany));
    }
}

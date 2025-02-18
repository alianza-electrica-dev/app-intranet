<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CotizacionController extends Controller
{
    public function crearCotizacion(Request $request, $company, $cliente)
    {
        if (!$this->validarSesion($company)) {
            return response()->json(['error' => 'No has iniciado sesión con esta empresa o la sesión ha expirado.'], 401);
        }
    
        $validatedData = $request->validate([
            'CardCode' => 'required|string',
            'DocumentLines' => 'required|array|min:1',
            'DocumentLines.*.ItemCode' => 'required|string',
            'DocumentLines.*.Quantity' => 'required|numeric|min:1',
            'DocumentLines.*.TaxCode' => 'required|string',
            'DocumentLines.*.UnitPrice' => 'required|numeric|min:0',
        ]);
    
        $validatedData['CardType'] = 'cCustomer';
    
        try {
            $response = Http::sapSL()->post('Quotations', $validatedData);
            
            if ($response->successful()) {
                return response()->json(['message' => 'Cotización creada con éxito', 'data' => $response->json()], 201);
            }
            
            return response()->json(['error' => 'Error al crear la cotización', 'details' => $response->json()], $response->status());
        } catch (\Exception $e) {
            Log::error('Error al conectar con SAP: ' . $e->getMessage());
            return response()->json(['error' => 'Ocurrió un error al procesar la cotización', 'details' => $e->getMessage()], 500);
        }
    }
    
    public function consultarCotizacion($company, $docEntry)
    {
        if (!$this->validarSesion($company)) {
            return response()->json(['error' => 'No has iniciado sesión con esta empresa o la sesión ha expirado.'], 401);
        }

        try {
            $response = Http::sapSL()->get("Quotations({$docEntry})");
            
            if ($response->successful()) {
                return response()->json($response->json(), 200);
            }
            
            return response()->json(['error' => 'No se encontró la cotización'], 404);
        } catch (\Exception $e) {
            Log::error('Error al conectar con SAP: ' . $e->getMessage());
            return response()->json(['error' => 'Ocurrió un error al consultar la cotización', 'details' => $e->getMessage()], 500);
        }
    }

    private function validarSesion($company)
    {
        return strtoupper($company) === str_replace('SBO_', '', strtoupper(session('companyDb', '')));
    }
}

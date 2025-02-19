<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuotationController extends Controller
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
            'DocumentLines.*.DeliveryTime' => 'required|string',
        ]);
    
        $validatedData['CardType'] = 'cCustomer';
    
        try {
            $response = Http::sapSL()->post('Quotations', $validatedData);
            
            if ($response->successful()) {
                $cotizacion = $response->json();
                
                foreach ($cotizacion['DocumentLines'] as &$linea) {
                    $producto = $this->obtenerDetallesProducto($linea['ItemCode']);
                    $linea = array_merge($linea, $producto);
                }
                
                return response()->json(['message' => 'Cotización creada con éxito', 'data' => $cotizacion], 201);
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
                $cotizacion = $response->json();
                foreach ($cotizacion['DocumentLines'] as &$linea) {
                    $producto = $this->obtenerDetallesProducto($linea['ItemCode']);
                    $linea = array_merge($linea, $producto);
                }
                return response()->json($cotizacion, 200);
            }
            
            return response()->json(['error' => 'No se encontró la cotización'], 404);
        } catch (\Exception $e) {
            Log::error('Error al conectar con SAP: ' . $e->getMessage());
            return response()->json(['error' => 'Ocurrió un error al consultar la cotización', 'details' => $e->getMessage()], 500);
        }
    }

    private function obtenerDetallesProducto($itemCode)
    {
        try {
            $response = Http::sapSL()->get("Items({$itemCode})");
            if ($response->successful()) {
                $producto = $response->json();
                
                $maxStock = 0;
                $minStock = 0;
                foreach ($producto['ItemWarehouseInfoCollection'] ?? [] as $almacen) {
                    $maxStock += $almacen['MaxStock'] ?? 0;
                    $minStock += $almacen['MinStock'] ?? 0;
                }
                
                $unitPrice = 0;
                foreach ($producto['ItemPrices'] ?? [] as $precio) {
                    $unitPrice = $precio['Price'] ?? 0;
                    break;
                }
                
                return [
                    'Description' => $producto['ItemName'] ?? '',
                    'ExchangeRate' => $producto['Currency'] ?? 'MXN',
                    'ListPrice' => $unitPrice,
                    'Discount' => $producto['DiscountPercent'] ?? 0,
                    'SalesPrice' => $unitPrice * (1 - ($producto['DiscountPercent'] ?? 0) / 100),
                    'UNM' => $producto['UoM'] ?? '',
                    'Marca' => $producto['ManufacturerName'] ?? '',
                    'Certifications' => $producto['Certifications'] ?? '',
                    'StockMax' => $maxStock,
                    'StockMin' => $minStock,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error al obtener detalles del producto: ' . $e->getMessage());
        }
        return [];
    }

    private function validarSesion($company)
    {
        return strtoupper($company) === str_replace('SBO_', '', strtoupper(session('companyDb', '')));
    }
}

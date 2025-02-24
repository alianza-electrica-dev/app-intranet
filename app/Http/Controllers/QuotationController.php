<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuotationController extends Controller
{
    public function createQuote(Request $request, $company, $cliente)
    {
        if (!$this->validateSession()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not logged in with this company or your session has expired.',
                'data' => null
            ], 401);
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
                $quote = $response->json();
                
                foreach ($quote['DocumentLines'] as &$line) {
                    $product = $this->getProductDetails($line['ItemCode']);
                    $line = array_merge($line, $product);
                }
                
                return response()->json([
                    'success' => true,
                    'message' => 'Quote created successfully',
                    'Quote creation' => $quote
                ], 201);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Error creating quote',
                'Quote creation error' => $response->json()
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Error connecting to SAP: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the quote',
                'error connecting to sap service' => null,
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function consultQuote($company, $docEntry)
    {
        if (!$this->validateSession()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not logged in with this company or your session has expired.',
                'data' => null
            ], 401);
        }

        try {
            $response = Http::sapSL()->get("Quotations({$docEntry})");
            
            if ($response->successful()) {
                $quote = $response->json();
                foreach ($quote['DocumentLines'] as &$line) {
                    $product = $this->getProductDetails($line['ItemCode']);
                    $line = array_merge($line, $product);
                }
                return response()->json([
                    'success' => true,
                    'message' => 'Quote retrieved successfully',
                    'Quote recovered' => $quote
                ], 200);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Quote not found',
                'Quote not found' => null
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error connecting to SAP: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while checking the quote',
                'error connecting to sap service' => null,
                'details' => $e->getMessage()
            ], 500);
        }
    }

    private function getProductDetails($itemCode)
    {
        try {
            $response = Http::sapSL()->get("Items({$itemCode})");
            if ($response->successful()) {
                $product = $response->json();
                
                $maxStock = 0;
                $minStock = 0;
                foreach ($product['ItemWarehouseInfoCollection'] ?? [] as $almacen) {
                    $maxStock += $almacen['MaxStock'] ?? 0;
                    $minStock += $almacen['MinStock'] ?? 0;
                }
                
                $unitPrice = 0;
                foreach ($product['ItemPrices'] ?? [] as $precio) {
                    $unitPrice = $precio['Price'] ?? 0;
                    break;
                }
                
                return [
                    'Description' => $product['ItemName'] ?? '',
                    'ExchangeRate' => $product['Currency'] ?? 'MXN',
                    'ListPrice' => $unitPrice,
                    'Discount' => $product['DiscountPercent'] ?? 0,
                    'SalesPrice' => $unitPrice * (1 - ($product['DiscountPercent'] ?? 0) / 100),
                    'UNM' => $product['UoM'] ?? '',
                    'Marca' => $product['ManufacturerName'] ?? '',
                    'Certifications' => $product['Certifications'] ?? '',
                    'StockMax' => $maxStock,
                    'StockMin' => $minStock,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error getting product details: ' . $e->getMessage());
        }
        return [];
    }

    private function validateSession(): bool
    {
        return !empty(session('companyDb', ''));
    }
}

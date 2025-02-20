<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class ProductsController extends Controller
{
    public function getProducts(string $company, string $identifier): JsonResponse
    {
        if (!$this->validateSession($company)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not logged in with this company or your session has expired',
                'data' => null
            ], 401);
        }

        try {
            $products = $this->gettingProducts();
            if ($products->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No products found.',
                    'Product' => null
                ], 404);
            }

            $exactProduct = $products->firstWhere('TreeCode', $identifier);
            if ($exactProduct) {
                return response()->json([
                    'success' => true,
                    'message' => 'Product found.',
                    'Product' => $this->formatProduct($exactProduct)
                ], 200);
            }

            foreach ($products as $product) {
                foreach ($product['ProductTreeLines'] as $line) {
                    if ($line['ItemName'] === $identifier) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Product found.',
                            'Product' => $this->formatProduct($product)
                        ], 200);
                    }
                }
            }

            $filteredProducts = $this->FilterProducts($products, $identifier);
            $filteredLines = $this->filterLinesProducts($products, $identifier);

            if ($filteredProducts->isNotEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Filtered products found.',
                    'Product' => $filteredProducts
                ], 200);
            }

            if ($filteredLines->isNotEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Filtered product lines found.',
                    'Product' => $filteredLines
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
                'Product' => null
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error in request to SAP Business One: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while connecting to the service',
                'Product' => null,
                'details' => $e->getMessage()
            ], 500);
        }
    }

    private function validateSession(string $company): bool
    {
        return strtoupper($company) === str_replace('SBO_', '', strtoupper(session('companyDb', '')));
    }

    private function gettingProducts(): Collection
    {
        $response = Http::sapSL()->get('ProductTrees');
    
        if ($response->status() === 401) {
            throw new \Exception('Session expired. Please log in again.');
        }
    
        return collect($response->json()['value'] ?? []);
    }
    
    private function FilterProducts(Collection $products, string $identifier): Collection
    {
        return $products->filter(fn($product) => stripos($product['TreeCode'], $identifier) !== false)
            ->map(fn($product) => ['TreeCode' => $product['TreeCode']])
            ->values();
    }

    private function filterLinesProducts(Collection $products, string $identifier): Collection
    {
        return $products->flatMap(fn($product) => collect($product['ProductTreeLines'])
            ->filter(fn($line) => stripos($line['ItemName'], $identifier) !== false)
            ->map(fn($line) => ['ItemName' => $line['ItemName']]))
            ->values();
    }

    private function formatProduct(array $product): array
    {
        return [
            'TreeCode' => $product['TreeCode'] ?? null,
            'TreeType' => $product['TreeType'] ?? null,
            'Quantity' => $product['Quantity'] ?? null,
            'ProductDescription' => $product['ProductDescription'] ?? null,
            'ProductTreeLines' => collect($product['ProductTreeLines'] ?? [])->map(fn($line) => [
                'ItemCode' => $line['ItemCode'] ?? null,
                'ItemName' => $line['ItemName'] ?? null,
                'Quantity' => $line['Quantity'] ?? null,
                'Warehouse' => $line['Warehouse'] ?? null,
                'Price' => $line['Price'] ?? 0.0,
            ])->values()
        ];
    }
}

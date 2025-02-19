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
            return $this->errorResponse('You are not logged in with this company or your session has expired', 401);
        }

        try {
            $products = $this->gettingProducts();
            if ($products->isEmpty()) {
                return $this->errorResponse('No products found.', 404);
            }

            $exactProduct = $products->firstWhere('TreeCode', $identifier);
            if ($exactProduct) {
                return $this->successResponse($this->formatProduct($exactProduct));
            }

            foreach ($products as $product) {
                foreach ($product['ProductTreeLines'] as $line) {
                    if ($line['ItemName'] === $identifier) {
                        return $this->successResponse($this->formatProduct($product));
                    }
                }
            }

            $filteredProducts = $this->FilterProducts($products, $identifier);
            $filteredLines = $this->filterLinesProducts($products, $identifier);

            if ($filteredProducts->isNotEmpty()) {
                return $this->successResponse($filteredProducts);
            }

            if ($filteredLines->isNotEmpty()) {
                return $this->successResponse($filteredLines);
            }

            return $this->errorResponse('Product not found.', 404);
        } catch (\Exception $e) {
            Log::error('Error in request to SAP Business One: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while connecting to the service', 500, $e->getMessage());
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

    private function successResponse($data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    private function errorResponse(string $message, int $status, string $details = ''): JsonResponse
    {
        return response()->json(['error' => $message, 'details' => $details], $status);
    }
}
?>
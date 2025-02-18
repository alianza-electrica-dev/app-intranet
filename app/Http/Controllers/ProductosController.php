<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class ProductosController extends Controller
{
    public function getProductos(string $company, string $identifier): JsonResponse
    {
        if (!$this->validarSesion($company)) {
            return $this->errorResponse('No has iniciado sesión con esta empresa o la sesión ha expirado.', 401);
        }

        try {
            $productos = $this->obtenerProductos();
            if ($productos->isEmpty()) {
                return $this->errorResponse('No se encontraron productos.', 404);
            }

            $productoExacto = $productos->firstWhere('TreeCode', $identifier);
            if ($productoExacto) {
                return $this->successResponse($this->formatearProducto($productoExacto));
            }

            foreach ($productos as $producto) {
                foreach ($producto['ProductTreeLines'] as $line) {
                    if ($line['ItemName'] === $identifier) {
                        return $this->successResponse($this->formatearProducto($producto));
                    }
                }
            }

            $filteredProducts = $this->filtrarProductos($productos, $identifier);
            $filteredLines = $this->filtrarLineasProductos($productos, $identifier);

            if ($filteredProducts->isNotEmpty()) {
                return $this->successResponse($filteredProducts);
            }

            if ($filteredLines->isNotEmpty()) {
                return $this->successResponse($filteredLines);
            }

            return $this->errorResponse('Producto no encontrado.', 404);
        } catch (\Exception $e) {
            Log::error('Error en la solicitud a SAP Business One: ' . $e->getMessage());
            return $this->errorResponse('Ocurrió un error al conectarse al servicio', 500, $e->getMessage());
        }
    }

    private function validarSesion(string $company): bool
    {
        return strtoupper($company) === str_replace('SBO_', '', strtoupper(session('companyDb', '')));
    }

    private function obtenerProductos(): Collection
    {
        $response = Http::sapSL()->get('ProductTrees');

        if ($response->status() === 401) {
            return response()->json(['error' => 'Sesión expirada. Por favor inicia sesión nuevamente.'], 401)->send();
        }

        return collect($response->json()['value'] ?? []);
    }

    private function filtrarProductos(Collection $productos, string $identifier): Collection
    {
        return $productos->filter(fn($product) => stripos($product['TreeCode'], $identifier) !== false)
            ->map(fn($product) => ['TreeCode' => $product['TreeCode']])
            ->values();
    }

    private function filtrarLineasProductos(Collection $productos, string $identifier): Collection
    {
        return $productos->flatMap(fn($product) => collect($product['ProductTreeLines'])
            ->filter(fn($line) => stripos($line['ItemName'], $identifier) !== false)
            ->map(fn($line) => ['ItemName' => $line['ItemName']]))
            ->values();
    }

    private function formatearProducto(array $producto): array
    {
        return [
            'TreeCode' => $producto['TreeCode'] ?? null,
            'TreeType' => $producto['TreeType'] ?? null,
            'Quantity' => $producto['Quantity'] ?? null,
            'ProductDescription' => $producto['ProductDescription'] ?? null,
            'ProductTreeLines' => collect($producto['ProductTreeLines'] ?? [])->map(fn($line) => [
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
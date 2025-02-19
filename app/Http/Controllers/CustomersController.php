<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;

class CustomersController extends Controller
{
    public function getClientes(string $company, string $identifier): JsonResponse
    {
        if (!$this->validarSesion($company)) {
            return $this->errorResponse('No has iniciado sesión con esta empresa o la sesión ha expirado.', 401);
        }

        $clientes = $this->consultarClientes($identifier);
        if (empty($clientes)) {
            return $this->errorResponse('Cliente no encontrado.', 404);
        }

        return count($clientes) === 1 && $this->esClienteExacto($clientes[0], $identifier)
            ? $this->successResponse($this->formatearCliente($clientes[0]))
            : $this->successResponse($this->listarClientes($clientes, is_numeric($identifier)));
    }

    public function getClienteDetalle(string $company, string $cardCode): JsonResponse
    {
        if (!$this->validarSesion($company)) {
            return $this->errorResponse('No has iniciado sesión con esta empresa o la sesión ha expirado.', 401);
        }

        $clientes = $this->consultarClientes($cardCode);
        return empty($clientes)
            ? $this->errorResponse('Cliente no encontrado.', 404)
            : $this->successResponse($this->formatearCliente($clientes[0]));
    }

    private function validarSesion(string $company): bool
    {
        return strtoupper($company) === str_replace('SBO_', '', strtoupper(session('companyDb', '')));
    }

    private function consultarClientes(string $identifier): array
    {
        $campoFiltro = is_numeric($identifier) ? 'CardCode' : 'CardName';
        $response = Http::sapSL()->get('BusinessPartners', [
            '$filter' => "contains($campoFiltro, '$identifier') and CardType eq 'C'"
        ]);

        return $response->successful() ? $response->json()['value'] ?? [] : [];
    }

    private function formatearCliente(array $cliente): array
    {
        return [
            'CardCode'      => $cliente['CardCode'] ?? null,
            'CardName'      => $cliente['CardName'] ?? null,
            'Fiscal'        => $this->obtenerDireccion($cliente['BPAddresses'] ?? [], 'FISCAL'),
            'Envio'         => $this->obtenerDireccion($cliente['BPAddresses'] ?? [], 'ENTREGA'),
            'PriceListName' => $this->obtenerNombreListaPrecios($cliente['PriceListNum'] ?? null),
            'PriceListNum'  => $cliente['PriceListNum'] ?? null
        ];
    }

    private function obtenerNombreListaPrecios(?int $priceListNum): ?string
    {
        if (!$priceListNum) return null;
        $response = Http::sapSL()->get("PriceLists({$priceListNum})");
        return $response->successful() ? $response->json()['PriceListName'] ?? null : null;
    }

    private function obtenerDireccion(array $direcciones, string $tipo): array
    {
        $direccion = collect($direcciones)->firstWhere('AddressName', $tipo) ?? [];
        return [
            'Calle'          => $direccion['Street'] ?? null,
            'Estado'         => $direccion['State'] ?? null,
            'CodigoPostal'   => $direccion['ZipCode'] ?? null,
            'Colonia'        => $direccion['Block'] ?? null,
            'NumeroInterior' => $direccion['BuildingFloorRoom'] ?? null,
            'NumeroExterior' => $direccion['StreetNo'] ?? null,
        ];
    }

    private function listarClientes(array $clientes, bool $esNumerico): array
    {
        return array_map(fn($c) => $esNumerico ? ['CardCode' => $c['CardCode']] : ['CardName' => $c['CardName']], $clientes);
    }

    private function esClienteExacto(array $cliente, string $identifier): bool
    {
        return in_array($identifier, [$cliente['CardCode'], $cliente['CardName']], true);
    }

    private function successResponse(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json(['error' => $message], $status);
    }
}

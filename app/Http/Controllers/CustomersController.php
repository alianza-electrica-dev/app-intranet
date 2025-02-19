<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class CustomersController extends Controller
{
    public function getCustomers(string $company, string $identifier): JsonResponse
    {
        if (!$this->validateSession($company)) {
            return $this->errorResponse('You are not logged in with this company or your session has expired.', 401);
        }

        try {
            $customers = $this->gettingCustomers();
            if ($customers->isEmpty()) {
                return $this->errorResponse('No clients were found.', 404);
            }
            $customerExact = $customers->first(function ($customer) use ($identifier) {
                return $customer['CardCode'] === $identifier || $customer['CardName'] === $identifier;
            });

            if ($customerExact) {
                return $this->successResponse($this->formatCustomer($customerExact));
            }
            $customersFiltered = $customers->filter(function ($customer) use ($identifier) {
                return stripos($customer['CardCode'], $identifier) !== false
                    || stripos($customer['CardName'], $identifier) !== false;
            })->map(function ($customer) {
                return [
                    'CardCode' => $customer['CardCode'],
                    'CardName' => $customer['CardName']
                ];
            })->values();

            if ($customersFiltered->isNotEmpty()) {
                return $this->successResponse($customersFiltered->toArray());
            }

            return $this->errorResponse('Customer not found.', 404);
        } catch (\Exception $e) {
            Log::error('Error in request to SAP Business One: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while connecting to the service', 500);
        }
    }

    public function getCustomersDetail(string $company, string $cardCode): JsonResponse
    {
        if (!$this->validateSession($company)) {
            return $this->errorResponse('You are not logged in with this company or your session has expired.', 401);
        }

        try {
            $customers = $this->gettingCustomers();
            if ($customers->isEmpty()) {
                return $this->errorResponse('No clients were found.', 404);
            }
            $customerExact = $customers->first(function ($customer) use ($cardCode) {
                return $customer['CardCode'] === $cardCode;
            });

            if ($customerExact) {
                return $this->successResponse($this->formatCustomer($customerExact));
            }

            return $this->errorResponse('Customer not found.', 404);
        } catch (\Exception $e) {
            Log::error('Error in request to SAP Business One: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while connecting to the service', 500);
        }
    }

    private function validateSession(string $company): bool
    {
        return strtoupper($company) === str_replace('SBO_', '', strtoupper(session('companyDb', '')));
    }

    private function gettingCustomers(): Collection
    {
        $response = Http::sapSL()->get('BusinessPartners', [
            '$filter' => "CardType eq 'C'"
        ]);

        if (!$response->successful()) {
            Log::error('Error getting customers: ' . $response->body());
            return collect([]);
        }

        return collect($response->json()['value'] ?? []);
    }

    private function formatCustomer(array $customer): array
    {
        return [
            'CardCode'      => $customer['CardCode'] ?? null,
            'CardName'      => $customer['CardName'] ?? null,
            'Fiscal'        => $this->getAddress($customer['BPAddresses'] ?? [], 'FISCAL'),
            'Envio'         => $this->getAddress($customer['BPAddresses'] ?? [], 'ENTREGA'),
            'PriceListName' => $this->getPriceListName($customer['PriceListNum'] ?? null),
            'PriceListNum'  => $customer['PriceListNum'] ?? null
        ];
    }

    private function getPriceListName(?int $priceListNum): ?string
    {
        if (!$priceListNum) {
            return null;
        }
        $response = Http::sapSL()->get("PriceLists({$priceListNum})");
        return $response->successful() ? $response->json()['PriceListName'] ?? null : null;
    }

    private function getAddress(array $addresses, string $tipo): array
    {
        $address = collect($addresses)->firstWhere('AddressName', $tipo) ?? [];
        return [
            'Street'          => $address['Street'] ?? null,
            'State'         => $address['State'] ?? null,
            'ZipCode'   => $address['ZipCode'] ?? null,
            'Block'        => $address['Block'] ?? null,
            'BuildingFloorRoom' => $address['BuildingFloorRoom'] ?? null,
            'StreetNo' => $address['StreetNo'] ?? null,
        ];
    }

    private function successResponse($data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json(['error' => $message], $status);
    }
}

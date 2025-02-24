<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriceListController extends Controller
{
    public function getPriceLists($cardCode)
    {
        if (!$this->validateSession()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not logged in or your session has expired.',
                'error'   => 'Unauthorized access'
            ], 401);
        }

        if (!$cardCode) {
            return response()->json([
                'success' => false,
                'message' => 'You must provide a CardCode.',
                'error'   => 'Invalid input'
            ], 400);
        }

        try {
            $customer = $this->gettingCustomer($cardCode);
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found.',
                ], 404);
            }

            $priceListNum = $customer['PriceListNum'] ?? null;
            if (!$priceListNum) {
                return response()->json([
                    'success' => false,
                    'message' => 'The customer does not have a price list assigned.',
                ], 404);
            }

            return $this->getPriceList($priceListNum);
        } catch (\Exception $e) {
            Log::error('Error in request to SAP Business One: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while connecting to the service.',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    private function validateSession(): bool
    {
        return !empty(session('companyDb', ''));
    }

    private function gettingCustomer($cardCode)
    {
        $response = Http::sapSL()->get('BusinessPartners', [
            '$filter' => "(CardCode eq '$cardCode' or CardName eq '$cardCode') and CardType eq 'C'"
        ]);

        if ($response->status() === 401) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please log in again.',
                'error'   => 'Unauthorized access'
            ], 401);
        }

        return collect($response->json()['value'] ?? [])->first();
    }

    private function getPriceList($priceListNum)
    {
        $response = Http::sapSL()->get("PriceLists({$priceListNum})");

        if ($response->successful()) {
            return response()->json([
                'success'      => true,
                'message'      => 'Price list retrieved successfully.',
                'PriceListNum' => $response->json()
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Error getting price list.',
            'error'   => $response->json()
        ], $response->status());
    }
}

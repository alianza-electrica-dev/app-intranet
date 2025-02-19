<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriceListController extends Controller
{
    public function getPriceLists($company, $cardCode)
    {
        if (!$this->validateSession($company)) {
            return response()->json(['error' => 'You are not logged in with this company or your session has expired.'], 401);
        }

        if (!$cardCode) {
            return response()->json(['error' => 'You must provide a CardCode.'], 400);
        }

        try {
            $customer = $this->gettingCustomer($cardCode);
            if (!$customer) {
                return response()->json(['message' => 'Customer not found.'], 404);
            }

            $priceListNum = $customer['PriceListNum'] ?? null;
            if (!$priceListNum) {
                return response()->json(['error' => 'The customer does not have a price list assigned.'], 404);
            }

            return $this->getPriceList($priceListNum);
        } catch (\Exception $e) {
            Log::error('Error in request to SAP Business One: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while connecting to the service', 'details' => $e->getMessage()], 500);
        }
    }

    private function validateSession($company)
    {
        $loggedCompany = session('companyDb');
        return $loggedCompany && strtoupper($company) === str_replace('SBO_', '', strtoupper($loggedCompany));
    }

    private function gettingCustomer($cardCode)
    {
        $response = Http::sapSL()->get('BusinessPartners', [
            '$filter' => "(CardCode eq '$cardCode' or CardName eq '$cardCode') and CardType eq 'C'"
        ]);

        if ($response->status() === 401) {
            response()->json(['error' => 'Session expired. Please log in again.'], 401)->send();
            exit;
        }

        return collect($response->json()['value'] ?? [])->first();
    }

    private function getPriceList($priceListNum)
    {
        $response = Http::sapSL()->get("PriceLists({$priceListNum})");

        return $response->successful()
            ? response()->json($response->json(), 200)
            : response()->json(['error' => 'Error getting price list', 'details' => $response->json()], $response->status());
    }
}

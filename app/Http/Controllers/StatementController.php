<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StatementController extends Controller
{
    public function getAccountStatus($company, $identifier)
    {
        if (!$this->validateSession($company)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not logged in with this company or your session has expired.',
                'data' => null
            ], 401);
        }

        if (!$identifier) {
            return response()->json([
                'success' => false,
                'message' => 'You must provide a CardCode or CardName.',
                'data' => null
            ], 400);
        }

        try {
            $customer = $this->consultCustomer($identifier);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found.',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Account status retrieved successfully.',
                'data' => $this->formatAccountStatus($customer)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in request to SAP Business One: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while connecting to the service',
                'data' => null,
                'details' => $e->getMessage()
            ], 500);
        }
    }

    private function validateSession($company)
    {
        $loggedCompany = session('companyDb');
        return $loggedCompany && strtoupper($company) === str_replace('SBO_', '', strtoupper($loggedCompany));
    }

    private function consultCustomer($identifier)
    {
        $response = Http::sapSL()->get('BusinessPartners', [
            '$filter' => "(CardCode eq '$identifier' or CardName eq '$identifier')"
        ]);

        if ($response->status() === 401) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please log in again.',
                'data' => null
            ], 401);
        }

        return collect($response->json()['value'] ?? [])->first();
    }

    private function formatAccountStatus($customer)
    {
        $currentBalance = $customer['CurrentAccountBalance'] ?? 0;
        $creditLimit = $customer['CreditLimit'] ?? 0;
        $lastPaymentDate = $this->getLastPaymentDate($customer['CardCode']);

        return [
            'CardCode'              => $customer['CardCode'],
            'CardName'              => $customer['CardName'],
            'CurrentAccountBalance' => $currentBalance,
            'CreditLimit'           => $creditLimit,
            'CreditoDisponible'     => max(0, $creditLimit - $currentBalance),
            'Debit balance'         => max(0, $currentBalance),
            'Credit Days'           => $lastPaymentDate ? $this->getCreditDaysSinceLastPayment($lastPaymentDate) : null,
            'LastPaymentDate'       => $lastPaymentDate,
            'PayTermsGrpCode'       => $customer['PayTermsGrpCode'] ?? null,
        ];
    }

    private function getCreditDaysSinceLastPayment($lastPaymentDate)
    {
        return (int) Carbon::parse($lastPaymentDate)->diffInDays(Carbon::now());
    }

    private function getLastPaymentDate($cardCode)
    {
        $response = Http::sapSL()->get("IncomingPayments?\$filter=CardCode eq '{$cardCode}'&\$orderby=DocDate desc&\$top=1");
        return $response->successful() ? ($response->json()['value'][0]['DocDate'] ?? null) : null;
    }
}
?>

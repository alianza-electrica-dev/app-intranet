<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShippingAddressController extends Controller
{
    public function store(Request $request, $company, $cliente)
    {
        if (!$this->validateSession()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not logged in with this company or your session has expired.',
                'data' => null
            ], 401);
        }

        $validatedData = $request->validate([
            'shipping_type' => 'required|string|in:CR,PAQ,RLOC,RFOR',
            'delivery_type' => 'required|string',
            'data' => 'required|array',
        ]);

        try {
            $response = $this->formatResponse($validatedData);
            return response()->json([
                'success' => true,
                'message' => 'Shipping address processed successfully.',
                'Shipping Address Received' => $response
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error processing shipping address: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the shipping address.',
                'Error receiving with shipping address' => null,
                'details' => $e->getMessage()
            ], 500);
        }
    }

    private function formatResponse(array $validatedData): array
    {
        $shippingType = strtoupper($validatedData['shipping_type']);
        $typeDelivery = strtoupper($validatedData['delivery_type']);

        $response = [
            'Type of shipping' => $shippingType,
            'Type of delivery' => $typeDelivery,
            'Contact Telephone' => $validatedData['data']['contact_telephone'] ?? '',
            'Contact Email' => $validatedData['data']['contact_email'] ?? '',
            'Delivery instructions' => $validatedData['data']['delivery_instructions'] ?? '',
        ];

        if (in_array($shippingType, ['RLOC', 'RFOR'])) {
            $response = array_merge($response, [
                'Paid or receivable' => $validatedData['data']['paid_or_receivable'] ?? '',
                'Customer Delivery Address' => $validatedData['data']['customer_delivery_address'] ?? '',
                'Street and Number' => $validatedData['data']['street_and_number'] ?? '',
                'Block' => $validatedData['data']['block'] ?? '',
                'State' => $validatedData['data']['state'] ?? '',
                'City' => $validatedData['data']['city'] ?? '',
                'Country' => $validatedData['data']['country'] ?? '',
                'ZipCode' => $validatedData['data']['ZipCode'] ?? '',
                'RFC' => $validatedData['data']['rfc'] ?? '',
            ]);
        } elseif ($shippingType == 'CR') {
            $response = array_merge($response, [
                'Name of Authorized Person' => $validatedData['data']['authorized_person'] ?? '',
                'Branch' => $validatedData['data']['branch'] ?? '',
                'Identity number' => $validatedData['data']['identity_number'] ?? '',
                'Collection date' => $validatedData['data']['collection_date'] ?? '',
            ]);
        } elseif ($shippingType == 'PAQ') {
            $response = array_merge($response, [
                'Name of Authorized Person' => $validatedData['data']['authorized_person'] ?? '',
                'Branch' => $validatedData['data']['branch'] ?? '',
                'Collection date' => $validatedData['data']['collection_date'] ?? '',
                'Identity number' => $validatedData['data']['identity_number'] ?? '',
            ]);
        }

        return $response;
    }

    private function validateSession(): bool
    {
        return !empty(session('companyDb', ''));
    }
}

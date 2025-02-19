<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;


class ShippingAddressController extends Controller
{
    public function store(Request $request, $company, $cliente)
    {
        if (!$this->validateSession($company)) {
            return response()->json(['error' => 'You are not logged in with this company or your session has expired.'], 401);
        }

        $validatedData = $request->validate([
            'shipping_type' => 'required|string|in:CR,PAQ,RLOC,RFOR',
            'delivery_type' => 'required|string',
            'data' => 'required|array',
        ]);

        return response()->json($this->formatResponse($validatedData), 200);
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
        } 
        // Si el envío es CR
        elseif ($shippingType == 'CR') {
            $response = array_merge($response, [
                'Name of Authorized Person' => $validatedData['data']['authorized_person'] ?? '',
                'Branch' => $validatedData['data']['branch'] ?? '',
                'Identity number' => $validatedData['data']['identity_number'] ?? '',
                'Collection date' => $validatedData['data']['collection_date'] ?? '',
            ]);
        }
        // Si el envío es PAQ
        elseif ($shippingType == 'PAQ') {
            $response = array_merge($response, [
                'Name of Authorized Person' => $validatedData['data']['authorized_person'] ?? '',
                'Branch' => $validatedData['data']['branch'] ?? '',
                'Collection date' => $validatedData['data']['collection_date'] ?? '',
                'Identity number' => $validatedData['data']['identity_number'] ?? '',
            ]);
        }

        return $response;
    }

    private function validateSession($company): bool
    {
        $loggedCompany = session('companyDb');
        return $loggedCompany && strtoupper($company) === str_replace('SBO_', '', strtoupper($loggedCompany));
    }
}
?>
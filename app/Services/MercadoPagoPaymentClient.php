<?php

namespace App\Services;

use App\Services\Contracts\PaymentGatewayClient;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Resources\Payment;

class MercadoPagoPaymentClient implements PaymentGatewayClient
{
    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('payment.mercadopago.access_token'));
    }

    public function charge(array $payload, string $idempotencyKey): Payment
    {
        $requestOptions = new RequestOptions();
        $requestOptions->setCustomHeaders([
            'x-idempotency-key' => $idempotencyKey,
        ]);

        try {
            return (new PaymentClient())->create($payload, $requestOptions);
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            Log::error('MercadoPago charge: exceção ao chamar a API', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
                'api_response' => $e->getApiResponse()->getContent(),
                'payload' => $payload,
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('MercadoPago charge: exceção ao chamar a API', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw $e;
        }
    }
}

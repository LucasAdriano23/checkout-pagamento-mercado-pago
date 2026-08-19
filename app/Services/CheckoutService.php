<?php

namespace App\Services;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use Database\Seeders\OrderSeeder;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use App\Models\Payment;

class CheckoutService {

    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('payment.mercadopago.access_token'));
    }

    public function loadCart(): array
    {
        return $this->getCartOrder()->toArray();
    }

    public function creditCardPayment(array $data): array
    {
        $order = $this->getCartOrder();

        $client = new PaymentClient();

        $response = $client->create([
            'transaction_amount' => (float)$data['transaction_amount'],
            'token' => $data['token'],
            'description' => $data['description'],
            'installments' => (int)$data['installments'],
            'payment_method_id' => $data['payment_method_id'],
            'issuer_id' => (int)$data['issuer_id'],
            'payer' => [
                'email' => $data['payer']['email'],
                'identification' => [
                    'type' => $data['payer']['identification']['type'],
                    'number' => $data['payer']['identification']['number'],
                ],
            ],
        ]);

        Log::info('MercadoPago payment created', $response->getResponse()->getContent());

        $payment = new Payment();
        $payment->external_id = (string) $response->id;
        $payment->order_id = $order->id;
        $payment->method = 1; // cartão de crédito
        $payment->status = $this->mapPaymentStatus($response->status)->value;
        $payment->installments = (int)$data['installments'];
        $payment->approved_at = $response->status === 'approved' ? now() : null;
        $payment->save();

    }

    private function mapPaymentStatus(?string $status): OrderStatusEnum
    {
        return match ($status) {
            'approved' => OrderStatusEnum::PAID,
            'pending', 'in_process', 'authorized' => OrderStatusEnum::PENDING,
            'rejected', 'cancelled' => OrderStatusEnum::REJECT,
            default => OrderStatusEnum::PENDING,
        };
    }

    private function getCartOrder(): Order
    {
        $order = Order::with('skus.product','skus.features')
            ->where('status', OrderStatusEnum::CART)
            ->where(function($query){
                $query->where('session_id', session()->getId());
                if (auth()->check()) {
                    $query->orWhere('user_id', auth()->id());
                }
            })->first();

        if(!$order && config('app.env') == 'local'){
            $seed = new OrderSeeder();
            $seed->run(session()->getId());
            return $this->getCartOrder();
        }

        return $order;
    }
}

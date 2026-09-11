<?php

namespace App\Services;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use MercadoPago\Resources\Payment;

class OrderService {
    public function update(int $order_id, Payment $payment, User $user, array $address): Order
    {
        $order = Order::findOrFail($order_id);
        $order->user_id = $user->id;
        $order->status = OrderStatusEnum::parse($payment->status);
        $order->save();

        $order->payments()->create([
            'external_id' => $payment->id,
            'method' => $this->mapPaymentMethod($payment->payment_type_id),
            'status' => $order->status->value,
            'installments' => $payment->installments,
            'approved_at' => $payment->date_approved ? Carbon::parse($payment->date_approved) : null,
            'qr_code_64' => $payment?->point_of_interaction?->transaction_data?->qr_code_base64,
            'qr_code' => $payment?->point_of_interaction?->transaction_data?->qr_code,
            'ticket_url' => $payment?->point_of_interaction?->transaction_data?->ticket_url,
        ]);

        $order->shippings()->create([
            'address' => $address['address'],
            'number' => $address['number'],
            'complement' => $address['complement'],
            'district' => $address['district'],
            'city' => $address['city'],
            'state' => $address['state'],
            'zipcode' => $address['zipcode'],
        ]);

        $order->load(['payments', 'shippings']);

        return $order;
    }

    public function getCartOrder(): Order
    {
        return Order::with('skus.product','skus.features')
            ->where('status', OrderStatusEnum::CART)
            ->where(function($query){
                $query->where('session_id', session()->getId());
                if (auth()->check()) {
                    $query->orWhere('user_id', auth()->id());
                }
            })->first();
    }

    public function buildDescription(Order $order): string
    {
        $description = $order->skus
            ->groupBy('product.name')
            ->filter(fn ($skus, $name) => filled($name))
            ->map(fn ($skus, $name) => $skus->sum('pivot.quantity').'x '.$name)
            ->implode(', ');

        return $description !== '' ? $description : 'Pedido '.$order->id;
    }

    private function mapPaymentMethod(?string $paymentTypeId): int
    {
        return match ($paymentTypeId) {
            'credit_card', 'debit_card' => 1,
            'bank_transfer' => 2,
            'ticket' => 3,
            default => 1,
        };
    }
}

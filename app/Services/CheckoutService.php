<?php

namespace App\Services;

use App\Enums\OrderStatusEnum;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use Database\Seeders\OrderSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;

class CheckoutService {

    public function __construct(private UserService $userService)
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

        $requestOptions = new RequestOptions();
        $requestOptions->setCustomHeaders([
            'x-idempotency-key' => (string) Str::uuid(),
        ]);

        $user = $this->userService->store(
            [
                'name' => $data['name'] ?? '',
                'email' => $data['email'] ?? '',
            ],
            [
                'zipcode' => preg_replace('/\D+/', '', $data['address']['zipcode'] ?? ''),
                'address' => $data['address']['address'] ?? '',
                'number' => $data['address']['number'] ?? '',
                'district' => $data['address']['district'] ?? '',
                'city' => $data['address']['city'] ?? '',
                'state' => $data['address']['state'] ?? '',
                'complement' => $data['address']['complement'] ?? null,
            ]
        );

        $order->update(['user_id' => $user->id]);

        [$firstName, $lastName] = $this->splitName($data['payer']['name'] ?? '');

        $payload = [
            'transaction_amount' => (float)$data['transaction_amount'],
            'token' => $data['token'],
            'description' => $this->buildDescription($order),
            'installments' => (int)$data['installments'],
            'payment_method_id' => $data['payment_method_id'],
            'issuer_id' => (int)$data['issuer_id'],
            'payer' => [
                'email' => config('payment.mercadopago.buyer_email'),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'identification' => [
                    'type' => $data['payer']['identification']['type'],
                    'number' => preg_replace('/\D+/', '', $data['payer']['identification']['number'] ?? ''),
                ],
            ],
        ];

        try {
            $response = $client->create($payload, $requestOptions);
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            Log::error('MercadoPago creditCardPayment: exceção ao chamar a API', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
                'api_response' => $e->getApiResponse()->getContent(),
                'payload' => $payload,
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('MercadoPago creditCardPayment: exceção ao chamar a API', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw $e;
        }

        $content = $response->getResponse()->getContent();

        Log::info('MercadoPago creditCardPayment: resposta recebida', ['content' => $content]);

        throw_if(!($content['id'] ?? null) || ($content['status'] ?? null) === 'rejected',
            PaymentException::class,
            $this->friendlyRejectionMessage($content['status_detail'] ?? null)
        );

        $this->storePayment($order, $content, method: 1, installments: (int)$data['installments']);

        return $content;
    }

    public function pixOrBankSlipPayment(array $data): array
    {
        $order = $this->getCartOrder();

        $client = new PaymentClient();

        $requestOptions = new RequestOptions();
        $requestOptions->setCustomHeaders([
            'x-idempotency-key' => (string) Str::uuid(),
        ]);

        $paymentMethodId = $data['method'];

        [$firstName, $lastName] = $this->splitName($data['name'] ?? '');

        $user = $this->userService->store(
            [
                'name' => $data['name'] ?? '',
                'email' => $data['email'] ?? '',
            ],
            [
                'zipcode' => preg_replace('/\D+/', '', $data['address']['zipcode'] ?? ''),
                'address' => $data['address']['address'] ?? '',
                'number' => $data['address']['number'] ?? '',
                'district' => $data['address']['district'] ?? '',
                'city' => $data['address']['city'] ?? '',
                'state' => $data['address']['state'] ?? '',
                'complement' => $data['address']['complement'] ?? null,
            ]
        );

        $order->update(['user_id' => $user->id]);

        $payload = [
            'transaction_amount' => (float) $data['amount'],
            'description' => $this->buildDescription($order),
            'payment_method_id' => $paymentMethodId,
            'payer' => [
                'email' => config('payment.mercadopago.buyer_email'),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'identification' => [
                    'type' => 'CPF',
                    'number' => preg_replace('/\D+/', '', $data['cpf'] ?? ''),
                ],
            ],
        ];

        if ($paymentMethodId !== 'pix') {
            $payload['payer']['address'] = [
                'zip_code' => preg_replace('/\D+/', '', $data['address']['zipcode'] ?? ''),
                'street_name' => $data['address']['address'] ?? '',
                'street_number' => $data['address']['number'] ?? '',
                'neighborhood' => $data['address']['district'] ?? '',
                'city' => $data['address']['city'] ?? '',
                'federal_unit' => $data['address']['state'] ?? '',
            ];
        }

        try {
            $response = $client->create($payload, $requestOptions);
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            Log::error('MercadoPago pixOrBankSlipPayment: exceção ao chamar a API', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
                'api_response' => $e->getApiResponse()->getContent(),
                'payload' => $payload,
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('MercadoPago pixOrBankSlipPayment: exceção ao chamar a API', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw $e;
        }

        $content = $response->getResponse()->getContent();

        Log::info('MercadoPago pixOrBankSlipPayment: resposta recebida', ['content' => $content]);

        throw_if(!($content['id'] ?? null) || ($content['status'] ?? null) === 'rejected',
            PaymentException::class,
            'Não foi possível gerar o pagamento. Verifique os dados e tente novamente.'
        );

        $method = $paymentMethodId === 'pix' ? 2 : 3;

        $this->storePayment($order, $content, method: $method, installments: null);

        return $content;
    }

    private function storePayment(Order $order, array $content, int $method, ?int $installments): Payment
    {
        $status = $this->mapPaymentStatus($content['status'] ?? null);

        $transactionData = $content['point_of_interaction']['transaction_data'] ?? [];

        $payment = Payment::create([
            'external_id' => (string) $content['id'],
            'order_id' => $order->id,
            'method' => $method,
            'status' => $status->value,
            'installments' => $installments,
            'approved_at' => ($content['status'] ?? null) === 'approved' ? now() : null,
            'qr_code_64' => $transactionData['qr_code_base64'] ?? null,
            'qr_code' => $transactionData['qr_code'] ?? null,
            'ticket_url' => $transactionData['ticket_url']
                ?? $content['transaction_details']['external_resource_url']
                ?? null,
        ]);

        $order->update(['status' => $status]);

        return $payment;
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

    private function friendlyRejectionMessage(?string $statusDetail): string
    {
        return match ($statusDetail) {
            'cc_rejected_bad_filled_card_number' => 'Revise o número do cartão.',
            'cc_rejected_bad_filled_date' => 'Revise a data de validade do cartão.',
            'cc_rejected_bad_filled_security_code' => 'Revise o código de segurança (CVV).',
            'cc_rejected_bad_filled_other' => 'Revise os dados do cartão informados.',
            'cc_rejected_blacklist', 'cc_rejected_high_risk' => 'Não foi possível aprovar este pagamento. Tente outro cartão ou meio de pagamento.',
            'cc_rejected_call_for_authorize' => 'Você precisa autorizar este pagamento junto ao seu banco antes de tentar novamente.',
            'cc_rejected_card_disabled' => 'Ligue para o seu banco para ativar o cartão ou utilize outro meio de pagamento.',
            'cc_rejected_duplicated_payment' => 'Já identificamos um pagamento com esses mesmos dados. Caso precise pagar novamente, utilize outro cartão.',
            'cc_rejected_insufficient_amount' => 'Cartão sem saldo/limite suficiente para esta compra.',
            'cc_rejected_invalid_installments' => 'Este cartão não aceita o número de parcelas escolhido.',
            'cc_rejected_max_attempts' => 'Você atingiu o limite de tentativas. Tente outro cartão ou meio de pagamento.',
            default => 'Verifique os dados do cartão e tente novamente.',
        };
    }

    private function buildDescription(Order $order): string
    {
        $description = $order->skus
            ->groupBy('product.name')
            ->filter(fn ($skus, $name) => filled($name))
            ->map(fn ($skus, $name) => $skus->sum('pivot.quantity').'x '.$name)
            ->implode(', ');

        return $description !== '' ? $description : 'Pedido '.$order->id;
    }

    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
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

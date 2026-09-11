<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use App\Mail\OrderCreatedMail;
use App\Services\Contracts\PaymentGatewayClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CheckoutService {

    public function __construct(
        private PaymentGatewayClient $gateway,
        private UserService $userService,
        private OrderService $orderService,
    ) {}

    public function creditCardPayment(array $data): array
    {
        $order = $this->orderService->getCartOrder();

        [$firstName, $lastName] = $this->splitName($data['payer']['name'] ?? '');

        $payload = [
            'transaction_amount' => (float)$data['transaction_amount'],
            'token' => $data['token'],
            'description' => $this->orderService->buildDescription($order),
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

        $response = $this->gateway->charge($payload, (string) Str::uuid());

        $content = $response->getResponse()->getContent();

        Log::info('MercadoPago creditCardPayment: resposta recebida', ['content' => $content]);

        throw_if(!($content['id'] ?? null) || ($content['status'] ?? null) === 'rejected',
            PaymentException::class,
            $this->friendlyRejectionMessage($content['status_detail'] ?? null)
        );

        $address = $this->buildAddress($data);

        $user = $this->userService->store(
            [
                'name' => $data['name'] ?? '',
                'email' => $data['email'] ?? '',
            ],
            $address
        );

        $order =  $this->orderService->update($order->id, $response, $user, $address);

        Mail::to($user->email)->queue(new OrderCreatedMail($order));

        return $content;
    }

    public function pixOrBankSlipPayment(array $data): array
    {
        $order = $this->orderService->getCartOrder();

        $paymentMethodId = $data['method'];

        [$firstName, $lastName] = $this->splitName($data['name'] ?? '');

        $address = $this->buildAddress($data);

        $payload = [
            'transaction_amount' => (float) $data['amount'],
            'description' => $this->orderService->buildDescription($order),
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
                'zip_code' => $address['zipcode'],
                'street_name' => $address['address'],
                'street_number' => $address['number'],
                'neighborhood' => $address['district'],
                'city' => $address['city'],
                'federal_unit' => $address['state'],
            ];
        }

        $response = $this->gateway->charge($payload, (string) Str::uuid());

        $content = $response->getResponse()->getContent();

        Log::info('MercadoPago pixOrBankSlipPayment: resposta recebida', ['content' => $content]);

        throw_if(!($content['id'] ?? null) || ($content['status'] ?? null) === 'rejected',
            PaymentException::class,
            'Não foi possível gerar o pagamento. Verifique os dados e tente novamente.'
        );

        $user = $this->userService->store(
            [
                'name' => $data['name'] ?? '',
                'email' => $data['email'] ?? '',
            ],
            $address
        );

        $order = $this->orderService->update($order->id, $response, $user, $address);

        Mail::to($user->email)->queue(new OrderCreatedMail($order));

        return $content;
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

    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function buildAddress(array $data): array
    {
        return [
            'zipcode' => preg_replace('/\D+/', '', $data['address']['zipcode'] ?? ''),
            'address' => $data['address']['address'] ?? '',
            'number' => $data['address']['number'] ?? '',
            'district' => $data['address']['district'] ?? '',
            'city' => $data['address']['city'] ?? '',
            'state' => $data['address']['state'] ?? '',
            'complement' => $data['address']['complement'] ?? null,
        ];
    }
}

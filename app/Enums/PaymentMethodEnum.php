<?php

namespace App\Enums;

enum PaymentMethodEnum: int
{
    case CREDIT_CARD = 1;
    case PIX = 2;
    case BOLETO = 3;

    public function getName(): string
    {
        return match ($this) {
            self::CREDIT_CARD => 'Cartão de crédito',
            self::PIX => 'Pix',
            self::BOLETO => 'Boleto',
            default => 'Método de pagamento não encontrado',
        };
    }

    public static function parse(?string $paymentTypeId): self
    {
        return match ($paymentTypeId) {
            'credit_card', 'debit_card' => self::CREDIT_CARD,
            'bank_transfer' => self::PIX,
            'ticket' => self::BOLETO,
            default => self::CREDIT_CARD,
        };
    }
}

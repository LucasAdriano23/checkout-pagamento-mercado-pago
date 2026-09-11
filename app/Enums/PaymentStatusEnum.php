<?php

namespace App\Enums;

enum PaymentStatusEnum: int
{
    case PENDING = 1;
    case APPROVED = 2;
    case AUTHORIZED = 3;
    case IN_PROCESS = 4;
    case IN_MEDIATION = 5;
    case REJECTED = 6;
    case CANCELLED = 7;
    case REFUNDED = 8;
    case CHARGED_BACK = 9;

    public function getName(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::APPROVED => 'Aprovado',
            self::AUTHORIZED => 'Autorizado',
            self::IN_PROCESS => 'Em análise',
            self::IN_MEDIATION => 'Em mediação',
            self::REJECTED => 'Rejeitado',
            self::CANCELLED => 'Cancelado',
            self::REFUNDED => 'Devolvido',
            self::CHARGED_BACK => 'Estornado',
            default => 'Status de pagamento não encontrado',
        };
    }

    public static function parse(?string $status): self
    {
        return match ($status) {
            'pending' => self::PENDING,
            'approved' => self::APPROVED,
            'authorized' => self::AUTHORIZED,
            'in_process' => self::IN_PROCESS,
            'in_mediation' => self::IN_MEDIATION,
            'rejected' => self::REJECTED,
            'cancelled' => self::CANCELLED,
            'refunded' => self::REFUNDED,
            'charged_back' => self::CHARGED_BACK,
            default => self::PENDING,
        };
    }
}

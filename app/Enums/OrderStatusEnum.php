<?php

namespace App\Enums;

enum OrderStatusEnum: int
{
    case CART = 1;
    case PENDING = 2;
    case PAID = 3;
    case CANCELED = 4;
    case REJECT = 5;

    public function getName()
    {
        return match($this)
        {
            self::CART => 'Criado',
            self::PENDING => 'Pendente',
            self::PAID => 'Pago',
            self::CANCELED => 'Cancelado',
            self::REJECT => 'Não aprovado',
            default => 'Status não encontrado'
        };
    }

    public function getStyles(): string
    {
        return match($this)
        {
            self::CART => 'px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-100',
            self::PENDING => 'px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-800',
            self::PAID => 'px-2 py-1 text-rs rounded-full bg-green-100 text-green-800',
            self::CANCELED => 'px-2 py-0.5 text-rs rounded-full bg-red-100 text-red-800',
            self::REJECT => 'px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800',
            default => ''
        };
    }

    public static function parse(?string $status): self
    {
        return match ($status) {
            'approved' => self::PAID,
            'pending', 'in_process', 'authorized' => self::PENDING,
            'rejected' => self::REJECT,
            'cancelled' => self::CANCELED,
            default => self::PENDING,
        };
    }
}

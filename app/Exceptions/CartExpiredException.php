<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;

class CartExpiredException extends Exception
{
    protected $message = "Não encontramos seu carrinho. Sua sessão pode ter expirado.";
    protected $code = Response::HTTP_GONE;

    public function render() {
        return response()->json([
            'error' => $this->message,
        ], $this->code);
    }
}

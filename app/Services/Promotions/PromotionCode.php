<?php

namespace App\Services\Promotions;

class PromotionCode
{
    public static function normalize(?string $code): string
    {
        return str($code ?? '')
            ->trim()
            ->upper()
            ->replaceMatches('/\s+/', '')
            ->toString();
    }
}

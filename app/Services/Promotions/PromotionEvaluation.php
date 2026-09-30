<?php

namespace App\Services\Promotions;

use App\Models\Promotion;
use Illuminate\Support\Collection;

class PromotionEvaluation
{
    public function __construct(
        public readonly ?Promotion $promotion,
        public readonly bool $valid,
        public readonly ?string $message,
        public readonly int $eligibleSubtotal,
        public readonly int $discountTotal,
        public readonly Collection $allocations,
    ) {
    }

    public static function none(): self
    {
        return new self(null, false, null, 0, 0, collect());
    }

    public static function invalid(?Promotion $promotion, string $message): self
    {
        return new self($promotion, false, $message, 0, 0, collect());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'promotion' => $this->promotion,
            'valid' => $this->valid,
            'message' => $this->message,
            'eligible_subtotal' => $this->eligibleSubtotal,
            'discount_total' => $this->discountTotal,
            'allocations' => $this->allocations,
        ];
    }
}

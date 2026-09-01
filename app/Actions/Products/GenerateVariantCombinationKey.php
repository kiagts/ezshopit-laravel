<?php

namespace App\Actions\Products;

class GenerateVariantCombinationKey
{
    /**
     * @param array<int, int|string> $attributeValueIdsByAttributeId
     */
    public function handle(array $attributeValueIdsByAttributeId): string
    {
        if ($attributeValueIdsByAttributeId === []) {
            return 'default';
        }

        ksort($attributeValueIdsByAttributeId, SORT_NUMERIC);

        return collect($attributeValueIdsByAttributeId)
            ->map(fn (int|string $valueId, int|string $attributeId): string => ((int) $attributeId).':'.((int) $valueId))
            ->implode('|');
    }
}

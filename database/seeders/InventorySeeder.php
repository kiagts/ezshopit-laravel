<?php

namespace Database\Seeders;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::query()->where('email', 'admin@example.com')->value('id');
        $variants = ProductVariant::query()->with('inventory')->orderBy('sku')->get();

        if ($variants->isEmpty()) {
            return;
        }

        InventoryMovement::query()->whereIn('product_variant_id', $variants->pluck('id'))->delete();

        foreach ($variants as $variant) {
            $target = $this->targetForSku($variant->sku);

            $inventory = Inventory::query()->updateOrCreate(
                ['product_variant_id' => $variant->id],
                [
                    'on_hand' => $target['on_hand'],
                    'reserved' => $target['reserved'],
                    'low_stock_threshold' => $target['threshold'],
                ],
            );

            $this->seedMovements($inventory, $target['on_hand'], $adminId);
        }
    }

    /**
     * @return array{on_hand: int, reserved: int, threshold: int}
     */
    private function targetForSku(string $sku): array
    {
        $stock = [
            'IPH17-BLK-8-256' => [15, 0],
            'IPH17-BLK-8-512' => [3, 0],
            'IPH17-WHT-8-256' => [0, 0],
            'IPH17-WHT-8-512' => [8, 1],
            'SGS26-BLK-12-256' => [18, 2],
            'SGS26-GRY-12-512' => [4, 0],
            'XRN16-BLK-6-128' => [30, 4],
            'XRN16-BLU-8-256' => [0, 0],
            'DIN14-SLV-16-512' => [9, 1],
            'DIN14-SLV-32-1TB' => [2, 0],
            'TPX1-BLK-16-512' => [6, 1],
            'TPX1-BLK-32-1TB' => [0, 0],
            'NEH-BLK-M' => [24, 3],
            'NEH-GRY-L' => [4, 0],
            'NRS-BLU-M' => [22, 3],
            'NRS-BLU-L' => [2, 0],
            'SGW-BLK-42' => [11, 1],
            'SGW-GRY-43' => [0, 0],
            'AUB-BLK-42' => [5, 0],
            'AUB-WHT-43' => [0, 0],
            'BOSCH-MIXER-COMPACT' => [7, 1],
            'LOGI-MXM4' => [3, 0],
            'SONY-WH1000XM6' => [12, 1],
        ];

        [$onHand, $reserved] = $stock[$sku] ?? [$this->fallbackStock($sku), 0];

        return [
            'on_hand' => $onHand,
            'reserved' => min($reserved, $onHand),
            'threshold' => 5,
        ];
    }

    private function fallbackStock(string $sku): int
    {
        return abs(crc32($sku)) % 19;
    }

    private function seedMovements(Inventory $inventory, int $onHand, ?int $adminId): void
    {
        if ($onHand === 0) {
            return;
        }

        $initial = $onHand + 3;

        $inventory->movements()->create([
            'product_variant_id' => $inventory->product_variant_id,
            'type' => InventoryMovementType::Initial,
            'quantity' => $initial,
            'before_quantity' => 0,
            'after_quantity' => $initial,
            'reason' => 'Seeded opening stock',
            'created_by' => $adminId,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        if ($initial !== $onHand) {
            $inventory->movements()->create([
                'product_variant_id' => $inventory->product_variant_id,
                'type' => InventoryMovementType::Adjustment,
                'quantity' => $onHand - $initial,
                'before_quantity' => $initial,
                'after_quantity' => $onHand,
                'reason' => 'Seeded stock count correction',
                'created_by' => $adminId,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);
        }
    }
}

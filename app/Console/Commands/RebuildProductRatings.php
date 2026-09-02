<?php

namespace App\Console\Commands;

use App\Services\Reviews\ProductRatingService;
use Illuminate\Console\Command;

class RebuildProductRatings extends Command
{
    protected $signature = 'reviews:rebuild-ratings';

    protected $description = 'Rebuild product rating aggregate counters from approved product reviews.';

    public function handle(ProductRatingService $ratings): int
    {
        $ratings->rebuild();
        $this->info('Product rating aggregates rebuilt from approved reviews.');

        return self::SUCCESS;
    }
}

<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryMediaSeeder extends Seeder
{
    private const DISK = 'public';

    private const IMAGES_DIR = 'demo/categories/images';
    private const ICONS_DIR = 'demo/categories/icons';

    /** Image canvas size (square) in pixels. */
    private const IMAGE_SIZE = 480;

    public function run(): void
    {
        $disk = Storage::disk(self::DISK);

        foreach ([self::IMAGES_DIR, self::ICONS_DIR] as $dir) {
            if (! $disk->exists($dir)) {
                $disk->makeDirectory($dir);
                $this->command?->info("Created missing directory: {$dir}");
            }
        }

        $tree = [
            'Electronics' => [
                'Mobile Phones' => ['Smartphones', 'Feature Phones', 'Mobile Accessories'],
                'Computers & Laptops' => ['Laptops', 'Desktop Computers', 'Computer Accessories'],
                'Audio' => ['Headphones', 'Earbuds', 'Speakers'],
                'Smart Wearables' => [],
            ],
            'Fashion' => [
                'Men' => ['Clothing', 'Shoes', 'Accessories'],
                'Women' => ['Clothing', 'Shoes', 'Handbags'],
                'Kids' => [],
            ],
            'Home & Living' => [
                'Kitchen' => [],
                'Furniture' => [],
                'Home Decor' => [],
                'Bedding' => [],
            ],
            'Beauty & Personal Care' => [
                'Skin Care' => [],
                'Hair Care' => [],
            ],
            'Sports & Fitness' => [
                'Exercise Equipment' => [],
                'Team Sports' => [],
            ],
            'Automotive' => [
                'Car Accessories' => [],
            ],
            'Gaming' => [
                'Consoles' => [],
                'Games' => [],
            ],
            'Books & Stationery' => [
                'Books' => [],
            ],
            'Baby & Kids' => [
                'Baby Gear' => [],
            ],
            'Groceries' => [
                'Pantry Staples' => [],
            ],
            'Pet Supplies' => [],
            'Tools & Home Improvement' => [],
        ];

        foreach ($tree as $rootName => $children) {
            $this->apply($rootName, null);

            foreach ($children as $childName => $grandChildren) {
                $this->apply($childName, $this->slug($rootName, null));

                foreach ($grandChildren as $grandChildName) {
                    $this->apply($grandChildName, $this->slug($childName, $this->slug($rootName, null)));
                }
            }
        }
    }

    private function apply(string $name, ?string $parentSlug): void
    {
        $slug = $this->slug($name, $parentSlug);

        $imagePath = self::IMAGES_DIR."/{$slug}.jpg";
        $iconPath = self::ICONS_DIR."/{$slug}.svg";

        $this->generateImage($name, $imagePath);
        $this->generateIcon($name, $iconPath);

        $updated = Category::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->update([
                'icon_path' => $iconPath,
                'image_path' => $imagePath,
            ]);

        if ($updated === 0) {
            $this->command?->warn("Category not found for slug [{$slug}], skipped DB update (files were still generated).");
        }
    }

    /**
     * Generates a square JPG: solid brand-derived background color + centered initial letter.
     */
    private function generateImage(string $name, string $destination): void
    {
        $disk = Storage::disk(self::DISK);

        if (! function_exists('imagecreatetruecolor')) {
            $this->command?->error('GD extension is not available; cannot generate placeholder images.');

            return;
        }

        $size = self::IMAGE_SIZE;
        $letter = mb_strtoupper(mb_substr($name, 0, 1));
        [$r, $g, $b] = $this->colorFromName($name);

        $image = imagecreatetruecolor($size, $size);
        $bg = imagecolorallocate($image, $r, $g, $b);
        imagefill($image, 0, 0, $bg);

        // Contrasting text color (white or near-black) based on background luminance.
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $textColor = $luminance > 0.6
            ? imagecolorallocate($image, 30, 30, 30)
            : imagecolorallocate($image, 255, 255, 255);

        $font = $this->findTtfFont();

        if ($font !== null) {
            $fontSize = (int) ($size * 0.35);
            $bbox = imagettfbbox($fontSize, 0, $font, $letter);
            $textWidth = abs($bbox[4] - $bbox[0]);
            $textHeight = abs($bbox[5] - $bbox[1]);
            $x = (int) (($size - $textWidth) / 2);
            $y = (int) (($size + $textHeight) / 2);
            imagettftext($image, $fontSize, 0, $x, $y, $textColor, $font, $letter);
        } else {
            // Fallback: built-in bitmap font, scaled up via a larger canvas trick.
            $fontIndex = 5; // largest built-in GD font
            $charWidth = imagefontwidth($fontIndex);
            $charHeight = imagefontheight($fontIndex);
            $x = (int) (($size - $charWidth) / 2);
            $y = (int) (($size - $charHeight) / 2);
            imagestring($image, $fontIndex, $x, $y, $letter, $textColor);
        }

        ob_start();
        imagejpeg($image, null, 85);
        $contents = ob_get_clean();
        imagedestroy($image);

        $disk->put($destination, $contents);
        $this->command?->info("Generated image: {$destination}");
    }

    /**
     * Generates a simple SVG icon: rounded square + centered initial letter.
     */
    private function generateIcon(string $name, string $destination): void
    {
        $disk = Storage::disk(self::DISK);

        $letter = mb_strtoupper(mb_substr($name, 0, 1));
        [$r, $g, $b] = $this->colorFromName($name);
        $hex = sprintf('#%02x%02x%02x', $r, $g, $b);

        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $textColor = $luminance > 0.6 ? '#1e1e1e' : '#ffffff';

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64" role="img" aria-label="{$name}">
            <rect width="64" height="64" rx="12" fill="{$hex}" />
            <text x="32" y="42" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="28" font-weight="600" fill="{$textColor}">{$letter}</text>
        </svg>
        SVG;

        $disk->put($destination, $svg);
        $this->command?->info("Generated icon: {$destination}");
    }

    /**
     * Deterministically derives an RGB color from the category name so the same
     * category always gets the same color across seeder runs.
     *
     * @return array{0:int,1:int,2:int}
     */
    private function colorFromName(string $name): array
    {
        $hash = substr(md5($name), 0, 6);

        $r = hexdec(substr($hash, 0, 2));
        $g = hexdec(substr($hash, 2, 2));
        $b = hexdec(substr($hash, 4, 2));

        // Pull toward a pleasant mid-range so colors aren't too dark or too neon.
        $r = (int) (($r + 120) / 2);
        $g = (int) (($g + 120) / 2);
        $b = (int) (($b + 120) / 2);

        return [$r, $g, $b];
    }

    /**
     * Looks for a usable TTF font on the system for nicer text rendering.
     * Falls back to null (built-in GD bitmap font) if none is found.
     */
    private function findTtfFont(): ?string
    {
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
            resource_path('fonts/arial.ttf'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function slug(string $name, ?string $parentSlug): string
    {
        $base = Str::slug($name);

        return $parentSlug === null ? $base : "{$parentSlug}-{$base}";
    }
}
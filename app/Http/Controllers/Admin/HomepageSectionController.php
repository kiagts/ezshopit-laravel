<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HomepageSectionItemType;
use App\Enums\HomepageSectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Homepage\StoreHomepageSectionRequest;
use App\Http\Requests\Admin\Homepage\UpdateHomepageSectionRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\Homepage\HomepageSectionSchema;
use App\Services\Homepage\HomepageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HomepageSectionController extends Controller
{
    public function __construct(
        private readonly HomepageSectionSchema $schema,
    ) {}

    public function index(): View
    {
        $sections = HomepageSection::query()
            ->withCount(['items', 'contentItems'])
            ->ordered()
            ->paginate(30);

        $counts = HomepageSection::query()
            ->get()
            ->groupBy(fn (HomepageSection $section): string => $section->derivedStatus())
            ->map->count();

        return view('admin.homepage.index', [
            'sections' => $sections,
            'counts' => $counts,
        ]);
    }

    public function create(): View
    {
        $section = new HomepageSection([
            'type' => HomepageSectionType::Products,
            'is_enabled' => true,
            'sort_order' => ((int) HomepageSection::query()->max('sort_order')) + 10,
            'settings' => ['source' => 'latest', 'limit' => 8],
        ]);

        return view('admin.homepage.create', $this->formData($section));
    }

    public function store(StoreHomepageSectionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $type = HomepageSectionType::from($data['type']);
        $settings = $this->schema->validateSettings($type, $data['settings'] ?? []);

        $section = DB::transaction(function () use ($request, $data, $type, $settings): HomepageSection {
            $section = new HomepageSection;
            $section->forceFill([
                'key' => $data['key'],
                'type' => $type,
                'name' => $data['name'],
                'title' => $data['title'] ?? null,
                'subtitle' => $data['subtitle'] ?? null,
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'settings' => $settings,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ])->save();

            $this->syncItems($section, $data['item_ids'] ?? []);
            $this->syncContentItems($section, $data['content_items'] ?? [], $request);

            return $section;
        });

        return redirect()
            ->route('admin.homepage.sections.edit', $section)
            ->with('status', 'Homepage section created.');
    }

    public function edit(HomepageSection $section): View
    {
        return view('admin.homepage.edit', $this->formData($section->load(['items', 'contentItems'])));
    }

    public function update(UpdateHomepageSectionRequest $request, HomepageSection $section): RedirectResponse
    {
        $data = $request->validated();
        $type = $section->type;
        $settings = $this->schema->validateSettings($type, $data['settings'] ?? []);

        DB::transaction(function () use ($request, $section, $data, $settings): void {
            $section->forceFill([
                'key' => $data['key'],
                'name' => $data['name'],
                'title' => $data['title'] ?? null,
                'subtitle' => $data['subtitle'] ?? null,
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'settings' => $settings,
                'updated_by' => $request->user()->id,
            ])->save();

            $this->syncItems($section, $data['item_ids'] ?? []);
            $this->syncContentItems($section, $data['content_items'] ?? [], $request);
        });

        return redirect()
            ->route('admin.homepage.sections.edit', $section)
            ->with('status', 'Homepage section updated.');
    }

    public function toggle(Request $request, HomepageSection $section): RedirectResponse
    {
        $section->forceFill([
            'is_enabled' => ! $section->is_enabled,
            'updated_by' => $request->user()->id,
        ])->save();

        return back()->with('status', 'Homepage section visibility updated.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.id' => ['required', 'integer', 'exists:homepage_sections,id'],
            'sections.*.sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            foreach ($validated['sections'] as $row) {
                HomepageSection::query()
                    ->whereKey($row['id'])
                    ->update([
                        'sort_order' => $row['sort_order'],
                        'updated_by' => $request->user()->id,
                    ]);
            }
        });

        return back()->with('status', 'Homepage section order updated.');
    }

    public function preview(HomepageService $homepage): View
    {
        return view('storefront.home', $homepage->build(preview: true))
            ->with('isHomepagePreview', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(HomepageSection $section): array
    {
        $data = [
            'section' => $section,
            'types' => HomepageSectionType::cases(),
            'schema' => $this->schema,
            'products' => Product::query()
                ->publiclyVisible()
                ->with('vendor:id,display_name')
                ->latest('published_at')
                ->limit(80)
                ->get(['id', 'vendor_id', 'name', 'slug', 'published_at']),
            'categories' => Category::query()
                ->active()
                ->ordered()
                ->limit(120)
                ->get(['id', 'name', 'parent_id']),
            'brands' => Brand::query()
                ->active()
                ->ordered()
                ->limit(80)
                ->get(['id', 'name']),
            'vendors' => Vendor::query()
                ->approved()
                ->orderBy('display_name')
                ->limit(80)
                ->get(['id', 'display_name', 'slug']),
        ];

        // Keep saved selections available and ordered, even outside the picker limits
        // or temporarily ineligible for public display.
        foreach (['products' => HomepageSectionItemType::Product, 'categories' => HomepageSectionItemType::Category, 'brands' => HomepageSectionItemType::Brand, 'vendors' => HomepageSectionItemType::Vendor] as $key => $type) {
            $ids = $section->items->where('item_type', $type)->pluck('item_id')->all();
            $model = $type->modelClass();
            $selected = $model::query()->whereIn('id', $ids)->get()
                ->sortBy(fn ($item) => array_search($item->id, $ids));
            $data[$key] = $selected->concat($data[$key])->unique('id')->values();
        }

        return $data;
    }

    /**
     * @param  array<int, mixed>  $itemIds
     */
    private function syncItems(HomepageSection $section, array $itemIds): void
    {
        $allowedTypes = $this->schema->allowedItemTypes($section->type);
        $section->items()->delete();

        if ($allowedTypes === []) {
            return;
        }

        $itemType = $allowedTypes[0];
        $ids = collect($itemIds)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $modelClass = $itemType->modelClass();
        $validIds = $modelClass::query()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (count($validIds) !== $ids->count()) {
            throw ValidationException::withMessages([
                'item_ids' => 'One or more selected homepage items no longer exist.',
            ]);
        }

        foreach ($ids as $index => $id) {
            $section->items()->create([
                'item_type' => $itemType,
                'item_id' => $id,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncContentItems(HomepageSection $section, array $items, Request $request): void
    {
        if (! $section->type->supportsContentItems()) {
            $section->contentItems()->delete();

            return;
        }

        $section->contentItems()->delete();

        foreach ($items as $index => $item) {
            if (blank($item['title'] ?? null)) {
                continue;
            }

            $this->validateContentItem($item);

            $imagePath = $this->storedImagePath($request, "content_items.{$index}.image_file", $item['image_path'] ?? null, $section);
            $mobileImagePath = $this->storedImagePath($request, "content_items.{$index}.mobile_image_file", $item['mobile_image_path'] ?? null, $section);

            $section->contentItems()->create([
                'title' => $item['title'],
                'body' => $item['body'] ?? null,
                'image_path' => $imagePath,
                'mobile_image_path' => $mobileImagePath,
                'image_alt' => $item['image_alt'] ?? null,
                'icon_key' => $item['icon_key'] ?? null,
                'cta_label' => $item['cta_label'] ?? null,
                'cta_url' => $item['cta_url'] ?? null,
                'sort_order' => ($index + 1) * 10,
                'settings' => $this->cleanContentSettings($item['settings'] ?? []),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function validateContentItem(array $item): void
    {
        validator($item, [
            'cta_url' => ['nullable', 'string', 'max:255', $this->schema->safeUrlRule()],
            'icon_key' => ['nullable', 'string', 'max:40', \Illuminate\Validation\Rule::in($this->schema->allowedIconKeys())],
        ])->validate();
    }

    private function storedImagePath(Request $request, string $key, ?string $existingPath, HomepageSection $section): ?string
    {
        if (! $request->hasFile($key)) {
            return $existingPath;
        }

        // Paths can be shared across sections. Never delete a client-supplied path.
        return $request->file($key)->store("homepage/{$section->id}", 'public');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function cleanContentSettings(array $settings): array
    {
        return collect($settings)
            ->only(['group', 'layout', 'eyebrow'])
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();
    }
}

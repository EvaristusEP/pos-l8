<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Menu;
use Illuminate\Support\Facades\Schema;

class BranchMenuCategoryCatalog
{
    private static ?bool $catalogCategoryStorageAvailable = null;

    private const CATALOGS = [
        'bakery' => [
            'categories' => ['roti' => 'Roti', 'pastry' => 'Pastry', 'kue' => 'Kue', 'dessert' => 'Dessert'],
            'legacy_category' => 'pastry',
        ],
        'coffee' => [
            'categories' => ['kopi' => 'Kopi', 'non-kopi' => 'Non-Kopi', 'teh' => 'Teh', 'makanan' => 'Makanan'],
            'legacy_category' => 'non-matcha',
        ],
        'gelato' => [
            'categories' => ['gelato' => 'Gelato', 'es-krim' => 'Es Krim', 'sundae' => 'Sundae', 'dessert' => 'Dessert'],
            'legacy_category' => 'non-matcha',
        ],
    ];

    public function categoriesFor(Branch $branch): array
    {
        return self::CATALOGS[$this->branchType($branch)]['categories'] ?? [];
    }

    public function storageAvailable(): bool
    {
        return self::$catalogCategoryStorageAvailable ??= Schema::hasColumn('branch_menu', 'catalog_category');
    }

    public function legacyCategoryIdFor(Branch $branch): ?int
    {
        $slug = self::CATALOGS[$this->branchType($branch)]['legacy_category'] ?? null;

        return $slug ? Category::query()->where('slug', $slug)->where('is_active', true)->value('id') : null;
    }

    public function resolve(Menu $menu, Branch $branch): array
    {
        $categories = $this->categoriesFor($branch);
        $storedSlug = $menu->branches->firstWhere('id', $branch->id)?->pivot?->catalog_category;

        if ($storedSlug && isset($categories[$storedSlug])) {
            return ['slug' => $storedSlug, 'name' => $categories[$storedSlug]];
        }

        $sourceCategory = $menu->category;
        if (! $sourceCategory) {
            return ['slug' => 'needs-classification', 'name' => 'Perlu klasifikasi'];
        }

        $resolved = app(PosCatalogCategoryResolver::class)->resolve($menu, $branch, $sourceCategory);
        if (isset($categories[$resolved['slug']])) {
            return $resolved;
        }

        return ['slug' => 'needs-classification', 'name' => 'Perlu klasifikasi'];
    }

    private function branchType(Branch $branch): ?string
    {
        $identity = strtolower($branch->code.' '.$branch->name);

        foreach (['bakery', 'coffee', 'gelato'] as $type) {
            if (str_contains($identity, $type)) {
                return $type;
            }
        }

        return null;
    }
}

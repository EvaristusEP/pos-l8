<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Menu;

class PosCatalogCategoryResolver
{
    private const BAKERY = [
        'roti-sourdough' => ['roti', 'Roti'],
        'roti-kayu-manis' => ['roti', 'Roti'],
        'brioche-susu' => ['roti', 'Roti'],
        'focaccia-rosemary' => ['roti', 'Roti'],
        'bagel-wijen' => ['roti', 'Roti'],
        'croissant-butter' => ['pastry', 'Pastry'],
        'pain-au-chocolat' => ['pastry', 'Pastry'],
        'danish-keju' => ['pastry', 'Pastry'],
        'tart-lemon' => ['pastry', 'Pastry'],
        'quiche-ayam' => ['needs-classification', 'Perlu klasifikasi'],
        'muffin-cokelat' => ['kue', 'Kue'],
        'muffin-blueberry' => ['kue', 'Kue'],
        'banana-bread' => ['kue', 'Kue'],
        'brownies-fudge' => ['kue', 'Kue'],
        'waffle-belgia' => ['dessert', 'Dessert'],
    ];

    public function resolve(Menu $menu, Branch $branch, Category $sourceCategory): array
    {
        $slug = (string) $menu->slug;

        if (strpos($slug, 'jooal-001-') === 0) {
            $mapped = self::BAKERY[substr($slug, strlen('jooal-001-'))] ?? null;
            if ($mapped) return ['slug' => $mapped[0], 'name' => $mapped[1]];
        }

        if (strpos($slug, 'jooal-002-') === 0) {
            $itemSlug = substr($slug, strlen('jooal-002-'));
            if (strpos($itemSlug, 'gelato-') === 0 || $itemSlug === 'affogato-classico') return ['slug' => 'gelato', 'name' => 'Gelato'];
            if (strpos($itemSlug, 'sundae-') === 0) return ['slug' => 'sundae', 'name' => 'Sundae'];
            if (strpos($itemSlug, 'waffle-') === 0 || strpos($itemSlug, 'milkshake-') === 0) return ['slug' => 'dessert', 'name' => 'Dessert'];
        }

        if (strpos($slug, 'jooal-003-') === 0) {
            if (in_array(substr($slug, strlen('jooal-003-')), ['matcha-latte', 'chocolate-cream'], true)) {
                return ['slug' => 'non-kopi', 'name' => 'Non-Kopi'];
            }

            return ['slug' => 'kopi', 'name' => 'Kopi'];
        }

        if (strpos($slug, 'jooal-shared-') === 0) {
            $itemSlug = substr($slug, strlen('jooal-shared-'));
            if ($branch->code === 'JOOAL-003' && $itemSlug === 'kopi-susu-gula-aren') return ['slug' => 'kopi', 'name' => 'Kopi'];
            if ($branch->code === 'JOOAL-003' && in_array($itemSlug, ['cokelat-signature', 'matcha-susu'], true)) return ['slug' => 'non-kopi', 'name' => 'Non-Kopi'];

            return ['slug' => 'needs-classification', 'name' => 'Perlu klasifikasi'];
        }

        if ($sourceCategory->slug === 'pastry' && strpos(strtolower($branch->name), 'bakery') !== false) {
            return ['slug' => 'pastry', 'name' => 'Pastry'];
        }

        return ['slug' => 'needs-classification', 'name' => 'Perlu klasifikasi'];
    }
}

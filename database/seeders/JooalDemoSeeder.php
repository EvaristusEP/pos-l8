<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\MenuVariant;
use App\Models\RecipeItem;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\Supplier;
use App\Models\Tag;
use App\Models\Topping;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\RecipeStockService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Opt-in, deterministic presentation dataset. Never call from DatabaseSeeder. */
class JooalDemoSeeder extends Seeder
{
    private const BRANCHES = [
        'JOOAL-001' => 'JOOAL Bakery',
        'JOOAL-002' => 'JOOAL Gelato',
        'JOOAL-003' => 'JOOAL Coffee',
    ];

    private const INGREDIENTS = [
        'coffee' => ['Biji Kopi Arabika', 'g', 18000, 17000],
        'milk' => ['Susu Segar', 'ml', 30000, 4000],
        'oat' => ['Susu Oat', 'ml', 8000, 1500],
        'sugar' => ['Gula Aren', 'g', 9000, 1800],
        'chocolate' => ['Cokelat Bubuk', 'g', 7000, 1500],
        'vanilla' => ['Sirup Vanila', 'ml', 5000, 800],
        'caramel' => ['Sirup Karamel', 'ml', 5000, 800],
        'matcha' => ['Bubuk Matcha', 'g', 5000, 800],
        'gelato' => ['Gelato Base', 'g', 24000, 4000],
        'cream' => ['Krim Susu', 'ml', 9000, 1800],
        'strawberry' => ['Puree Stroberi', 'g', 7000, 1200],
        'mango' => ['Puree Mangga', 'g', 7000, 1200],
        'pistachio' => ['Pasta Pistachio', 'g', 100, 20],
        'cookie' => ['Remah Biskuit', 'g', 5000, 900],
        'waffle' => ['Adonan Waffle', 'g', 6000, 1000],
        'flour' => ['Tepung Terigu', 'g', 20000, 3500],
        'butter' => ['Mentega', 'g', 8000, 1500],
        'olive' => ['Minyak Zaitun', 'ml', 3000, 500],
        'egg' => ['Telur', 'g', 12000, 2500],
        'yeast' => ['Ragi Roti', 'g', 900, 150],
        'cheese' => ['Keju', 'g', 5000, 900],
        'chicken' => ['Ayam Suwir', 'g', 5000, 900],
        'cinnamon' => ['Kayu Manis', 'g', 2, 1],
        'almond' => ['Kacang Almond', 'g', 2500, 450],
        'cup' => ['Gelas Minuman', 'pcs', 1200, 120],
        'straw' => ['Sedotan Kertas', 'pcs', 1200, 120],
        'bowl' => ['Cup Dessert', 'pcs', 1000, 100],
        'box' => ['Kotak Bakery', 'pcs', 800, 80],
        'napkin' => ['Serbet', 'pcs', 2000, 200],
    ];

    /** Product definitions are compactly generated into 15 branch specialties plus 3 shared drinks. */
    private const PRODUCT_GROUPS = [
        '001' => [
            ['Roti Sourdough', 26000, 'flour:90,butter:8,yeast:2,box:1'], ['Croissant Butter', 24000, 'flour:65,butter:18,egg:8,box:1'],
            ['Pain au Chocolat', 28000, 'flour:60,butter:16,chocolate:12,box:1'], ['Roti Kayu Manis', 22000, 'flour:65,butter:10,cinnamon:4,box:1'],
            ['Brioche Susu', 23000, 'flour:60,butter:12,egg:10,milk:20,box:1'], ['Muffin Cokelat', 22000, 'flour:55,butter:8,chocolate:12,egg:8,box:1'],
            ['Muffin Blueberry', 23000, 'flour:55,butter:8,strawberry:12,egg:8,box:1'], ['Danish Keju', 27000, 'flour:55,butter:12,cheese:18,box:1'],
            ['Focaccia Rosemary', 25000, 'flour:70,olive:8,yeast:2,box:1'], ['Bagel Wijen', 21000, 'flour:70,butter:5,yeast:2,box:1'],
            ['Banana Bread', 22000, 'flour:55,butter:8,egg:8,cinnamon:2,box:1'], ['Tart Lemon', 29000, 'flour:40,butter:14,egg:10,cream:12,box:1'],
            ['Brownies Fudge', 26000, 'flour:35,butter:12,chocolate:22,egg:10,box:1'], ['Quiche Ayam', 32000, 'flour:40,butter:12,chicken:25,cheese:15,egg:12,box:1'],
            ['Waffle Belgia', 30000, 'waffle:100,butter:10,egg:12,cream:15,napkin:1'],
        ],
        '002' => [
            ['Gelato Vanilla Bean', 24000, 'gelato:110,vanilla:5,bowl:1'], ['Gelato Cokelat Belgia', 26000, 'gelato:110,chocolate:18,bowl:1'],
            ['Gelato Stroberi', 25000, 'gelato:110,strawberry:20,bowl:1'], ['Gelato Mangga', 25000, 'gelato:110,mango:20,bowl:1'],
            ['Gelato Pistachio', 29000, 'gelato:110,pistachio:12,bowl:1'], ['Gelato Matcha', 27000, 'gelato:110,matcha:10,bowl:1'],
            ['Gelato Cookies', 27000, 'gelato:110,cookie:18,bowl:1'], ['Gelato Salted Caramel', 27000, 'gelato:110,caramel:12,bowl:1'],
            ['Gelato Almond', 29000, 'gelato:110,almond:12,bowl:1'], ['Affogato Classico', 34000, 'gelato:85,coffee:18,bowl:1'],
            ['Sundae Stroberi', 32000, 'gelato:120,strawberry:22,cream:15,bowl:1'], ['Sundae Cokelat', 32000, 'gelato:120,chocolate:20,cream:15,bowl:1'],
            ['Waffle Gelato', 38000, 'waffle:95,gelato:90,cream:12,napkin:1'], ['Milkshake Vanilla', 33000, 'gelato:80,milk:150,vanilla:5,cup:1,straw:1'],
            ['Milkshake Cokelat', 35000, 'gelato:80,milk:150,chocolate:15,cup:1,straw:1'],
        ],
        '003' => [
            ['Espresso Single Origin', 22000, 'coffee:18,cup:1'], ['Cappuccino', 29000, 'coffee:18,milk:140,cup:1'],
            ['Flat White', 30000, 'coffee:20,milk:150,cup:1'], ['Caffe Mocha', 33000, 'coffee:18,milk:120,chocolate:12,cup:1'],
            ['Caramel Macchiato', 34000, 'coffee:18,milk:130,caramel:10,cup:1'], ['Vanilla Latte', 32000, 'coffee:18,milk:150,vanilla:8,cup:1'],
            ['Cold Brew', 28000, 'coffee:22,cup:1,straw:1'], ['V60 Ethiopia', 32000, 'coffee:20,cup:1'],
            ['Kopi Susu Aren', 30000, 'coffee:18,milk:100,sugar:12,cup:1'], ['Matcha Latte', 33000, 'matcha:8,milk:160,cup:1'],
            ['Chocolate Cream', 31000, 'chocolate:18,milk:140,cream:12,cup:1'], ['Oat Latte', 34000, 'coffee:18,oat:160,cup:1'],
            ['Espresso Tonic', 32000, 'coffee:18,cup:1,straw:1'], ['Piccolo', 27000, 'coffee:18,milk:70,cup:1'],
            ['Iced Americano', 26000, 'coffee:18,cup:1,straw:1'],
        ],
    ];

    private const SHARED_DRINKS = [
        ['Cokelat Signature', 30000, 'chocolate:18,milk:140,cup:1,straw:1'],
        ['Matcha Susu', 32000, 'matcha:8,milk:150,cup:1,straw:1'],
        ['Kopi Susu Gula Aren', 29000, 'coffee:18,milk:100,sugar:12,cup:1,straw:1'],
    ];

    public function run(): void
    {
        $this->assertSafeTargetAndSchema();

        DB::transaction(function () {
            $owner = User::query()->where('role', User::ROLE_OWNER)->orderBy('id')->first();
            if (! $owner) throw new RuntimeException('An existing Owner account is required.');

            $branches = collect(self::BRANCHES)->map(function ($name, $code) {
                $branch = Branch::query()->where('code', $code)->firstOrFail();
                if (! $branch->is_active || ! in_array($branch->name, [$name, 'Cabang Utama', 'Gelato', 'Coffee', 'JOOAL Cabang Utama'], true)) {
                    throw new RuntimeException('Branch '.$code.' is unexpected or inactive; aborting without changing its identity.');
                }
                if ($branch->name !== $name) $branch->update(['name' => $name]);
                return $branch->refresh();
            });
            if (Branch::count() !== 3) throw new RuntimeException('Expected exactly the three audited branch identities.');

            $this->replaceLegacyDemoData();

            $ingredients = $this->ensureIngredients();
            $suppliers = $this->ensureSuppliers();
            $customers = $this->ensureCustomers($branches);
            $coupons = $this->ensureCoupons();
            $tag = Tag::query()->firstOrCreate(['slug' => 'jooal-catalog'], ['name' => 'JOOAL Catalog', 'is_active' => true]);
            if ($tag->name !== 'JOOAL Catalog' || ! $tag->is_active) throw new RuntimeException('Catalog tag identifier conflicts.');
            $products = $this->ensureProducts($ingredients, $tag);
            $addons = $this->ensureAddons($ingredients);
            $staff = $this->ensureStaff($branches);
            $this->ensureAttendance($branches, $staff);
            $this->ensureTables($branches);

            foreach ($branches as $branch) {
                $supplier = $suppliers[$branch->code];
                $this->receiveOpeningStock($branch, $supplier, $owner, $ingredients);
                $this->receiveDemoTopUp($branch, $supplier, $owner, $ingredients);
                $branchProducts = $products[$branch->code];
                foreach ($branchProducts as $index => $product) {
                    $this->syncBranchMenu($branch, $product['menu'], $product['price']);
                }
            }

            $this->ensureTransactions($branches, $products, $customers, $staff, $addons);
            foreach ($branches as $branch) $this->assertStockLedger($branch, $ingredients);
        }, 3);
    }

    private function assertSafeTargetAndSchema(): void
    {
        if (app()->environment('testing')) {
            if (! Schema::hasTable('coupons') || ! Schema::hasColumn('transactions', 'coupon_id')) {
                throw new RuntimeException('Test schema is missing coupon tables; run only the isolated test migrations.');
            }
            return;
        }

        $database = DB::selectOne('SELECT DATABASE() AS active_database');
        if (! $database || $database->active_database !== 'jooal_demo') {
            throw new RuntimeException('JooalDemoSeeder writes only when SELECT DATABASE() confirms jooal_demo.');
        }
        foreach (['coupons', 'customers', 'suppliers', 'attendances', 'stock_receipts', 'stock_receipt_items', 'recipe_items', 'branch_menu', 'branch_user'] as $table) {
            if (! Schema::hasTable($table)) throw new RuntimeException('Required table '.$table.' is absent. No migration is run by this seeder.');
        }
        foreach (['branch_id', 'customer_id', 'coupon_id', 'coupon_code', 'idempotency_key', 'idempotency_fingerprint'] as $column) {
            if (! Schema::hasColumn('transactions', $column)) throw new RuntimeException('Required transactions.'.$column.' is absent. No migration is run by this seeder.');
        }
    }

    private function replaceLegacyDemoData(): void
    {
        $legacyNumbers = [];
        foreach (array_keys(self::BRANCHES) as $code) foreach ([1, 2] as $i) $legacyNumbers[] = 'DEMO-'.$code.'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $transactions = Transaction::query()->whereIn('transaction_number', $legacyNumbers)->get();
        if ($transactions->isEmpty()) {
            $newTransactions = 0;
            foreach (array_keys(self::BRANCHES) as $code) for ($day = 1; $day <= 10; $day++) for ($slot = 1; $slot <= 3; $slot++) {
                $number = 'JOOAL-'.substr($code, -3).'-202610'.str_pad((string) $day, 2, '0', STR_PAD_LEFT).'-'.str_pad((string) $slot, 2, '0', STR_PAD_LEFT);
                if (Transaction::query()->where('transaction_number', $number)->exists()) $newTransactions++;
            }
            $newMenus = Menu::query()->where('slug', 'like', 'jooal-%')->count();
            if (in_array($newTransactions, [60, 90], true) && $newMenus === 48
                && StockReceipt::query()->where('receipt_number', 'like', 'JOOAL-STOCK-%')->count() === 3
                && Ingredient::query()->where('slug', 'like', 'jooal-%')->count() === count(self::INGREDIENTS)) return;
            throw new RuntimeException('No complete audited legacy dataset or complete JOOAL dataset exists; no replacement was performed.');
        }
        if ($transactions->count() !== 6) throw new RuntimeException('Legacy demo transaction set differs from the audited six rows.');
        if (DB::table('refunds')->whereIn('transaction_id', $transactions->pluck('id'))->exists()) throw new RuntimeException('A legacy demo transaction has a refund; preserving it and aborting.');
        if (Transaction::query()->where('transaction_number', 'like', 'DEMO-%')->whereNotIn('id', $transactions->pluck('id'))->exists()) throw new RuntimeException('Unexpected DEMO transaction exists; aborting.');
        $transactionIds = $transactions->pluck('id');
        if (TransactionItem::query()->whereIn('transaction_id', $transactionIds)->count() !== 6) throw new RuntimeException('Legacy transaction item rows differ from audit.');
        $txMovements = StockMovement::query()->whereIn('transaction_id', $transactionIds)->get();
        if ($txMovements->count() !== 21) throw new RuntimeException('Legacy sale movement rows differ from audit.');

        $receipts = StockReceipt::query()->whereIn('receipt_number', ['DEMO-OPEN-JOOAL001', 'DEMO-OPEN-JOOAL002', 'DEMO-OPEN-JOOAL003'])->get();
        if ($receipts->count() !== 3 || StockReceiptItem::query()->whereIn('stock_receipt_id', $receipts->pluck('id'))->count() !== 15) throw new RuntimeException('Legacy opening receipt rows differ from audit.');
        $receiptMovementIds = StockReceiptItem::query()->whereIn('stock_receipt_id', $receipts->pluck('id'))->pluck('stock_movement_id');
        if ($receiptMovementIds->count() !== 15 || StockMovement::query()->whereIn('id', $receiptMovementIds)->where('type', 'stock_in')->count() !== 15) throw new RuntimeException('Legacy receipt movements differ from audit.');

        $menuSlugs = ['demo-jooal-latte', 'demo-jooal-affogato'];
        $menus = Menu::query()->whereIn('slug', $menuSlugs)->get();
        if ($menus->count() !== 2 || DB::table('branch_menu')->whereIn('menu_id', $menus->pluck('id'))->count() !== 6) throw new RuntimeException('Legacy menu ownership differs from audit.');
        if (TransactionItem::query()->whereIn('menu_id', $menus->pluck('id'))->whereNotIn('transaction_id', $transactionIds)->exists()) throw new RuntimeException('An unmarked sale uses an old demo menu; aborting.');

        $ingredientSlugs = ['demo-jooal-coffee-beans', 'demo-jooal-milk', 'demo-jooal-gelato-base', 'demo-jooal-cup', 'demo-jooal-straw'];
        $ingredients = Ingredient::query()->whereIn('slug', $ingredientSlugs)->get();
        if ($ingredients->count() !== 5 || BranchStock::query()->whereIn('ingredient_id', $ingredients->pluck('id'))->count() !== 15) throw new RuntimeException('Legacy ingredient/stock ownership differs from audit.');
        if (StockMovement::query()->whereIn('ingredient_id', $ingredients->pluck('id'))->whereNotIn('id', $txMovements->pluck('id')->merge($receiptMovementIds))->exists()) throw new RuntimeException('Unexpected movement references an old demo ingredient.');
        $topping = Topping::query()->where('slug', 'demo-extra-shot')->first();
        if (! $topping) throw new RuntimeException('Legacy addon is absent.');
        if (RecipeItem::query()->whereIn('ingredient_id', $ingredients->pluck('id'))->where(function ($q) use ($menus, $topping) {
            $q->whereNotNull('menu_id')->whereNotIn('menu_id', $menus->pluck('id'))
                ->orWhere(function ($standalone) use ($topping) { $standalone->whereNull('menu_id')->where(function ($top) use ($topping) { $top->whereNull('topping_id')->orWhere('topping_id', '<>', $topping->id); }); });
        })->exists()) throw new RuntimeException('Unexpected recipe references an old ingredient.');

        if (TransactionItem::query()->where('topping_id', $topping->id)->whereNotIn('transaction_id', $transactionIds)->exists()) throw new RuntimeException('Legacy addon is referenced by an unmarked sale.');
        $customers = Customer::query()->where('name', 'like', 'DEMO - Pelanggan JOOAL-%')->get();
        if ($customers->count() !== 3 || Transaction::query()->whereIn('customer_id', $customers->pluck('id'))->whereNotIn('id', $transactionIds)->exists()) throw new RuntimeException('Legacy customer ownership differs from audit.');
        $coupon = Coupon::query()->where('code', 'JOOALDEMO10')->first();
        if (Coupon::query()->where('code', 'like', 'JOOALDEMO%')->count() !== 1
            || ! $coupon || Transaction::query()->where('coupon_id', $coupon->id)->exists()) throw new RuntimeException('Legacy coupon ownership differs from audit or is in use.');
        $tables = DiningTable::query()->whereIn('name', ['DEMO-001', 'DEMO-002', 'DEMO-003'])->get();
        if ($tables->count() !== 3 || Transaction::query()->whereIn('dining_table_id', $tables->pluck('id'))->exists()) throw new RuntimeException('Legacy table ownership differs from audit.');
        $supplier = Supplier::query()->where('name', 'DEMO - Pemasok JOOAL')->first();
        if (Supplier::query()->where('name', 'like', 'DEMO - Pemasok JOOAL%')->count() !== 1
            || ! $supplier || StockReceipt::query()->where('supplier_id', $supplier->id)->whereNotIn('id', $receipts->pluck('id'))->exists()) throw new RuntimeException('Legacy supplier ownership differs from audit or has additional receipts.');
        $tag = Tag::query()->where('slug', 'demo-jooal')->first();
        if (! $tag || DB::table('menu_tag')->where('tag_id', $tag->id)->whereNotIn('menu_id', $menus->pluck('id'))->exists()) throw new RuntimeException('Legacy demo tag is absent or linked to an unmarked menu.');

        DB::table('stock_receipt_items')->whereIn('stock_receipt_id', $receipts->pluck('id'))->delete();
        StockMovement::query()->whereIn('id', $receiptMovementIds)->delete();
        $receipts->each->delete();
        StockMovement::query()->whereIn('id', $txMovements->pluck('id'))->delete();
        $transactions->each->delete();
        RecipeItem::query()->whereIn('menu_id', $menus->pluck('id'))->delete();
        RecipeItem::query()->where('topping_id', $topping->id)->delete();
        DB::table('branch_menu')->whereIn('menu_id', $menus->pluck('id'))->delete();
        DB::table('menu_tag')->whereIn('menu_id', $menus->pluck('id'))->delete();
        $menus->each->delete();
        $topping->delete();
        BranchStock::query()->whereIn('ingredient_id', $ingredients->pluck('id'))->delete();
        $ingredients->each->delete();
        Customer::query()->whereIn('id', $customers->pluck('id'))->delete();
        DiningTable::query()->whereIn('id', $tables->pluck('id'))->delete();
        $coupon->delete(); $supplier->delete();
        DB::table('menu_tag')->where('tag_id', $tag->id)->delete(); $tag->delete();
        DB::table('coupons')->where('code', 'like', 'JOOALDEMO%')->delete();
        DB::table('suppliers')->where('name', 'like', 'DEMO - Pemasok JOOAL%')->delete();
    }

    private function ensureIngredients(): array
    {
        $result = [];
        foreach (self::INGREDIENTS as $key => [$name, $unit, $opening, $minimum]) {
            $slug = 'jooal-'.$key;
            $item = Ingredient::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'unit' => $unit, 'minimum_stock' => $minimum, 'is_active' => true]);
            if ($item->name !== $name || $item->unit !== $unit || (int) $item->minimum_stock !== $minimum || ! $item->is_active) throw new RuntimeException('Ingredient '.$slug.' conflicts with the approved dataset.');
            $result[$key] = $item;
        }
        return $result;
    }

    private function ensureSuppliers(): array
    {
        $names = ['Nusantara Dairy', 'Java Roastery Supply', 'Prima Pangan Sejuk', 'Tepung Makmur', 'Cokelat Artisan', 'Kemasan Lestari', 'Buah Segar Mandiri', 'Gelato Ingredients Co', 'Rempah Pilihan', 'Pastry Provisions'];
        $records = [];
        foreach ($names as $i => $name) {
            $email = 'procurement'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).'@jooal.example.test';
            $supplier = Supplier::query()->firstOrCreate(['name' => $name], ['contact_name' => ['Ayu Pratama','Bima Saputra','Citra Dewi','Dimas Putra','Eka Wulandari','Fajar Nugraha','Gita Lestari','Hana Permata','Indra Wijaya','Jihan Maharani'][$i], 'phone' => '021555'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'email' => $email, 'address' => 'Jakarta, Indonesia', 'notes' => 'Pemasok presentasi JOOAL POS', 'is_active' => true]);
            if (! $supplier->is_active || $supplier->email !== $email) throw new RuntimeException('Supplier '.$name.' conflicts with expected data.');
            $records[] = $supplier;
        }
        return ['JOOAL-001' => $records[5], 'JOOAL-002' => $records[7], 'JOOAL-003' => $records[1]];
    }

    private function ensureCustomers($branches): array
    {
        $names = [
            'JOOAL-001' => ['Nadia Rahma', 'Rizky Mahendra', 'Salsa Kirana', 'Arga Prasetyo', 'Maya Puspita', 'Dewi Anggraini', 'Farhan Akbar', 'Intan Permata', 'Kevin Wijaya', 'Larasati Putri'],
            'JOOAL-002' => ['Nadia Rahma Gelato', 'Rizky Mahendra Gelato', 'Salsa Kirana Gelato', 'Arga Prasetyo Gelato', 'Maya Puspita Gelato', 'Naufal Ramadhan', 'Olivia Tan', 'Putri Maharani', 'Rafi Hidayat', 'Sekar Ayuningtyas'],
            'JOOAL-003' => ['Nadia Rahma Coffee', 'Rizky Mahendra Coffee', 'Salsa Kirana Coffee', 'Arga Prasetyo Coffee', 'Maya Puspita Coffee', 'Tasya Amalia', 'Umar Faruq', 'Vina Oktaviani', 'Wahyu Prakoso', 'Yasmin Aulia'],
        ];
        $result = [];
        foreach ($branches as $branch) {
            foreach ($names[$branch->code] as $i => $name) {
                $emailName = $i < 5 ? ['Nadia Rahma', 'Rizky Mahendra', 'Salsa Kirana', 'Arga Prasetyo', 'Maya Puspita'][$i] : $name;
                $email = strtolower(str_replace(' ', '.', $emailName)).'.'.substr($branch->code, -3).'@customer.example.test';
                $customer = Customer::query()->where('branch_id', $branch->id)->where('email', $email)->first();
                if ($customer) {
                    $legacyName = ['Nadia Rahma', 'Rizky Mahendra', 'Salsa Kirana', 'Arga Prasetyo', 'Maya Puspita'][$i] ?? $name;
                    if (! in_array($customer->name, [$legacyName, $name], true) || ! $customer->is_active) throw new RuntimeException('Customer '.$email.' conflicts.');
                    if ($customer->name !== $name) $customer->update(['name' => $name]);
                } else {
                    $customer = Customer::query()->create(['branch_id' => $branch->id, 'email' => $email, 'name' => $name, 'phone' => '0812'.substr($branch->code, -3).str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'notes' => 'Pelanggan loyal; preferensi dicatat oleh tim cabang.', 'is_active' => true]);
                }
                if ($customer->name !== $name || ! $customer->is_active) throw new RuntimeException('Customer '.$email.' conflicts.');
                $result[$branch->code][$i] = $customer;
            }
        }
        return $result;
    }

    private function ensureCoupons(): array
    {
        $result = [];
        for ($i = 1; $i <= 10; $i++) {
            $code = 'JOOAL'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $type = $i <= 6 ? 'percentage' : 'fixed';
            $value = $type === 'percentage' ? (string) (5 + $i) : (string) (($i - 5) * 5000);
            $coupon = Coupon::query()->firstOrCreate(['code' => $code], ['discount_type' => $type, 'discount_value' => $value, 'starts_at' => '2026-01-01 00:00:00', 'ends_at' => '2027-12-31 23:59:59', 'usage_limit' => 250, 'used_count' => 0, 'is_active' => true]);
            if (! $coupon->is_active || $coupon->discount_type !== $type || (float) $coupon->discount_value !== (float) $value) throw new RuntimeException('Coupon '.$code.' conflicts.');
            $result[] = $coupon;
        }
        return $result;
    }

    private function ensureProducts(array $ingredients, Tag $tag): array
    {
        $all = [];
        foreach (self::BRANCHES as $code => $branchName) {
            $suffix = substr($code, -3);
            $branchCategorySlug = $suffix === '001' ? 'pastry' : 'non-matcha';
            $definitions = [];
            foreach (self::PRODUCT_GROUPS[$suffix] as $row) $definitions[] = [$suffix, $row[0], $row[1], $row[2]];
            foreach (self::SHARED_DRINKS as $row) $definitions[] = ['shared', $row[0], $row[1], $row[2]];
            foreach ($definitions as [$group, $name, $price, $recipeText]) {
                $categorySlug = $group === 'shared' ? 'non-matcha' : $branchCategorySlug;
                $category = Category::query()->where('slug', $categorySlug)->where('is_active', true)->first();
                if (! $category) throw new RuntimeException('Required active category '.$categorySlug.' is missing.');
                $slug = 'jooal-'.($group === 'shared' ? 'shared' : $suffix).'-'.strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
                $description = $group === 'shared' ? 'Disiapkan segar di setiap cabang JOOAL.' : 'Disiapkan segar setiap hari di '.$branchName.'.';
                $menu = Menu::query()->firstOrCreate(['slug' => $slug], ['category_id' => $category->id, 'name' => $name, 'description' => $description, 'price' => $price, 'is_active' => true]);
                if ($menu->name !== $name || (int) $menu->category_id !== (int) $category->id || (float) $menu->price !== (float) $price || ! $menu->is_active) throw new RuntimeException('Product '.$slug.' conflicts.');
                $variant = null;
                if ($categorySlug === 'pastry') {
                    $variant = MenuVariant::query()->firstOrCreate(['slug' => $slug.'-original'], ['menu_id' => $menu->id, 'name' => 'Original', 'price_adjustment' => 0, 'is_active' => true]);
                    if ((int) $variant->menu_id !== (int) $menu->id || ! $variant->is_active) throw new RuntimeException('Original pastry variant conflicts for '.$slug.'.');
                }
                $recipes = [];
                foreach (explode(',', $recipeText) as $pair) {
                    [$key, $quantity] = explode(':', $pair);
                    $ingredient = $ingredients[$key] ?? null;
                    if (! $ingredient) throw new RuntimeException('Unknown product ingredient '.$key.'.');
                    $this->ensureRecipe($menu, $ingredient, number_format((float) $quantity, 3, '.', ''), $ingredient->unit);
                    $recipes[] = [$key, (int) $quantity];
                }
                if (! app(RecipeStockService::class)->menuRecipeReady($menu)) throw new RuntimeException('Product recipe is incomplete: '.$slug);
                if (! $menu->tags()->whereKey($tag->id)->exists()) $menu->tags()->attach($tag->id);
                $all[$code][] = ['menu' => $menu, 'variant' => $variant, 'price' => $price, 'recipes' => $recipes];
            }
        }
        return $all;
    }

    private function ensureAddons(array $ingredients): array
    {
        $definitions = [
            ['extra-shot', 'Extra Shot Espresso', 7000, 'coffee', '18.000'],
            ['whipped-cream', 'Krim Kocok', 6000, 'cream', '25.000'],
            ['almond-crunch', 'Almond Crunch', 8000, 'almond', '12.000'],
            ['gelato-scoop', 'Scoop Gelato', 12000, 'gelato', '70.000'],
            ['caramel-drizzle', 'Caramel Drizzle', 5000, 'caramel', '12.000'],
            ['oat-upgrade', 'Oat Milk Upgrade', 7000, 'oat', '100.000'],
        ];
        $result = [];
        foreach ($definitions as [$slug, $name, $price, $ingredientKey, $qty]) {
            $addon = Topping::query()->firstOrCreate(['slug' => 'jooal-'.$slug], ['name' => $name, 'price' => $price, 'is_active' => true]);
            if ($addon->name !== $name || (float) $addon->price !== (float) $price || ! $addon->is_active) throw new RuntimeException('Addon '.$slug.' conflicts.');
            $this->ensureRecipe($addon, $ingredients[$ingredientKey], $qty, $ingredients[$ingredientKey]->unit);
            $result[] = $addon;
        }
        return $result;
    }

    private function ensureRecipe(Menu|Topping $product, Ingredient $ingredient, string $quantity, string $unit): void
    {
        $scope = ['menu_id' => $product instanceof Menu ? $product->id : null, 'menu_variant_id' => null, 'size_id' => null, 'topping_id' => $product instanceof Topping ? $product->id : null, 'ingredient_id' => $ingredient->id];
        $recipe = RecipeItem::query()->firstOrCreate($scope, ['quantity' => $quantity, 'unit' => $unit, 'is_active' => true]);
        if (! $recipe->is_active || (string) $recipe->quantity !== $quantity || $recipe->unit !== $unit) throw new RuntimeException('Recipe conflicts for '.$product->name.'.');
    }

    private function ensureStaff($branches): array
    {
        $names = [
            'JOOAL-001' => ['manager' => 'Alya Putri', 'cashier' => 'Bima Santoso', 'inventory' => 'Citra Lestari'],
            'JOOAL-002' => ['manager' => 'Dimas Pratama', 'cashier' => 'Nabila Sari', 'inventory' => 'Rangga Saputra'],
            'JOOAL-003' => ['manager' => 'Fajar Nugroho', 'cashier' => 'Gita Maharani', 'inventory' => 'Hana Kusuma'],
        ];
        $result = [];
        foreach ($branches as $branch) foreach ($names[$branch->code] as $role => $baseName) {
            $suffix = substr($branch->code, -3);
            $email = 'jooal.'.$role.'.'.$suffix.'@example.test';
            $legacyEmail = 'demo-'.$role.'-'.$suffix.'@example.test';
            $legacyUser = User::query()->where('email', $legacyEmail)->first();
            $currentUser = User::query()->where('email', $email)->first();
            if ($legacyUser && $currentUser && (int) $legacyUser->id !== (int) $currentUser->id) throw new RuntimeException('Two demo staff accounts claim the same branch role; preserving and aborting.');
            $user = $currentUser ?: $legacyUser;
            $legacyBaseName = ['manager' => 'Alya Putri', 'cashier' => 'Bima Santoso', 'inventory' => 'Citra Lestari'][$role];
            $legacyName = $legacyBaseName.' '.$branch->name;
            if ($user) {
                if ($user->role !== $role || ! $user->is_active || ! in_array($user->name, [$legacyName, $baseName.' '.$branch->name], true)
                    || DB::table('branch_user')->where('user_id', $user->id)->where('branch_id', '<>', $branch->id)->exists()) throw new RuntimeException('Legacy staff identity/assignment conflicts; preserving and aborting.');
                $user->update(['name' => $baseName.' '.$branch->name, 'email' => $email]);
            } else {
                $user = User::query()->create(['name' => $baseName.' '.$branch->name, 'email' => $email, 'password' => bcrypt('JooalDemo#2026'), 'role' => $role, 'is_active' => true]);
            }
            if (! DB::table('branch_user')->where('user_id', $user->id)->where('branch_id', $branch->id)->exists()) DB::table('branch_user')->insert(['branch_id' => $branch->id, 'user_id' => $user->id, 'is_default' => 1, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $result[$branch->code][$role] = $user;
        }
        return $result;
    }

    private function ensureAttendance($branches, array $staff): void
    {
        foreach ($branches as $branch) {
            foreach ([['cashier', range(1, 10)], ['manager', [1, 3, 5, 7, 9]], ['inventory', [2, 4, 6, 8, 10]]] as [$role, $days]) {
                foreach ($days as $day) {
                    $user = $staff[$branch->code][$role];
                    $minute = $role === 'cashier' ? ($day * 7) % 50 : ($day * 11 + strlen($role) * 3 + (int) substr($branch->code, -1) * 5) % 60;
                    $hour = $role === 'manager' ? 7 : ($role === 'inventory' ? 8 : 8);
                    $checkIn = Carbon::parse('2026-10-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT).' '.str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $minute, 2, '0', STR_PAD_LEFT).':00', $branch->timezone);
                    if (DB::table('attendances')->where('user_id', $user->id)->where('branch_id', $branch->id)->where('check_in_at', $checkIn->toDateTimeString())->exists()) continue;
                    $durationMinutes = $role === 'cashier' ? 480 : 465 + (($day * 17 + (int) substr($branch->code, -1) * 13) % 76);
                    DB::table('attendances')->insert(['user_id' => $user->id, 'branch_id' => $branch->id, 'check_in_at' => $checkIn, 'check_out_at' => (clone $checkIn)->addMinutes($durationMinutes), 'status' => 'checked_out', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    private function ensureTables($branches): void
    {
        foreach ($branches as $branch) for ($i = 1; $i <= 4; $i++) DiningTable::query()->firstOrCreate(['branch_id' => $branch->id, 'name' => 'Meja '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)], ['capacity' => $i === 4 ? 6 : 2, 'is_active' => true]);
    }

    private function syncBranchMenu(Branch $branch, Menu $menu, int $price): void
    {
        $row = DB::table('branch_menu')->where('branch_id', $branch->id)->where('menu_id', $menu->id)->first();
        if ($row) {
            if ((float) $row->price !== (float) $price || ! (bool) $row->is_available) throw new RuntimeException('Branch menu assignment conflicts for '.$menu->slug.'.');
            return;
        }
        DB::table('branch_menu')->insert(['branch_id' => $branch->id, 'menu_id' => $menu->id, 'price' => $price, 'is_available' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function receiveOpeningStock(Branch $branch, Supplier $supplier, User $owner, array $ingredients): void
    {
        $number = 'JOOAL-STOCK-'.substr($branch->code, -3).'-20261001';
        $existing = StockReceipt::query()->with('items.movement')->where('receipt_number', $number)->first();
        if ($existing) {
            if ((int) $existing->branch_id !== (int) $branch->id || (int) $existing->supplier_id !== (int) $supplier->id
                || $existing->reason !== 'Persediaan awal Oktober 2026' || $existing->items->count() !== count(self::INGREDIENTS)) {
                throw new RuntimeException($number.' differs from its expected opening receipt.');
            }
            $math = app(RecipeStockService::class);
            foreach (self::INGREDIENTS as $key => [$name, $unit, $opening]) {
                $ingredient = $ingredients[$key];
                $line = $existing->items->firstWhere('ingredient_id', $ingredient->id);
                $movement = optional($line)->movement;
                $quantity = $math->formatMilli($opening * 1000);
                if (! $line || (string) $line->quantity !== $quantity || ! $movement
                    || (int) $movement->branch_id !== (int) $branch->id || (int) $movement->ingredient_id !== (int) $ingredient->id
                    || $movement->type !== 'stock_in' || $movement->reference_key !== 'jooal:opening:'.$branch->code.':'.$ingredient->id
                    || (string) $movement->quantity_delta !== $quantity || $math->toMilli((string) $movement->quantity_before) + $opening * 1000
                        !== $math->toMilli((string) $movement->quantity_after)) {
                    throw new RuntimeException($number.' opening movement differs from the approved ingredient balance.');
                }
            }
            return;
        }
        $receipt = StockReceipt::query()->create(['receipt_number' => $number, 'branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'received_by' => $owner->id, 'reason' => 'Persediaan awal Oktober 2026', 'received_at' => '2026-10-01 07:00:00']);
        $math = app(RecipeStockService::class);
        foreach (self::INGREDIENTS as $key => [$name, $unit, $opening]) {
            $ingredient = $ingredients[$key];
            $stock = BranchStock::query()->firstOrCreate(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id], ['quantity' => '0.000']);
            $before = $math->toMilli((string) $stock->quantity);
            $qty = $opening * 1000;
            if ($before !== 0) throw new RuntimeException('New opening receipt encountered nonzero stock for '.$ingredient->slug.'.');
            $after = $before + $qty;
            $stock->update(['quantity' => $math->formatMilli($after)]);
            $movement = StockMovement::query()->create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'user_id' => $owner->id, 'reference_key' => 'jooal:opening:'.$branch->code.':'.$ingredient->id, 'type' => 'stock_in', 'quantity_delta' => $math->formatMilli($qty), 'quantity_before' => $math->formatMilli($before), 'quantity_after' => $math->formatMilli($after), 'reason' => 'Persediaan awal Oktober 2026']);
            StockReceiptItem::query()->create(['stock_receipt_id' => $receipt->id, 'ingredient_id' => $ingredient->id, 'stock_movement_id' => $movement->id, 'quantity' => $math->formatMilli($qty)]);
        }
    }

    /** Add a separately identified demo receipt to cover the added sample baskets. */
    private function receiveDemoTopUp(Branch $branch, Supplier $supplier, User $owner, array $ingredients): void
    {
        $number = 'JOOAL-TOPUP-'.substr($branch->code, -3).'-202610';
        $receipt = StockReceipt::query()->with('items.movement')->where('receipt_number', $number)->first();
        $math = app(RecipeStockService::class);
        if ($receipt) {
            if ((int) $receipt->branch_id !== (int) $branch->id || (int) $receipt->supplier_id !== (int) $supplier->id
                || $receipt->reason !== 'Persediaan demo tambahan untuk transaksi presentasi' || $receipt->items->count() !== count(self::INGREDIENTS)) throw new RuntimeException($number.' differs from its expected demo top-up receipt.');
            foreach (self::INGREDIENTS as $key => [$name, $unit, $opening]) {
                $ingredient = $ingredients[$key]; $qty = intdiv($opening, 2) * 1000;
                $line = $receipt->items->firstWhere('ingredient_id', $ingredient->id); $movement = optional($line)->movement;
                if (! $line || (string) $line->quantity !== $math->formatMilli($qty) || ! $movement
                    || $movement->reference_key !== 'jooal:topup:'.$branch->code.':'.$ingredient->id
                    || (string) $movement->quantity_delta !== $math->formatMilli($qty)
                    || $math->toMilli((string) $movement->quantity_before) + $qty !== $math->toMilli((string) $movement->quantity_after)) throw new RuntimeException($number.' receipt line differs from its approved top-up.');
            }
            return;
        }
        $receipt = StockReceipt::query()->create(['receipt_number' => $number, 'branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'received_by' => $owner->id, 'reason' => 'Persediaan demo tambahan untuk transaksi presentasi', 'received_at' => '2026-10-05 07:00:00']);
        foreach (self::INGREDIENTS as $key => [$name, $unit, $opening]) {
            $ingredient = $ingredients[$key]; $qty = intdiv($opening, 2) * 1000;
            $stock = BranchStock::query()->where('branch_id', $branch->id)->where('ingredient_id', $ingredient->id)->lockForUpdate()->firstOrFail();
            $before = $math->toMilli((string) $stock->quantity); $after = $before + $qty;
            $stock->update(['quantity' => $math->formatMilli($after)]);
            $movement = StockMovement::query()->create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'user_id' => $owner->id, 'reference_key' => 'jooal:topup:'.$branch->code.':'.$ingredient->id, 'type' => 'stock_in', 'quantity_delta' => $math->formatMilli($qty), 'quantity_before' => $math->formatMilli($before), 'quantity_after' => $math->formatMilli($after), 'reason' => 'Persediaan demo tambahan untuk transaksi presentasi']);
            StockReceiptItem::query()->create(['stock_receipt_id' => $receipt->id, 'ingredient_id' => $ingredient->id, 'stock_movement_id' => $movement->id, 'quantity' => $math->formatMilli($qty)]);
        }
    }

    private function ensureTransactions($branches, array $products, array $customers, array $staff, array $addons): void
    {
        $globalSequence = 0;
        foreach ($branches as $branch) for ($day = 1; $day <= 10; $day++) for ($slot = 1; $slot <= 2; $slot++) {
            $globalSequence++;
            $productIndex = (($day * 3 + $slot * 5) % count($products[$branch->code]));
            $product = $products[$branch->code][$productIndex];
            $quantity = 1 + (($day + $slot) % 2);
            $addon = (($day + $slot) % 4 === 0) ? $addons[($day + $slot) % count($addons)] : null;
            $this->seedSale($branch, $staff[$branch->code]['cashier'], $customers[$branch->code][($day + $slot) % 5], $product, $addon, $day, $slot, $quantity, $globalSequence);
        }
        foreach ($branches as $branch) for ($day = 1; $day <= 10; $day++) {
            $list = array_values(array_filter($products[$branch->code], fn ($product) => ! collect($product['recipes'])->contains(fn ($recipe) => $recipe[0] === 'cinnamon')));
            $firstIndex = ($day * 7 + (int) substr($branch->code, -1)) % count($list);
            $secondIndex = ($firstIndex + 3 + ($day % 4)) % count($list);
            if ($secondIndex === $firstIndex) $secondIndex = ($firstIndex + 1) % count($list);
            $items = [
                ['product' => $list[$firstIndex], 'quantity' => 1 + ($day % 2)],
                ['product' => $list[$secondIndex], 'quantity' => 1],
            ];
            $minute = ($day * 13 + (int) substr($branch->code, -1) * 7) % 60;
            $hour = 11 + ($day % 6);
            $time = str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $minute, 2, '0', STR_PAD_LEFT).':00';
            $customerIndex = 5 + (($day + (int) substr($branch->code, -1)) % 5);
            $sequence = 60 + $day + (array_search($branch, $branches->values()->all(), true) * 10);
            $this->seedBasketSale($branch, $staff[$branch->code]['cashier'], $customers[$branch->code][$customerIndex], $items, $day, $time, $sequence);
        }
    }

    /** Add a deterministic, two-line basket while leaving previously seeded sales unchanged. */
    private function seedBasketSale(Branch $branch, User $cashier, Customer $customer, array $items, int $day, string $time, int $sequence): void
    {
        $number = 'JOOAL-'.substr($branch->code, -3).'-202610'.str_pad((string) $day, 2, '0', STR_PAD_LEFT).'-03';
        $key = 'jooal:'.$number;
        $math = app(RecipeStockService::class);
        $cart = [];
        $snapshots = [];
        $grandTotal = 0.0;
        foreach ($items as $line) {
            $product = $line['product'];
            $menu = $product['menu'];
            $branchPrice = (float) DB::table('branch_menu')->where('branch_id', $branch->id)->where('menu_id', $menu->id)->value('price');
            $unitPrice = $branchPrice + (float) optional($product['variant'])->price_adjustment;
            $quantity = (int) $line['quantity'];
            $lineTotal = $unitPrice * $quantity;
            $grandTotal += $lineTotal;
            $cart[] = ['menu_id' => $menu->id, 'menu_variant_id' => optional($product['variant'])->id, 'size_id' => null, 'topping_ids' => [], 'quantity' => $quantity];
            $snapshots[] = ['menu' => $menu, 'variant' => $product['variant'], 'branch_price' => $branchPrice, 'unit_price' => $unitPrice, 'quantity' => $quantity, 'line_total' => $lineTotal];
        }
        $requirements = $math->requirementsForCart(collect($cart));
        $soldAt = Carbon::parse('2026-10-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT).' '.$time, $branch->timezone);
        $fingerprintParts = array_map(fn ($snapshot, $cartItem) => ['menu' => $snapshot['menu']->slug, 'quantity' => $snapshot['quantity'], 'variant' => optional($snapshot['variant'])->slug, 'recipe' => $math->recipeFingerprintForItem($cartItem)], $snapshots, $cart);
        $fingerprint = hash('sha256', json_encode(['branch' => $branch->code, 'items' => $fingerprintParts]));
        $payment = $sequence % 2 === 0 ? 'qris' : 'cash';
        $transaction = Transaction::query()->where('transaction_number', $number)->first();
        if ($transaction) {
            if ((int) $transaction->branch_id !== (int) $branch->id || $transaction->idempotency_key !== $key || $transaction->idempotency_fingerprint !== $fingerprint
                || $transaction->status !== 'completed' || $transaction->payment_method !== $payment || (int) $transaction->customer_id !== (int) $customer->id
                || $transaction->customer_name !== $customer->name || (float) $transaction->grand_total !== $grandTotal
                || (float) $transaction->subtotal !== $grandTotal || (float) $transaction->amount_paid !== $grandTotal
                || ! $transaction->sold_at || ! $transaction->sold_at->equalTo($soldAt)) throw new RuntimeException($number.' conflicts with its idempotent basket snapshot.');
            $saved = TransactionItem::query()->where('transaction_id', $transaction->id)->orderBy('id')->get();
            if ($saved->count() !== count($snapshots)) throw new RuntimeException($number.' item count differs from its basket snapshot.');
            foreach ($snapshots as $index => $snapshot) {
                $item = $saved[$index];
                if ((int) $item->menu_id !== (int) $snapshot['menu']->id || (int) $item->quantity !== $snapshot['quantity']
                    || (float) $item->branch_price !== $snapshot['branch_price'] || (float) $item->unit_price !== $snapshot['unit_price']
                    || (float) $item->line_total !== $snapshot['line_total']) throw new RuntimeException($number.' line item differs from its basket snapshot.');
            }
            $this->assertSaleMovements($branch, $number, $requirements, $math);
            return;
        }
        if (Transaction::query()->where('idempotency_key', $key)->exists()) throw new RuntimeException($key.' is already attached to another transaction.');
        $transaction = Transaction::query()->create(['transaction_number' => $number, 'idempotency_key' => $key, 'idempotency_fingerprint' => $fingerprint, 'cashier_id' => $cashier->id, 'branch_id' => $branch->id, 'customer_id' => $customer->id, 'customer_name' => $customer->name, 'service_type' => 'take_away', 'fulfillment_type' => 'self_service', 'pager_number' => 'P'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT), 'subtotal' => $grandTotal, 'discount_total' => 0, 'tax_total' => 0, 'grand_total' => $grandTotal, 'amount_paid' => $grandTotal, 'change_amount' => 0, 'payment_method' => $payment, 'status' => 'completed', 'sold_at' => $soldAt]);
        foreach ($snapshots as $snapshot) TransactionItem::query()->create(['transaction_id' => $transaction->id, 'menu_id' => $snapshot['menu']->id, 'menu_name' => $snapshot['menu']->name, 'matcha_type_id' => null, 'size_id' => null, 'topping_id' => null, 'quantity' => $snapshot['quantity'], 'unit_price' => $snapshot['unit_price'], 'menu_price' => $snapshot['menu']->price, 'branch_price' => $snapshot['branch_price'], 'line_total' => $snapshot['line_total'], 'item_options' => ['toppings' => [], 'variant' => optional($snapshot['variant'])->name]]);
        foreach ($requirements as $requirement) {
            $ingredient = $requirement['ingredient'];
            $stock = BranchStock::query()->where('branch_id', $branch->id)->where('ingredient_id', $ingredient->id)->lockForUpdate()->firstOrFail();
            $before = $math->toMilli((string) $stock->quantity); $needed = (int) $requirement['quantity_milli'];
            if ($before < $needed) throw new RuntimeException('Insufficient stock for '.$ingredient->name.' at '.$branch->name.'.');
            $after = $before - $needed; $stock->update(['quantity' => $math->formatMilli($after)]);
            StockMovement::query()->create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'user_id' => $cashier->id, 'transaction_id' => $transaction->id, 'reference_key' => 'jooal:sale:'.$number.':'.$ingredient->id, 'type' => 'sale', 'quantity_delta' => '-'.$math->formatMilli($needed), 'quantity_before' => $math->formatMilli($before), 'quantity_after' => $math->formatMilli($after), 'reason' => 'Penjualan historis '.$number]);
        }
    }

    private function assertSaleMovements(Branch $branch, string $number, $requirements, RecipeStockService $math): void
    {
        $movements = StockMovement::query()->where('transaction_id', Transaction::query()->where('transaction_number', $number)->value('id'))->get()->keyBy('ingredient_id');
        if ($movements->count() !== $requirements->count()) throw new RuntimeException($number.' stock movement count differs from its basket recipe.');
        foreach ($requirements as $requirement) {
            $ingredient = $requirement['ingredient']; $movement = $movements->get($ingredient->id); $needed = (int) $requirement['quantity_milli'];
            if (! $movement || (int) $movement->branch_id !== (int) $branch->id || $movement->type !== 'sale'
                || $movement->reference_key !== 'jooal:sale:'.$number.':'.$ingredient->id
                || (string) $movement->quantity_delta !== '-'.$math->formatMilli($needed)
                || $math->toMilli((string) $movement->quantity_before) - $needed !== $math->toMilli((string) $movement->quantity_after)) throw new RuntimeException($number.' stock movement differs from its basket recipe.');
        }
    }

    private function seedSale(Branch $branch, User $cashier, Customer $customer, array $product, ?Topping $addon, int $day, int $slot, int $quantity, int $sequence): void
    {
        $menu = $product['menu'];
        $number = 'JOOAL-'.substr($branch->code, -3).'-202610'.str_pad((string) $day, 2, '0', STR_PAD_LEFT).'-'.str_pad((string) $slot, 2, '0', STR_PAD_LEFT);
        $key = 'jooal:'.$number;
        $payment = $sequence % 3 === 0 ? 'qris' : 'cash';
        $branchPrice = (float) DB::table('branch_menu')->where('branch_id', $branch->id)->where('menu_id', $menu->id)->value('price');
        $unitPrice = $branchPrice + ($addon ? (float) $addon->price : 0);
        $total = $unitPrice * $quantity;
        $cartItem = ['menu_id' => $menu->id, 'menu_variant_id' => optional($product['variant'])->id, 'size_id' => null, 'topping_ids' => $addon ? [$addon->id] : [], 'quantity' => $quantity];
        $math = app(RecipeStockService::class);
        $requirements = $math->requirementsForCart(collect([$cartItem]));
        $soldAt = Carbon::parse('2026-10-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT).' '.($slot === 1 ? '10:15' : '16:40').':00', $branch->timezone);
        $fingerprint = hash('sha256', json_encode(['branch' => $branch->code, 'menu' => $menu->slug, 'addon' => $addon ? $addon->slug : null, 'qty' => $quantity, 'recipe' => $math->recipeFingerprintForItem($cartItem)]));
        $transaction = Transaction::query()->where('transaction_number', $number)->first();
        if ($transaction) {
            $emailLocalPart = explode('@', (string) $customer->email)[0];
            $legacySnapshotName = ucwords(str_replace('.', ' ', preg_replace('/\.\d{3}$/', '', $emailLocalPart)));
            if ((int) $transaction->branch_id !== (int) $branch->id || $transaction->idempotency_key !== $key
                || $transaction->idempotency_fingerprint !== $fingerprint || $transaction->status !== 'completed'
                || $transaction->payment_method !== $payment || (int) $transaction->customer_id !== (int) $customer->id
                || ! in_array($transaction->customer_name, [$customer->name, $legacySnapshotName], true) || (float) $transaction->subtotal !== $total
                || (float) $transaction->grand_total !== $total || (float) $transaction->amount_paid !== $total
                || (float) $transaction->discount_total !== 0.0 || (float) $transaction->tax_total !== 0.0
                || (float) $transaction->change_amount !== 0.0 || ! $transaction->sold_at || ! $transaction->sold_at->equalTo($soldAt)) {
                throw new RuntimeException($number.' conflicts with its expected idempotent transaction snapshot.');
            }
            $items = TransactionItem::query()->where('transaction_id', $transaction->id)->get();
            $item = $items->first();
            $expectedOptions = ['toppings' => $addon ? [$addon->name] : []];
            if ($items->count() !== 1 || ! $item || (int) $item->menu_id !== (int) $menu->id
                || $item->menu_name !== $menu->name || (int) $item->quantity !== $quantity
                || (int) $item->topping_id !== (int) optional($addon)->id
                || (float) $item->unit_price !== $unitPrice || (float) $item->menu_price !== (float) $menu->price
                || (float) $item->branch_price !== $branchPrice || (float) $item->line_total !== $total
                || json_encode($item->item_options) !== json_encode($expectedOptions)) {
                throw new RuntimeException($number.' item snapshot differs from its idempotent definition.');
            }
            $movements = StockMovement::query()->where('transaction_id', $transaction->id)->get()->keyBy('ingredient_id');
            if ($movements->count() !== $requirements->count()) throw new RuntimeException($number.' stock movement count differs from its recipe.');
            foreach ($requirements as $requirement) {
                $ingredient = $requirement['ingredient'];
                $movement = $movements->get($ingredient->id);
                $needed = $math->formatMilli((int) $requirement['quantity_milli']);
                if (! $movement || (int) $movement->branch_id !== (int) $branch->id || $movement->type !== 'sale'
                    || $movement->reference_key !== 'jooal:sale:'.$number.':'.$ingredient->id
                    || (string) $movement->quantity_delta !== '-'.$needed
                    || $math->toMilli((string) $movement->quantity_before) - (int) $requirement['quantity_milli']
                        !== $math->toMilli((string) $movement->quantity_after)) {
                    throw new RuntimeException($number.' stock movement differs from its recipe requirements.');
                }
            }
            return;
        }
        if (Transaction::query()->where('idempotency_key', $key)->exists()) throw new RuntimeException($key.' is already attached to another transaction.');
        $transaction = Transaction::query()->create(['transaction_number' => $number, 'idempotency_key' => $key, 'idempotency_fingerprint' => $fingerprint, 'cashier_id' => $cashier->id, 'branch_id' => $branch->id, 'customer_id' => $customer->id, 'customer_name' => $customer->name, 'service_type' => 'take_away', 'fulfillment_type' => 'self_service', 'pager_number' => 'P'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT), 'subtotal' => $total, 'discount_total' => 0, 'tax_total' => 0, 'grand_total' => $total, 'amount_paid' => $total, 'change_amount' => 0, 'payment_method' => $payment, 'status' => 'completed', 'sold_at' => $soldAt]);
        TransactionItem::query()->create(['transaction_id' => $transaction->id, 'menu_id' => $menu->id, 'menu_name' => $menu->name, 'matcha_type_id' => null, 'size_id' => null, 'topping_id' => $addon?->id, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'menu_price' => $menu->price, 'branch_price' => $branchPrice, 'line_total' => $total, 'item_options' => ['toppings' => $addon ? [$addon->name] : []]]);
        foreach ($requirements as $requirement) {
            $ingredient = $requirement['ingredient'];
            $stock = BranchStock::query()->where('branch_id', $branch->id)->where('ingredient_id', $ingredient->id)->lockForUpdate()->firstOrFail();
            $before = $math->toMilli((string) $stock->quantity);
            $needed = (int) $requirement['quantity_milli'];
            if ($before < $needed) throw new RuntimeException('Insufficient stock for '.$ingredient->name.' at '.$branch->name.'.');
            $after = $before - $needed;
            $stock->update(['quantity' => $math->formatMilli($after)]);
            StockMovement::query()->create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'user_id' => $cashier->id, 'transaction_id' => $transaction->id, 'reference_key' => 'jooal:sale:'.$number.':'.$ingredient->id, 'type' => 'sale', 'quantity_delta' => '-'.$math->formatMilli($needed), 'quantity_before' => $math->formatMilli($before), 'quantity_after' => $math->formatMilli($after), 'reason' => 'Penjualan historis '.$number]);
        }
    }

    private function assertStockLedger(Branch $branch, array $ingredients): void
    {
        $math = app(RecipeStockService::class);
        foreach ($ingredients as $ingredient) {
            $expectedBefore = 0;
            foreach (StockMovement::query()->where('branch_id', $branch->id)->where('ingredient_id', $ingredient->id)->orderBy('id')->get() as $movement) {
                $delta = (string) $movement->quantity_delta;
                $negative = strpos($delta, '-') === 0;
                $milli = $math->toMilli($negative ? substr($delta, 1) : $delta) * ($negative ? -1 : 1);
                $before = $math->toMilli((string) $movement->quantity_before); $after = $math->toMilli((string) $movement->quantity_after);
                if ($before !== $expectedBefore || $after !== $before + $milli || $after < 0) throw new RuntimeException('Stock ledger chain failed for '.$ingredient->slug.' at '.$branch->name.'.');
                $expectedBefore = $after;
            }
            $stock = BranchStock::query()->where('branch_id', $branch->id)->where('ingredient_id', $ingredient->id)->first();
            if ($math->toMilli((string) optional($stock)->quantity) !== $expectedBefore) throw new RuntimeException('Branch balance differs from ledger for '.$ingredient->slug.'.');
        }
    }
}


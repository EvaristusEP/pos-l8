<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sidebar_groups_existing_master_report_and_inventory_routes(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $response = $this->actingAs($owner)->get('/dashboard/owner')->assertOk();

        $response->assertSee('Master Data')
            ->assertSee('Report')
            ->assertSee('Inventory')
            ->assertSee('aria-controls="sidebar-master-data"', false)
            ->assertSee('aria-controls="sidebar-reports"', false)
            ->assertSee('aria-controls="sidebar-inventory"', false)
            ->assertSee(route('master-menu.index'), false)
            ->assertSee(route('reports.payment-methods'), false)
            ->assertSee(route('reports.refunds'), false)
            ->assertSee(route('reports.attendance'), false)
            ->assertSee(route('inventory.stock-in.index'), false)
            ->assertSee(route('inventory.stocks.index'), false)
            ->assertSee(route('inventory.movements.index'), false)
            ->assertSee(route('inventory.materials.index'), false)
            ->assertSee(route('orders.index'), false)
            ->assertSee(route('coupons.index'), false)
            ->assertSee(route('settings.system'), false)
            ->assertSee(route('users.index'), false)
            ->assertSee('Roles')
            ->assertDontSee('Email Invoice - via transaction detail')
            ->assertSee('Gross Profit — unavailable')
            ->assertSee('Branch Management');
        $response->assertSee('pos-sidebar-dashboard', false)
            ->assertSee('pos-sidebar-footer', false)
            ->assertSee('Auto')
            ->assertDontSee('Tetap terlihat')
            ->assertDontSee('Menu yang bisa di-scroll')
            ->assertDontSee('Kasir cepat, pesanan rapi.')
            ->assertDontSee('data-theme-toggle', false);

        $html = $response->getContent();
        $dashboardLink = strpos($html, 'href="'.route('dashboard').'"');
        $attendanceLink = strpos($html, 'href="'.route('attendance.index').'"');
        $masterDataLink = strpos($html, 'aria-controls="sidebar-master-data"');

        $this->assertNotFalse($dashboardLink);
        $this->assertNotFalse($attendanceLink);
        $this->assertNotFalse($masterDataLink);
        $this->assertLessThan($attendanceLink, $dashboardLink);
        $this->assertLessThan($masterDataLink, $attendanceLink);
    }

    public function test_cashier_sidebar_keeps_pos_navigation_without_admin_submenus(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        $response = $this->actingAs($cashier)->get('/attendance')->assertOk();

        $response->assertSee('Absensi')
            ->assertSee(route('dashboard.pos'), false)
            ->assertSee(route('history'), false)
            ->assertDontSee('sidebar-master-data', false)
            ->assertDontSee('sidebar-reports', false)
            ->assertDontSee('sidebar-inventory', false);
    }

    public function test_pos_navigation_and_branch_options_follow_role_access(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $branch = \App\Models\Branch::create(['code' => 'OWN-01', 'name' => 'JOOAL Jakarta', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);

        $ownerHtml = $this->actingAs($owner)->withSession(['active_branch_id' => $branch->id])->get('/dashboard/pos')->assertOk()->getContent();
        $this->assertStringContainsString('POS / Kasir', $ownerHtml);
        $this->assertStringContainsString(route('dashboard.pos'), $ownerHtml);
        $this->assertStringContainsString(route('orders.index'), $ownerHtml);
        $this->assertStringContainsString(route('history'), $ownerHtml);
        $this->assertStringContainsString('JOOAL Jakarta', $ownerHtml);
        $this->assertStringContainsString('pos-branch-options', $ownerHtml);
        $this->assertStringContainsString('aria-current="true"', $ownerHtml);

        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $cashier->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $cashierHtml = $this->actingAs($cashier)->get('/dashboard/pos')->assertOk()->getContent();
        $this->assertStringContainsString('POS / Kasir', $cashierHtml);
        $this->assertStringContainsString('JOOAL Jakarta', $cashierHtml);

        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $inventoryHtml = $this->actingAs($inventory)->get('/dashboard/inventory')->assertOk()->getContent();
        $this->assertStringNotContainsString('POS / Kasir', $inventoryHtml);
        $this->assertStringNotContainsString(route('dashboard.pos'), $inventoryHtml);
    }
    public function test_inventory_submenus_and_materials_have_exact_active_group_and_link(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        foreach ([
            'inventory.stock-in.index' => '/inventory/stock-in',
            'inventory.stocks.index' => '/inventory/branches',
            'inventory.movements.index' => '/inventory/movements',
        ] as $routeName => $expectedPath) {
            $html = $this->actingAs($owner)->get(route($routeName))->assertOk()->getContent();
            $activePaths = $this->activeSidebarPaths($html, [
                '/inventory/stock-in', '/inventory/branches', '/inventory/movements', '/inventory/materials',
            ]);

            $this->assertSame([$expectedPath], $activePaths, $routeName.' should highlight one matching submenu.');
            $this->assertStringContainsString('groups: { master: false, reports: false, inventory: true }', $html);
        }

        $html = $this->actingAs($owner)->get(route('inventory.materials.index'))->assertOk()->getContent();
        $this->assertSame(['/inventory/materials'], $this->activeSidebarPaths($html, [
            '/inventory/stock-in', '/inventory/branches', '/inventory/movements', '/inventory/materials',
        ]));
        $this->assertStringContainsString('groups: { master: true, reports: false, inventory: false }', $html);
    }

    public function test_shared_sidebar_renders_once_on_addons_materials_and_settings_pages(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        foreach (['addons.index', 'inventory.materials.index', 'settings.system'] as $routeName) {
            $html = $this->actingAs($owner)->get(route($routeName))->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'class="pos-sidebar-shell"'), $routeName.' should render one sidebar shell.');
            $this->assertSame(2, substr_count($html, '@click="toggle()"'), $routeName.' should render desktop and mobile sidebar toggles.');
            $this->assertStringContainsString('aria-label="Buka navigasi"', $html, $routeName.' should expose the mobile drawer toggle.');
        }
    }

    public function test_global_sidebar_and_content_grid_render_across_priority_pages(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $shellRoutes = [
            'attendance.index', 'recipes.index', 'reports.payment-methods', 'orders.index', 'history',
            'inventory.materials.index', 'inventory.stock-in.index', 'inventory.movements.index',
            'dashboard.inventory', 'settings.system',
        ];

        foreach ($shellRoutes as $routeName) {
            $response = $this->actingAs($owner)->get(route($routeName))->assertOk();
            $html = $response->getContent();
            $this->assertSame(1, substr_count($html, 'class="pos-sidebar-shell"'), $routeName.' should render the shared sidebar once.');
            $this->assertStringContainsString('body:has(.pos-sidebar) > :not(.pos-sidebar-shell)', $html, $routeName.' should use the shared content grid.');
        }
    }

    public function test_sidebar_keeps_dashboard_scrollable_navigation_and_footer_regions_separate(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $html = $this->actingAs($owner)->get('/dashboard/owner')->assertOk()->getContent();

        $dashboard = strpos($html, 'class="pos-sidebar-dashboard');
        $navigation = strpos($html, 'class="pos-sidebar-nav');
        $footer = strpos($html, 'class="pos-sidebar-footer');

        $this->assertNotFalse($dashboard);
        $this->assertNotFalse($navigation);
        $this->assertNotFalse($footer);
        $this->assertLessThan($navigation, $dashboard);
        $this->assertLessThan($footer, $navigation);
        $dashboardLinkEnd = strpos($html, '</a>', $dashboard);
        $dashboardLink = substr($html, $dashboard, $dashboardLinkEnd - $dashboard);
        $this->assertStringContainsString('Dashboard', $dashboardLink);
        $this->assertStringNotContainsString('path d="m5 12 4 4L19 6"', $dashboardLink, 'Dashboard must not render a checkmark indicator.');
        $sidebarStyles = file_get_contents(resource_path('views/components/pos-sidebar.blade.php'));
        $this->assertStringContainsString('body:has(.pos-sidebar) { --pos-sidebar-width: 72px; display: grid;', $sidebarStyles);
        $this->assertStringContainsString('.pos-sidebar { position: fixed !important;', $sidebarStyles);
        $this->assertStringContainsString('> :not(.pos-sidebar-shell):not(script):not(style):not(dialog) { grid-column: 2;', $sidebarStyles, 'Top-layer dialogs must not inherit the page grid margin reset.');
        $this->assertStringContainsString('> :not(.pos-sidebar-shell):not(script):not(style):not(dialog) { display: block;', $sidebarStyles, 'Mobile page layout rules must not force dialogs to display as page content.');
        $this->assertStringNotContainsString('> :not(.pos-sidebar-shell):not(script):not(style) { grid-column: 2;', $sidebarStyles);
        $this->assertStringContainsString('height: 100vh; height: 100dvh;', $sidebarStyles);
        $this->assertStringContainsString('.pos-sidebar-nav { flex: 1 1 0%; min-height: 0; overflow-x: hidden; overflow-y: auto;', $sidebarStyles);
        $this->assertStringContainsString('grid-template-columns: var(--pos-sidebar-width) minmax(0, 1fr)', $sidebarStyles);
        $this->assertStringContainsString('html[data-sidebar="expanded"] body:has(.pos-sidebar) { --pos-sidebar-width: 224px;', $sidebarStyles);
        $this->assertStringContainsString('--pos-sidebar-width: min(220px, 58vw)', $sidebarStyles);
        $this->assertStringContainsString('.pos-sidebar-footer { flex: 0 1 auto; min-height: 0; max-height: 40dvh;', $sidebarStyles);
        $this->assertStringContainsString('.pos-sidebar-admin-nav { display: flex; flex-direction: column; gap: .2rem;', $sidebarStyles);
        $this->assertStringContainsString('min-height: 2.25rem; align-items: center; gap: .55rem;', $sidebarStyles);
        $this->assertStringContainsString('width: 2rem;', $sidebarStyles, 'Primary and footer menu icons share one fixed icon slot.');
        $this->assertStringContainsString('flex: 0 0 2rem;', $sidebarStyles, 'Icon slots cannot shrink and shift menu labels.');
        $this->assertStringContainsString('.pos-sidebar-nav > div > button > svg:first-child', $sidebarStyles, 'Grouped submenu toggles must match the rendered nav > div > button structure.');
        $this->assertStringContainsString('.pos-sidebar-nav > div > button > span', $sidebarStyles, 'Grouped menu labels must flex between the icon and arrow.');
        $this->assertStringNotContainsString('.pos-sidebar-nav > :is(a, div > button)', $sidebarStyles, 'A direct-child selector cannot match buttons nested inside group divs.');
        $this->assertStringContainsString('.pos-sidebar-dashboard-link { min-height: 2.5rem; align-items: center; gap: .55rem; padding: .25rem .5rem;', $sidebarStyles, 'Dashboard uses the same icon and label alignment as the main navigation.');
        $this->assertStringContainsString('body:has(.pos-sidebar) > :not(.pos-sidebar-shell):not(script):not(style):not(dialog) { grid-column: 2;', $sidebarStyles);
        $this->assertStringContainsString('pos-sidebar-branch-select-collapsed', $sidebarStyles);
        $this->assertStringContainsString('aria-label="Pilih cabang', $html);
        $this->assertStringContainsString('this.expanded = true; this.notify(); this.groups[name] = true;', $html, 'Collapsed navigation groups remain reachable when clicked.');
        $this->assertSame(2, substr_count($html, '@click="toggle()"'), 'Render desktop and mobile sidebar toggles.');
        $this->assertStringContainsString('aria-label="Buka navigasi"', $html);
        $this->assertStringContainsString('aria-label="Tutup navigasi"', $html);
        $this->assertStringContainsString('closeMobile()', $html);
        $this->assertStringContainsString('if (window.innerWidth < 768) expanded = false;', $html, 'A stored desktop expansion must not open the mobile drawer on page load.');
        $this->assertStringContainsString('@media (max-width: 767px)', $sidebarStyles);
        $this->assertStringContainsString('transform: translateX(calc(-100% - 1rem))', $sidebarStyles);
        $this->assertStringContainsString('position: fixed !important; inset: 0 auto auto 0', $sidebarStyles);
        $this->assertStringNotContainsString('html[data-sidebar="collapsed"] aside {', $sidebarStyles);
        $this->assertStringContainsString('setTimeout(() => { if (!this.$el.matches(\':hover\') && this.expanded)', $html);
        $this->assertStringContainsString('this.autoMode = !this.autoMode', $html);
        $this->assertStringContainsString('toggleAuto()', $html);
        $this->assertStringContainsString('html[data-sidebar="collapsed"] .pos-sidebar-header { display: flex; justify-content: center;', $html);
        $this->assertStringContainsString('html[data-sidebar="collapsed"] .pos-sidebar-dashboard-link,', $html);
        $this->assertStringContainsString('html[data-sidebar="collapsed"] .pos-sidebar-nav > a,', $html);
        $this->assertStringContainsString('html[data-sidebar="collapsed"] .pos-sidebar-footer form button', $html);
        $this->assertStringContainsString('images/jooal-logo.png', $html);
        $this->assertStringContainsString('pos-sidebar-logo-fallback', $html);
        $this->assertStringContainsString('pos-sidebar-logo-mark-fallback', $html);
        $this->assertStringContainsString("asset('images/jooal-logo.png')", $sidebarStyles);
        $this->assertStringContainsString("this.closest('a').dataset.logoFailed='true'", $sidebarStyles);
        $this->assertStringContainsString('pos-sidebar-brand-name', $sidebarStyles);
        $this->assertStringContainsString('alt="" aria-hidden="true" class="pos-sidebar-logo"', $sidebarStyles);
        $this->assertStringContainsString('x-show="expanded && !branchMenuOpen"', $html);
        $this->assertStringContainsString('Daftar cabang', $html);
        $this->assertStringContainsString('@if (optional($activeBranch)->id === $branch->id) aria-current="true"', $sidebarStyles);
    }

    public function test_settings_exposes_four_themes_and_sidebar_uses_palette_variables(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $html = $this->actingAs($owner)->get(route('settings.system'))->assertOk()->getContent();

        foreach (['light', 'dark-teal', 'slate-night', 'coffee-night'] as $theme) {
            $this->assertStringContainsString('value="'.$theme.'"', $html);
        }
        $this->assertStringNotContainsString('value="auto"', $html);

        $sidebar = file_get_contents(resource_path('views/components/pos-sidebar.blade.php'));
        $styles = file_get_contents(resource_path('css/app.css'));
        $appJs = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('background: var(--theme-sidebar)', $sidebar);
        $this->assertStringContainsString('color: var(--theme-sidebar-text)', $sidebar);
        $this->assertStringContainsString('localStorage.setItem(\'jooal-theme-choice\', choice)', $sidebar);
        $this->assertStringContainsString('root.dataset.theme = mode;', $sidebar);
        $this->assertStringContainsString('root.dataset.themePalette = palette;', $sidebar);
        $this->assertStringContainsString('localStorage.setItem(\'jooal-theme-palette\', palette)', $sidebar);
        $this->assertStringNotContainsString('dataset.theme =', $appJs, 'The app bundle must not overwrite the selected palette on page load.');
        $this->assertStringNotContainsString('DOMContentLoaded\', () => applyTheme', $appJs);
        $this->assertStringContainsString('applyTheme(storedChoice);', $sidebar);
        $this->assertStringContainsString('document.addEventListener(\'DOMContentLoaded\', function () { applyTheme(localStorage.getItem(\'jooal-theme-choice\') || storedChoice); });', $sidebar);
        $this->assertStringContainsString('document.querySelectorAll(\'[data-theme-select]\').forEach(function (select) { select.value = choice; });', $sidebar);
        $this->assertStringContainsString('if (select) applyTheme(select.value);', $sidebar);
        $this->assertStringNotContainsString('prefers-color-scheme', $sidebar);
        $this->assertStringContainsString("legacyMode === 'dark' ? 'dark-teal' : 'light'", $sidebar);
        $this->assertStringContainsString('--theme-body: #202124;', $styles);
        $this->assertStringContainsString('[class~="bg-emerald-50/70"]', $styles);
        $this->assertStringContainsString('--theme-sidebar: #176b50;', $styles);
        $this->assertStringContainsString('--theme-sidebar: #153d34;', $styles);
        $this->assertStringContainsString('--theme-sidebar: #26374a;', $styles);
        $this->assertStringContainsString('--theme-sidebar: #3b2b22;', $styles);
    }

    private function activeSidebarPaths(string $html, array $paths): array
    {
        preg_match_all('/<a href="([^"]+)"[^>]*class="([^"]*)"/s', $html, $matches, PREG_SET_ORDER);
        $active = [];

        foreach ($matches as $match) {
            $path = parse_url(html_entity_decode($match[1]), PHP_URL_PATH);
            if (in_array($path, $paths, true) && str_contains($match[2], 'bg-emerald-500')) {
                $active[] = $path;
            }
        }

        return $active;
    }
}

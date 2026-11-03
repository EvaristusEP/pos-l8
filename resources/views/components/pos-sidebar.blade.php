<style>
    body:has(.pos-sidebar) { --pos-sidebar-width: 72px; display: grid; grid-template-columns: var(--pos-sidebar-width) minmax(0, 1fr); align-items: stretch; min-height: 100vh; min-height: 100dvh; min-width: 0; transition: grid-template-columns .2s ease; }
    html[data-sidebar="expanded"] body:has(.pos-sidebar) { --pos-sidebar-width: 224px; grid-template-columns: var(--pos-sidebar-width) minmax(0, 1fr); }
    .pos-sidebar-shell { grid-column: 1; grid-row: 1; width: 100%; min-width: 0; min-height: 100vh; min-height: 100dvh; }
    body:has(.pos-sidebar) > :not(.pos-sidebar-shell):not(script):not(style):not(dialog) { grid-column: 2; grid-row: 1; width: auto; min-width: 0; margin: 0 !important; }
    .pos-sidebar { position: fixed !important; inset: 0 auto auto 0 !important; z-index: 40 !important; width: var(--pos-sidebar-width) !important; height: 100vh; height: 100dvh; overflow: hidden; background: var(--theme-sidebar) !important; border-color: var(--theme-sidebar-border) !important; color: var(--theme-sidebar-text) !important; }
    .pos-sidebar-inner { display: flex; height: 100%; min-height: 0; flex-direction: column; padding: .65rem !important; }
    .pos-sidebar-nav { flex: 1 1 0%; min-height: 0; overflow-x: hidden; overflow-y: auto; overscroll-behavior: contain; scrollbar-width: thin; scrollbar-color: var(--theme-sidebar-border) transparent; }
    .pos-sidebar-nav::-webkit-scrollbar { width: 5px; }
    .pos-sidebar-nav::-webkit-scrollbar-thumb { border-radius: 9999px; background: var(--theme-sidebar-border); }
    .pos-sidebar-footer { flex: 0 1 auto; min-height: 0; max-height: 40dvh; overflow-x: hidden; overflow-y: auto; overscroll-behavior: contain; scrollbar-width: thin; scrollbar-color: var(--theme-sidebar-border) transparent; }
    .pos-sidebar-footer::-webkit-scrollbar { width: 5px; }
    .pos-sidebar-footer::-webkit-scrollbar-thumb { border-radius: 9999px; background: var(--theme-sidebar-border); }
    .pos-sidebar-submenu { margin: .15rem 0 .3rem .75rem; padding-left: .55rem; border-left: 1px solid var(--theme-sidebar-border); }
    .pos-sidebar-submenu a { min-height: 1.95rem; padding-block: .3rem; line-height: 1.25; }
    .pos-sidebar-footer { border-top-color: var(--theme-sidebar-border) !important; }
    html[data-theme-palette] .pos-sidebar .pos-sidebar-footer select { background: var(--theme-sidebar-control) !important; color: var(--theme-sidebar-control-ink) !important; border: 1px solid var(--theme-sidebar-control-border) !important; }
    html[data-theme-palette] .pos-sidebar [aria-disabled="true"] { color: var(--theme-sidebar-muted) !important; opacity: 1; }
    .pos-sidebar-footer .pos-sidebar-branch-form { background: var(--theme-sidebar-hover) !important; }
    html[data-theme-palette] .pos-sidebar :is(a, button, span, label):not(.bg-emerald-500) { color: var(--theme-sidebar-muted) !important; }
    html[data-theme-palette] .pos-sidebar :is(a, button):not(.bg-emerald-500):hover { background: var(--theme-sidebar-hover) !important; color: var(--theme-sidebar-text) !important; }
    html[data-theme-palette] .pos-sidebar .bg-emerald-500 { background: var(--theme-sidebar-active) !important; color: var(--theme-sidebar-active-ink) !important; }
    html[data-theme-palette] .pos-sidebar .bg-emerald-500 :is(span, svg) { color: var(--theme-sidebar-active-ink) !important; }
    .pos-sidebar svg { color: var(--theme-sidebar-icon-ink); }
    html[data-theme-palette] .pos-sidebar nav > div > button.bg-white\/10,
    html[data-theme-palette] .pos-sidebar nav > div > button.bg-white\/10 span { background: var(--theme-sidebar-hover) !important; color: var(--theme-sidebar-text) !important; }
    html[data-theme-palette] .pos-sidebar .pos-sidebar-brand { color: var(--theme-sidebar-text) !important; }
    .pos-sidebar :focus-visible { outline: 2px solid var(--theme-sidebar-focus); outline-offset: 2px; }
    @media (max-width: 1023px) {
        html[data-sidebar="expanded"] body:has(.pos-sidebar) { --pos-sidebar-width: min(220px, 58vw); grid-template-columns: var(--pos-sidebar-width) minmax(0, 1fr); }
    }
    .pos-sidebar-header { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: .5rem; }
    .pos-sidebar-logo { display: block; width: 2rem; height: 2rem; max-width: 2rem; flex: 0 0 2rem; object-fit: contain; object-position: center; }
    .pos-sidebar-logo-fallback { display: none; }
    .pos-sidebar-logo-mark { display: none; }
    .pos-sidebar-logo-mark-fallback { display: none; place-items: center; width: 2rem; height: 2rem; flex: 0 0 2rem; border-radius: .45rem; background: var(--theme-sidebar-icon-bg); color: var(--theme-sidebar-text); }
    .pos-sidebar-brand { display: flex; align-items: center; gap: .45rem; min-width: 0; }
    .pos-sidebar-brand { min-width: 0; overflow: hidden; text-overflow: ellipsis; }
    .pos-sidebar-brand-name { display: inline-block; min-width: 0; white-space: nowrap; }
    .pos-sidebar-brand[data-logo-failed="true"] .pos-sidebar-logo-fallback { display: inline-block; }
    .pos-sidebar-brand[data-logo-failed="true"] .pos-sidebar-brand-name { display: none; }
    .pos-sidebar-dashboard-link { min-height: 3rem; }
    .pos-sidebar-dashboard-link > svg:first-child,
    .pos-sidebar-nav > a > svg:first-child,
    .pos-sidebar-nav > div > button > svg:first-child,
    .pos-sidebar-footer .pos-sidebar-admin-nav > :is(a, span) > svg:first-child,
    .pos-sidebar-footer .pos-sidebar-logout button > svg:first-child {
        display: block;
        box-sizing: border-box;
        width: 2rem;
        height: 2rem;
        flex: 0 0 2rem;
        padding: .5rem;
        border-radius: .55rem;
        background: var(--theme-sidebar-icon-bg);
        color: var(--theme-sidebar-icon-ink);
    }
    .pos-sidebar-footer > :is(a, span, form) { margin: 0; }
    .pos-sidebar-header { height: 3rem !important; }
    .pos-sidebar-dashboard-link { min-height: 2.5rem; align-items: center; gap: .55rem; padding: .25rem .5rem; border-radius: var(--radius-control); line-height: 1.25; }
    .pos-sidebar-nav > a,
    .pos-sidebar-nav > div > button { min-height: 2.5rem; height: auto; gap: .55rem; padding: .25rem .5rem; border-radius: var(--radius-control); line-height: 1.25; }
    .pos-sidebar-dashboard-link > span,
    .pos-sidebar-nav > a > span,
    .pos-sidebar-nav > div > button > span,
    .pos-sidebar-admin-nav > :is(a, span) > span,
    .pos-sidebar-logout button > span { min-width: 0; flex: 1 1 auto; }
    .pos-sidebar-admin-nav { display: flex; flex-direction: column; gap: .2rem; margin-top: .35rem; padding-top: .45rem; border-top: 1px solid var(--theme-sidebar-border); }
    .pos-sidebar-footer .pos-sidebar-admin-nav > :is(a, span) { display: flex; min-height: 2.25rem; align-items: center; gap: .55rem; padding: .15rem .5rem; border-radius: var(--radius-control); }
    .pos-sidebar-footer > .pos-sidebar-logout { margin-top: .35rem; padding-top: .45rem; border-top: 1px solid var(--theme-sidebar-border); }
    .pos-sidebar-footer .pos-sidebar-logout button { display: flex; min-height: 2.3rem; align-items: center; gap: .55rem; padding-inline: .5rem; border-radius: var(--radius-control); }
    .pos-sidebar-footer > form.pos-sidebar-branch-form { margin-bottom: .35rem; padding: .45rem !important; }
    .pos-sidebar-branch-control { position: relative; min-width: 0; }
    .pos-sidebar-branch-select { display: block; width: 100%; min-height: 2.35rem; border: 1px solid var(--theme-sidebar-control-border); border-radius: var(--radius-control); background: var(--theme-sidebar-control); color: var(--theme-sidebar-control-ink); padding: .4rem .5rem; font-size: .75rem; font-weight: 700; }
    .pos-sidebar-branch-select-expanded { position: static; opacity: 1; }
    .pos-sidebar-branch-select-collapsed { position: absolute; inset: 0; z-index: 1; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    .pos-sidebar-branch-select-collapsed:focus-visible { opacity: 1; background: var(--theme-sidebar-control) !important; color: var(--theme-sidebar-control-ink) !important; }
    @media (min-width: 1024px) {
        html[data-sidebar="collapsed"] .pos-sidebar-dashboard-link,
        html[data-sidebar="collapsed"] .pos-sidebar-nav > a,
        html[data-sidebar="collapsed"] .pos-sidebar-nav > div > button,
        html[data-sidebar="collapsed"] .pos-sidebar-footer .pos-sidebar-admin-nav > :is(a, span),
        html[data-sidebar="collapsed"] .pos-sidebar-footer form.pos-sidebar-logout button {
            justify-content: center;
            padding-inline: 0 !important;
        }
        html[data-sidebar="collapsed"] .pos-sidebar-header { display: flex; justify-content: center; padding-inline: 0 !important; }
        html[data-sidebar="collapsed"] .pos-sidebar-brand { justify-content: center; }
        html[data-sidebar="collapsed"] .pos-sidebar-brand-name,
        html[data-sidebar="collapsed"] .pos-sidebar-logo-fallback { display: none !important; }
        html[data-sidebar="collapsed"] .pos-sidebar-brand[data-logo-failed="true"] .pos-sidebar-logo-mark-fallback { display: grid; }
        html[data-sidebar="collapsed"] .pos-sidebar-dashboard,
        html[data-sidebar="collapsed"] .pos-sidebar-nav { margin-top: .5rem !important; }
        html[data-sidebar="collapsed"] .pos-sidebar-nav > a > svg:first-child,
        html[data-sidebar="collapsed"] .pos-sidebar-nav > div > button > svg:first-child,
        html[data-sidebar="collapsed"] .pos-sidebar-dashboard-link > svg:first-child,
        html[data-sidebar="collapsed"] .pos-sidebar-footer .pos-sidebar-admin-nav > :is(a, span) > svg:first-child,
        html[data-sidebar="collapsed"] .pos-sidebar-footer .pos-sidebar-logout button > svg:first-child { margin: 0; }
        html[data-sidebar="collapsed"] .pos-sidebar-footer > form.pos-sidebar-branch-form { padding: .25rem !important; }
        html[data-sidebar="collapsed"] .pos-sidebar-branch-control { width: 2.35rem; height: 2.35rem; margin-inline: auto; }
        html[data-sidebar="collapsed"] .pos-sidebar-branch-control > svg { display: block; width: 1.25rem; height: 1.25rem; margin: .55rem; color: var(--theme-sidebar-icon-ink); }
        html[data-sidebar="collapsed"] .pos-sidebar-dashboard-link,
        html[data-sidebar="collapsed"] .pos-sidebar-nav > a,
        html[data-sidebar="collapsed"] .pos-sidebar-nav > div > button,
        html[data-sidebar="collapsed"] .pos-sidebar-footer .pos-sidebar-admin-nav > a,
        html[data-sidebar="collapsed"] .pos-sidebar-footer .pos-sidebar-admin-nav > span,
        html[data-sidebar="collapsed"] .pos-sidebar-footer form button {
            justify-content: center;
            padding-inline: 0 !important;
        }
    }
    .pos-sidebar-mobile-backdrop, .pos-sidebar-mobile-trigger { display: none; visibility: hidden; pointer-events: none; }
    @media (max-width: 767px) {
        body:has(.pos-sidebar) { display: block; position: relative; }
        body:has(.pos-sidebar) > :not(.pos-sidebar-shell):not(script):not(style):not(dialog) { display: block; width: 100%; min-height: 100dvh; margin: 0 !important; padding-top: 4.25rem !important; }
        .pos-sidebar-shell { position: fixed; inset: 0; z-index: 100; width: 100%; min-height: 0; pointer-events: none; }
        .pos-sidebar { z-index: 1 !important; width: min(280px, 84vw) !important; transform: translateX(calc(-100% - 1rem)); transition: transform .22s ease; pointer-events: auto; box-shadow: 0 1.5rem 3rem rgba(7, 30, 22, .28) !important; }
        html[data-sidebar="expanded"] .pos-sidebar { transform: translateX(0); }
        .pos-sidebar-mobile-backdrop { position: fixed; inset: 0; z-index: 0; display: block; width: 100%; height: 100%; visibility: visible; pointer-events: auto; border: 0; background: rgba(10, 24, 19, .48); }
        .pos-sidebar-mobile-trigger { position: fixed; top: .75rem; left: .75rem; z-index: 101; display: block; width: 2.75rem; height: 2.75rem; padding: .65rem; visibility: visible; pointer-events: auto; border: 1px solid var(--theme-sidebar-border); border-radius: var(--radius-control); background: var(--theme-sidebar); color: var(--theme-sidebar-text); box-shadow: var(--shadow-surface); }
        .pos-sidebar-mobile-trigger svg { display: block; width: 1.35rem; height: 1.35rem; margin-inline: auto; color: var(--theme-sidebar-icon-ink); }
        html[data-sidebar="expanded"] body:has(.pos-sidebar) { overflow: hidden; }
        .pos-sidebar-inner { height: 100dvh !important; }
        .pos-sidebar-footer { max-height: 40dvh; }
    }
    @media (prefers-reduced-motion: reduce) { body:has(.pos-sidebar), .pos-sidebar { transition-duration: .01ms !important; } }
</style>
@php
    $branchContext = app(\App\Services\BranchContext::class);
    $activeBranch = $branchContext->activeFor(auth()->user());
    $availableBranches = $branchContext->availableFor(auth()->user());
@endphp
@php
    $masterDataActive = request()->routeIs('master-menu.*', 'addons.*', 'tags.*', 'tables.*', 'customers.*', 'suppliers.*', 'recipes.*', 'inventory.materials.index');
    $reportsActive = request()->routeIs('reports.*');
    $inventoryActive = request()->routeIs('inventory.stock-in.index', 'inventory.stocks.index', 'inventory.movements.index', 'dashboard.inventory', 'inventory.index');
@endphp
<div class="pos-sidebar-shell" x-data="{ expanded: localStorage.getItem('jooal-sidebar-expanded') === 'true', autoMode: localStorage.getItem('jooal-sidebar-manual') !== 'true', closeTimer: null, groups: { master: {{ $masterDataActive ? 'true' : 'false' }}, reports: {{ $reportsActive ? 'true' : 'false' }}, inventory: {{ $inventoryActive ? 'true' : 'false' }} }, notify() { localStorage.setItem('jooal-sidebar-expanded', this.expanded); document.documentElement.dataset.sidebar = this.expanded ? 'expanded' : 'collapsed'; window.dispatchEvent(new CustomEvent('pos-sidebar', { detail: { expanded: this.expanded } })); }, toggle() { clearTimeout(this.closeTimer); this.expanded = !this.expanded; this.notify(); }, closeMobile() { if (window.innerWidth < 768 && this.expanded) { this.expanded = false; this.notify(); } }, toggleAuto() { clearTimeout(this.closeTimer); this.autoMode = !this.autoMode; localStorage.setItem('jooal-sidebar-manual', String(!this.autoMode)); if (this.autoMode) this.expanded = false; this.notify(); }, toggleGroup(name) { if (!this.expanded && window.innerWidth >= 1024) { this.expanded = true; this.notify(); this.groups[name] = true; return; } this.groups[name] = !this.groups[name]; }, scheduleClose() { if (!this.autoMode || window.innerWidth < 1024) return; clearTimeout(this.closeTimer); this.closeTimer = setTimeout(() => { if (!this.$el.matches(':hover') && this.expanded) { this.expanded = false; this.notify(); } }, 1000); }, cancelClose() { clearTimeout(this.closeTimer); if (this.autoMode && window.innerWidth >= 1024 && !this.expanded) { this.expanded = true; this.notify(); } }, handleFocusOut(event) { if (!this.$el.contains(event.relatedTarget)) this.scheduleClose(); } }" x-init="if (window.innerWidth < 768) expanded = false; document.documentElement.dataset.sidebar = expanded ? 'expanded' : 'collapsed'; window.addEventListener('resize', () => { if (window.innerWidth < 768 && expanded) closeMobile(); })">
    <button type="button" x-show="expanded" x-cloak class="pos-sidebar-mobile-backdrop" aria-label="Tutup navigasi" @click="closeMobile()"></button>
    <aside @mouseleave="scheduleClose()" @mouseenter="cancelClose()" @focusin="cancelClose()" @focusout="handleFocusOut($event)" @click="if (window.innerWidth < 768 && $event.target.closest('a')) closeMobile()" class="pos-sidebar w-full overflow-hidden border-r border-emerald-900/10 bg-[#123c35] text-white shadow-2xl transition-[width] duration-300">
        <div class="pos-sidebar-inner flex h-full min-h-0 flex-col p-3">
            <div class="pos-sidebar-header h-14 shrink-0 overflow-hidden px-1">
                <a href="{{ route('dashboard') }}" class="pos-sidebar-brand" aria-label="JOOAL"><img src="{{ asset('images/jooal-logo.png') }}" alt="" aria-hidden="true" class="pos-sidebar-logo" onerror="this.hidden=true;this.closest('a').dataset.logoFailed='true'"><span class="pos-sidebar-logo-fallback text-base font-extrabold">JOOAL</span><span class="pos-sidebar-brand-name text-sm font-extrabold tracking-wide">JOOAL</span><span class="pos-sidebar-logo-mark-fallback text-sm font-extrabold">J</span></a>
                <button type="button" @click="toggleAuto()" x-show="expanded" x-cloak :aria-pressed="autoMode.toString()" :title="autoMode ? 'Matikan mode Auto' : 'Aktifkan mode Auto'" x-text="autoMode ? 'Auto' : 'Manual'" class="grid h-8 shrink-0 place-items-center rounded-md px-2 text-xs font-bold text-emerald-100 transition hover:bg-white/10"></button>
                <button @click="toggle()" type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-md text-emerald-100 transition hover:bg-white/10" :aria-label="expanded ? 'Perkecil sidebar' : 'Perbesar sidebar'"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            </div>
            <div class="pos-sidebar-dashboard mt-2 shrink-0">
                <a href="{{ route('dashboard') }}" title="{{ __('nav.dashboard') }}" class="pos-sidebar-dashboard-link flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition {{ request()->routeIs('dashboard', 'dashboard.owner', 'dashboard.manager', 'dashboard.cashier') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-50 hover:bg-white/10' }}"><svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><span x-show="expanded" x-cloak class="min-w-0 flex-1">{{ __('nav.dashboard') }}</span></a>
            </div>
            <nav class="pos-sidebar-nav mt-2 space-y-1" aria-label="Navigasi utama">
                <a href="{{ route('attendance.index') }}" title="{{ __('nav.attendance') }}" class="flex h-12 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition {{ request()->routeIs('attendance.*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-50 hover:bg-white/10' }}"><x-sidebar-icon name="clock" /><span x-show="expanded" x-cloak>{{ __('nav.attendance') }}</span></a>
            @if (auth()->user()->isOwner() || auth()->user()->isManager() || auth()->user()->isInventory())
                    <div>
                        <button type="button" @click="toggleGroup('master')" :aria-expanded="(expanded) && groups.master" aria-controls="sidebar-master-data" title="{{ __('nav.master_data') }}" class="flex h-12 w-full items-center gap-3 rounded-xl px-3 text-left text-sm font-semibold transition {{ $masterDataActive ? 'bg-white/10 text-white' : 'text-emerald-50 hover:bg-white/10' }}"><x-sidebar-icon name="layers" /><span x-show="expanded" x-cloak class="min-w-0 flex-1">{{ __('nav.master_data') }}</span><svg x-show="expanded" x-cloak :class="groups.master ? 'rotate-90' : ''" class="h-4 w-4 shrink-0 transition-transform" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7.2 4.2a.75.75 0 0 1 1.1 0l4.5 4.8a.75.75 0 0 1 0 1l-4.5 4.8a.75.75 0 0 1-1.1-1L11.2 9.5 7.2 5.3a.75.75 0 0 1 0-1.1Z"/></svg></button>
                        <div id="sidebar-master-data" x-show="(expanded) && groups.master" x-transition x-cloak class="pos-sidebar-submenu space-y-1">
                            @if (auth()->user()->isOwner())
                                <a href="{{ route('master-menu.index') }}" title="{{ __('nav.products') }} · {{ __('nav.master_menu') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('master-menu.*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.products') }} · {{ __('nav.master_menu') }}</a>
                                <a href="{{ route('inventory.materials.index') }}#materials-master" title="{{ __('nav.materials') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('inventory.materials.index') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.materials') }}</a>
                                <a href="{{ route('tags.index') }}" title="{{ __('nav.tags') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('tags.*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.tags') }}</a>
                                <a href="{{ route('addons.index') }}" title="{{ __('nav.addons') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('addons.*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.addons') }}</a>
                                <a href="{{ route('recipes.index') }}" title="{{ __('nav.recipes') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('recipes.*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.recipes') }}</a>
                            @endif
                            @if (auth()->user()->isOwner() || auth()->user()->isManager())
                                <a href="{{ route('tables.index') }}" title="{{ __('nav.tables') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('tables.*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.tables') }}</a>
                                <a href="{{ route('customers.index') }}" title="{{ __('nav.customers') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('customers.*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.customers') }}</a>
                            @endif
                            @if (auth()->user()->isOwner() || auth()->user()->isManager() || auth()->user()->isInventory())
                                <a href="{{ route('suppliers.index') }}" title="{{ __('nav.suppliers') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('suppliers.*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.suppliers') }}</a>
                            @endif
                        </div>
                    </div>
                @endif

                @if (auth()->user()->isOwner())
                    <a href="{{ route('branches.index') }}" title="{{ __('nav.branch_management') }}" class="flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-semibold transition {{ request()->routeIs('branches.*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-50 hover:bg-white/10' }}"><x-sidebar-icon name="building" /><span x-show="expanded" x-cloak>{{ __('nav.branch_management') }}</span></a>
                @endif

                @if (auth()->user()->isOwner() || auth()->user()->isManager() || auth()->user()->isCashier())
                    @php $posNavigationActive = request()->routeIs('dashboard.pos', 'checkout*', 'orders.*', 'history*'); @endphp
                    <div x-data="{ open: {{ $posNavigationActive ? 'true' : 'false' }} }">
                        <button type="button" @click="if (!expanded && window.innerWidth >= 1024) { expanded = true; notify(); open = true; } else { open = !open; }" :aria-expanded="(expanded) && open" aria-controls="sidebar-pos-navigation" title="POS / Kasir" class="flex h-12 w-full items-center gap-3 rounded-xl px-3 text-left text-sm font-semibold transition {{ $posNavigationActive ? 'bg-white/10 text-white' : 'text-emerald-50 hover:bg-white/10' }}"><svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 4h2l2 12h10l2-8H7"/><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/></svg><span x-show="expanded" x-cloak class="min-w-0 flex-1">POS / Kasir</span><svg x-show="expanded" x-cloak :class="open ? 'rotate-90' : ''" class="h-4 w-4 shrink-0 transition-transform" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7.2 4.2a.75.75 0 0 1 1.1 0l4.5 4.8a.75.75 0 0 1 0 1l-4.5 4.8a.75.75 0 0 1-1.1 1L11.2 9.5 7.2 5.3a.75.75 0 0 1 0-1.1Z"/></svg></button>
                        <div id="sidebar-pos-navigation" x-show="(expanded) && open" x-transition x-cloak class="pos-sidebar-submenu space-y-1">
                            <a href="{{ route('dashboard.pos') }}" title="Kasir{{ optional($activeBranch)->name ? ' - '.optional($activeBranch)->name : '' }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('dashboard.pos', 'checkout*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">Kasir{{ optional($activeBranch)->name ? ' - '.optional($activeBranch)->name : '' }}</a>
                            <a href="{{ route('orders.index') }}" title="{{ __('nav.orders') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('orders.*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">Orders</a>
                            <a href="{{ route('history') }}" title="Riwayat Transaksi" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('history*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">Riwayat Transaksi</a>
                        </div>
                    </div>
                @endif

                @if (auth()->user()->isOwner() || auth()->user()->isManager())
                    <div>
                        <button type="button" @click="toggleGroup('reports')" :aria-expanded="(expanded) && groups.reports" aria-controls="sidebar-reports" title="{{ __('nav.reports') }}" class="flex h-12 w-full items-center gap-3 rounded-xl px-3 text-left text-sm font-semibold transition {{ $reportsActive ? 'bg-white/10 text-white' : 'text-emerald-50 hover:bg-white/10' }}"><svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 19V5M4 19h16M8 16v-5M12 16V8M16 16v-9"/></svg><span x-show="expanded" x-cloak class="min-w-0 flex-1">{{ __('nav.reports') }}</span><svg x-show="expanded" x-cloak :class="groups.reports ? 'rotate-90' : ''" class="h-4 w-4 shrink-0 transition-transform" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7.2 4.2a.75.75 0 0 1 1.1 0l4.5 4.8a.75.75 0 0 1 0 1l-4.5 4.8a.75.75 0 0 1-1.1-1L11.2 9.5 7.2 5.3a.75.75 0 0 1 0-1.1Z"/></svg></button>
                        <div id="sidebar-reports" x-show="(expanded) && groups.reports" x-transition x-cloak class="pos-sidebar-submenu space-y-1">
                            <span class="flex items-center rounded-lg px-3 text-xs font-semibold text-emerald-100/60" title="{{ __('nav.gross_profit_unavailable') }}" aria-disabled="true">{{ __('nav.gross_profit_unavailable') }}</span>
                            <a href="{{ route('reports.sales') }}" title="{{ __('nav.sales_report') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('reports.sales') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.sales_report') }}</a>
                            <a href="{{ route('reports.payment-methods') }}" title="{{ __('nav.payment_method') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('reports.payment-methods') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.payment_method') }}</a>
                            <a href="{{ route('reports.refunds') }}" title="{{ __('nav.refund_report') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('reports.refunds') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.refund_report') }}</a>
                            <a href="{{ route('reports.attendance') }}" title="{{ __('nav.attendance_report') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('reports.attendance') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.attendance_report') }}</a>
                        </div>
                    </div>
                @endif

                @if (auth()->user()->isOwner() || auth()->user()->isManager() || auth()->user()->isInventory())
                    <div>
                        <button type="button" @click="toggleGroup('inventory')" :aria-expanded="(expanded) && groups.inventory" aria-controls="sidebar-inventory" title="{{ __('nav.inventory') }}" class="flex h-12 w-full items-center gap-3 rounded-xl px-3 text-left text-sm font-semibold transition {{ $inventoryActive ? 'bg-white/10 text-white' : 'text-emerald-50 hover:bg-white/10' }}"><svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 7v13h16V7M8 7V4h8v3M8 11h8M8 15h5"/></svg><span x-show="expanded" x-cloak class="min-w-0 flex-1">{{ __('nav.inventory') }}</span><svg x-show="expanded" x-cloak :class="groups.inventory ? 'rotate-90' : ''" class="h-4 w-4 shrink-0 transition-transform" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7.2 4.2a.75.75 0 0 1 1.1 0l4.5 4.8a.75.75 0 0 1 0 1l-4.5 4.8a.75.75 0 0 1-1.1-1L11.2 9.5 7.2 5.3a.75.75 0 0 1 0-1.1Z"/></svg></button>
                        <div id="sidebar-inventory" x-show="(expanded) && groups.inventory" x-transition x-cloak class="pos-sidebar-submenu space-y-1">
                            <a href="{{ route('inventory.stock-in.index') }}#stock-in" title="{{ __('nav.stock_in') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('inventory.stock-in.index') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.stock_in') }}</a>
                            <a href="{{ route('inventory.stocks.index') }}#materials" title="{{ __('nav.stock_per_branch') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('inventory.stocks.index', 'dashboard.inventory', 'inventory.index') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.stock_per_branch') }}</a>
                            <a href="{{ route('inventory.movements.index') }}#stock-movements" title="{{ __('nav.stock_movement_history') }}" class="flex items-center rounded-lg px-3 text-xs font-semibold transition {{ request()->routeIs('inventory.movements.index') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}">{{ __('nav.stock_movement_history') }}</a>
                        </div>
                    </div>
                @endif

                @if (auth()->user()->isOwner())
                    <a href="{{ route('coupons.index') }}" title="{{ __('nav.coupon') }}" class="flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-semibold transition {{ request()->routeIs('coupons.*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-50 hover:bg-white/10' }}"><x-sidebar-icon name="ticket" /><span x-show="expanded" x-cloak>{{ __('nav.coupon') }}</span></a>
                @endif

                @if (auth()->user()->isOwner() || auth()->user()->isManager() || auth()->user()->isCashier())
                    <a href="{{ route('orders.index') }}" title="{{ __('nav.orders') }}" class="flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-semibold transition {{ request()->routeIs('orders.*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-50 hover:bg-white/10' }}"><x-sidebar-icon name="receipt" /><span x-show="expanded" x-cloak>{{ __('nav.orders') }}</span></a>
                    <a href="{{ route('history') }}" title="{{ __('nav.history') }}" class="flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-semibold transition {{ request()->routeIs('history*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-50 hover:bg-white/10' }}"><svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5M12 7v5l3 2"/></svg><span x-show="expanded" x-cloak>{{ __('nav.history') }}</span></a>
                @endif

            </nav>

            @php
                $branchContext = app(\App\Services\BranchContext::class);
                $activeBranch = $branchContext->activeFor(auth()->user());
                $availableBranches = $branchContext->availableFor(auth()->user());
            @endphp

            <div class="pos-sidebar-footer mt-3 min-h-0 shrink-0 border-t border-white/10 pt-3">
                @php
                    $branchReturnTo = 'dashboard';
                    if (request()->routeIs('tables.*')) $branchReturnTo = 'tables';
                    elseif (request()->routeIs('customers.*')) $branchReturnTo = 'customers';
                    elseif (request()->routeIs('inventory.stock-in.index')) $branchReturnTo = 'inventory-stock-in';
                    elseif (request()->routeIs('inventory.stocks.index', 'dashboard.inventory', 'inventory.index')) $branchReturnTo = 'inventory-stocks';
                    elseif (request()->routeIs('inventory.movements.index')) $branchReturnTo = 'inventory-movements';
                    elseif (request()->routeIs('inventory.materials.index')) $branchReturnTo = 'inventory-materials';
                    elseif (request()->routeIs('inventory.*')) $branchReturnTo = 'inventory';
                    elseif (request()->routeIs('master-menu.*')) $branchReturnTo = 'master-menu';
                    elseif (request()->routeIs('orders.*')) $branchReturnTo = 'orders';
                    elseif (request()->routeIs('reports.sales')) $branchReturnTo = 'reports-sales';
                    elseif (request()->routeIs('reports.payment-methods')) $branchReturnTo = 'reports-payment';
                    elseif (request()->routeIs('reports.refunds')) $branchReturnTo = 'reports-refunds';
                    elseif (request()->routeIs('reports.attendance')) $branchReturnTo = 'reports-attendance';
                    elseif (request()->routeIs('attendance.*')) $branchReturnTo = 'attendance';
                    elseif (request()->routeIs('dashboard.pos', 'checkout*')) $branchReturnTo = 'pos';
                    elseif (request()->routeIs('history*')) $branchReturnTo = 'history';
                @endphp
                <div x-data="{ branchMenuOpen: false }" class="pos-sidebar-branch-form rounded-lg bg-white/10 p-2">
                    <button type="button" @click="if (!expanded && window.innerWidth >= 1024) { expanded = true; notify(); branchMenuOpen = true; } else { branchMenuOpen = !branchMenuOpen; }" :aria-expanded="(expanded) && branchMenuOpen" aria-controls="pos-branch-options" aria-label="Pilih cabang{{ optional($activeBranch)->display_name ? ', aktif: '.$activeBranch->display_name : '' }}" title="Pilih cabang aktif{{ optional($activeBranch)->display_name ? ': '.optional($activeBranch)->display_name : '' }}" class="flex min-h-10 w-full min-w-0 items-center gap-2 rounded-lg px-2 text-left text-xs font-bold text-emerald-50 transition hover:bg-white/10">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 19.5z"/><path d="M9 21v-6h6v6"/></svg>
                        <span x-show="expanded && !branchMenuOpen" x-cloak class="min-w-0 flex-1 truncate"><span class="block text-[.65rem] font-medium text-emerald-200">Cabang aktif</span><span class="block truncate">{{ optional($activeBranch)->display_name ?? 'Pilih cabang' }}</span></span>
                        <svg x-show="expanded" x-cloak :class="branchMenuOpen ? 'rotate-180' : ''" class="h-4 w-4 shrink-0 transition-transform" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                    </button>
                    <div id="pos-branch-options" x-show="(expanded) && branchMenuOpen" x-transition x-cloak class="pos-sidebar-submenu mt-2 space-y-1" aria-label="Daftar cabang">
                        @forelse ($availableBranches as $branch)
                            <form method="POST" action="{{ route('branches.select.update') }}">@csrf<input type="hidden" name="branch_id" value="{{ $branch->id }}"><input type="hidden" name="return_to" value="{{ $branchReturnTo }}"><button type="submit" @if (optional($activeBranch)->id === $branch->id) aria-current="true" @endif class="flex min-h-9 w-full items-center gap-2 rounded-lg px-3 text-left text-xs font-semibold transition {{ optional($activeBranch)->id === $branch->id ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-100 hover:bg-white/10' }}"><span class="min-w-0 flex-1 truncate">{{ $branch->display_name }}</span>@if (optional($activeBranch)->id === $branch->id)<svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-label="Cabang aktif"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.1 7.16a1 1 0 0 1-1.42.002L3.29 8.965A1 1 0 1 1 4.71 7.55l4.19 4.2 6.39-6.45a1 1 0 0 1 1.414-.01Z" clip-rule="evenodd"/></svg>@endif</button></form>
                        @empty
                            <span class="block px-3 py-2 text-xs text-emerald-100/70">Belum ada cabang yang dapat digunakan.</span>
                        @endforelse
                    </div>
                </div>
                @if (auth()->user()->isOwner())
                    <div class="pos-sidebar-admin-nav">
                        <a href="{{ route('users.index') }}" title="Users" class="text-sm font-semibold transition hover:bg-white/10 {{ request()->routeIs('users.*') ? 'bg-emerald-500 text-[#123c35]' : '' }}"><svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1M16 5.5a3 3 0 0 1 0 5.8M18 14a5 5 0 0 1 3 4.5V20"/></svg><span x-show="expanded" x-cloak>Users</span></a>
                        <span class="text-sm font-semibold" title="{{ __('nav.roles_via_users') }}" aria-disabled="true"><x-sidebar-icon name="shield" class="h-4 w-4 shrink-0" /><span x-show="expanded" x-cloak>{{ __('nav.roles_via_users') }}</span></span>
                        <a href="{{ route('settings.system') }}" title="{{ __('nav.settings') }}" class="text-sm font-semibold transition hover:bg-white/10 {{ request()->routeIs('settings.system') ? 'bg-emerald-500 text-[#123c35]' : '' }}"><x-sidebar-icon name="settings" class="h-4 w-4 shrink-0" /><span x-show="expanded" x-cloak>{{ __('nav.settings') }}</span></a>
                    </div>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="pos-sidebar-logout">@csrf<button type="submit" class="w-full text-left text-sm font-semibold transition hover:bg-white/10"><svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg><span x-show="expanded" x-cloak>Keluar</span></button></form>
            </div>
        </div>
    </aside>
    <button type="button" x-show="!expanded" x-cloak class="pos-sidebar-mobile-trigger" aria-label="Buka navigasi" title="Buka navigasi" @click="toggle()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
</div>
<script>
    (function () {
        var root = document.documentElement;
        var validThemes = ['light', 'dark-teal', 'slate-night', 'coffee-night'];
        var storedChoice = localStorage.getItem('jooal-theme-choice');
        if (!validThemes.includes(storedChoice)) {
            var legacyPalette = localStorage.getItem('jooal-theme-palette');
            var legacyMode = localStorage.getItem('jooal-theme');
            storedChoice = validThemes.includes(legacyPalette)
                ? legacyPalette
                : (legacyMode === 'dark' ? 'dark-teal' : 'light');
        }
        function applyTheme(choice) {
            if (!validThemes.includes(choice)) choice = 'light';
            var mode = choice === 'light' ? 'light' : 'dark';
            var palette = choice;
            root.dataset.theme = mode;
            root.dataset.themePalette = palette;
            localStorage.setItem('jooal-theme-choice', choice);
            localStorage.setItem('jooal-theme', mode);
            localStorage.setItem('jooal-theme-palette', palette);
            document.querySelectorAll('[data-theme-select]').forEach(function (select) { select.value = choice; });
        }
        window.jooalApplyTheme = applyTheme;
        applyTheme(storedChoice);
        document.addEventListener('DOMContentLoaded', function () { applyTheme(localStorage.getItem('jooal-theme-choice') || storedChoice); });
        root.dataset.sidebar = localStorage.getItem('jooal-sidebar-expanded') === 'true' ? 'expanded' : 'collapsed';
        document.addEventListener('change', function (event) {
            var select = event.target.closest('[data-theme-select]');
            if (select) applyTheme(select.value);
        });
        document.addEventListener('click', function (event) {
            var opener = event.target.closest('[data-dialog-open]');
            if (opener) {
                var dialog = document.getElementById(opener.dataset.dialogOpen);
                if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
            }

            var closer = event.target.closest('[data-dialog-close]');
            if (closer) closer.closest('dialog')?.close();
            if (event.target instanceof HTMLDialogElement && event.target.classList.contains('crud-dialog')) event.target.close();
        });
        document.addEventListener('submit', function (event) {
            var button = event.target.querySelector('[data-submit-button]');
            if (!button || button.disabled) return;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.dataset.originalText = button.textContent;
            button.textContent = 'Menyimpan…';
        });

        function applyListFilters(listId) {
            var list = document.getElementById(listId);
            if (!list) return;
            var search = document.querySelector('[data-filter-search="' + listId + '"]');
            var query = search ? search.value.trim().toLocaleLowerCase() : '';
            var filters = Array.from(document.querySelectorAll('[data-filter-select="' + listId + '"]'));
            var items = Array.from(list.querySelectorAll('[data-filter-item]'));
            var visible = 0;
            items.forEach(function (item) {
                var matches = !query || (item.dataset.filterText || '').toLocaleLowerCase().includes(query);
                filters.forEach(function (filter) {
                    var key = filter.dataset.filterKey;
                    if (filter.value && item.dataset[key] !== filter.value) matches = false;
                });
                item.classList.toggle('hidden', !matches);
                if (matches) visible++;
            });
            var empty = list.querySelector('[data-filter-empty]');
            if (empty) empty.classList.toggle('hidden', items.length === 0 || visible > 0);
        }
        document.addEventListener('input', function (event) { var input = event.target.closest('[data-filter-search]'); if (input) applyListFilters(input.dataset.filterSearch); });
        document.addEventListener('change', function (event) { var filter = event.target.closest('[data-filter-select]'); if (filter) applyListFilters(filter.dataset.filterSelect); });

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('dialog[data-open-on-load]').forEach(function (dialog) {
                if (typeof dialog.showModal === 'function') dialog.showModal();
            });
        });
    }());
</script>

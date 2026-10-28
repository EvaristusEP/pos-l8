<aside
    x-data="{ expanded: localStorage.getItem('pos-sidebar-expanded') === 'true', closeTimer: null, notify() { localStorage.setItem('pos-sidebar-expanded', this.expanded); window.dispatchEvent(new CustomEvent('pos-sidebar', { detail: { expanded: this.expanded } })); }, toggle() { this.expanded = !this.expanded; this.notify(); }, scheduleClose() { clearTimeout(this.closeTimer); this.closeTimer = setTimeout(() => { if (this.expanded) { this.expanded = false; this.notify(); } }, 1800); }, cancelClose() { clearTimeout(this.closeTimer); } }"
    @mouseleave="scheduleClose()"
    @mouseenter="cancelClose()"
    class="fixed inset-y-0 left-0 z-40 hidden w-20 overflow-hidden border-r border-emerald-900/10 bg-[#123c35] text-white shadow-2xl transition-[width] duration-300 lg:block"
    :class="expanded ? 'w-[300px]' : 'w-20'"
>
    <div class="flex h-full flex-col p-3">
        <div class="flex h-14 items-center gap-3 overflow-hidden px-1">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-400 text-[#123c35]">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 3h10l1 4H6l1-4Z"/><path d="M5 7h14l-1 13H6L5 7Z"/><path d="M9 13h6"/></svg>
            </div>
            <span x-show="expanded" x-cloak class="whitespace-nowrap text-base font-extrabold">Matcha House</span>
        </div>

        <button @click="toggle()" type="button" class="mt-5 flex h-11 w-full items-center gap-3 rounded-xl px-3 text-emerald-100 transition hover:bg-white/10" :aria-label="expanded ? 'Collapse sidebar' : 'Expand sidebar'">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            <span x-show="expanded" x-cloak class="whitespace-nowrap text-sm font-semibold">Collapse menu</span>
        </button>

        <nav class="mt-5 space-y-2">
            <a href="{{ route('dashboard') }}" title="Dashboard" class="flex h-12 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition {{ request()->routeIs('dashboard') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-50 hover:bg-white/10' }}">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                <span x-show="expanded" x-cloak class="whitespace-nowrap">Dashboard</span>
            </a>
            <a href="{{ route('checkout') }}" title="Checkout" class="flex h-12 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition {{ request()->routeIs('checkout*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-50 hover:bg-white/10' }}">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 4h2l2 12h10l2-8H7"/><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/></svg>
                <span x-show="expanded" x-cloak class="whitespace-nowrap">Checkout</span>
            </a>
            <a href="{{ route('history') }}" title="Riwayat transaksi" class="flex h-12 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition {{ request()->routeIs('history*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-50 hover:bg-white/10' }}">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5M12 7v5l3 2"/></svg>
                <span x-show="expanded" x-cloak class="whitespace-nowrap">Riwayat</span>
            </a>
            <a href="{{ route('reports.sales') }}" title="Laporan penjualan" class="flex h-12 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition {{ request()->routeIs('reports.*') ? 'bg-emerald-500 text-[#123c35]' : 'text-emerald-50 hover:bg-white/10' }}">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19V5M4 19h16M8 16v-5M12 16V8M16 16v-9"/></svg>
                <span x-show="expanded" x-cloak class="whitespace-nowrap">Laporan Penjualan</span>
            </a>
        </nav>

        <p x-show="expanded" x-cloak class="mt-auto px-3 text-xs leading-5 text-emerald-200">Kasir cepat, pesanan rapi.</p>
    </div>
</aside>

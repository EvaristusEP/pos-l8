@props([
    'id',
    'title',
    'description' => null,
    'openOnLoad' => false,
    'size' => 'default',
])

<dialog id="{{ $id }}" class="crud-dialog{{ $size === 'wide' ? ' crud-dialog-wide' : '' }}" style="box-sizing: border-box; width: min({{ $size === 'wide' ? '56rem' : '40rem' }}, calc(100vw - 2rem)); max-width: calc(100vw - 2rem); margin: auto;" aria-labelledby="{{ $id }}-title" @if($openOnLoad) data-open-on-load @endif>
    <header class="crud-dialog-header flex items-start justify-between gap-4 border-b border-slate-200 bg-white p-4 sm:p-5">
        <div class="min-w-0">
            <h2 id="{{ $id }}-title" class="text-lg font-extrabold text-[#123c35] sm:text-xl">{{ $title }}</h2>
            @if($description)<p class="mt-1 text-sm text-slate-500">{{ $description }}</p>@endif
        </div>
        <button type="button" data-dialog-close class="grid h-10 w-10 shrink-0 place-items-center rounded-md border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-600" aria-label="Tutup dialog"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="m4 4 12 12M16 4 4 16"/></svg></button>
    </header>
    <div class="crud-dialog-content min-w-0 p-4 sm:p-5">
        {{ $slot }}
    </div>
</dialog>

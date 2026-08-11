@extends('layouts.user')

@section('title', ($language === 'en' ? 'Interactive Map' : 'Peta Interaktif') . ' - EMBER')
@section('hideFooter', true)

@section('content')
    @php
        $availableYears = $locations
            ->pluck('date')
            ->filter()
            ->map(fn ($date) => (int) substr($date, 0, 4))
            ->unique()
            ->sort()
            ->values();
    @endphp

    <section class="relative h-[calc(100vh-6.5rem)] min-h-[600px] w-full overflow-hidden bg-slate-100">
        <div class="absolute left-16 top-3 z-[500] max-w-[calc(100%-5rem)] bg-white/95 px-4 py-3 shadow-xl ring-1 ring-slate-200 backdrop-blur sm:left-20 sm:top-5 sm:max-w-[calc(100%-6.25rem)] sm:px-5 sm:py-4">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-red-600">{{ $language === 'en' ? 'Interactive map' : 'Peta interaktif' }}</p>
            <h1 class="mt-1 text-lg font-bold tracking-tight text-slate-950 sm:text-xl">{{ $language === 'en' ? 'EMBER location distribution' : 'Persebaran lokasi EMBER' }}</h1>
        </div>

        <div id="map-drilldown-control" class="absolute right-3 top-24 z-[500] w-[min(330px,calc(100%-1.5rem))] bg-white/95 p-4 shadow-xl ring-1 ring-slate-200 backdrop-blur sm:right-5 sm:top-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p id="map-boundary-level" class="text-[10px] font-bold uppercase tracking-[0.16em] text-red-600">Provinsi</p>
                    <p id="map-boundary-breadcrumb" class="mt-1 text-sm font-bold text-slate-950">Sumatera</p>
                    <p id="map-boundary-instruction" class="mt-1 text-xs leading-5 text-slate-500">Klik provinsi untuk melihat kabupaten/kota.</p>
                </div>
                <button id="map-boundary-reset" type="button" class="hidden shrink-0 border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700">Reset</button>
            </div>
        </div>

        <div id="map-status-filter" class="absolute bottom-28 right-3 z-[500] w-[min(210px,calc(100%-1.5rem))] rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-xl backdrop-blur sm:bottom-8 sm:right-5">
            <div class="mb-2.5 flex items-center justify-between gap-3">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">{{ $language === 'en' ? 'Status filter' : 'Filter status' }}</p>
                <button type="button" data-map-status-reset class="text-[10px] font-black text-red-600 transition hover:text-red-500">{{ $language === 'en' ? 'Select all' : 'Pilih semua' }}</button>
            </div>
            <div class="grid grid-cols-1 gap-2 text-xs font-bold text-slate-600">
                @foreach ([
                    ['high', 'bg-red-500', $language === 'en' ? 'High' : 'Tinggi'],
                    ['medium', 'bg-amber-400', $language === 'en' ? 'Medium' : 'Sedang'],
                    ['low', 'bg-emerald-500', $language === 'en' ? 'Low' : 'Rendah'],
                    ['unrated', 'bg-slate-500', $language === 'en' ? 'Unrated' : 'Belum dinilai'],
                ] as [$key, $color, $label])
                    <button type="button" data-map-status="{{ $key }}" aria-pressed="true" class="map-status-option flex min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left transition hover:border-slate-300 hover:bg-slate-50">
                        <span class="size-2.5 shrink-0 rounded-full {{ $color }}"></span>
                        <span class="truncate">{{ $label }}</span>
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" class="ml-auto size-3.5 shrink-0 text-red-600" aria-hidden="true"><path d="m4 10 4 4 8-8"/></svg>
                    </button>
                @endforeach
            </div>
        </div>

        <div id="map-year-filter" class="absolute bottom-7 left-3 z-[550] w-[min(420px,calc(100%-1.5rem))] sm:bottom-8 sm:left-5">
            <div class="map-year-slider-control" style="--slider-progress: calc(100% - 1.125rem)">
                <span class="sr-only">{{ $language === 'en' ? 'Filter locations by year' : 'Filter lokasi berdasarkan tahun' }}</span>
                <div class="map-year-slider-track" aria-hidden="true">
                    @foreach ($availableYears as $year)
                        <span class="map-year-slider-mark" data-year-mark="{{ $year }}" data-active="false">
                            <span class="map-year-slider-mark-label">{{ $year }}</span>
                            <span class="map-year-slider-dot"></span>
                        </span>
                    @endforeach
                    <span class="map-year-slider-mark" data-year-mark="all" data-active="true">
                        <span class="map-year-slider-mark-label">{{ $language === 'en' ? 'All' : 'Semua' }}</span>
                        <span class="map-year-slider-dot"></span>
                    </span>
                </div>
                <input
                    id="map-year-range"
                    class="map-year-slider-input"
                    type="range"
                    min="0"
                    max="{{ $availableYears->count() }}"
                    value="{{ $availableYears->count() }}"
                    step="1"
                    data-year-values="{{ $availableYears->join(',') }}"
                    aria-label="{{ $language === 'en' ? 'Location year' : 'Tahun lokasi' }}"
                >
                <output id="map-year-output" class="map-year-slider-value" for="map-year-range" aria-live="polite">
                    <span id="map-year-label">{{ $language === 'en' ? 'All' : 'Semua' }}</span>
                    <span class="sr-only">, </span>
                    <span id="map-result-count" class="sr-only">{{ $locations->count() }} {{ $language === 'en' ? 'locations' : 'lokasi' }}</span>
                </output>
            </div>
            <p class="mt-1 text-center text-[10px] font-bold text-slate-500">&larr; {{ $language === 'en' ? 'Drag to change year' : 'Geser untuk mengganti tahun' }} &rarr;</p>
        </div>

        <div class="absolute inset-0 bg-white">
            <div id="ember-map" class="h-full w-full bg-slate-100" aria-label="{{ $language === 'en' ? 'EMBER location distribution map' : 'Peta persebaran lokasi EMBER' }}"></div>
            <div id="map-filter-empty" class="pointer-events-none absolute right-4 top-4 z-[550] hidden max-w-sm bg-white/95 px-5 py-4 text-center text-sm font-semibold text-slate-700 shadow-lg backdrop-blur sm:right-5 sm:top-5" aria-live="polite">
                {{ $language === 'en' ? 'No locations match the selected filters.' : 'Tidak ada titik lokasi yang sesuai dengan filter.' }}
            </div>

            <aside id="map-detail-panel" data-open="false" class="map-detail-panel absolute inset-y-0 right-0 z-[600] flex w-full max-w-sm flex-col bg-white shadow-2xl" aria-hidden="true" aria-label="{{ $language === 'en' ? 'Location details' : 'Detail lokasi' }}">
                <div class="flex items-start justify-between gap-4 bg-slate-950 p-5 text-white">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-red-400">{{ $language === 'en' ? 'Location details' : 'Detail lokasi' }}</p>
                        <h2 id="map-detail-title" class="mt-2 truncate text-xl font-bold">-</h2>
                        <p id="map-detail-region" class="mt-1 line-clamp-2 text-xs leading-5 text-slate-400">-</p>
                    </div>
                    <button id="map-detail-close" type="button" class="flex size-9 shrink-0 items-center justify-center border border-white/20 text-slate-300 transition hover:bg-white/10 hover:text-white" aria-label="{{ $language === 'en' ? 'Close location details' : 'Tutup detail lokasi' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-5" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-5">
                    <div class="grid grid-cols-2 gap-px bg-slate-200">
                        <div class="bg-slate-50 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Confidence</p>
                            <p id="map-detail-confidence" class="mt-2 font-bold text-slate-900">-</p>
                        </div>
                        <div class="bg-slate-50 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Status</p>
                            <p id="map-detail-status" class="mt-2 font-bold text-slate-900">-</p>
                        </div>
                    </div>

                    <dl class="mt-5 divide-y divide-slate-100 border-y border-slate-200">
                        @foreach (($language === 'en' ? [
                            'Province' => 'province', 'Regency/City' => 'regency', 'District' => 'district',
                            'Village' => 'village', 'Date' => 'date', 'Coordinates' => 'coordinates',
                        ] : [
                            'Provinsi' => 'province', 'Kabupaten/Kota' => 'regency', 'Kecamatan' => 'district',
                            'Desa' => 'village', 'Tanggal' => 'date', 'Koordinat' => 'coordinates',
                        ]) as $label => $key)
                            <div class="grid grid-cols-[110px_1fr] gap-3 py-3.5">
                                <dt class="text-xs font-semibold text-slate-400">{{ $label }}</dt>
                                <dd id="map-detail-{{ $key }}" class="break-words text-sm font-semibold text-slate-800">-</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div class="border-t border-slate-200 p-5">
                    <a id="map-detail-link" href="#" class="flex w-full items-center justify-center gap-2 bg-red-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-red-500">
                        {{ $language === 'en' ? 'Open full details' : 'Buka detail lengkap' }} <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </aside>
            <script id="ember-map-data" type="application/json">{!! json_encode(['language' => $language, 'locations' => $locations, 'boundaryLayers' => $boundaryLayers], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        </div>
    </section>
@endsection

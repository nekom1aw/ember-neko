@extends('layouts.user')

@section('title', ($language === 'en' ? 'Statistics' : 'Statistik') . ' - EMBER')

@section('content')
    @php
        $statuses = [
            'high' => ['label' => $language === 'en' ? 'High' : 'Tinggi', 'color' => 'bg-red-500', 'text' => 'text-red-600', 'hex' => '#ef4444'],
            'medium' => ['label' => $language === 'en' ? 'Medium' : 'Sedang', 'color' => 'bg-amber-400', 'text' => 'text-amber-600', 'hex' => '#fbbf24'],
            'low' => ['label' => $language === 'en' ? 'Low' : 'Rendah', 'color' => 'bg-emerald-500', 'text' => 'text-emerald-600', 'hex' => '#10b981'],
            'unrated' => ['label' => $language === 'en' ? 'Unrated' : 'Belum dinilai', 'color' => 'bg-slate-500', 'text' => 'text-slate-600', 'hex' => '#64748b'],
        ];
        $chartStatistics = $yearlyStatistics->sortBy('year')->values();
        $maxYearTotal = max(1, (int) $chartStatistics->max('total'));
        $latestStatistic = $chartStatistics->last();
    @endphp

    <section class="relative isolate overflow-hidden bg-slate-950 px-4 py-14 text-white sm:px-6 lg:px-8 lg:py-18">
        <div class="absolute -right-32 -top-40 -z-10 size-[32rem] rounded-full bg-red-600/20 blur-3xl" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-10 opacity-[.04]" style="background-image:linear-gradient(rgba(255,255,255,.8) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.8) 1px,transparent 1px);background-size:48px 48px" aria-hidden="true"></div>
        <div class="mx-auto max-w-7xl">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.2em] text-red-300">
                <span class="size-1.5 rounded-full bg-red-500"></span> EMBER DATA
            </div>
            <h1 class="mt-5 text-4xl font-black tracking-[-0.04em] sm:text-5xl">{{ $language === 'en' ? 'Annual Statistics' : 'Statistik Tahunan' }}</h1>
            <p class="mt-4 max-w-2xl leading-7 text-slate-300">{{ $language === 'en' ? 'Yearly distribution of EMBER locations by confidence status.' : 'Distribusi titik lokasi EMBER setiap tahun berdasarkan status confidence.' }}</p>
        </div>
    </section>

    <section class="relative mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" data-reveal>
            <article class="relative overflow-hidden rounded-2xl bg-slate-950 p-5 text-white shadow-xl shadow-slate-950/10">
                <div class="absolute -right-5 -top-5 size-24 rounded-full bg-red-500/20 blur-2xl" aria-hidden="true"></div>
                <p class="relative text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">{{ $language === 'en' ? 'All locations' : 'Semua lokasi' }}</p>
                <p class="relative mt-3 text-4xl font-black tracking-tight">{{ number_format($summary['total']) }}</p>
                <p class="relative mt-2 text-xs text-slate-500">{{ $language === 'en' ? 'Recorded monitoring points' : 'Titik pemantauan tercatat' }}</p>
            </article>
            @foreach ($statuses as $key => $status)
                <article class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-xl hover:shadow-slate-950/[.06]">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $status['label'] }}</p>
                        <span class="size-2.5 rounded-full {{ $status['color'] }} shadow-[0_0_0_4px_rgba(148,163,184,.1)]"></span>
                    </div>
                    <p class="mt-3 text-4xl font-black tracking-tight {{ $status['text'] }}">{{ number_format($summary[$key]) }}</p>
                    <p class="mt-2 text-xs text-slate-400">{{ $summary['total'] > 0 ? number_format(($summary[$key] / $summary['total']) * 100, 1) : 0 }}% {{ $language === 'en' ? 'of total' : 'dari total' }}</p>
                </article>
            @endforeach
        </div>

        @if ($chartStatistics->isNotEmpty())
            <section class="mt-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_20px_60px_rgba(15,23,42,.07)]" data-reveal>
                <div class="flex flex-col gap-5 border-b border-slate-100 px-5 py-6 sm:px-7 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-600">{{ $language === 'en' ? 'Data visualization' : 'Visualisasi data' }}</p>
                        <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $language === 'en' ? 'Location trend by year' : 'Tren lokasi per tahun' }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ $language === 'en' ? 'Stacked bars show the confidence composition for each year.' : 'Batang bertumpuk menunjukkan komposisi confidence pada setiap tahun.' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        @foreach ($statuses as $status)
                            <span class="inline-flex items-center gap-2 text-xs font-bold text-slate-600"><span class="size-2.5 rounded-full {{ $status['color'] }}"></span>{{ $status['label'] }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="overflow-x-auto px-4 pb-6 pt-7 sm:px-7">
                    <div class="relative" style="min-width: {{ max(620, $chartStatistics->count() * 105) }}px">
                        <div class="pointer-events-none absolute inset-x-0 top-0 h-72" aria-hidden="true">
                            @foreach ([100, 75, 50, 25, 0] as $level)
                                <div class="absolute inset-x-0 flex items-center gap-3" style="top: {{ 100 - $level }}%">
                                    <span class="w-8 -translate-y-1/2 text-right text-[10px] font-semibold text-slate-400">{{ (int) round($maxYearTotal * $level / 100) }}</span>
                                    <span class="h-px flex-1 bg-slate-100"></span>
                                </div>
                            @endforeach
                        </div>

                        <div class="relative ml-11 flex h-[324px] items-end justify-around gap-5 px-3">
                            @foreach ($chartStatistics as $statistic)
                                @php($barHeight = max(4, ($statistic['total'] / $maxYearTotal) * 100))
                                <div class="group relative flex h-full min-w-16 flex-1 flex-col items-center justify-end">
                                    <div class="pointer-events-none absolute bottom-[calc(var(--bar-height)+2.8rem)] left-1/2 z-20 hidden w-48 -translate-x-1/2 rounded-2xl bg-slate-950 p-4 text-white shadow-2xl group-hover:block" style="--bar-height: {{ $barHeight }}%">
                                        <div class="flex items-center justify-between gap-4">
                                            <strong class="text-base">{{ $statistic['year'] }}</strong>
                                            <span class="text-xs font-bold text-red-300">{{ $statistic['total'] }} {{ $language === 'en' ? 'points' : 'titik' }}</span>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-2 text-[10px] text-slate-300">
                                            @foreach ($statuses as $key => $status)
                                                <span class="flex items-center gap-1.5"><i class="size-1.5 rounded-full {{ $status['color'] }}"></i>{{ $status['label'] }}: {{ $statistic['counts'][$key] }}</span>
                                            @endforeach
                                        </div>
                                    </div>

                                    <span class="mb-2 text-xs font-black text-slate-700">{{ $statistic['total'] }}</span>
                                    <div class="statistics-bar flex w-full max-w-16 flex-col overflow-hidden rounded-t-xl bg-slate-100 shadow-sm ring-1 ring-inset ring-slate-200" style="height: {{ $barHeight }}%; animation-delay: {{ $loop->index * 90 }}ms" aria-label="{{ $statistic['year'] }}: {{ $statistic['total'] }} {{ $language === 'en' ? 'locations' : 'lokasi' }}">
                                        @foreach ($statuses as $key => $status)
                                            @if ($statistic['percentages'][$key] > 0)
                                                <span class="{{ $status['color'] }} transition group-hover:brightness-110" style="height: {{ $statistic['percentages'][$key] }}%" title="{{ $status['label'] }}: {{ $statistic['counts'][$key] }}"></span>
                                            @endif
                                        @endforeach
                                    </div>
                                    <span class="mt-3 text-xs font-black text-slate-600">{{ $statistic['year'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="grid border-t border-slate-100 bg-slate-50/70 sm:grid-cols-3">
                    <div class="p-5 sm:border-r sm:border-slate-200">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $language === 'en' ? 'Years recorded' : 'Tahun tercatat' }}</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ $chartStatistics->count() }}</p>
                    </div>
                    <div class="border-y border-slate-200 p-5 sm:border-y-0 sm:border-r">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $language === 'en' ? 'Highest annual total' : 'Total tahunan tertinggi' }}</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format($maxYearTotal) }}</p>
                    </div>
                    <div class="p-5">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $language === 'en' ? 'Latest year' : 'Tahun terbaru' }}</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ $latestStatistic['year'] }}</p>
                    </div>
                </div>
            </section>

            <section class="mt-8 overflow-hidden rounded-3xl border border-slate-200 bg-slate-950 text-white shadow-[0_20px_60px_rgba(15,23,42,.12)]" data-reveal data-province-trend>
                <div class="flex flex-col gap-5 border-b border-white/10 px-5 py-6 sm:px-7 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-400">{{ $language === 'en' ? 'Province distribution' : 'Sebaran provinsi' }}</p>
                        <h2 class="mt-2 text-2xl font-black tracking-tight">{{ $language === 'en' ? 'Trend by province' : 'Tren per provinsi' }}</h2>
                        <p class="mt-2 text-sm text-slate-400">{{ $language === 'en' ? 'Select a province to view changes in its number of locations.' : 'Pilih provinsi untuk melihat perubahan jumlah lokasinya.' }}</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="text-xs font-bold text-slate-300">
                            {{ $language === 'en' ? 'Period' : 'Periode' }}
                            <select data-province-period class="mt-2 block w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-bold text-white outline-none focus:border-red-400 focus:ring-4 focus:ring-red-500/15">
                                <option value="yearly" class="bg-slate-900">{{ $language === 'en' ? 'By year' : 'Per tahun' }}</option>
                                <option value="monthly" class="bg-slate-900">{{ $language === 'en' ? 'By month' : 'Per bulan' }}</option>
                            </select>
                        </label>
                        <label data-province-year-wrapper class="hidden text-xs font-bold text-slate-300">
                            {{ $language === 'en' ? 'Year' : 'Tahun' }}
                            <select data-province-year class="mt-2 block w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-bold text-white outline-none focus:border-red-400 focus:ring-4 focus:ring-red-500/15">
                                @foreach ($provinceYears->sortDesc() as $year)
                                    <option value="{{ $year }}" class="bg-slate-900">{{ $year }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs font-bold text-slate-300">
                            {{ $language === 'en' ? 'Selected province' : 'Provinsi terpilih' }}
                            <select data-province-select class="mt-2 block w-full min-w-56 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-bold text-white outline-none focus:border-red-400 focus:ring-4 focus:ring-red-500/15">
                                @foreach ($provinceStatistics as $province)
                                    <option value="{{ $loop->index }}" class="bg-slate-900">{{ $province['name'] }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>

                <div class="grid lg:grid-cols-[minmax(0,1fr)_300px]">
                    <div class="min-w-0 border-b border-white/10 p-4 sm:p-6 lg:border-b-0 lg:border-r">
                        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p data-province-chart-name class="text-xl font-black">-</p>
                                <p data-province-chart-subtitle class="mt-1 text-xs text-slate-500">{{ $language === 'en' ? 'Number of locations per year' : 'Jumlah lokasi per tahun' }}</p>
                            </div>
                            <p class="text-right text-xs text-slate-400"><strong data-province-chart-total class="block text-2xl font-black text-red-400">0</strong>{{ $language === 'en' ? 'total locations' : 'total lokasi' }}</p>
                        </div>
                        <div class="relative overflow-x-auto rounded-2xl border border-white/10 bg-white/[.035] p-2">
                            <svg data-province-chart class="h-[330px] min-w-[620px] w-full" viewBox="0 0 900 330" role="img" aria-label="{{ $language === 'en' ? 'Province annual trend line chart' : 'Grafik garis tren tahunan provinsi' }}"></svg>
                            <div data-province-tooltip class="pointer-events-none absolute hidden rounded-xl bg-white px-3 py-2 text-xs font-bold text-slate-950 shadow-xl"></div>
                        </div>
                    </div>

                    <aside class="p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-black">{{ $language === 'en' ? 'Province list' : 'Daftar provinsi' }}</h3>
                            <span class="rounded-full bg-white/10 px-2.5 py-1 text-[10px] font-bold text-slate-400">{{ $provinceStatistics->count() }}</span>
                        </div>
                        <div class="mt-4 max-h-[340px] space-y-1.5 overflow-y-auto pr-1" data-province-list>
                            @foreach ($provinceStatistics as $province)
                                <button type="button" data-province-option="{{ $loop->index }}" class="group flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-white/10">
                                    <span class="min-w-0 truncate text-xs font-bold text-slate-300 group-hover:text-white">{{ $province['name'] }}</span>
                                    <span data-province-option-total class="shrink-0 rounded-full bg-white/5 px-2 py-1 text-[10px] font-black text-slate-500 group-hover:text-red-300">{{ number_format($province['total']) }}</span>
                                </button>
                            @endforeach
                        </div>
                    </aside>
                </div>

                <script data-province-trend-data type="application/json">{!! json_encode([
                    'years' => $provinceYears,
                    'provinces' => $provinceStatistics,
                    'locationLabel' => $language === 'en' ? 'locations' : 'lokasi',
                    'yearlySubtitle' => $language === 'en' ? 'Number of locations per year' : 'Jumlah lokasi per tahun',
                    'monthlySubtitle' => $language === 'en' ? 'Monthly locations in the selected year' : 'Jumlah lokasi bulanan pada tahun terpilih',
                    'months' => $language === 'en'
                        ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                        : ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            </section>

            <section class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white" data-reveal>
                <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-red-600">{{ $language === 'en' ? 'Detailed data' : 'Data rinci' }}</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">{{ $language === 'en' ? 'Annual recap' : 'Rekap tahunan' }}</h2>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-500">{{ $chartStatistics->count() }} {{ $language === 'en' ? 'years' : 'tahun' }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-5 py-4">{{ $language === 'en' ? 'Year' : 'Tahun' }}</th>
                                <th class="px-5 py-4">{{ $language === 'en' ? 'Total' : 'Jumlah' }}</th>
                                @foreach ($statuses as $status)<th class="px-5 py-4"><span class="inline-flex items-center gap-2"><i class="size-2 rounded-full {{ $status['color'] }}"></i>{{ $status['label'] }}</span></th>@endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($chartStatistics->reverse() as $statistic)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-5 py-4 font-black text-slate-950">{{ $statistic['year'] }}</td>
                                    <td class="px-5 py-4 font-black text-slate-950">{{ $statistic['total'] }}</td>
                                    @foreach ($statuses as $key => $status)<td class="px-5 py-4 font-medium text-slate-600">{{ $statistic['counts'][$key] }}</td>@endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500">{{ $language === 'en' ? 'No dated location data is available yet.' : 'Belum ada data lokasi yang memiliki tanggal.' }}</div>
        @endif
    </section>
@endsection

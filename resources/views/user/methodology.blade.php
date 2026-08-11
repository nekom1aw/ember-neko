@extends('layouts.user')

@section('title', ($language === 'en' ? 'Methodology' : 'Metodologi') . ' - EMBER')

@section('content')
    @php
        $content = $methodology?->{'content_'.$language};
    @endphp

    <section class="mx-auto max-w-5xl px-4 py-14 sm:px-6 lg:px-8">
        @if ($methodology)
            <div class="rich-content text-lg leading-8 text-slate-700">{!! $content ?: e($language === 'en' ? 'Methodology content is not available yet.' : 'Konten Methodology belum tersedia.') !!}</div>
        @else
            <p class="border border-slate-200 bg-white p-8 text-slate-500">{{ $language === 'en' ? 'Methodology content is not available yet.' : 'Konten Methodology belum tersedia.' }}</p>
        @endif
    </section>
@endsection

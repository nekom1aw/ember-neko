@extends('layouts.user')

@section('title', ($language === 'en' ? 'About' : 'Tentang') . ' - EMBER')

@section('content')
    @php
        $content = $about?->{'content_'.$language};
    @endphp

    <section class="mx-auto max-w-5xl px-4 py-14 sm:px-6 lg:px-8">
        @if ($about)
            <div class="rich-content max-w-4xl text-lg leading-8 text-slate-700">{!! $content ?: e($language === 'en' ? 'About content is not available yet.' : 'Konten About belum tersedia.') !!}</div>
        @else
            <p class="border border-slate-200 bg-white p-8 text-slate-500">{{ $language === 'en' ? 'About content is not available yet.' : 'Konten About belum tersedia.' }}</p>
        @endif
    </section>
@endsection

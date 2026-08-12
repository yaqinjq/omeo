@extends('layouts.app')
@section('title', 'Panduan Penggunaan OMEO')
@section('content')
<div class="p-6">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Panduan Penggunaan OMEO</h1>
        <p class="text-sm text-gray-500 mt-1">Cara pakai fitur-fitur yang sedang berjalan sekarang, dikelompokkan per modul. Untuk riwayat perubahan per tanggal, lihat <a href="{{ route('changelog.index') }}" class="text-indigo-600 hover:underline">Changelog & Dokumentasi</a>.</p>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach($sections as $section)
        <div class="rounded-xl border border-gray-100 shadow-sm bg-white overflow-hidden">
            {{-- Section header --}}
            <div class="px-4 py-3 flex items-center gap-3"
                 style="background:{{ $section['color'] }}15; border-bottom:2px solid {{ $section['color'] }}30">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background:{{ $section['color'] }}">
                    <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2">
                        <path d="{{ $section['icon'] }}"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-800">{{ $section['category'] }}</h3>
            </div>

            {{-- Articles --}}
            <div class="divide-y divide-gray-50" x-data="{ open: 0 }">
                @foreach($section['articles'] as $idx => $article)
                <div>
                    <button
                        class="w-full text-left px-4 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors"
                        @click="open = open === {{ $idx }} ? null : {{ $idx }}"
                    >
                        <span class="text-sm font-medium text-gray-700">{{ $article['title'] }}</span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0"
                             :class="open === {{ $idx }} ? 'rotate-180' : ''"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                    <div x-show="open === {{ $idx }}" x-collapse class="px-4 pb-4">
                        <div class="text-sm text-gray-600 leading-relaxed bg-gray-50 rounded-lg p-3">
                            {!! $article['body'] !!}
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

</div>
@endsection

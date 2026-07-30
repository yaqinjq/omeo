@extends('layouts.app')
@section('title', 'Changelog & Dokumentasi OMEO')
@section('content')
<div class="p-6" x-data="{ tab: 'timeline' }">

    {{-- HEADER --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Changelog & Dokumentasi OMEO</h1>
            <p class="text-sm text-gray-500 mt-1">Riwayat update sistem dan referensi teknis pengembang</p>
        </div>
        <div class="text-xs text-gray-400 text-right">
            <div>HR Suite</div>
            <div class="font-semibold text-gray-600">v3.3.10</div>
        </div>
    </div>

    {{-- TAB NAV --}}
    <div class="flex gap-1 mb-6 border-b border-gray-200">
        <button
            @click="tab = 'timeline'"
            :class="tab === 'timeline' ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
            class="px-4 py-2 text-sm transition-colors"
        >
            <span class="flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 3v18h18"/><path d="M7 15l3-3 3 2 4-5"/>
                </svg>
                Timeline Update
            </span>
        </button>
        <button
            @click="tab = 'docs'"
            :class="tab === 'docs' ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
            class="px-4 py-2 text-sm transition-colors"
        >
            <span class="flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 3h18v4H3zM3 10h18v4H3zM3 17h10v4H3z"/>
                </svg>
                Dokumentasi Teknis
            </span>
        </button>
        <button
            @click="tab = 'pimpinan'"
            :class="tab === 'pimpinan' ? 'border-b-2 border-purple-600 text-purple-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
            class="px-4 py-2 text-sm transition-colors"
        >
            <span class="flex items-center gap-2">
                👔 Laporan Pimpinan
            </span>
        </button>
    </div>

    {{-- TAB: TIMELINE --}}
    <div x-show="tab === 'timeline'" x-transition>
        <div class="relative">
            {{-- Vertical line --}}
            <div class="absolute left-[7.5rem] top-0 bottom-0 w-px bg-gray-200" style="left:120px"></div>

            <div class="space-y-8">
                @foreach($timeline as $entry)
                <div class="flex gap-6 relative">
                    {{-- Date + version label (left column) --}}
                    <div class="w-28 flex-shrink-0 text-right pt-1">
                        <div class="text-xs font-bold" style="color:{{ $entry['color'] }}">{{ $entry['version'] }}</div>
                        <div class="text-xs text-gray-400 mt-0.5">{{ $entry['date'] }}</div>
                    </div>

                    {{-- Dot --}}
                    <div class="relative flex-shrink-0 flex items-start pt-1.5" style="z-index:1">
                        <div class="w-4 h-4 rounded-full border-2 border-white shadow"
                             style="background:{{ $entry['color'] }}"></div>
                    </div>

                    {{-- Content card --}}
                    <div class="flex-1 pb-2">
                        <div class="rounded-xl border border-gray-100 shadow-sm bg-white overflow-hidden">
                            <div class="px-4 py-2.5 text-sm font-semibold text-white"
                                 style="background:{{ $entry['color'] }}">
                                {{ $entry['label'] }}
                            </div>
                            <ul class="divide-y divide-gray-50">
                                @foreach($entry['items'] as $item)
                                <li class="px-4 py-2 text-sm text-gray-700 flex gap-2">
                                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" style="color:{{ $entry['color'] }}"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <path d="M5 13l4 4L19 7"/>
                                    </svg>
                                    {{ $item }}
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- TAB: DOKUMENTASI --}}
    <div x-show="tab === 'docs'" x-transition>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach($docs as $section)
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
                <div class="divide-y divide-gray-50" x-data="{ open: null }">
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


    {{-- TAB: LAPORAN PIMPINAN --}}
    <div x-show="tab === 'pimpinan'" x-transition>
        <div class="mb-4 px-4 py-3 rounded-xl text-sm" style="background:#faf5ff;border:1px solid #e9d5ff;color:#6d28d9">
            📋 Ringkasan update fitur dalam bahasa non-teknis — untuk dilaporkan kepada pimpinan.
        </div>

        <div class="space-y-5">
            @foreach($pimpinanReport as $item)
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 flex items-start justify-between gap-4" style="border-bottom:1px solid #f3f4f6">
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">{{ $item['date'] }}</p>
                        <h3 class="text-base font-bold text-gray-800">{{ $item['title'] }}</h3>
                    </div>
                    <span class="text-xs px-3 py-1 rounded-full whitespace-nowrap flex-shrink-0"
                          style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0">
                        {{ $item['stats'] }}
                    </span>
                </div>

                <div class="px-5 py-4">
                    <p class="text-sm text-gray-600 mb-4">{{ $item['summary'] }}</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 mb-1.5">📍 Cara Akses</p>
                            <p class="text-sm font-mono px-3 py-2 rounded-lg" style="background:#f8fafc;color:#374151">
                                {{ $item['access'] }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 mb-1.5">📋 Cara Pakai</p>
                            <ol class="space-y-1">
                                @foreach($item['howto'] as $i => $step)
                                <li class="text-xs text-gray-600 flex gap-2">
                                    <span class="font-bold flex-shrink-0" style="color:#7c3aed">{{ $i + 1 }}.</span>
                                    {{ $step }}
                                </li>
                                @endforeach
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

</div>
@endsection

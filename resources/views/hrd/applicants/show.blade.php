@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 dark:bg-gray-900 pb-20 font-sans">
    
    {{-- HERO BANNER (Game Style) --}}
    <div class="relative h-60 bg-gradient-to-r from-blue-900 to-indigo-900 overflow-hidden">
        <div class="absolute inset-0 opacity-20" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png');"></div>
        <div class="absolute bottom-0 left-0 w-full h-24 bg-gradient-to-t from-gray-100 dark:from-gray-900 to-transparent"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative -mt-24">
        <div class="flex flex-col md:flex-row gap-8 items-start">
            
            {{-- LEFT COLUMN: AVATAR & MAIN STATS --}}
            <div class="w-full md:w-80 shrink-0">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-6 text-center relative overflow-hidden">
                    {{-- Avatar --}}
                    <div class="relative w-40 h-40 mx-auto rounded-full p-1 bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-600 shadow-2xl mb-4">
                        @if($profile->photo_path)
                            <img src="{{ asset('storage/'.$profile->photo_path) }}" class="w-full h-full rounded-full object-cover border-4 border-white dark:border-gray-800">
                        @else
                            <div class="w-full h-full rounded-full bg-gray-200 flex items-center justify-center text-4xl font-bold text-gray-400 border-4 border-white">
                                {{ substr($profile->full_name, 0, 1) }}
                            </div>
                        @endif
                        <div class="absolute bottom-2 right-2 w-8 h-8 bg-green-500 border-4 border-white dark:border-gray-800 rounded-full" title="Active"></div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white leading-tight">{{ $profile->full_name }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 font-medium mb-4">{{ $profile->gender }} • {{ \Carbon\Carbon::parse($profile->date_of_birth)->age }} Tahun</p>

                    <div class="flex justify-center gap-2 mb-6">
                        <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold uppercase tracking-wider">Candidate</span>
                        <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-bold uppercase tracking-wider">Level 1</span>
                    </div>

                    {{-- Actions --}}
                    <div class="grid grid-cols-2 gap-3 mb-2">
                        @if($profile->cv_path)
                        <a href="{{ asset('storage/'.$profile->cv_path) }}" target="_blank" class="flex items-center justify-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-bold transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            CV PDF
                        </a>
                        @endif
                        <a href="https://wa.me/{{ $profile->whatsapp }}" target="_blank" class="flex items-center justify-center gap-2 px-4 py-2 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg text-xs font-bold transition">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                            WA
                        </a>
                    </div>
                </div>

                {{-- STATS SIDEBAR (MOCKUP) --}}
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mt-6">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Psychometric Stats</h3>
                    
                    {{-- IQ --}}
                    <div class="mb-4">
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300">IQ Score</span>
                            <span class="text-sm font-bold text-blue-600">{{ $stats['iq'] }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ ($stats['iq']/160)*100 }}%"></div>
                        </div>
                    </div>

                    {{-- DISC --}}
                    <div class="mb-4">
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300">DISC Profile</span>
                            <span class="text-sm font-bold text-purple-600">{{ $stats['disc'] }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-purple-600 h-2 rounded-full" style="width: 85%"></div>
                        </div>
                    </div>

                    {{-- Interview --}}
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300">Interview Score</span>
                            <span class="text-sm font-bold text-green-600">{{ $stats['interview_score'] }}/100</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full" style="width: {{ $stats['interview_score'] }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIGHT COLUMN: DETAILS TABS --}}
            <div class="flex-1 w-full min-w-0">
                
                {{-- TOP STATS GRID --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="text-xs text-gray-500 uppercase font-bold">Join Date</div>
                        <div class="text-lg font-bold text-gray-900 dark:text-white">-</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="text-xs text-gray-500 uppercase font-bold">Status</div>
                        <div class="text-lg font-bold text-green-600">Applied</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="text-xs text-gray-500 uppercase font-bold">Probation</div>
                        <div class="text-lg font-bold text-gray-900 dark:text-white">-</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="text-xs text-gray-500 uppercase font-bold">Last Appraisal</div>
                        <div class="text-lg font-bold text-gray-900 dark:text-white">-</div>
                    </div>
                </div>

                {{-- TABS CONTENT --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden" x-data="{ tab: 'biodata' }">
                    {{-- Tab Headers --}}
                    <div class="flex border-b border-gray-200 dark:border-gray-700 overflow-x-auto">
                        <button @click="tab = 'biodata'" :class="{'border-blue-500 text-blue-600': tab === 'biodata'}" class="px-6 py-4 text-sm font-bold text-gray-600 dark:text-gray-300 border-b-2 border-transparent hover:text-blue-600 whitespace-nowrap">
                            Biodata & Alamat
                        </button>
                        <button @click="tab = 'pendidikan'" :class="{'border-blue-500 text-blue-600': tab === 'pendidikan'}" class="px-6 py-4 text-sm font-bold text-gray-600 dark:text-gray-300 border-b-2 border-transparent hover:text-blue-600 whitespace-nowrap">
                            Pendidikan & Skill
                        </button>
                        <button @click="tab = 'history'" :class="{'border-blue-500 text-blue-600': tab === 'history'}" class="px-6 py-4 text-sm font-bold text-gray-600 dark:text-gray-300 border-b-2 border-transparent hover:text-blue-600 whitespace-nowrap">
                            Riwayat Karir
                        </button>
                    </div>

                    {{-- Tab Body --}}
                    <div class="p-6">
                        {{-- TAB 1: BIODATA --}}
                        <div x-show="tab === 'biodata'" class="space-y-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Informasi Dasar</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                                <div><span class="block text-gray-500 text-xs uppercase">Tempat Lahir</span> <span class="font-semibold">{{ $profile->place_of_birth }}</span></div>
                                <div><span class="block text-gray-500 text-xs uppercase">No. KTP</span> <span class="font-semibold">{{ $profile->ktp_number }}</span></div>
                                <div><span class="block text-gray-500 text-xs uppercase">Agama</span> <span class="font-semibold">{{ $profile->religion }}</span></div>
                                <div><span class="block text-gray-500 text-xs uppercase">Status Nikah</span> <span class="font-semibold">{{ $profile->marital_status }}</span></div>
                                <div class="md:col-span-2"><span class="block text-gray-500 text-xs uppercase">Alamat Domisili</span> <span class="font-semibold">{{ $profile->domicile_address }}</span></div>
                            </div>

                            <hr class="border-gray-100 dark:border-gray-700">
                            
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Keluarga</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left">
                                    <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase text-gray-500">
                                        <tr><th class="px-4 py-2">Hubungan</th><th class="px-4 py-2">Nama</th><th class="px-4 py-2">Pekerjaan</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($profile->families as $fam)
                                        <tr class="border-b border-gray-100 dark:border-gray-700">
                                            <td class="px-4 py-2 font-bold">{{ $fam['relation'] ?? '-' }}</td>
                                            <td class="px-4 py-2">{{ $fam['name'] ?? '-' }}</td>
                                            <td class="px-4 py-2">{{ $fam['job'] ?? '-' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- TAB 2: PENDIDIKAN --}}
                        <div x-show="tab === 'pendidikan'" class="space-y-6" style="display: none;">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Pendidikan Formal</h3>
                            <div class="space-y-4">
                                @foreach($profile->educations as $edu)
                                <div class="flex gap-4 items-start">
                                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center shrink-0 font-bold text-xs">
                                        {{ $edu['year_out'] ?? 'Year' }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-gray-900 dark:text-white">{{ $edu['school'] ?? '-' }}</div>
                                        <div class="text-sm text-gray-500">{{ $edu['level'] ?? '-' }} - {{ $edu['major'] ?? '-' }}</div>
                                        <div class="text-xs text-blue-600 font-bold mt-1">IPK: {{ $edu['gpa'] ?? '-' }}</div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- TAB 3: HISTORY (Timeline Style) --}}
                        <div x-show="tab === 'history'" style="display: none;">
                            <div class="relative border-l-2 border-gray-200 dark:border-gray-700 ml-3 space-y-8 pl-6 py-2">
                                {{-- Item 1 --}}
                                <div class="relative">
                                    <div class="absolute -left-[31px] bg-green-500 h-4 w-4 rounded-full border-2 border-white"></div>
                                    <div class="font-bold text-gray-900 dark:text-white">Application Received</div>
                                    <div class="text-xs text-gray-500">{{ $profile->created_at->format('d M Y') }}</div>
                                    <p class="text-sm mt-1 text-gray-600">Pelamar melengkapi form aplikasi.</p>
                                </div>
                                {{-- Item 2 (Placeholder) --}}
                                <div class="relative opacity-50">
                                    <div class="absolute -left-[31px] bg-gray-300 h-4 w-4 rounded-full border-2 border-white"></div>
                                    <div class="font-bold text-gray-900 dark:text-white">Interview HR</div>
                                    <div class="text-xs text-gray-500">-</div>
                                    <p class="text-sm mt-1 text-gray-600">Menunggu jadwal.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Alpine.js for Tabs (If not installed, use vanilla JS below) --}}
<script src="//unpkg.com/alpinejs" defer></script>
@endsection
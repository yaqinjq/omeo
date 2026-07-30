@extends('layouts.app')

@section('content')
@php
    $age = $profile->date_of_birth ? \Carbon\Carbon::parse($profile->date_of_birth)->age : null;
    $referenceContacts = $profile->reference_contacts ?? [];
@endphp

<div class="min-h-screen bg-gray-100 dark:bg-gray-900 pb-10 font-sans">
    <div class="relative h-40 bg-gradient-to-r from-blue-900 to-indigo-900 overflow-hidden">
        <div class="absolute inset-0 opacity-20" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png');"></div>
        <div class="absolute bottom-0 left-0 w-full h-24 bg-gradient-to-t from-gray-100 dark:from-gray-900 to-transparent"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative -mt-12">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <aside class="lg:col-span-4 space-y-6 lg:sticky lg:top-24">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-6 mx-auto lg:mx-0" style="max-width: 360px; width: 100%;">
                    <div class="relative mx-auto rounded-full p-1 bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-600 mb-4" style="width: 112px; height: 112px;">
                        @if($profile->photo_path)
                            <img src="{{ asset('storage/'.$profile->photo_path) }}" class="w-full h-full rounded-full object-cover border-4 border-white dark:border-gray-800" alt="Foto Kandidat">
                        @else
                            <div class="w-full h-full rounded-full bg-gray-200 grid place-items-center text-4xl font-bold text-gray-400 border-4 border-white">
                                {{ substr($profile->full_name ?: 'K', 0, 1) }}
                            </div>
                        @endif
                    </div>

                    <h2 class="text-2xl font-bold text-center text-gray-900 dark:text-white">{{ $profile->full_name ?: '-' }}</h2>
                    <p class="text-sm text-center text-gray-500 dark:text-gray-400 mt-1">
                        {{ $profile->gender ?: '-' }}
                        @if($age !== null)
                            • {{ $age }} Tahun
                        @endif
                    </p>

                    <div class="mt-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 px-3 py-2 text-center">
                        <div class="text-[11px] uppercase tracking-wide text-blue-600 font-bold">Ekspektasi Gaji</div>
                        <div class="text-sm font-bold text-blue-800 dark:text-blue-300">
                            {{ $profile->salary_expectation ? 'Rp '.number_format((float) $profile->salary_expectation, 0, ',', '.') : '-' }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mt-5">
                        @if($profile->cv_path)
                            <a href="{{ asset('storage/'.$profile->cv_path) }}" target="_blank" class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs font-bold text-center">Lihat CV</a>
                        @else
                            <div class="px-3 py-2 rounded-lg bg-gray-100 text-xs font-bold text-center text-gray-400">CV Belum Ada</div>
                        @endif

                        @if($profile->ktp_path)
                            <a href="{{ asset('storage/'.$profile->ktp_path) }}" target="_blank" class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs font-bold text-center">Lihat KTP</a>
                        @else
                            <div class="px-3 py-2 rounded-lg bg-gray-100 text-xs font-bold text-center text-gray-400">KTP Belum Ada</div>
                        @endif

                        @if($profile->whatsapp)
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', (string) $profile->whatsapp) }}" target="_blank" class="px-3 py-2 rounded-lg bg-green-100 hover:bg-green-200 text-green-700 text-xs font-bold text-center col-span-2">Kontak WhatsApp</a>
                        @endif
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mx-auto lg:mx-0" style="max-width: 360px; width: 100%;">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Status Penilaian Tes</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between"><span>IQ Score</span><span class="font-bold">{{ $stats['iq'] ?? '-' }}</span></div>
                        <div class="flex justify-between"><span>DISC Profile</span><span class="font-bold">{{ $stats['disc'] ?? '-' }}</span></div>
                        <div class="flex justify-between"><span>Interview</span><span class="font-bold">{{ isset($stats['interview_score']) ? $stats['interview_score'].'/100' : '-' }}</span></div>
                        <div class="flex justify-between"><span>Technical</span><span class="font-bold">{{ isset($stats['technical_score']) ? $stats['technical_score'].'/100' : '-' }}</span></div>
                    </div>
                    <p class="text-xs text-amber-600 mt-3">Nilai ditampilkan jika sudah ada data real. Tidak ada data dummy.</p>
                </div>
            </aside>

            <section class="lg:col-span-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden" x-data="{ tab: 'biodata' }">
                    <div class="flex border-b border-gray-200 dark:border-gray-700 overflow-x-auto">
                        <button @click="tab = 'biodata'" :class="{'border-blue-500 text-blue-600': tab === 'biodata'}" class="px-5 py-4 text-sm font-bold border-b-2 border-transparent whitespace-nowrap">Biodata & Dokumen</button>
                        <button @click="tab = 'pendidikan'" :class="{'border-blue-500 text-blue-600': tab === 'pendidikan'}" class="px-5 py-4 text-sm font-bold border-b-2 border-transparent whitespace-nowrap">Pendidikan & Skill</button>
                        <button @click="tab = 'karir'" :class="{'border-blue-500 text-blue-600': tab === 'karir'}" class="px-5 py-4 text-sm font-bold border-b-2 border-transparent whitespace-nowrap">Karir & Referensi</button>
                        <button @click="tab = 'kesehatan'" :class="{'border-blue-500 text-blue-600': tab === 'kesehatan'}" class="px-5 py-4 text-sm font-bold border-b-2 border-transparent whitespace-nowrap">Kesehatan & Sosial</button>
                    </div>

                    <div class="p-6 space-y-8">
                        <div x-show="tab === 'biodata'" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div><span class="text-xs text-gray-500 uppercase block">Nama</span><span class="font-semibold">{{ $profile->full_name ?: '-' }}</span></div>
                                <div><span class="text-xs text-gray-500 uppercase block">Email</span><span class="font-semibold">{{ $profile->user->email ?? '-' }}</span></div>
                                <div><span class="text-xs text-gray-500 uppercase block">NIK</span><span class="font-semibold">{{ $profile->ktp_number ?: '-' }}</span></div>
                                <div><span class="text-xs text-gray-500 uppercase block">Tanggal Lahir</span><span class="font-semibold">{{ $profile->date_of_birth ?: '-' }}</span></div>
                                <div><span class="text-xs text-gray-500 uppercase block">Tempat Lahir</span><span class="font-semibold">{{ $profile->place_of_birth ?: '-' }}</span></div>
                                <div><span class="text-xs text-gray-500 uppercase block">Agama</span><span class="font-semibold">{{ $profile->religion ?: '-' }}</span></div>
                                <div><span class="text-xs text-gray-500 uppercase block">Status Nikah</span><span class="font-semibold">{{ $profile->marital_status ?: '-' }}</span></div>
                                <div><span class="text-xs text-gray-500 uppercase block">No. WhatsApp</span><span class="font-semibold">{{ $profile->whatsapp ?: '-' }}</span></div>
                                <div class="md:col-span-2"><span class="text-xs text-gray-500 uppercase block">Ekspektasi Gaji</span><span class="font-semibold">{{ $profile->salary_expectation ? 'Rp '.number_format((float) $profile->salary_expectation, 0, ',', '.') : '-' }}</span></div>
                            </div>

                            <hr class="border-gray-100 dark:border-gray-700">

                            <div>
                                <h4 class="font-bold mb-2">Alamat</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div><span class="text-xs text-gray-500 uppercase block">Alamat KTP</span><span class="font-semibold">{{ $profile->ktp_address ?: '-' }}</span></div>
                                    <div><span class="text-xs text-gray-500 uppercase block">Alamat Domisili</span><span class="font-semibold">{{ $profile->domicile_address ?: '-' }}</span></div>
                                    <div><span class="text-xs text-gray-500 uppercase block">Provinsi</span><span class="font-semibold">{{ $profile->ktp_province ?: '-' }}</span></div>
                                    <div><span class="text-xs text-gray-500 uppercase block">Kota/Kabupaten</span><span class="font-semibold">{{ $profile->ktp_city ?: '-' }}</span></div>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-bold mb-2">Susunan Keluarga</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Hubungan</th>
                                                <th class="px-3 py-2 text-left">Nama</th>
                                                <th class="px-3 py-2 text-left">Pekerjaan</th>
                                                <th class="px-3 py-2 text-left">Pendidikan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($profile->families as $fam)
                                                <tr class="border-b border-gray-100">
                                                    <td class="px-3 py-2">{{ $fam['relation'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $fam['name'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $fam['job'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $fam['education'] ?? '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="px-3 py-3 text-gray-400">Belum ada data keluarga.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div x-show="tab === 'pendidikan'" class="space-y-6" style="display:none;">
                            <div>
                                <h4 class="font-bold mb-3">Riwayat Pendidikan</h4>
                                <div class="space-y-3">
                                    @forelse($profile->educations as $edu)
                                        <div class="p-4 rounded-lg border border-gray-200">
                                            <div class="font-semibold">{{ $edu['school'] ?? '-' }}</div>
                                            <div class="text-sm text-gray-600">{{ $edu['level'] ?? '-' }} - {{ $edu['major'] ?? '-' }}</div>
                                            <div class="text-xs text-gray-500 mt-1">{{ $edu['year_in'] ?? '-' }} - {{ $edu['year_out'] ?? '-' }} | IPK: {{ $edu['gpa'] ?? '-' }}</div>
                                        </div>
                                    @empty
                                        <p class="text-sm text-gray-400">Belum ada riwayat pendidikan.</p>
                                    @endforelse
                                </div>
                            </div>

                            <div>
                                <h4 class="font-bold mb-3">Bahasa</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                            <tr><th class="px-3 py-2 text-left">Bahasa</th><th class="px-3 py-2 text-left">Lisan</th><th class="px-3 py-2 text-left">Tulisan</th></tr>
                                        </thead>
                                        <tbody>
                                            @forelse($profile->languages as $lang)
                                                <tr class="border-b border-gray-100"><td class="px-3 py-2">{{ $lang['language'] ?? '-' }}</td><td class="px-3 py-2">{{ $lang['speaking'] ?? '-' }}</td><td class="px-3 py-2">{{ $lang['writing'] ?? '-' }}</td></tr>
                                            @empty
                                                <tr><td colspan="3" class="px-3 py-3 text-gray-400">Belum ada data bahasa.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-bold mb-3">Kursus & Pelatihan</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Nama Kursus</th>
                                                <th class="px-3 py-2 text-left">Penyelenggara</th>
                                                <th class="px-3 py-2 text-left">Tahun</th>
                                                <th class="px-3 py-2 text-left">Sertifikat</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($profile->courses as $course)
                                                <tr class="border-b border-gray-100">
                                                    <td class="px-3 py-2">{{ $course['name'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $course['organizer'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $course['year'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $course['certificate'] ?? '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="px-3 py-3 text-gray-400">Belum ada data kursus & pelatihan.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div x-show="tab === 'karir'" class="space-y-6" style="display:none;">
                            <div>
                                <h4 class="font-bold mb-3">Riwayat Pekerjaan</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Perusahaan</th>
                                                <th class="px-3 py-2 text-left">Jabatan</th>
                                                <th class="px-3 py-2 text-left">Periode</th>
                                                <th class="px-3 py-2 text-left">Gaji</th>
                                                <th class="px-3 py-2 text-left">Alasan Keluar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($profile->work_experiences as $work)
                                                <tr class="border-b border-gray-100">
                                                    <td class="px-3 py-2">{{ $work['company'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $work['position'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $work['date_start'] ?? '-' }} - {{ $work['date_end'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $work['salary'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $work['reason'] ?? '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="5" class="px-3 py-3 text-gray-400">Belum ada riwayat pekerjaan.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-bold mb-3">Kontak Referensi / Darurat</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                            <tr><th class="px-3 py-2 text-left">Nama</th><th class="px-3 py-2 text-left">Hubungan</th><th class="px-3 py-2 text-left">Perusahaan</th><th class="px-3 py-2 text-left">No. HP</th></tr>
                                        </thead>
                                        <tbody>
                                            @forelse($referenceContacts as $ref)
                                                <tr class="border-b border-gray-100">
                                                    <td class="px-3 py-2">{{ $ref['name'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $ref['relation'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $ref['company'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $ref['phone'] ?? '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="px-3 py-3 text-gray-400">Belum ada kontak referensi.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-bold mb-3">Pengalaman Organisasi</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Nama Organisasi</th>
                                                <th class="px-3 py-2 text-left">Jabatan</th>
                                                <th class="px-3 py-2 text-left">Tahun</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($profile->organizations as $org)
                                                <tr class="border-b border-gray-100">
                                                    <td class="px-3 py-2">{{ $org['name'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $org['role'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $org['year'] ?? '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="px-3 py-3 text-gray-400">Belum ada pengalaman organisasi.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div x-show="tab === 'kesehatan'" class="space-y-6" style="display:none;">
                            <div>
                                <h4 class="font-bold mb-3">Riwayat Penyakit</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                            <tr><th class="px-3 py-2 text-left">Penyakit</th><th class="px-3 py-2 text-left">Tahun</th><th class="px-3 py-2 text-left">Opname</th><th class="px-3 py-2 text-left">Keterangan</th></tr>
                                        </thead>
                                        <tbody>
                                            @forelse($profile->medical_histories as $med)
                                                <tr class="border-b border-gray-100">
                                                    <td class="px-3 py-2">{{ $med['illness'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $med['year'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $med['hospitalized'] ?? '-' }}</td>
                                                    <td class="px-3 py-2">{{ $med['note'] ?? '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="px-3 py-3 text-gray-400">Belum ada riwayat penyakit.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-bold mb-3">Sosial Media</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                            <tr><th class="px-3 py-2 text-left">Platform</th><th class="px-3 py-2 text-left">Handle / Link</th></tr>
                                        </thead>
                                        <tbody>
                                            @forelse($profile->social_medias as $soc)
                                                <tr class="border-b border-gray-100"><td class="px-3 py-2">{{ $soc['platform'] ?? '-' }}</td><td class="px-3 py-2">{{ $soc['handle'] ?? '-' }}</td></tr>
                                            @empty
                                                <tr><td colspan="2" class="px-3 py-3 text-gray-400">Belum ada data sosial media.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="//unpkg.com/alpinejs" defer></script>
@endsection

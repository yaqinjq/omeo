@php
$statusColors = [
    'registered' => ['bg' => '#dbeafe', 'text' => '#1d4ed8'],
    'checked_in' => ['bg' => '#dcfce7', 'text' => '#15803d'],
    'no_show'    => ['bg' => '#fee2e2', 'text' => '#b91c1c'],
    'withdrawn'  => ['bg' => '#f1f5f9', 'text' => '#64748b'],
];
@endphp
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0">
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">No. Antrian</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Nama</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Posisi</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Via (Referrer)</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Check-In</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Interview</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Tes</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Final</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($candidates as $c)
                @php
                $sc = $statusColors[$c->registration_status] ?? ['bg' => '#f1f5f9', 'text' => '#64748b'];

                $interviewBadge = match($c->interview_status ?? 'pending') {
                    'passed'  => ['✅ Lolos',       '#16a34a', '#dcfce7'],
                    'failed'  => ['❌ Tidak Lolos', '#dc2626', '#fee2e2'],
                    'skipped' => ['⏭️ Skip',        '#6b7280', '#f3f4f6'],
                    default   => ['⏳ Pending',      '#d97706', '#fef3c7'],
                };

                $testLabel = ($c->test_score !== null)
                    ? number_format((float)$c->test_score, 0) . '%'
                    : '—';
                $testBadge = match($c->test_status ?? 'pending') {
                    'passed'  => ['✅ ' . $testLabel, '#16a34a', '#dcfce7'],
                    'failed'  => ['❌ ' . $testLabel, '#dc2626', '#fee2e2'],
                    default   => ['—',                '#9ca3af', '#f9fafb'],
                };

                $finalBadge = match($c->final_status ?? 'pending') {
                    'accepted' => ['🎉 Diterima',  '#16a34a', '#dcfce7'],
                    'reserved' => ['📋 Cadangan',  '#7c3aed', '#f3e8ff'],
                    'rejected' => ['❌ Ditolak',   '#dc2626', '#fee2e2'],
                    default    => ['⏳ Menunggu',   '#d97706', '#fef3c7'],
                };

                $canInterview = $c->registration_status === 'checked_in'
                    && ($c->interview_status ?? 'pending') === 'pending';
                @endphp
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 font-mono font-semibold text-indigo-700">{{ $c->registration_number }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('walkin.candidate.show', $c->id) }}"
                           class="font-semibold text-gray-800 hover:text-indigo-600 hover:underline">
                            {{ $c->candidate_name }}
                        </a>
                        <div class="text-xs text-gray-400">{{ $c->candidate_phone }}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $c->applied_position ?: '-' }}</td>
                    <td class="px-4 py-3">
                        @if($c->referrer)
                        <span class="text-xs text-gray-600">{{ $c->referrer->name }}</span>
                        <div class="text-xs font-mono text-gray-400">{{ $c->referral_code }}</div>
                        @else
                        <span class="text-xs text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold"
                              style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }}">
                            {{ $c->registration_status_label }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        {{ $c->check_in_time?->format('H:i') ?: '-' }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold"
                              style="background:{{ $interviewBadge[2] }};color:{{ $interviewBadge[1] }}">
                            {{ $interviewBadge[0] }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold"
                              style="background:{{ $testBadge[2] }};color:{{ $testBadge[1] }}">
                            {{ $testBadge[0] }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold"
                              style="background:{{ $finalBadge[2] }};color:{{ $finalBadge[1] }}">
                            {{ $finalBadge[0] }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1.5 flex-wrap">
                            {{-- Quick check-in --}}
                            @if($c->registration_status === 'registered')
                            <form method="POST" action="{{ route('walkin.checkin', $c->id) }}">
                                @csrf
                                <button type="submit"
                                        class="px-2.5 py-1 rounded-lg text-xs font-semibold text-white"
                                        style="background:#0ea5e9"
                                        onclick="return confirm('Check-in {{ addslashes($c->candidate_name) }}?')">
                                    Check-In
                                </button>
                            </form>
                            @endif

                            {{-- Quick interview actions (hanya jika sudah hadir & interview masih pending) --}}
                            @if($canInterview)
                            <form method="POST" action="{{ route('walkin.candidate.interview', $c->id) }}">
                                @csrf
                                <input type="hidden" name="interview_status" value="passed">
                                <button type="submit"
                                        class="px-2 py-1 rounded text-xs font-semibold"
                                        style="background:#dcfce7;color:#16a34a">✅</button>
                            </form>
                            <form method="POST" action="{{ route('walkin.candidate.interview', $c->id) }}">
                                @csrf
                                <input type="hidden" name="interview_status" value="failed">
                                <button type="submit"
                                        class="px-2 py-1 rounded text-xs font-semibold"
                                        style="background:#fee2e2;color:#dc2626">❌</button>
                            </form>
                            @endif

                            {{-- Detail link --}}
                            <a href="{{ route('walkin.candidate.show', $c->id) }}"
                               class="px-2.5 py-1 rounded-lg text-xs font-semibold"
                               style="background:#f1f5f9;color:#475569">
                                Detail
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-4 py-10 text-center text-gray-400">Belum ada kandidat.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

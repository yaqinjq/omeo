@extends('layouts.app')
@section('title', 'Cross-billing BPJS')
@section('content')

<div style="max-width:1200px; margin:0 auto;" x-data="crossBillingPage()">

    {{-- ═══ MODAL OVERLAY ═══ --}}
    <div x-show="modalOpen"
         x-transition:enter="transition duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="closeModal()"
         @keydown.escape.window="closeModal()"
         style="position:fixed; inset:0; z-index:9998;
                background:rgba(15,23,42,0.5); backdrop-filter:blur(2px);
                display:flex; align-items:center; justify-content:center;
                padding:20px;">

        {{-- Inner card — TIDAK pakai x-show agar display:flex tidak dihapus Alpine --}}
        <div @click.stop
             style="background:white; border-radius:16px;
                    width:100%; max-width:680px;
                    display:flex; flex-direction:column; overflow:hidden;
                    box-shadow:0 20px 60px rgba(0,0,0,0.2);">

            {{-- Modal Header --}}
            <div style="padding:18px 22px; border-bottom:1px solid #F1F5F9;
                        display:flex; justify-content:space-between;
                        align-items:center; flex-shrink:0;">
                <div>
                    <div style="font-weight:800; font-size:16px; color:#1e293b;"
                         x-text="modalTitle"></div>
                    <div style="font-size:12px; color:#94A3B8; margin-top:2px;"
                         x-text="modalSubtitle"></div>
                </div>
                <button @click="closeModal()"
                        style="background:#F1F5F9; border:none; width:32px; height:32px;
                               border-radius:50%; cursor:pointer; font-size:15px;
                               display:flex; align-items:center; justify-content:center;
                               color:#64748B; flex-shrink:0;">✕</button>
            </div>

            {{-- Modal Search --}}
            <div style="padding:12px 22px; border-bottom:1px solid #F8FAFC; flex-shrink:0;">
                <input type="text" x-model="modalSearch"
                       placeholder="Cari nama atau NIK..."
                       style="width:100%; padding:8px 12px; border:1.5px solid #E2E8F0;
                              border-radius:8px; font-size:13px; outline:none;
                              box-sizing:border-box;"
                       onfocus="this.style.borderColor='#7C3AED'"
                       onblur="this.style.borderColor='#E2E8F0'">
            </div>

            {{-- Modal List — max-height eksplisit agar scroll aktif tanpa bergantung flex parent --}}
            <div style="overflow-y:auto; padding:4px 22px;
                        max-height:calc(80vh - 175px);
                        overscroll-behavior:contain;
                        scrollbar-width:thin; scrollbar-color:#C4B5FD #F8FAFC;">
                <template x-for="(emp, idx) in filteredModal()" :key="idx">
                    <div style="display:flex; align-items:flex-start; gap:12px;
                                padding:10px 0; border-bottom:1px solid #F8FAFC;">

                        {{-- Nomor + Avatar --}}
                        <div style="flex-shrink:0; display:flex; align-items:center; gap:8px;">
                            <span style="color:#CBD5E1; font-size:11px; width:20px; text-align:right;"
                                  x-text="(idx + 1) + '.'"></span>
                            <div style="width:34px; height:34px; border-radius:8px;
                                        background:#7C3AED; color:white; font-weight:700;
                                        font-size:13px; display:flex; align-items:center;
                                        justify-content:center; flex-shrink:0;"
                                 x-text="(emp.nama_bpjs || '?').charAt(0)"></div>
                        </div>

                        {{-- Info --}}
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:600; color:#1e293b; font-size:13px;"
                                 x-text="emp.nama_bpjs"></div>
                            <div style="font-size:11px; color:#94A3B8; margin-top:2px;"
                                 x-text="'NIK: ' + emp.nik + (emp.posisi ? ' · ' + emp.posisi : '')"></div>
                            <div x-show="emp.match_note"
                                 style="font-size:10px; color:#854D0E; background:#FEF9C3;
                                        border-radius:4px; padding:2px 6px; margin-top:4px;
                                        line-height:1.4; display:inline-block; max-width:100%;"
                                 x-text="emp.match_note"></div>
                            <div x-show="emp.outlet_name_raw && emp.match_status === 'other_session'"
                                 style="font-size:11px; color:#1D4ED8; margin-top:3px;"
                                 x-text="'📍 ' + emp.outlet_name_raw"></div>
                        </div>

                        {{-- Total iuran --}}
                        <div style="flex-shrink:0; text-align:right;">
                            <div style="font-size:12px; font-weight:700; color:#7C3AED;"
                                 x-text="'Rp ' + Number(emp.total_iuran).toLocaleString('id-ID')"></div>
                        </div>
                    </div>
                </template>

                <div x-show="filteredModal().length === 0"
                     style="text-align:center; padding:40px; color:#94A3B8; font-size:13px;">
                    Tidak ada hasil untuk "<span x-text="modalSearch"></span>"
                </div>
            </div>

            {{-- Modal Footer --}}
            <div style="padding:12px 22px; border-top:1px solid #F1F5F9;
                        text-align:right; flex-shrink:0;">
                <span style="font-size:12px; color:#94A3B8;"
                      x-text="filteredModal().length + ' karyawan ditampilkan'"></span>
            </div>
        </div>
    </div>
    {{-- ═══ END MODAL ═══ --}}

    {{-- Header --}}
    <div style="margin-bottom:20px;">
        <a href="{{ route('finance.bpjs-reconciliation.show', $bill->id) }}"
           style="font-size:13px; color:#7C3AED; text-decoration:none;">← Kembali ke Rekonsiliasi</a>
        <h1 style="font-size:22px; font-weight:800; margin:8px 0 4px; color:#1e293b;">
            📊 Cross-billing BPJS
        </h1>
        <div style="font-size:13px; color:#64748B;">
            {{ $bill->nama_perusahaan ?? $bill->source_file_name }}
            @if($bill->npp) · NPP: <span style="font-family:monospace;">{{ $bill->npp }}</span>@endif
            · Periode {{ $bill->periode }}
        </div>
    </div>

    {{-- Filter Session Payroll --}}
    <form method="GET" style="margin-bottom:20px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <label style="font-size:13px; color:#475569; font-weight:600;">Referensi Payroll:</label>
        <select name="session_id" onchange="this.form.submit()"
                style="border:1.5px solid #E2E8F0; border-radius:8px; padding:6px 14px;
                       font-size:13px; color:#1e293b; background:white; cursor:pointer;">
            @foreach($sessions as $s)
            <option value="{{ $s->id }}" {{ $s->id == $sessionId ? 'selected' : '' }}>
                {{ $s->source_file_name }} ({{ $s->periode }}) — {{ number_format($s->total_rows) }} baris
            </option>
            @endforeach
        </select>
    </form>

    {{-- Stats cards --}}
    <div style="display:grid; gap:12px;
                grid-template-columns:repeat(auto-fill,minmax(150px,1fr));
                margin-bottom:20px;">

        {{-- Total Karyawan — tidak bisa diklik --}}
        <div style="background:#DCFCE7; border-radius:12px; padding:14px 16px; text-align:center;">
            <div style="font-size:24px; font-weight:800; color:#166534;">{{ number_format($stats['total_karyawan']) }}</div>
            <div style="font-size:11px; color:#166534; font-weight:600; margin-top:2px;">Total Karyawan</div>
        </div>

        {{-- Terdata Session Ini — tidak bisa diklik --}}
        <div style="background:#DCFCE7; border-radius:12px; padding:14px 16px; text-align:center;">
            <div style="font-size:24px; font-weight:800; color:#059669;">{{ number_format($stats['terdata_juni']) }}</div>
            <div style="font-size:11px; color:#059669; font-weight:600; margin-top:2px;">✓ Terdata Session Ini</div>
        </div>

        {{-- Periode Lain — BISA DIKLIK --}}
        <div @click="openModal('other_session',
                     '📅 Karyawan Data Periode Lain',
                     'Ditemukan di payroll periode sebelumnya — mohon pastikan masih aktif')"
             style="background:#DBEAFE; border-radius:12px; padding:14px 16px;
                    text-align:center; cursor:pointer; transition:opacity .15s;"
             @mouseenter="$el.style.opacity='.85'"
             @mouseleave="$el.style.opacity='1'">
            <div style="font-size:24px; font-weight:800; color:#1D4ED8;">{{ number_format($stats['terdata_lain']) }}</div>
            <div style="font-size:11px; color:#1D4ED8; font-weight:600; margin-top:2px;">📅 Periode Lain</div>
        </div>

        {{-- Perlu Verifikasi — BISA DIKLIK --}}
        <div @click="openModal('needs_review',
                     '⚠️ Karyawan Perlu Verifikasi HRD',
                     'Nama di BPJS berbeda dengan nama di payroll — harap konfirmasi')"
             style="background:#FEF9C3; border-radius:12px; padding:14px 16px;
                    text-align:center; cursor:pointer; transition:opacity .15s;"
             @mouseenter="$el.style.opacity='.85'"
             @mouseleave="$el.style.opacity='1'">
            <div style="font-size:24px; font-weight:800; color:#854D0E;">{{ number_format($stats['perlu_verifikasi']) }}</div>
            <div style="font-size:11px; color:#854D0E; font-weight:600; margin-top:2px;">⚠️ Perlu Verifikasi</div>
        </div>

        {{-- Belum di Data Payroll CSV — BISA DIKLIK --}}
        <div @click="openModal('not_found',
                     '✗ Karyawan Belum di Data Payroll CSV',
                     'Kemungkinan staff kantor pusat — payroll tidak di-upload per outlet')"
             style="background:#FEE2E2; border-radius:12px; padding:14px 16px;
                    text-align:center; cursor:pointer; transition:opacity .15s;"
             @mouseenter="$el.style.opacity='.85'"
             @mouseleave="$el.style.opacity='1'">
            <div style="font-size:24px; font-weight:800; color:#DC2626;">{{ number_format($stats['belum_terdata']) }}</div>
            <div style="font-size:11px; color:#DC2626; font-weight:600; margin-top:2px;">✗ Belum di Data Payroll CSV</div>
            <div style="font-size:10px; color:#DC2626; opacity:0.7; margin-top:2px;">(kantor pusat/non-outlet)</div>
        </div>

        {{-- Total Iuran --}}
        <div style="background:#FEF9C3; border-radius:12px; padding:14px 16px; text-align:center;">
            <div style="font-size:14px; font-weight:800; color:#854D0E; line-height:1.3;">
                Rp {{ number_format($stats['total_iuran'], 0, ',', '.') }}
            </div>
            <div style="font-size:11px; color:#854D0E; font-weight:600; margin-top:2px;">Total Iuran</div>
        </div>
    </div>

    {{-- Banner peringatan perlu verifikasi --}}
    @if($stats['perlu_verifikasi'] > 0)
    <div style="background:#FEF9C3; border:1px solid #F59E0B; border-radius:10px;
                padding:12px 16px; margin-bottom:16px; font-size:13px; color:#854D0E;">
        <strong>⚠️ Perhatian untuk HRD &amp; Finance:</strong>
        Terdapat {{ $stats['perlu_verifikasi'] }} karyawan yang namanya di tagihan BPJS
        <em>berbeda</em> dengan data payroll. Mohon verifikasi kesesuaian NIK sebelum
        menagihkan ke outlet. Data ditandai dengan badge <strong>"⚠️ Perlu Cek"</strong>.
        <button @click="openModal('needs_review',
                        '⚠️ Karyawan Perlu Verifikasi HRD',
                        'Nama di BPJS berbeda dengan nama di payroll — harap konfirmasi')"
                style="margin-left:8px; background:#F59E0B; color:white; border:none;
                       padding:3px 10px; border-radius:6px; font-size:12px;
                       font-weight:600; cursor:pointer;">Lihat daftar →</button>
    </div>
    @endif

    {{-- Export button --}}
    <div style="margin-bottom:16px; display:flex; justify-content:flex-end;">
        <a href="{{ route('finance.bpjs-reconciliation.cross-billing.export', ['bill'=>$bill->id,'session_id'=>$sessionId]) }}"
           style="background:#059669; color:white; padding:9px 18px; border-radius:9px;
                  font-size:13px; font-weight:700; text-decoration:none;
                  display:inline-flex; align-items:center; gap:6px;">
            ⬇ Export Excel
        </a>
    </div>

    {{-- Accordion per outlet --}}
    @foreach($summary as $idx => $group)
    <div style="margin-bottom:10px; border-radius:12px;
                border:1.5px solid {{ $group->outlet === 'BELUM TERDATA' ? '#FECACA' : '#E2E8F0' }};
                overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.04);">

        {{-- Outlet header --}}
        <div @click="open === {{ $idx }} ? open = null : open = {{ $idx }}"
             style="background:{{ $group->outlet === 'BELUM TERDATA' ? '#FFF7ED' : 'white' }};
                    padding:14px 20px; cursor:pointer;
                    display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:12px; flex:1; min-width:0;">
                <span style="font-weight:700; font-size:14px;
                             color:{{ $group->outlet === 'BELUM TERDATA' ? '#92400E' : '#1e293b' }};
                             overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    {{ $group->outlet === 'BELUM TERDATA' ? '⚠️ ' : '🏪 ' }}{{ $group->outlet }}
                </span>
                <span style="background:#F3E8FF; color:#7C3AED; padding:3px 10px;
                             border-radius:99px; font-size:12px; font-weight:600; white-space:nowrap;">
                    {{ $group->jumlah_karyawan }} karyawan
                </span>
            </div>
            <div style="display:flex; align-items:center; gap:16px; flex-shrink:0;">
                <span style="font-weight:800; color:#7C3AED; font-size:15px; white-space:nowrap;">
                    Rp {{ number_format($group->total_iuran, 0, ',', '.') }}
                </span>
                <span x-text="open === {{ $idx }} ? '▲' : '▼'"
                      style="color:#94A3B8; font-size:11px; width:12px; text-align:center;"></span>
            </div>
        </div>

        {{-- Collapsible detail --}}
        <div x-show="open === {{ $idx }}" x-transition style="display:none; overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:12px; min-width:700px;">
                <thead>
                    <tr style="background:#F8FAFC; border-bottom:1px solid #E2E8F0;">
                        <th style="padding:8px 12px; text-align:left; color:#64748B; font-weight:600; width:32px;">No</th>
                        <th style="padding:8px 12px; text-align:left; color:#64748B; font-weight:600;">NIK</th>
                        <th style="padding:8px 12px; text-align:left; color:#64748B; font-weight:600;">Nama BPJS</th>
                        <th style="padding:8px 12px; text-align:left; color:#64748B; font-weight:600;">Posisi</th>
                        <th style="padding:8px 12px; text-align:left; color:#64748B; font-weight:600;">Status</th>
                        <th style="padding:8px 12px; text-align:right; color:#64748B; font-weight:600;">Total Iuran</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group->karyawan as $no => $k)
                    @php
                        $matchStatus = $k->match_status ?? 'confirmed';
                        $badgeStyle  = match($matchStatus) {
                            'confirmed'     => 'background:#DCFCE7;color:#166534;',
                            'other_session' => 'background:#DBEAFE;color:#1D4ED8;',
                            'needs_review'  => 'background:#FEF9C3;color:#854D0E;',
                            'not_found'     => 'background:#FEE2E2;color:#991B1B;',
                            default         => 'background:#F1F5F9;color:#64748B;',
                        };
                        $badgeText = match($matchStatus) {
                            'confirmed'     => '✓ Terdata',
                            'other_session' => '📅 Periode Lain',
                            'needs_review'  => '⚠️ Perlu Cek',
                            'not_found'     => '✗ Tidak Ditemukan',
                            default         => '-',
                        };
                    @endphp
                    <tr style="{{ $no % 2 === 0 ? 'background:white;' : 'background:#FAFAFA;' }}
                                border-bottom:1px solid #F1F5F9;">
                        <td style="padding:7px 12px; color:#94A3B8;">{{ $no + 1 }}</td>
                        <td style="padding:7px 12px; font-family:monospace; color:#475569; font-size:11px;">
                            {{ $k->nik }}
                        </td>
                        <td style="padding:7px 12px; font-weight:600; color:#1e293b;">{{ $k->nama_bpjs }}</td>
                        <td style="padding:7px 12px; color:#64748B;">{{ $k->posisi ?? '—' }}</td>
                        <td style="padding:7px 12px;">
                            <span style="{{ $badgeStyle }} padding:2px 8px; border-radius:6px;
                                          font-size:10px; font-weight:600; white-space:nowrap;">
                                {{ $badgeText }}
                            </span>
                            @if(!empty($k->match_note) && $matchStatus === 'needs_review')
                            <div style="font-size:10px; color:#854D0E; margin-top:3px;
                                        font-style:italic; max-width:220px; line-height:1.4;">
                                {{ $k->match_note }}
                            </div>
                            @endif
                        </td>
                        <td style="padding:7px 12px; text-align:right; font-weight:600; color:#7C3AED;">
                            Rp {{ number_format((float)$k->total_iuran, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                    <tr style="background:#F3E8FF;">
                        <td colspan="5" style="padding:8px 12px; font-weight:700; color:#7C3AED;">
                            Subtotal {{ $group->outlet }}
                        </td>
                        <td style="padding:8px 12px; text-align:right; font-weight:800; color:#7C3AED;">
                            Rp {{ number_format($group->total_iuran, 0, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endforeach

    {{-- Grand total footer --}}
    <div style="margin-top:16px; background:#1e293b; border-radius:12px;
                padding:14px 20px; display:flex; justify-content:space-between; align-items:center;">
        <span style="color:white; font-weight:700; font-size:14px;">
            GRAND TOTAL — {{ number_format($stats['total_karyawan']) }} karyawan,
            {{ number_format($stats['total_outlets']) }} outlet
        </span>
        <span style="color:#A78BFA; font-weight:800; font-size:18px;">
            Rp {{ number_format($stats['total_iuran'], 0, ',', '.') }}
        </span>
    </div>

</div>{{-- end x-data crossBillingPage() --}}

<script>
function crossBillingPage() {
    const allEmployees = @json($summary->flatMap(fn($g) => $g->karyawan)->values());

    return {
        // Accordion state (dipindah dari x-data inline)
        open: null,

        // Modal state
        modalOpen:     false,
        modalTitle:    '',
        modalSubtitle: '',
        modalSearch:   '',
        modalFilter:   '',
        modalData:     [],

        init() {
            // Lock body scroll saat modal buka, unlock saat tutup
            this.$watch('modalOpen', val => {
                document.body.style.overflow = val ? 'hidden' : '';
            });
        },

        openModal(status, title, subtitle) {
            this.modalFilter   = status;
            this.modalTitle    = title;
            this.modalSubtitle = subtitle;
            this.modalSearch   = '';
            this.modalData     = allEmployees.filter(e => e.match_status === status);
            this.modalOpen     = true;
        },

        closeModal() {
            this.modalOpen = false;
            // $watch akan otomatis unlock body scroll
        },

        filteredModal() {
            if (!this.modalSearch) return this.modalData;
            const q = this.modalSearch.toLowerCase();
            return this.modalData.filter(e =>
                (e.nama_bpjs || '').toLowerCase().includes(q) ||
                (e.nik || '').toString().includes(q)
            );
        },
    };
}
</script>
@endsection

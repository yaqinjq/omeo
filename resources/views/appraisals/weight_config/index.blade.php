@extends('layouts.app')
@section('title', 'Setting Bobot Appraisal')
@section('content')
<div class="p-4" x-data>

    {{-- HEADER --}}
    <div style="margin-bottom:24px;">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:6px;">
            <a href="{{ route('appraisals.index') }}"
               style="color:#64748B; text-decoration:none; font-size:13px; display:flex; align-items:center; gap:4px;">
                &#8592; Kembali
            </a>
        </div>
        <h1 style="font-size:22px; font-weight:800; color:#1e293b; margin:0;">Pengaturan Bobot Formula Appraisal</h1>
        <p style="color:#64748B; font-size:13px; margin-top:4px;">
            Atur persentase kontribusi setiap komponen dalam perhitungan skor akhir. Total bobot harus selalu 100%.
        </p>
    </div>

    @if(session('success'))
    <div style="background:#DCFCE7; border:1px solid #BBF7D0; border-radius:8px; padding:12px 16px; font-size:13px; color:#166534; margin-bottom:20px;">
        &#10003; {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div style="background:#FEE2E2; border:1px solid #FECACA; border-radius:8px; padding:12px 16px; font-size:13px; color:#991B1B; margin-bottom:20px;">
        @foreach($errors->all() as $err) <div>&#x26A0; {{ $err }}</div> @endforeach
    </div>
    @endif

    {{-- INFO BOX --}}
    <div style="background:#EFF6FF; border-left:4px solid #2563EB; border-radius:10px; padding:14px 18px; margin-bottom:24px; font-size:13px; color:#1e3a8a; line-height:1.7;">
        <b>Cara kerja formula:</b><br>
        Skor akhir = rata-rata tertimbang dari semua komponen yang aktif pada appraisal tersebut.<br>
        Jika suatu komponen tidak diaktifkan pada appraisal tertentu, bobotnya otomatis didistribusikan ke komponen lain yang aktif.<br>
        <b>Setting per-tipe</b> akan override <b>Setting Global</b>. Jika tidak ada setting per-tipe, Setting Global yang digunakan.
    </div>

    {{-- TABS --}}
    @php
        $tabs = ['global' => ['label'=>'Setting Global','icon'=>'🌐','type'=>null,'cfg'=>$global]];
        foreach($typeLabels as $k => $lbl) {
            $tabs[$k] = ['label'=>$lbl,'icon'=>'📋','type'=>$k,'cfg'=>$perType[$k] ?? null];
        }
        $activeTab = request('tab', 'global');
        if(!isset($tabs[$activeTab])) $activeTab = 'global';
    @endphp

    <div style="display:flex; gap:4px; margin-bottom:0; border-bottom:2px solid #E2E8F0;">
        @foreach($tabs as $key => $tab)
        <a href="?tab={{ $key }}"
           style="padding:10px 18px; font-size:13px; font-weight:600; text-decoration:none; border-radius:8px 8px 0 0; border:1.5px solid {{ $activeTab===$key ? '#E2E8F0' : 'transparent' }}; border-bottom:{{ $activeTab===$key ? '2px solid white' : 'none' }}; margin-bottom:{{ $activeTab===$key ? '-2px' : '0' }}; color:{{ $activeTab===$key ? '#1e293b' : '#94A3B8' }}; background:{{ $activeTab===$key ? 'white' : 'transparent' }};">
            {{ $tab['icon'] }} {{ $tab['label'] }}
            @if($key !== 'global' && $tab['cfg'])
            <span style="background:#7C3AED; color:white; font-size:9px; padding:1px 5px; border-radius:99px; margin-left:4px; vertical-align:middle;">custom</span>
            @endif
        </a>
        @endforeach
    </div>

    @php $current = $tabs[$activeTab]; $cfg = $current['cfg']; @endphp

    {{-- TAB CONTENT --}}
    <div style="background:white; border:1.5px solid #E2E8F0; border-top:none; border-radius:0 0 14px 14px; padding:28px;">

        @if($activeTab !== 'global' && !$cfg)
        {{-- Per-type: belum ada custom setting --}}
        <div style="background:#FFFBEB; border:1px solid #FCD34D; border-radius:10px; padding:16px 20px; margin-bottom:24px; font-size:13px; color:#92400E;">
            &#9888; Saat ini menggunakan <b>Setting Global</b> untuk tipe <b>{{ $current['label'] }}</b>.
            Isi form di bawah dan klik <b>Simpan</b> untuk membuat setting khusus tipe ini.
        </div>
        @elseif($activeTab !== 'global' && $cfg)
        <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:10px; padding:12px 20px; margin-bottom:24px; font-size:13px; color:#166534; display:flex; justify-content:space-between; align-items:center;">
            <span>&#10003; Setting <b>custom</b> aktif untuk tipe <b>{{ $current['label'] }}</b>. Override setting global.</span>
            <form method="POST" action="{{ route('appraisals.weight-config.destroy', $cfg->id) }}"
                  onsubmit="return confirm('Hapus setting {{ $current['label'] }}? Akan kembali menggunakan Setting Global.')">
                @csrf @method('DELETE')
                <button type="submit"
                        style="background:#FEE2E2; color:#991B1B; border:1px solid #FECACA; padding:5px 12px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;">
                    &#x2715; Hapus / Gunakan Global
                </button>
            </form>
        </div>
        @endif

        {{-- FORM dengan Alpine.js live preview --}}
        @php
            $initCriteria = $cfg ? (float)$cfg->weight_criteria : 50;
            $initKpi      = $cfg ? (float)$cfg->weight_kpi      : ($activeTab==='global' ? 30 : (float)($global->weight_kpi ?? 30));
            $initTraining = $cfg ? (float)$cfg->weight_training  : ($activeTab==='global' ? 20 : (float)($global->weight_training ?? 20));
            $initSkill    = $cfg ? (float)$cfg->weight_skill     : (float)($global->weight_skill ?? 0);
            $initPosition = $cfg ? (float)$cfg->weight_position  : (float)($global->weight_position ?? 0);
            if($activeTab !== 'global' && !$cfg) {
                $initCriteria = (float)$global->weight_criteria;
                $initKpi      = (float)$global->weight_kpi;
                $initTraining = (float)$global->weight_training;
                $initSkill    = (float)$global->weight_skill;
                $initPosition = (float)$global->weight_position;
            }
        @endphp

        <div x-data="{
            w: {
                criteria: {{ $initCriteria }},
                kpi:      {{ $initKpi }},
                training: {{ $initTraining }},
                skill:    {{ $initSkill }},
                position: {{ $initPosition }}
            },
            get total() {
                return parseFloat((this.w.criteria + this.w.kpi + this.w.training + this.w.skill + this.w.position).toFixed(2));
            },
            get isValid() { return Math.abs(this.total - 100) < 0.01; },
            get formulaParts() {
                const labels = {criteria:'Kriteria Bintang', kpi:'KPI', training:'Training', skill:'Skill', position:'Posisi'};
                return Object.entries(this.w)
                    .filter(([k,v]) => v > 0)
                    .map(([k,v]) => labels[k] + ' ' + v + '%')
                    .join(' + ');
            }
        }">

            <form method="POST" action="{{ route('appraisals.weight-config.save') }}">
                @csrf
                <input type="hidden" name="scope"          value="{{ $activeTab === 'global' ? 'global' : 'per_type' }}">
                <input type="hidden" name="appraisal_type" value="{{ $current['type'] ?? '' }}">

                {{-- KOMPONEN TABLE --}}
                <table style="width:100%; border-collapse:collapse; margin-bottom:24px;">
                    <thead>
                        <tr style="background:#F8FAFC;">
                            <th style="padding:10px 14px; text-align:left; font-size:11px; color:#64748B; font-weight:700; border-bottom:1.5px solid #E2E8F0; width:200px;">KOMPONEN</th>
                            <th style="padding:10px 14px; text-align:left; font-size:11px; color:#64748B; font-weight:700; border-bottom:1.5px solid #E2E8F0;">DESKRIPSI</th>
                            <th style="padding:10px 14px; text-align:center; font-size:11px; color:#64748B; font-weight:700; border-bottom:1.5px solid #E2E8F0; width:130px;">BOBOT (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach([
                            ['key'=>'criteria','label'=>'Kriteria Bintang','icon'=>'⭐','color'=>'#1D4ED8','bg'=>'#DBEAFE',
                             'desc'=>'Rata-rata skor dari semua indikator penilaian (1–5 bintang). Selalu aktif di setiap appraisal.'],
                            ['key'=>'kpi','label'=>'KPI','icon'=>'🎯','color'=>'#059669','bg'=>'#DCFCE7',
                             'desc'=>'Pencapaian target kerja (Key Performance Indicator). Diisi manual oleh evaluator (0–100%).'],
                            ['key'=>'training','label'=>'Hasil Training','icon'=>'📚','color'=>'#7C3AED','bg'=>'#F3E8FF',
                             'desc'=>'Rata-rata nilai PreTest & PostTest materi training yang diselesaikan. Bisa otomatis dari modul Training atau diisi manual.'],
                            ['key'=>'skill','label'=>'Kompetensi Skill','icon'=>'🔧','color'=>'#B45309','bg'=>'#FEF9C3',
                             'desc'=>'Penilaian kompetensi keahlian teknis karyawan. Aktif jika dicentang di form assignment appraisal.'],
                            ['key'=>'position','label'=>'Kompetensi Posisi','icon'=>'🏷','color'=>'#0369A1','bg'=>'#E0F2FE',
                             'desc'=>'Penilaian kesesuaian kompetensi dengan posisi/jabatan. Aktif jika dicentang di form assignment appraisal.'],
                        ] as $idx => $row)
                        <tr style="{{ $idx % 2 === 1 ? 'background:#FAFAFA;' : '' }} border-bottom:1px solid #F1F5F9;">
                            <td style="padding:14px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="background:{{ $row['bg'] }}; color:{{ $row['color'] }}; width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0;">{{ $row['icon'] }}</span>
                                    <span style="font-weight:700; font-size:13px; color:#1e293b;">{{ $row['label'] }}</span>
                                </div>
                            </td>
                            <td style="padding:14px; font-size:12px; color:#64748B; line-height:1.5;">{{ $row['desc'] }}</td>
                            <td style="padding:14px; text-align:center;">
                                <div style="display:flex; align-items:center; gap:6px; justify-content:center;">
                                    <input type="number"
                                           name="weight_{{ $row['key'] }}"
                                           x-model.number="w.{{ $row['key'] }}"
                                           min="0" max="100" step="0.5"
                                           style="width:72px; border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:14px; font-weight:700; text-align:center; outline:none;"
                                           :style="isValid ? '' : 'border-color:#FCA5A5;'"
                                           onfocus="this.style.borderColor='#7C3AED'" onblur="this.style.borderColor=''">
                                    <span style="font-size:14px; font-weight:700; color:#64748B;">%</span>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- LIVE SUMMARY --}}
                <div style="background:#F8FAFC; border-radius:12px; padding:16px 20px; margin-bottom:24px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                        <div>
                            <div style="font-size:11px; font-weight:700; color:#64748B; margin-bottom:4px;">RUMUS AKTIF</div>
                            <div style="font-size:13px; color:#1e293b; font-weight:600;" x-text="formulaParts + ' = ' + total + '%'"></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:11px; font-weight:700; color:#64748B; margin-bottom:4px;">TOTAL BOBOT</div>
                            <div style="font-size:28px; font-weight:900; line-height:1;"
                                 :style="isValid ? 'color:#166534;' : 'color:#991B1B;'"
                                 x-text="total + '%'"></div>
                            <div style="font-size:11px; margin-top:2px;"
                                 :style="isValid ? 'color:#166534;' : 'color:#991B1B;'"
                                 x-text="isValid ? '✓ Valid — siap disimpan' : '✗ Harus tepat 100%'"></div>
                        </div>
                    </div>
                </div>

                {{-- SAVE BUTTON --}}
                <div class="flex items-center gap-4 flex-wrap mt-2">
                    <button type="submit"
                            :disabled="!isValid"
                            :class="!isValid ? 'opacity-40 cursor-not-allowed' : ''"
                            class="btn-primary text-sm px-6 py-2.5">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Simpan Setting {{ $activeTab === 'global' ? 'Global' : $current['label'] }}
                    </button>

                    <span x-show="!isValid" x-cloak class="text-xs text-amber-600">
                        Total bobot harus tepat 100%.
                    </span>

                    @if($cfg)
                    <div class="ml-auto text-right" style="font-size:11px; color:#94A3B8;">
                        <div>Terakhir diubah: <b>{{ $cfg->updated_at?->format('d M Y H:i') }}</b></div>
                        @if($cfg->updatedByUser)
                        <div>oleh <b style="color:#64748B;">{{ $cfg->updatedByUser->name }}</b></div>
                        @endif
                    </div>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- SEMUA SETTING AKTIF (ringkasan) --}}
    <div style="margin-top:28px; background:white; border:1.5px solid #E2E8F0; border-radius:14px; overflow:hidden;">
        <div style="padding:14px 20px; border-bottom:1px solid #F1F5F9;">
            <h3 style="font-size:13px; font-weight:700; color:#1e293b; margin:0;">Ringkasan Setting yang Sedang Aktif</h3>
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:12px;">
            <thead>
                <tr style="background:#F8FAFC;">
                    <th style="padding:10px 14px; text-align:left; color:#64748B; font-weight:600;">Tipe Appraisal</th>
                    <th style="padding:10px 14px; text-align:center; color:#64748B; font-weight:600;">Kriteria</th>
                    <th style="padding:10px 14px; text-align:center; color:#64748B; font-weight:600;">KPI</th>
                    <th style="padding:10px 14px; text-align:center; color:#64748B; font-weight:600;">Training</th>
                    <th style="padding:10px 14px; text-align:center; color:#64748B; font-weight:600;">Skill</th>
                    <th style="padding:10px 14px; text-align:center; color:#64748B; font-weight:600;">Posisi</th>
                    <th style="padding:10px 14px; text-align:left; color:#64748B; font-weight:600;">Rumus</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $summaryRows = [
                        ['label'=>'Global (Fallback)', 'cfg'=>$global, 'badge'=>'global'],
                    ];
                    foreach($typeLabels as $k => $lbl) {
                        $effective = $perType[$k] ?? $global;
                        $summaryRows[] = ['label'=>$lbl, 'cfg'=>$effective, 'badge'=>isset($perType[$k]) ? 'custom' : 'global'];
                    }
                @endphp
                @foreach($summaryRows as $idx => $sr)
                @php $c = $sr['cfg']; @endphp
                <tr style="{{ $idx % 2 === 1 ? 'background:#FAFAFA;' : '' }} border-bottom:1px solid #F1F5F9;">
                    <td style="padding:10px 14px; font-weight:600; color:#1e293b;">
                        {{ $sr['label'] }}
                        <span style="background:{{ $sr['badge']==='custom' ? '#F3E8FF' : '#F1F5F9' }}; color:{{ $sr['badge']==='custom' ? '#7C3AED' : '#64748B' }}; font-size:9px; padding:1px 5px; border-radius:99px; margin-left:4px; font-weight:700;">
                            {{ $sr['badge'] }}
                        </span>
                    </td>
                    @foreach(['weight_criteria','weight_kpi','weight_training','weight_skill','weight_position'] as $wk)
                    <td style="padding:10px 14px; text-align:center;">
                        <span style="font-weight:700; color:{{ (float)$c->$wk > 0 ? '#1e293b' : '#CBD5E1' }};">
                            {{ number_format((float)$c->$wk, 0) }}%
                        </span>
                    </td>
                    @endforeach
                    <td style="padding:10px 14px; color:#475569; font-size:11px;">{{ $c->formulaString() }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection

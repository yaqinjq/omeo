@extends('layouts.app')
@section('title', 'Struktur Organisasi')
@section('content')

<div x-data="orgChart()" class="p-4">

    {{-- HEADER --}}
    <div style="display:flex; justify-content:space-between;
                align-items:center; margin-bottom:24px;
                flex-wrap:wrap; gap:12px;">
        <div>
            <div style="display:flex; align-items:center; gap:10px;">
                <a href="{{ route('positions.index') }}"
                   style="color:#7C3AED; text-decoration:none;
                          font-size:13px; font-weight:500;">
                    ← Master Posisi
                </a>
                <span style="color:#CBD5E1;">|</span>
                <h1 style="font-size:22px; font-weight:800;
                           color:#1e293b; margin:0;">
                    🌳 Struktur Organisasi
                </h1>
            </div>
            <p style="color:#64748B; font-size:13px; margin:4px 0 0 0;">
                Klik posisi manapun untuk melihat daftar karyawan
            </p>
        </div>

        {{-- Summary cards --}}
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @foreach([
                ['label'=>'Departemen', 'value'=>$stats['total_dept'],      'color'=>'#7C3AED', 'bg'=>'#F3E8FF'],
                ['label'=>'Posisi',     'value'=>$stats['total_positions'],  'color'=>'#1D4ED8', 'bg'=>'#DBEAFE'],
                ['label'=>'Terpetakan','value'=>$stats['total_mapped'],     'color'=>'#059669', 'bg'=>'#DCFCE7'],
                ['label'=>'Belum',      'value'=>$stats['total_unmapped'],   'color'=>'#DC2626', 'bg'=>'#FEE2E2'],
            ] as $card)
            <div style="background:{{ $card['bg'] }}; border-radius:12px;
                        padding:10px 16px; text-align:center; min-width:90px;">
                <div style="font-size:22px; font-weight:800; color:{{ $card['color'] }};">
                    {{ number_format($card['value']) }}
                </div>
                <div style="font-size:11px; color:{{ $card['color'] }};
                             font-weight:600; opacity:0.8;">
                    {{ $card['label'] }}
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- DEPARTMENTS — 2-col grid --}}
    <div style="display:grid; gap:16px;
                grid-template-columns:repeat(auto-fill,minmax(min(100%,500px),1fr));
                margin-bottom:20px;">
    @forelse($departments as $dept)
    <div style="border-radius:14px; border:1.5px solid #E2E8F0;
                overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.05);">

        {{-- Dept header: compact --}}
        <div style="background:linear-gradient(135deg,#7C3AED,#5B21B6);
                    color:white; padding:12px 16px;
                    display:flex; justify-content:space-between;
                    align-items:center;">
            <div>
                <div style="font-weight:700; font-size:13px;">{{ $dept->name }}</div>
                @if($dept->code)
                <div style="font-size:10px; opacity:0.65;">{{ $dept->code }}</div>
                @endif
            </div>
            <span style="background:rgba(255,255,255,0.2); padding:2px 10px;
                         border-radius:99px; font-size:11px; font-weight:600;
                         white-space:nowrap;">
                {{ $dept->positions->sum('employees_count') }} karyawan
            </span>
        </div>

        {{-- Position cards: compact flex wrap --}}
        <div style="padding:10px; background:#FAFAFA;
                    display:flex; flex-wrap:wrap; gap:8px;">
            @forelse($dept->positions as $pos)
            <button
                type="button"
                @click="openPanel({
                    id: {{ $pos->id }},
                    name: '{{ addslashes($pos->name) }}',
                    level: {{ $pos->level ?? 0 }},
                    count: {{ $pos->employees_count }},
                    dept: '{{ addslashes($dept->name) }}'
                })"
                style="background:white; border-radius:10px;
                       border:1.5px solid #E2E8F0; padding:10px 14px;
                       cursor:pointer; text-align:left; outline:none;
                       min-width:140px; flex:1; transition:all 0.15s;"
                onmouseover="this.style.borderColor='#7C3AED';
                             this.style.boxShadow='0 3px 10px rgba(124,58,237,0.12)'"
                onmouseout="this.style.borderColor='#E2E8F0';
                            this.style.boxShadow='none'">

                <div style="display:flex; justify-content:space-between;
                            align-items:flex-start; margin-bottom:6px;">
                    <div style="font-weight:700; color:#1e293b; font-size:12px;
                                line-height:1.3; flex:1; padding-right:8px;">
                        {{ $pos->name }}
                    </div>
                    <span style="background:#F3E8FF; color:#7C3AED; padding:1px 6px;
                                 border-radius:99px; font-size:9px; font-weight:700;
                                 flex-shrink:0;">
                        L{{ $pos->level ?? '-' }}
                    </span>
                </div>

                <div style="display:flex; align-items:baseline; gap:3px;">
                    <span style="font-size:20px; font-weight:800;
                                 color:#7C3AED; line-height:1;">
                        {{ $pos->employees_count }}
                    </span>
                    <span style="font-size:10px; color:#94A3B8;">karyawan</span>
                </div>
            </button>
            @empty
            <div style="color:#CBD5E1; font-size:11px;
                        padding:8px; font-style:italic;">
                Belum ada posisi
            </div>
            @endforelse
        </div>
    </div>
    @empty
    <div style="border-radius:16px; padding:48px; text-align:center;
                background:#F8FAFC; border:1.5px dashed #CBD5E1; color:#94A3B8;
                grid-column:1/-1;">
        <div style="font-size:2.5rem; margin-bottom:8px;">🏢</div>
        <p style="font-size:13px; margin:0;">
            Belum ada departemen.
            <a href="{{ route('positions.index') }}" style="color:#7c3aed;">Tambah departemen dulu →</a>
        </p>
    </div>
    @endforelse
    </div>{{-- end 2-col grid --}}

    {{-- Unassigned positions --}}
    @if($unassigned->isNotEmpty())
    <div style="margin-bottom:20px; border-radius:16px;
                border:1.5px solid #FED7AA; overflow:hidden;">
        <div style="background:linear-gradient(135deg,#F59E0B,#D97706);
                    color:white; padding:14px 20px;
                    font-weight:700; font-size:15px;
                    display:flex; justify-content:space-between; align-items:center;">
            <span>⚠️ Belum Terdepartemen</span>
            <span style="background:rgba(255,255,255,0.2); padding:4px 14px;
                         border-radius:99px; font-size:12px; font-weight:700;">
                {{ $unassigned->count() }} posisi
            </span>
        </div>
        <div style="padding:16px; background:#FFFBEB;
                    display:grid; gap:10px;
                    grid-template-columns:repeat(auto-fill, minmax(200px,1fr));">
            @foreach($unassigned as $pos)
            <button type="button"
                @click="openPanel({
                    id: {{ $pos->id }},
                    name: '{{ addslashes($pos->name) }}',
                    level: {{ $pos->level ?? 0 }},
                    count: {{ $pos->employees_count }},
                    dept: 'Belum Terdepartemen'
                })"
                style="background:white; border-radius:12px;
                       border:1.5px solid #FED7AA; padding:16px;
                       cursor:pointer; text-align:left;
                       width:100%; outline:none; transition:border-color 0.15s;"
                onmouseover="this.style.borderColor='#F59E0B'"
                onmouseout="this.style.borderColor='#FED7AA'">
                <div style="font-weight:700; color:#1e293b;
                            font-size:13px; margin-bottom:8px;">
                    {{ $pos->name }}
                </div>
                <div style="display:flex; align-items:baseline; gap:4px;">
                    <span style="font-size:24px; font-weight:800; color:#F59E0B; line-height:1;">
                        {{ $pos->employees_count }}
                    </span>
                    <span style="font-size:11px; color:#94A3B8; font-weight:400;">
                        karyawan
                    </span>
                </div>
            </button>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ═══ SLIDE PANEL ═══ --}}

    {{-- Backdrop --}}
    <div x-show="panelOpen"
         x-transition:enter="transition-opacity duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="panelOpen = false"
         style="position:fixed; inset:0; background:rgba(15,23,42,0.5);
                z-index:9998; backdrop-filter:blur(2px);">
    </div>

    {{-- Panel --}}
    <div x-show="panelOpen"
         x-transition:enter="transition duration-300 ease-out"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition duration-200 ease-in"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="position:fixed; top:0; right:0;
                height:100vh; width:440px; max-width:95vw;
                background:white; z-index:9999;
                display:flex; flex-direction:column;
                overflow:hidden;
                box-shadow:-12px 0 40px rgba(0,0,0,0.2);">

        {{-- Panel header --}}
        <div style="background:linear-gradient(135deg,#7C3AED,#5B21B6);
                    color:white; padding:20px 20px 16px; flex-shrink:0;">

            <div style="display:flex; justify-content:space-between;
                        align-items:flex-start; margin-bottom:12px;">
                <div style="flex:1; padding-right:12px;">
                    <div style="font-size:11px; opacity:0.7; text-transform:uppercase;
                                letter-spacing:0.05em; margin-bottom:4px;"
                         x-text="currentDept">
                    </div>
                    <div style="font-weight:800; font-size:18px; line-height:1.2;"
                         x-text="currentName">
                    </div>
                </div>
                <button @click="panelOpen = false"
                        style="background:rgba(255,255,255,0.15);
                               border:1px solid rgba(255,255,255,0.3);
                               color:white; width:34px; height:34px;
                               border-radius:50%; cursor:pointer;
                               font-size:16px; flex-shrink:0;
                               display:flex; align-items:center;
                               justify-content:center;">
                    ✕
                </button>
            </div>

            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <span style="background:rgba(255,255,255,0.2); padding:4px 12px;
                             border-radius:99px; font-size:12px; font-weight:600;">
                    Level <span x-text="currentLevel"></span>
                </span>
                <span style="background:rgba(255,255,255,0.2); padding:4px 12px;
                             border-radius:99px; font-size:12px; font-weight:600;">
                    <span x-text="panelEmployees.length"></span> karyawan
                </span>
            </div>
        </div>

        {{-- Search --}}
        <div style="padding:12px 16px; background:#F8FAFC;
                    border-bottom:1px solid #E2E8F0; flex-shrink:0;">
            <div style="position:relative;">
                <span style="position:absolute; left:12px; top:50%;
                             transform:translateY(-50%); color:#94A3B8; font-size:14px;">
                    🔍
                </span>
                <input type="text"
                       x-model="panelSearch"
                       placeholder="Cari nama, NIK, atau nomor..."
                       style="width:100%; padding:9px 12px 9px 36px;
                              border:1.5px solid #E2E8F0; border-radius:10px;
                              font-size:13px; outline:none; background:white;
                              box-sizing:border-box; color:#1e293b;"
                       onfocus="this.style.borderColor='#7C3AED'"
                       onblur="this.style.borderColor='#E2E8F0'">
            </div>
            <div style="margin-top:8px; font-size:11px; color:#94A3B8;"
                 x-text="filteredEmployees().length + ' dari ' +
                          panelEmployees.length + ' karyawan ditampilkan'">
            </div>
        </div>

        {{-- List + loading dalam SATU div permanen (flex:1 tidak boleh kena x-show) --}}
        <div style="flex:1; overflow-y:auto; min-height:0;
                    padding:10px 12px; position:relative;
                    overscroll-behavior:contain;
                    -webkit-overflow-scrolling:touch;">

            {{-- Loading overlay: absolute di dalam div list --}}
            <div x-show="panelLoading"
                 style="position:absolute; inset:0; z-index:10;
                        background:white; display:flex;
                        align-items:center; justify-content:center;
                        flex-direction:column; gap:12px;">
                <div style="width:36px; height:36px;
                            border:3px solid #F3E8FF;
                            border-top-color:#7C3AED;
                            border-radius:50%;
                            animation:spin 0.8s linear infinite;">
                </div>
                <span style="font-size:13px; color:#64748B;">
                    Memuat data karyawan…
                </span>
            </div>

            {{-- List karyawan --}}
            <template x-for="emp in filteredEmployees()" :key="emp.id">
                <a :href="emp.profile_url"
                   target="_blank"
                   style="display:flex; align-items:center; gap:12px;
                          padding:10px 12px; border-radius:10px; margin-bottom:6px;
                          text-decoration:none; color:inherit;
                          border:1px solid #F1F5F9; background:white;
                          transition:all 0.1s;"
                   onmouseover="
                       this.style.background='#F9F5FF';
                       this.style.borderColor='#DDD6FE';
                       this.style.transform='translateX(3px)'
                   "
                   onmouseout="
                       this.style.background='white';
                       this.style.borderColor='#F1F5F9';
                       this.style.transform='translateX(0)'
                   ">

                    {{-- Avatar: foto asli atau initial letter --}}
                    <div style="width:38px; height:38px; flex-shrink:0;
                                border-radius:10px; overflow:hidden;
                                background:#7C3AED; position:relative;">

                        {{-- Foto asli (jika ada) --}}
                        <template x-if="emp.photo_url">
                            <img :src="emp.photo_url"
                                 :alt="emp.full_name"
                                 style="width:100%; height:100%;
                                        object-fit:cover; display:block;"
                                 @@error="$el.style.display='none'">
                        </template>

                        {{-- Initial letter (fallback, selalu ada di belakang foto) --}}
                        <div style="position:absolute; inset:0;
                                    display:flex; align-items:center;
                                    justify-content:center;
                                    color:white; font-weight:800;
                                    font-size:15px; z-index:-1;"
                             x-text="emp.full_name.charAt(0).toUpperCase()">
                        </div>
                    </div>

                    {{-- Info karyawan --}}
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:600; color:#1e293b; font-size:13px;
                                    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                             x-text="emp.full_name">
                        </div>
                        <div style="font-size:11px; color:#94A3B8; margin-top:2px;
                                    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                             x-text="(emp.outlet && emp.outlet !== '-'
                                      ? emp.outlet + ' · ' : '')
                                     + emp.employee_number">
                        </div>
                    </div>

                    {{-- Status badge --}}
                    <span :style="
                            emp.status === 'probation'  ? 'background:#FEF9C3;color:#854D0E;' :
                            emp.status === 'permanent'  ? 'background:#DCFCE7;color:#166534;' :
                            emp.status === 'contract'   ? 'background:#DBEAFE;color:#1D4ED8;' :
                                                          'background:#F1F5F9;color:#64748B;'"
                          style="padding:3px 8px; border-radius:6px; font-size:10px;
                                 font-weight:700; text-transform:capitalize;
                                 flex-shrink:0; white-space:nowrap;"
                          x-text="emp.status">
                    </span>

                    <span style="color:#CBD5E1; font-size:12px;
                                 flex-shrink:0; margin-left:4px;">→</span>
                </a>
            </template>

            {{-- Empty: tidak ada hasil search --}}
            <div x-show="!panelLoading &&
                          filteredEmployees().length === 0 &&
                          panelEmployees.length > 0"
                 style="text-align:center; padding:40px 20px; color:#94A3B8;">
                <div style="font-size:36px; margin-bottom:12px;">🔍</div>
                <div style="font-size:13px; font-weight:600;">Tidak ada yang cocok</div>
                <div style="font-size:12px; margin-top:4px;">Coba kata kunci lain</div>
            </div>

            {{-- Empty: posisi belum punya karyawan --}}
            <div x-show="!panelLoading && panelEmployees.length === 0"
                 style="text-align:center; padding:40px 20px; color:#94A3B8;">
                <div style="font-size:36px; margin-bottom:12px;">👥</div>
                <div style="font-size:13px; font-weight:600;">Belum ada karyawan di posisi ini</div>
            </div>

        </div>
    </div>

</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
function orgChart() {
    return {
        panelOpen:      false,
        panelLoading:   false,
        panelSearch:    '',
        panelEmployees: [],
        currentName:    '',
        currentLevel:   '',
        currentDept:    '',

        openPanel(pos) {
            this.panelOpen      = true;
            this.panelLoading   = true;
            this.panelEmployees = [];
            this.panelSearch    = '';
            this.currentName    = pos.name;
            this.currentLevel   = pos.level;
            this.currentDept    = pos.dept;

            fetch(`/positions/${pos.id}/employees`)
                .then(r => r.json())
                .then(data => {
                    this.panelEmployees = data.employees;
                    this.panelLoading   = false;
                })
                .catch(() => { this.panelLoading = false; });
        },

        filteredEmployees() {
            if (!this.panelSearch) return this.panelEmployees;
            const q = this.panelSearch.toLowerCase();
            return this.panelEmployees.filter(e =>
                e.full_name.toLowerCase().includes(q) ||
                e.nik.includes(q) ||
                e.employee_number.toLowerCase().includes(q)
            );
        },
    };
}
</script>
@endsection

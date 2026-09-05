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
            <p style="color:#64748B; font-size:13px; margin:4px 0 0 0;" x-show="!editMode">
                <span x-show="viewMode === 'position'">Klik posisi manapun untuk melihat daftar karyawan</span>
                <span x-show="viewMode === 'person'" x-cloak>Setiap kotak adalah 1 karyawan — foto, nama, dan jabatan</span>
            </p>
            <p style="color:#059669; font-size:13px; margin:4px 0 0 0; font-weight:600;" x-show="editMode" x-cloak>
                <span x-show="viewMode === 'position'">Mode Atur Struktur aktif — drag kotak posisi lalu drop ke posisi lain untuk mengatur "melapor ke"</span>
                <span x-show="viewMode === 'person'">Mode Atur Struktur aktif — drag karyawan lalu drop ke atasannya untuk mengatur "atasan/bawahan"</span>
            </p>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <div style="display:flex; background:#F1F5F9; border-radius:10px; padding:3px; gap:2px;">
                <button type="button" @click="viewMode = 'position'"
                        :style="viewMode === 'position' ? 'background:white;color:#1e293b;box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'background:transparent;color:#64748B;'"
                        style="padding:7px 14px; border-radius:8px; font-size:12.5px; font-weight:600; border:none; cursor:pointer;">
                    🏢 Per Posisi
                </button>
                <button type="button" @click="viewMode = 'person'"
                        :style="viewMode === 'person' ? 'background:white;color:#1e293b;box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'background:transparent;color:#64748B;'"
                        style="padding:7px 14px; border-radius:8px; font-size:12.5px; font-weight:600; border:none; cursor:pointer;">
                    👤 Per Orang
                </button>
            </div>

            <button type="button" @click="editMode = !editMode"
                    :style="editMode
                        ? 'background:#059669;color:white;border:1px solid #059669;'
                        : 'background:#F3E8FF;color:#7C3AED;border:1px solid #DDD6FE;'"
                    style="padding:8px 16px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer;">
                <span x-text="editMode ? '✓ Selesai Atur Struktur' : '🔧 Atur Struktur (Drag & Drop)'"></span>
            </button>

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
    </div>

    <div x-show="viewMode === 'position'">

    {{-- DEPARTMENTS — tree infografis, 1 kartu lebar per departemen --}}
    <div style="display:flex; flex-direction:column; gap:16px; margin-bottom:20px;">
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

        {{-- Tree posisi --}}
        <div style="background:#FAFAFA;">
            @php $tree = $deptTrees[$dept->id]; @endphp
            @if($tree['roots']->isEmpty())
            <div style="color:#CBD5E1; font-size:11px; padding:20px; font-style:italic; text-align:center;">
                Belum ada posisi
            </div>
            @else
            <div class="oc-unparent-zone" x-show="editMode" x-cloak
                 @dragover.prevent="dragOverId = -1"
                 @dragleave="dragOverId = null"
                 @drop.prevent="drop(null)"
                 :style="dragOverId === -1 ? 'border-color:#059669;background:#ECFDF5;color:#059669;' : ''">
                ⬆ Drop di sini untuk jadikan posisi paling atas (tidak melapor ke siapapun)
            </div>
            <div class="oc-dept-tree oc-scroll">
                @foreach($tree['roots'] as $root)
                @include('positions._org_chart_node', [
                    'position'         => $root,
                    'childrenByParent' => $tree['childrenByParent'],
                    'representatives'  => $representatives,
                    'deptName'         => $dept->name,
                ])
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @empty
    <div style="border-radius:16px; padding:48px; text-align:center;
                background:#F8FAFC; border:1.5px dashed #CBD5E1; color:#94A3B8;">
        <div style="font-size:2.5rem; margin-bottom:8px;">🏢</div>
        <p style="font-size:13px; margin:0;">
            Belum ada departemen.
            <a href="{{ route('positions.index') }}" style="color:#7c3aed;">Tambah departemen dulu →</a>
        </p>
    </div>
    @endforelse
    </div>{{-- end department list --}}

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
        <div style="background:#FFFBEB;">
            <div class="oc-unparent-zone" x-show="editMode" x-cloak
                 @dragover.prevent="dragOverId = -1"
                 @dragleave="dragOverId = null"
                 @drop.prevent="drop(null)"
                 :style="dragOverId === -1 ? 'border-color:#059669;background:#ECFDF5;color:#059669;' : ''">
                ⬆ Drop di sini untuk jadikan posisi paling atas (tidak melapor ke siapapun)
            </div>
            <div class="oc-dept-tree oc-scroll">
                @foreach($unassignedTree['roots'] as $root)
                @include('positions._org_chart_node', [
                    'position'         => $root,
                    'childrenByParent' => $unassignedTree['childrenByParent'],
                    'representatives'  => $representatives,
                    'deptName'         => 'Belum Terdepartemen',
                ])
                @endforeach
            </div>
        </div>
    </div>
    @endif

    </div>{{-- end viewMode === 'position' --}}

    <div x-show="viewMode === 'person'" x-cloak>

    {{-- Panel Brand/Outlet — drop karyawan di sini untuk pindah outlet --}}
    <div x-show="editMode" x-cloak style="background:white; border:1.5px solid #DDD6FE; border-radius:14px;
                padding:14px 16px; margin-bottom:16px;">
        <div style="font-size:12px; font-weight:700; color:#7C3AED; text-transform:uppercase;
                    letter-spacing:0.03em; margin-bottom:10px;">
            🏬 Drop karyawan di sini untuk pindah Brand / Outlet
        </div>
        <div style="display:flex; flex-direction:column; gap:10px; max-height:220px; overflow-y:auto;" class="oc-scroll">
            @forelse($brandGroups as $brand => $outlets)
            <div>
                <div style="font-size:11px; font-weight:700; color:#475569; margin-bottom:6px;">{{ $brand }}</div>
                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                    @foreach($outlets as $outlet)
                    <div class="oc-outlet-chip"
                         :class="{ 'oc-outlet-chip-dragover': dragOverOutletId === {{ $outlet->id }} }"
                         @dragover.prevent="dragOverOutletId = {{ $outlet->id }}"
                         @dragleave="dragOverOutletId = null"
                         @drop.prevent="dropOnOutlet({{ $outlet->id }})">
                        {{ $outlet->name }}
                    </div>
                    @endforeach
                </div>
            </div>
            @empty
            <div style="font-size:12px; color:#94A3B8; font-style:italic;">Belum ada data outlet.</div>
            @endforelse
        </div>
    </div>

    {{-- DEPARTMENTS — tree per-orang, hierarki atasan/bawahan lewat manager_id --}}
    <div style="display:flex; flex-direction:column; gap:16px; margin-bottom:20px;">
    @forelse($departments as $dept)
    @php $empTree = $employeeDeptTrees[$dept->id]; @endphp
    @if($empTree['roots']->isNotEmpty())
    <div style="border-radius:14px; border:1.5px solid #E2E8F0;
                overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.05);">

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
                {{ $empTree['roots']->count() + collect($empTree['childrenByParent'])->flatten()->count() }} karyawan
            </span>
        </div>

        <div style="background:#FAFAFA;">
            <div class="oc-unparent-zone" x-show="editMode" x-cloak
                 @dragover.prevent="dragOverId = -1"
                 @dragleave="dragOverId = null"
                 @drop.prevent="drop(null)"
                 :style="dragOverId === -1 ? 'border-color:#059669;background:#ECFDF5;color:#059669;' : ''">
                ⬆ Drop di sini untuk jadikan karyawan ini paling atas (tidak punya atasan)
            </div>
            <div class="oc-dept-tree oc-scroll">
                @foreach($empTree['roots'] as $root)
                @include('positions._org_chart_person_node', [
                    'employee'         => $root,
                    'childrenByParent' => $empTree['childrenByParent'],
                ])
                @endforeach
            </div>
        </div>
    </div>
    @endif
    @empty
    @endforelse
    </div>{{-- end department list per-orang --}}

    {{-- Karyawan belum terdepartemen --}}
    @if($employeeUnassignedTree['roots']->isNotEmpty())
    <div style="margin-bottom:20px; border-radius:16px;
                border:1.5px solid #FED7AA; overflow:hidden;">
        <div style="background:linear-gradient(135deg,#F59E0B,#D97706);
                    color:white; padding:14px 20px;
                    font-weight:700; font-size:15px;
                    display:flex; justify-content:space-between; align-items:center;">
            <span>⚠️ Belum Terdepartemen</span>
        </div>
        <div style="background:#FFFBEB;">
            <div class="oc-unparent-zone" x-show="editMode" x-cloak
                 @dragover.prevent="dragOverId = -1"
                 @dragleave="dragOverId = null"
                 @drop.prevent="drop(null)"
                 :style="dragOverId === -1 ? 'border-color:#059669;background:#ECFDF5;color:#059669;' : ''">
                ⬆ Drop di sini untuk jadikan karyawan ini paling atas (tidak punya atasan)
            </div>
            <div class="oc-dept-tree oc-scroll">
                @foreach($employeeUnassignedTree['roots'] as $root)
                @include('positions._org_chart_person_node', [
                    'employee'         => $root,
                    'childrenByParent' => $employeeUnassignedTree['childrenByParent'],
                ])
                @endforeach
            </div>
        </div>
    </div>
    @endif

    </div>{{-- end viewMode === 'person' --}}

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

        {{-- Job Description --}}
        <div class="oc-scroll" style="padding:14px 16px; background:#FAF5FF;
                    border-bottom:1px solid #E9D5FF; flex-shrink:0;
                    max-height:160px; overflow-y:auto;">
            <div style="font-size:11px; font-weight:700; color:#7C3AED;
                        text-transform:uppercase; letter-spacing:0.05em;
                        margin-bottom:6px;">
                📋 Deskripsi Tugas
            </div>
            <div style="font-size:12.5px; line-height:1.5; color:#4C1D95;
                        white-space:pre-line;"
                 x-show="currentDescription"
                 x-text="currentDescription">
            </div>
            <div style="font-size:12px; color:#A78BFA; font-style:italic;"
                 x-show="!currentDescription">
                Belum ada deskripsi tugas untuk posisi ini.
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
        <div class="oc-scroll" style="flex:1; overflow-y:auto; min-height:0;
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

            {{-- Error state: fetch gagal (bukan sekadar posisi kosong) --}}
            <div x-show="!panelLoading && panelError"
                 style="text-align:center; padding:32px 20px; color:#991B1B;
                        background:#FEF2F2; border:1px solid #FECACA;
                        border-radius:12px; margin:4px;">
                <div style="font-size:32px; margin-bottom:10px;">⚠️</div>
                <div style="font-size:13px; font-weight:700;">Gagal memuat data karyawan</div>
                <div style="font-size:12px; margin-top:4px; color:#B91C1C;" x-text="panelError"></div>
                <button type="button" @click="retryPanel()"
                        style="margin-top:14px; background:#DC2626; color:white; border:none;
                               padding:7px 18px; border-radius:8px; font-size:12px;
                               font-weight:600; cursor:pointer;">
                    Coba lagi
                </button>
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
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="font-weight:600; color:#1e293b; font-size:13px;
                                        white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                                 x-text="emp.full_name">
                            </span>
                            <span x-show="emp.is_leader"
                                  style="background:#FEF3C7; color:#B45309; font-size:9px; font-weight:700;
                                         padding:1px 7px; border-radius:99px; flex-shrink:0; white-space:nowrap;">
                                👑 Leader
                            </span>
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
            <div x-show="!panelLoading && !panelError &&
                          filteredEmployees().length === 0 &&
                          panelEmployees.length > 0"
                 style="text-align:center; padding:40px 20px; color:#94A3B8;">
                <div style="font-size:36px; margin-bottom:12px;">🔍</div>
                <div style="font-size:13px; font-weight:600;">Tidak ada yang cocok</div>
                <div style="font-size:12px; margin-top:4px;">Coba kata kunci lain</div>
            </div>

            {{-- Empty: posisi belum punya karyawan (bukan error) --}}
            <div x-show="!panelLoading && !panelError && panelEmployees.length === 0"
                 style="text-align:center; padding:40px 20px; color:#94A3B8;">
                <div style="font-size:36px; margin-bottom:12px;">👥</div>
                <div style="font-size:13px; font-weight:600;">Belum ada karyawan di posisi ini</div>
            </div>

        </div>
    </div>

</div>

<style>
[x-cloak] { display: none !important; }
@keyframes spin { to { transform: rotate(360deg); } }
.oc-scroll { scrollbar-width: thin; scrollbar-color: #C4B5FD #F3E8FF; }
.oc-scroll::-webkit-scrollbar { width: 8px; }
.oc-scroll::-webkit-scrollbar-track { background: #F3E8FF; }
.oc-scroll::-webkit-scrollbar-thumb { background: #C4B5FD; border-radius: 99px; }
.oc-scroll::-webkit-scrollbar-thumb:hover { background: #A78BFA; }

/* ── Org-chart tree infografis ───────────────────────────────────────── */
.oc-dept-tree {
    display: flex; justify-content: center; gap: 28px;
    padding: 24px 20px; overflow-x: auto;
}
.oc-dept-tree::-webkit-scrollbar { height: 8px; }
.oc-node { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; }
.oc-box {
    position: relative; background: white; border: 2px solid #E2E8F0;
    border-radius: 16px; padding: 16px 14px 12px; width: 150px;
    cursor: pointer; text-align: center; font-family: inherit;
    transition: border-color .15s, box-shadow .15s, transform .15s;
}
.oc-box:hover {
    border-color: #7C3AED; box-shadow: 0 6px 16px rgba(124,58,237,0.18);
    transform: translateY(-2px);
}
.oc-photo {
    width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 8px;
    overflow: hidden; background: linear-gradient(135deg,#7C3AED,#A78BFA);
    display: flex; align-items: center; justify-content: center;
    border: 3px solid #F3E8FF; color: white; font-weight: 800; font-size: 20px;
}
.oc-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
.oc-ribbon {
    font-size: 12.5px; font-weight: 800; color: #1e293b; line-height: 1.25;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden;
}
.oc-posname {
    font-size: 10.5px; color: #7C3AED; font-weight: 600; margin-top: 3px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.oc-badge-level {
    position: absolute; top: -9px; left: 50%; transform: translateX(-50%);
    background: #7C3AED; color: white; font-size: 9.5px; font-weight: 800;
    padding: 2px 9px; border-radius: 99px; border: 2px solid white; white-space: nowrap;
}
.oc-badge-more {
    display: inline-block; margin-top: 6px; background: #F3E8FF; color: #7C3AED;
    font-size: 9.5px; font-weight: 700; padding: 2px 8px; border-radius: 99px;
}
.oc-stem { width: 0; height: 20px; border-left: 2px dashed #C4B5FD; }
.oc-children { display: flex; gap: 20px; position: relative; padding-top: 20px; }
.oc-children::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0;
    border-top: 2px dashed #C4B5FD;
}
.oc-branch { position: relative; }
.oc-branch::before {
    content: ''; position: absolute; top: -20px; left: 50%; width: 0; height: 20px;
    border-left: 2px dashed #C4B5FD; transform: translateX(-1px);
}
.oc-box-dragover {
    border-color: #059669 !important; background: #ECFDF5 !important;
    box-shadow: 0 0 0 3px rgba(5,150,105,0.15) !important;
}
.oc-box-editable { cursor: grab; border-style: dashed; }
.oc-box-editable:active { cursor: grabbing; }
.oc-leader-tag {
    font-size: 9px; font-weight: 700; color: #B45309; background: #FEF3C7;
    border-radius: 99px; padding: 1px 7px; display: inline-block; margin-top: 3px;
}
.oc-unparent-zone {
    margin: 12px 20px 0; padding: 12px; border: 2px dashed #A7F3D0;
    border-radius: 12px; text-align: center; font-size: 11.5px; font-weight: 600;
    color: #10B981; background: #F0FDF4; transition: all .15s;
}
.oc-outlet-label {
    font-size: 10px; color: #0369A1; font-weight: 600; margin-top: 3px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.oc-outlet-chip {
    background: #F0F9FF; border: 1.5px dashed #7DD3FC; color: #0369A1;
    font-size: 11.5px; font-weight: 600; padding: 5px 12px; border-radius: 99px;
    transition: all .15s;
}
.oc-outlet-chip-dragover {
    background: #DBEAFE !important; border-color: #1D4ED8 !important; color: #1D4ED8 !important;
    box-shadow: 0 0 0 3px rgba(29,78,216,0.15);
}
</style>

<script>
function orgChart() {
    return {
        panelOpen:      false,
        panelLoading:   false,
        panelError:     '',
        panelSearch:    '',
        panelEmployees: [],
        currentName:    '',
        currentLevel:   '',
        currentDept:    '',
        currentDescription: '',
        lastPos:        null,

        viewMode:    'position',
        editMode:    false,
        draggedId:   null,
        draggedKind: null,
        dragOverId:  null,
        dragOverOutletId: null,

        openPanel(pos) {
            this.panelOpen      = true;
            this.panelLoading   = true;
            this.panelError     = '';
            this.panelEmployees = [];
            this.panelSearch    = '';
            this.currentName    = pos.name;
            this.currentLevel   = pos.level;
            this.currentDept    = pos.dept;
            this.currentDescription = pos.description || '';
            this.lastPos        = pos;

            fetch(`/positions/${pos.id}/employees`)
                .then(r => {
                    if (! r.ok) throw new Error('Server merespons status ' + r.status);
                    return r.json();
                })
                .then(data => {
                    this.panelEmployees = data.employees;
                    this.panelLoading   = false;
                })
                .catch(err => {
                    this.panelLoading = false;
                    this.panelError   = err.message || 'Gagal memuat data karyawan.';
                    console.error('org-chart: fetch /positions/' + pos.id + '/employees gagal', err);
                });
        },

        retryPanel() {
            if (this.lastPos) this.openPanel(this.lastPos);
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

        dragStart(id, kind) {
            this.draggedId   = id;
            this.draggedKind = kind;
        },

        dragOver(id) {
            if (id !== this.draggedId) this.dragOverId = id;
        },

        dragLeave(id) {
            if (this.dragOverId === id) this.dragOverId = null;
        },

        drop(targetId) {
            const sourceId  = this.draggedId;
            const kind      = this.draggedKind;
            this.dragOverId = null;
            this.draggedId  = null;
            this.draggedKind = null;

            if (!sourceId || sourceId === targetId) return;

            const isPosition = kind === 'position';
            const noun       = isPosition ? 'posisi' : 'karyawan';
            const label      = targetId
                ? `Jadikan ${noun} ini melapor ke ${noun} yang dipilih?`
                : `Jadikan ${noun} ini paling atas (tidak melapor ke siapapun)?`;
            if (!confirm(label)) return;

            const url  = isPosition
                ? `/positions/${sourceId}/set-parent`
                : `/employees/${sourceId}/set-manager`;
            const body = isPosition
                ? { parent_position_id: targetId }
                : { manager_id: targetId };

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(body),
            })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok) { alert(data.message || 'Gagal menyimpan struktur.'); return; }
                    window.location.reload();
                })
                .catch(() => alert('Gagal menyimpan struktur, coba lagi.'));
        },

        dropOnOutlet(outletId) {
            const sourceId    = this.draggedId;
            const kind        = this.draggedKind;
            this.dragOverOutletId = null;
            this.draggedId    = null;
            this.draggedKind  = null;

            if (!sourceId || kind !== 'employee') return;
            if (!confirm('Pindahkan karyawan ini ke outlet yang dipilih?')) return;

            fetch(`/employees/${sourceId}/reassign-outlet`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ outlet_id: outletId }),
            })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok) { alert(data.message || 'Gagal menyimpan outlet.'); return; }
                    window.location.reload();
                })
                .catch(() => alert('Gagal menyimpan outlet, coba lagi.'));
        },
    };
}
</script>
@endsection

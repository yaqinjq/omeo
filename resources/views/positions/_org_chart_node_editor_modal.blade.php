{{--
    Modal "+" builder struktur — dipakai untuk membuat node baru (mode
    create) maupun mengedit node yang sudah ada (mode edit). State &
    metode ada di nodeEditor (nested di dalam orgChart() Alpine component).
--}}
<div x-show="nodeEditor.show" x-cloak
     style="position:fixed; inset:0; z-index:10000; background:rgba(15,23,42,0.5);
            display:flex; align-items:center; justify-content:center; padding:16px;"
     @click.self="nodeEditor.close()">
    <div style="background:white; border-radius:16px; width:100%; max-width:420px;
                max-height:90vh; overflow-y:auto; padding:22px;" class="oc-scroll">

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="font-size:16px; font-weight:800; color:#1e293b; margin:0;"
                x-text="nodeEditor.mode === 'create' ? 'Tambah Node' : 'Edit Node'"></h3>
            <button type="button" @click="nodeEditor.close()"
                    style="background:none; border:none; font-size:18px; color:#94A3B8; cursor:pointer;">✕</button>
        </div>

        <div x-show="nodeEditor.error" x-cloak
             style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B;
                    padding:8px 12px; border-radius:8px; font-size:12.5px; margin-bottom:14px;"
             x-text="nodeEditor.error"></div>

        {{-- Tipe node (hanya bisa dipilih saat membuat baru & belum dikunci —
             dikunci otomatis kalau dibuka lewat tombol "Tambah Anggota") --}}
        <div style="margin-bottom:14px;" x-show="nodeEditor.mode === 'create' && !nodeEditor.lockType" x-cloak>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:6px;">Tipe Node</label>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                <button type="button" @click="nodeEditor.nodeType = 'employee'"
                        :style="nodeEditor.nodeType === 'employee' ? 'background:#F3E8FF;border-color:#7C3AED;color:#7C3AED;' : 'background:white;border-color:#E2E8F0;color:#64748B;'"
                        style="padding:8px; border-radius:8px; border:1.5px solid; font-size:12px; font-weight:600; cursor:pointer;">
                    🙂 Karyawan
                </button>
                <button type="button" @click="nodeEditor.nodeType = 'department'"
                        :style="nodeEditor.nodeType === 'department' ? 'background:#F3E8FF;border-color:#7C3AED;color:#7C3AED;' : 'background:white;border-color:#E2E8F0;color:#64748B;'"
                        style="padding:8px; border-radius:8px; border:1.5px solid; font-size:12px; font-weight:600; cursor:pointer;">
                    🏢 Departemen
                </button>
                <button type="button" @click="nodeEditor.nodeType = 'brand'"
                        :style="nodeEditor.nodeType === 'brand' ? 'background:#F3E8FF;border-color:#7C3AED;color:#7C3AED;' : 'background:white;border-color:#E2E8F0;color:#64748B;'"
                        style="padding:8px; border-radius:8px; border:1.5px solid; font-size:12px; font-weight:600; cursor:pointer;">
                    🏬 Brand
                </button>
                <button type="button" @click="nodeEditor.nodeType = 'outlet'"
                        :style="nodeEditor.nodeType === 'outlet' ? 'background:#F3E8FF;border-color:#7C3AED;color:#7C3AED;' : 'background:white;border-color:#E2E8F0;color:#64748B;'"
                        style="padding:8px; border-radius:8px; border:1.5px solid; font-size:12px; font-weight:600; cursor:pointer;">
                    📍 Outlet
                </button>
            </div>
        </div>

        {{-- ── Field: Karyawan ── --}}
        <div x-show="nodeEditor.nodeType === 'employee'" x-cloak style="display:flex; flex-direction:column; gap:12px;">
            <div>
                <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Pilih Karyawan</label>
                @include('positions._org_chart_search_picker', [
                    'items' => 'window.OC_EMPLOYEES',
                    'model' => 'nodeEditor.employeeId',
                    'placeholder' => 'Cari nama karyawan...',
                ])
            </div>
            <div>
                <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Pilih Posisi (opsional)</label>
                @include('positions._org_chart_search_picker', [
                    'items' => 'window.OC_POSITIONS',
                    'model' => 'nodeEditor.employeePositionId',
                    'placeholder' => 'Cari posisi...',
                ])
                <input type="text" x-model="nodeEditor.newPositionName" placeholder="Atau ketik nama posisi baru"
                       style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:12.5px; margin-top:6px; box-sizing:border-box;">
            </div>
            <div>
                <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Pilih Departemen (opsional)</label>
                @include('positions._org_chart_search_picker', [
                    'items' => 'window.OC_DEPARTMENTS',
                    'model' => 'nodeEditor.employeeDepartmentId',
                    'placeholder' => 'Cari departemen...',
                ])
                <input type="text" x-model="nodeEditor.newEmployeeDeptName" placeholder="Atau ketik nama departemen baru"
                       style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:12.5px; margin-top:6px; box-sizing:border-box;">
            </div>
            <div>
                <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:6px;">Status</label>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <label style="display:flex; align-items:center; gap:5px; font-size:12px; color:#475569;">
                        <input type="radio" value="auto" x-model="nodeEditor.leaderStatus"> Otomatis
                    </label>
                    <label style="display:flex; align-items:center; gap:5px; font-size:12px; color:#475569;">
                        <input type="radio" value="leader" x-model="nodeEditor.leaderStatus"> Leader
                    </label>
                    <label style="display:flex; align-items:center; gap:5px; font-size:12px; color:#475569;">
                        <input type="radio" value="anggota" x-model="nodeEditor.leaderStatus"> Anggota
                    </label>
                </div>
                <p style="font-size:11px; color:#94A3B8; margin-top:4px;">Otomatis = jadi Leader begitu ada bawahan, jadi Anggota kalau belum.</p>
            </div>
        </div>

        {{-- ── Field: Departemen ── --}}
        <div x-show="nodeEditor.nodeType === 'department'" x-cloak>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Pilih Departemen</label>
            @include('positions._org_chart_search_picker', [
                'items' => 'window.OC_DEPARTMENTS',
                'model' => 'nodeEditor.departmentId',
                'placeholder' => 'Cari departemen...',
            ])
            <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin:10px 0 4px;">Atau Buat Departemen Baru</label>
            <input type="text" x-model="nodeEditor.newDepartmentName" placeholder="Nama departemen baru"
                   style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
        </div>

        {{-- ── Field: Brand ── --}}
        <div x-show="nodeEditor.nodeType === 'brand'" x-cloak>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Pilih Brand yang Sudah Ada</label>
            <select x-model="nodeEditor.brandName" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; margin-bottom:8px;">
                <option value="">— Pilih —</option>
                @foreach($existingBrandNames as $brand)
                <option value="{{ $brand }}">{{ $brand }}</option>
                @endforeach
            </select>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Atau Ketik Brand Baru</label>
            <input type="text" x-model="nodeEditor.newBrandName" placeholder="Nama brand baru"
                   style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
        </div>

        {{-- ── Field: Outlet ── --}}
        <div x-show="nodeEditor.nodeType === 'outlet'" x-cloak>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Pilih Outlet</label>
            @include('positions._org_chart_search_picker', [
                'items' => 'window.OC_OUTLETS',
                'model' => 'nodeEditor.outletId',
                'placeholder' => 'Cari outlet...',
            ])
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px;">
            <button type="button" x-show="nodeEditor.mode === 'edit'" @click="nodeEditor.remove()"
                    style="background:#FEF2F2; color:#DC2626; border:1px solid #FECACA; padding:8px 14px;
                           border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">
                Hapus Node
            </button>
            <span x-show="nodeEditor.mode === 'create'"></span>
            <div style="display:flex; gap:8px;">
                <button type="button" @click="nodeEditor.close()"
                        style="background:#F1F5F9; color:#475569; border:none; padding:8px 16px;
                               border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">
                    Batal
                </button>
                <button type="button" @click="nodeEditor.save()" :disabled="nodeEditor.saving"
                        style="background:#7C3AED; color:white; border:none; padding:8px 18px;
                               border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">
                    <span x-text="nodeEditor.saving ? 'Menyimpan…' : 'Simpan'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

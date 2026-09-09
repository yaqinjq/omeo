{{--
    Combobox live-search generik, dipakai berkali-kali di modal node editor
    (Karyawan/Posisi/Departemen/Outlet — semuanya bisa ratusan-ribuan baris,
    <select> polos tidak praktis).
    Variabel yang wajib dikirim lewat @include:
    - $items: nama variabel JS global berisi array {id,name} (mis. 'window.OC_EMPLOYEES')
    - $model: path Alpine ke tempat ID tersimpan (mis. 'nodeEditor.employeeId')
    - $placeholder: teks placeholder input
    - $syncWhen: ekspresi Alpine boolean, kapan label re-sync ke nilai $model
      (mis. 'nodeEditor.show' atau 'show') — beda tiap halaman yang pakai komponen ini
--}}
<div x-data="searchPicker({{ $items }})" x-effect="if ({{ $syncWhen }}) search = labelFor({{ $model }})" style="position:relative;">
    <input type="text" x-model="search"
           @focus="open = true"
           @click.outside="open = false"
           placeholder="{{ $placeholder }}"
           style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
    <div x-show="open" x-cloak
         style="position:absolute; z-index:20; background:white; border:1px solid #E2E8F0;
                border-radius:8px; margin-top:4px; max-height:200px; overflow-y:auto; width:100%;
                box-shadow:0 4px 12px rgba(0,0,0,0.1);">
        <template x-for="item in filtered()" :key="item.id">
            <div @click="{{ $model }} = item.id; search = item.name; open = false"
                 x-text="item.name"
                 style="padding:7px 10px; font-size:12.5px; cursor:pointer;"
                 @mouseover="$el.style.background='#F3E8FF'"
                 @mouseout="$el.style.background='white'"></div>
        </template>
        <div x-show="filtered().length === 0" style="padding:8px 10px; font-size:12px; color:#94A3B8;">
            Tidak ditemukan
        </div>
    </div>
</div>

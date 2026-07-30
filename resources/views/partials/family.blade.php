<section class="space-y-4 border-t border-gray-100 pt-6">
    <div class="flex items-center justify-between">
        <div class="text-xs tracking-widest text-muted uppercase font-bold text-gray-500">
            Data Keluarga (Sesuai Kartu Keluarga)
        </div>
        <button type="button" class="px-3 py-1 bg-blue-50 text-blue-600 rounded text-xs font-semibold hover:bg-blue-100" onclick="addFamilyRow()">
            + Tambah Anggota
        </button>
    </div>

    <div class="overflow-x-auto border rounded-lg border-gray-200">
        <table class="w-full text-sm text-left" id="familyTable">
            <thead class="bg-gray-50 text-gray-600 font-medium">
                <tr>
                    <th class="px-4 py-2">No. KK</th>
                    <th class="px-4 py-2">NIK</th>
                    <th class="px-4 py-2">Nama Lengkap</th>
                    <th class="px-4 py-2">Hubungan</th>
                    <th class="px-4 py-2">Pekerjaan</th>
                    <th class="px-4 py-2 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                {{-- Loop data yang sudah tersimpan --}}
                @php $families = old('families', $profile->families ?? []); @endphp
                
                @forelse($families as $index => $family)
                <tr>
                    <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[{{ $index }}][kk_no]" value="{{ $family['kk_no'] ?? '' }}" placeholder="No. KK"></td>
                    <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[{{ $index }}][nik]" value="{{ $family['nik'] ?? '' }}" placeholder="NIK"></td>
                    <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[{{ $index }}][name]" value="{{ $family['name'] ?? '' }}" placeholder="Nama"></td>
                    <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[{{ $index }}][relation]" value="{{ $family['relation'] ?? '' }}" placeholder="Istri/Anak"></td>
                    <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[{{ $index }}][job]" value="{{ $family['job'] ?? '' }}" placeholder="Pekerjaan"></td>
                    <td class="p-2 text-center">
                        <button type="button" class="text-red-500 hover:text-red-700 text-xs" onclick="this.closest('tr').remove()">Hapus</button>
                    </td>
                </tr>
                @empty
                {{-- Tampilan Default (Baris Kosong Pertama) --}}
                <tr>
                    <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[0][kk_no]" placeholder="No. KK"></td>
                    <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[0][nik]" placeholder="NIK"></td>
                    <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[0][name]" placeholder="Nama"></td>
                    <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[0][relation]" placeholder="Hubungan"></td>
                    <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[0][job]" placeholder="Pekerjaan"></td>
                    <td class="p-2 text-center">
                        <button type="button" class="text-red-500 hover:text-red-700 text-xs" onclick="this.closest('tr').remove()">Hapus</button>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

{{-- Script Javascript Sederhana untuk Tambah Baris --}}
<script>
    function addFamilyRow() {
        // Hitung index baru berdasarkan jumlah baris saat ini
        const table = document.getElementById('familyTable').getElementsByTagName('tbody')[0];
        const rowCount = table.rows.length; 
        const newRow = table.insertRow(rowCount);
        
        // Buat index unik (timestamp) agar tidak bentrok
        const idx = Date.now(); 

        newRow.innerHTML = `
            <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[${idx}][kk_no]" placeholder="No. KK"></td>
            <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[${idx}][nik]" placeholder="NIK"></td>
            <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[${idx}][name]" placeholder="Nama"></td>
            <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[${idx}][relation]" placeholder="Hubungan"></td>
            <td class="p-2"><input class="w-full border-gray-300 rounded text-sm" name="families[${idx}][job]" placeholder="Pekerjaan"></td>
            <td class="p-2 text-center">
                <button type="button" class="text-red-500 hover:text-red-700 text-xs" onclick="this.closest('tr').remove()">Hapus</button>
            </td>
        `;
    }
</script>
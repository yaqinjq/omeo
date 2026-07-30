@php
    $signatories = old('signatories', $template->signatories_json ?? [
        ['role' => 'Mengetahui, HRD', 'name' => '{{hrd_name}}'],
        ['role' => 'Daily Worker', 'name' => '{{candidate_name}}'],
    ]);
@endphp

@if ($errors->any())
    <div class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
        <div class="font-semibold mb-1">Terjadi kesalahan:</div>
        <ul class="list-disc ml-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4" x-data="contractTemplateBuilder()" x-init="init()" @input.debounce.100ms="refreshPreview()" @change="refreshPreview()">
    <div class="space-y-4 xl:col-span-2">
        <div class="rounded-lg border border-sky-200 bg-sky-50 p-4" data-tour-id="intro">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-sky-900">Panduan Pengisian Template</h2>
                    <p class="text-sm text-sky-800 mt-1">Ikuti urutan section 1-5, lalu simpan. Klik tombol tour jika butuh panduan langkah demi langkah.</p>
                </div>
                <button id="startTourButton" type="button" class="px-3 py-2 rounded bg-sky-700 text-white text-sm hover:bg-sky-800">Mulai Tour Guide</button>
            </div>
        </div>

        <section class="rounded-lg border p-4 bg-slate-50/60" data-tour-id="identity">
            <h2 class="font-semibold mb-3">1) Identitas Template</h2>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium">Nama Template <span class="text-red-600">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $template->name) }}" class="mt-1 w-full border rounded px-3 py-2" placeholder="Contoh: Kontrak Daily Worker Outlet A" required>
                </div>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" class="rounded" @checked(old('is_active', $template->is_active))>
                    <span>Template aktif</span>
                </label>
            </div>
        </section>

        <section class="rounded-lg border p-4 bg-slate-50/60 space-y-3" data-tour-id="letterhead">
            <h2 class="font-semibold">2) Kop Surat</h2>

            <div>
                <label class="block text-sm font-medium">Logo Kop (opsional)</label>
                <input type="file" name="logo_file" accept=".png,.jpg,.jpeg,.webp" class="mt-1 w-full border rounded px-3 py-2 bg-white">
                @if(!empty($template->logo_path))
                    <img src="{{ asset('storage/'.$template->logo_path) }}" alt="logo" class="mt-2 h-16 border rounded p-1 bg-white">
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium">Konten Kop Header</label>
                <textarea name="letterhead_html" rows="3" class="mt-1 w-full border rounded px-3 py-2" data-placeholder-target placeholder="PT Contoh Indonesia&#10;Jl. Mawar No. 123, Bandung&#10;Telp: 08xxxxxxxxxx">{{ old('letterhead_html', $template->letterhead_html) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium">Judul Dokumen <span class="text-red-600">*</span></label>
                <input type="text" name="document_title" value="{{ old('document_title', $template->document_title ?: 'SURAT PERJANJIAN KERJA DAILY WORKER') }}" class="mt-1 w-full border rounded px-3 py-2" required>
            </div>
        </section>

        <section class="rounded-lg border p-4 bg-slate-50/60 space-y-3" data-tour-id="content">
            <h2 class="font-semibold">3) Konten Kontrak</h2>
            <p class="text-xs text-slate-500">Klik placeholder untuk menyisipkan data otomatis ke area yang sedang aktif.</p>

            <div class="flex flex-wrap gap-2">
                @foreach($requiredPlaceholders as $placeholder)
                    <button type="button" class="px-2 py-1 rounded border text-xs bg-white hover:bg-slate-100" @click="insertPlaceholder(@js($placeholder))">{{ $placeholder }}</button>
                @endforeach
            </div>

            <div>
                <label class="block text-sm font-medium">Paragraf Pembuka</label>
                <textarea name="opening_paragraph" rows="4" class="mt-1 w-full border rounded px-3 py-2" data-placeholder-target placeholder="Pada hari ini, @{{today_date}}, ...">{{ old('opening_paragraph', $template->opening_paragraph) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium">Isi Utama Kontrak <span class="text-red-600">*</span></label>
                <textarea name="main_content" rows="10" class="mt-1 w-full border rounded px-3 py-2" data-placeholder-target required>{{ old('main_content', $template->main_content ?: $template->body_html) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium">Paragraf Penutup</label>
                <textarea name="closing_paragraph" rows="4" class="mt-1 w-full border rounded px-3 py-2" data-placeholder-target>{{ old('closing_paragraph', $template->closing_paragraph) }}</textarea>
            </div>
        </section>

        <section class="rounded-lg border p-4 bg-slate-50/60 space-y-3" data-tour-id="signatories">
            <h2 class="font-semibold">4) Penandatangan</h2>
            <p class="text-xs text-slate-500">Isi pihak yang menandatangani. Nama bisa placeholder (contoh: @{{candidate_name}}).</p>

            <div id="signatoriesList" class="space-y-2">
                @foreach($signatories as $index => $row)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 signatory-row">
                        <input type="text" name="signatories[{{ $index }}][role]" value="{{ data_get($row, 'role') }}" class="border rounded px-3 py-2" placeholder="Peran / Jabatan penandatangan">
                        <div class="flex gap-2">
                            <input type="text" name="signatories[{{ $index }}][name]" value="{{ data_get($row, 'name') }}" class="w-full border rounded px-3 py-2" data-placeholder-target placeholder="Nama penandatangan (boleh placeholder)">
                            <button type="button" class="px-3 py-2 border rounded text-red-600" @click="removeSignatory($event)">Hapus</button>
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="button" class="px-3 py-2 rounded border bg-white" @click="addSignatory()">+ Tambah Penandatangan</button>
        </section>

        <section class="rounded-lg border p-4 bg-amber-50 border-amber-200 text-xs text-amber-900">
            <div class="font-semibold mb-1">Data Otomatis yang Tersedia</div>
            <div>
                Nama kandidat, NIK, email, telepon, alamat, posisi, outlet, nomor kontrak, tanggal cetak, nama HRD. Klik tombol placeholder untuk menyisipkan otomatis.
            </div>
        </section>

        <section class="rounded-lg border p-4 bg-slate-50/60" data-tour-id="numbering">
            <h2 class="font-semibold mb-3">5) Penomoran Kontrak</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-medium">Prefix</label>
                    <input type="text" name="numbering_prefix" value="{{ old('numbering_prefix', $template->numbering_prefix) }}" class="mt-1 w-full border rounded px-3 py-2" placeholder="DW/OMEO/">
                </div>
                <div>
                    <label class="block text-sm font-medium">Format</label>
                    <input type="text" name="numbering_format" value="{{ old('numbering_format', $template->numbering_format) }}" class="mt-1 w-full border rounded px-3 py-2" placeholder="{prefix}{YYYY}{MM}{SEQ4}">
                </div>
                <div>
                    <label class="block text-sm font-medium">Nomor Selanjutnya</label>
                    <input type="number" min="1" name="next_sequence" value="{{ old('next_sequence', $template->next_sequence ?? 1) }}" class="mt-1 w-full border rounded px-3 py-2">
                </div>
            </div>
        </section>

        <div class="flex gap-2" data-tour-id="save">
            <button class="px-4 py-2 rounded bg-slate-900 text-white">Simpan</button>
            <a href="{{ route('hrd.contract-templates.index') }}" class="px-4 py-2 rounded border">Batal</a>
        </div>
    </div>

    <aside class="xl:col-span-1">
        <div class="xl:sticky xl:top-4 rounded-lg border bg-white p-4">
            <h2 class="font-semibold">Preview Dokumen</h2>
            <p class="text-xs text-slate-500 mt-1">Preview mengikuti data builder saat ini (belum disimpan).</p>

            <div class="mt-3 rounded border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700" data-tour-id="help-panel">
                <div class="font-semibold text-slate-800 mb-2">Tata Cara Cepat</div>
                <ol class="list-decimal ml-4 space-y-1">
                    <li>Isi Identitas Template.</li>
                    <li>Lengkapi Kop Surat dan Judul.</li>
                    <li>Tulis isi kontrak, gunakan tombol placeholder.</li>
                    <li>Atur penandatangan dan penomoran.</li>
                    <li>Cek preview, lalu klik Simpan.</li>
                </ol>
            </div>

            <div class="mt-4 text-sm leading-6">
                <div class="text-center" x-show="preview.letterhead" x-html="formatText(preview.letterhead)"></div>
                <hr class="my-3" x-show="preview.letterhead">
                <h3 class="text-center font-semibold text-base" x-text="preview.documentTitle || 'SURAT PERJANJIAN KERJA DAILY WORKER'"></h3>
                <p class="mt-3" x-show="preview.openingParagraph" x-html="formatText(preview.openingParagraph)"></p>
                <div class="mt-3" x-html="formatText(preview.mainContent)"></div>
                <p class="mt-3" x-show="preview.closingParagraph" x-html="formatText(preview.closingParagraph)"></p>

                <div class="grid grid-cols-1 gap-4 mt-6 sm:grid-cols-2">
                    <template x-for="(row, index) in preview.signatories" :key="index">
                        <div class="text-center">
                            <div class="font-semibold" x-text="row.role || '-'"></div>
                            <div class="h-16"></div>
                            <div class="font-semibold" x-html="formatText(row.name || '-')"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </aside>
</div>

<div id="contractTemplateTourOverlay" class="fixed inset-0 z-50 hidden">
    <div id="contractTemplateTourBackdrop" class="absolute inset-0 bg-slate-950/60"></div>
    <div class="absolute inset-x-4 top-6 mx-auto max-w-lg rounded-lg bg-white p-4 shadow-xl z-10">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p id="tourStepText" class="text-xs font-medium text-slate-500">Langkah 1 dari 1</p>
                <h3 id="tourTitleText" class="text-base font-semibold text-slate-900">Tour Guide</h3>
            </div>
            <button id="tourCloseButton" type="button" class="text-slate-500 hover:text-slate-700">Tutup</button>
        </div>
        <p id="tourDescriptionText" class="mt-2 text-sm text-slate-700"></p>
        <div class="mt-4 flex items-center justify-between">
            <button id="tourPrevButton" type="button" class="px-3 py-2 rounded border text-sm">Sebelumnya</button>
            <button id="tourNextButton" type="button" class="px-3 py-2 rounded bg-slate-900 text-white text-sm">Lanjut</button>
        </div>
    </div>
</div>

<script>
function contractTemplateBuilder() {
    return {
        preview: {
            letterhead: '',
            documentTitle: '',
            openingParagraph: '',
            mainContent: '',
            closingParagraph: '',
            signatories: [],
        },
        signatoryIndex: {{ count($signatories) }},
        init() {
            this.refreshPreview();
        },
        insertPlaceholder(token) {
            const active = document.activeElement;
            if (!active || !active.matches('textarea[data-placeholder-target], input[data-placeholder-target]')) {
                return;
            }

            const start = active.selectionStart ?? active.value.length;
            const end = active.selectionEnd ?? active.value.length;
            active.value = active.value.substring(0, start) + token + active.value.substring(end);
            active.focus();
            const cursor = start + token.length;
            if (active.setSelectionRange) {
                active.setSelectionRange(cursor, cursor);
            }
            this.refreshPreview();
        },
        addSignatory() {
            const list = document.getElementById('signatoriesList');
            if (!list) return;

            const idx = this.signatoryIndex++;
            const row = document.createElement('div');
            row.className = 'grid grid-cols-1 md:grid-cols-2 gap-2 signatory-row';
            row.innerHTML = `
                <input type="text" name="signatories[${idx}][role]" class="border rounded px-3 py-2" placeholder="Peran / Jabatan penandatangan">
                <div class="flex gap-2">
                    <input type="text" name="signatories[${idx}][name]" class="w-full border rounded px-3 py-2" data-placeholder-target placeholder="Nama penandatangan (boleh placeholder)">
                    <button type="button" class="px-3 py-2 border rounded text-red-600" onclick="this.closest('.signatory-row').remove()">Hapus</button>
                </div>
            `;
            list.appendChild(row);
            this.refreshPreview();
        },
        removeSignatory(event) {
            event.target.closest('.signatory-row')?.remove();
            this.refreshPreview();
        },
        refreshPreview() {
            const root = this.$root;
            this.preview.letterhead = this.getFieldValue(root, 'letterhead_html');
            this.preview.documentTitle = this.getFieldValue(root, 'document_title');
            this.preview.openingParagraph = this.getFieldValue(root, 'opening_paragraph');
            this.preview.mainContent = this.getFieldValue(root, 'main_content');
            this.preview.closingParagraph = this.getFieldValue(root, 'closing_paragraph');
            this.preview.signatories = Array.from(root.querySelectorAll('.signatory-row'))
                .map((row) => ({
                    role: row.querySelector('input[name*="[role]"]')?.value?.trim() || '',
                    name: row.querySelector('input[name*="[name]"]')?.value?.trim() || '',
                }))
                .filter((row) => row.role !== '' || row.name !== '');
        },
        getFieldValue(root, name) {
            return root.querySelector(`[name="${name}"]`)?.value || '';
        },
        formatText(value) {
            const escaped = this.escapeHtml(String(value || ''));
            return escaped
                .replace(/\{\{[a-zA-Z0-9_]+\}\}/g, '<span class="rounded bg-amber-100 px-1 text-amber-900">$&</span>')
                .replace(/\n/g, '<br>');
        },
        escapeHtml(value) {
            return value
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },
    }
}

(function initContractTemplateTour() {
    const steps = [
        { target: 'intro', title: 'Panduan Umum', description: 'Gunakan tombol Mulai Tour Guide kapan saja untuk melihat alur pengisian dari awal sampai simpan.' },
        { target: 'identity', title: 'Identitas Template', description: 'Isi nama template yang mudah dikenali HRD dan aktifkan jika ingin langsung digunakan.' },
        { target: 'letterhead', title: 'Kop Surat', description: 'Masukkan logo (opsional), isi header perusahaan, lalu cek judul dokumen.' },
        { target: 'content', title: 'Konten Kontrak', description: 'Tulis paragraf pembuka, isi utama, dan penutup. Gunakan tombol placeholder seperti @{{candidate_name}} agar data kandidat bisa terisi otomatis saat generate kontrak.' },
        { target: 'signatories', title: 'Penandatangan', description: 'Isi role dan nama penandatangan. Nama bisa placeholder seperti @{{hrd_name}} atau @{{candidate_name}}.' },
        { target: 'numbering', title: 'Penomoran', description: 'Atur prefix, format nomor, dan angka urut berikutnya agar nomor kontrak konsisten.' },
        { target: 'help-panel', title: 'Preview dan Ringkasan', description: 'Panel kanan menampilkan preview realtime serta ringkasan tata cara cepat.' },
        { target: 'save', title: 'Simpan Template', description: 'Setelah semua sesuai, klik Simpan untuk menyimpan template ke sistem.' },
    ];

    function setup() {
        const overlay = document.getElementById('contractTemplateTourOverlay');
        const startButton = document.getElementById('startTourButton');
        const closeButton = document.getElementById('tourCloseButton');
        const prevButton = document.getElementById('tourPrevButton');
        const nextButton = document.getElementById('tourNextButton');
        const backdrop = document.getElementById('contractTemplateTourBackdrop');
        const titleText = document.getElementById('tourTitleText');
        const descText = document.getElementById('tourDescriptionText');
        const stepText = document.getElementById('tourStepText');

        if (!overlay || !startButton || !closeButton || !prevButton || !nextButton || !titleText || !descText || !stepText) {
            return;
        }

        let currentIndex = 0;

        const clearHighlights = () => {
            document.querySelectorAll('[data-tour-id]').forEach((node) => {
                node.classList.remove('ring-2', 'ring-sky-400', 'ring-offset-2');
            });
        };

        const render = () => {
            const step = steps[currentIndex];
            if (!step) {
                return;
            }

            titleText.textContent = step.title;
            descText.textContent = step.description;
            stepText.textContent = `Langkah ${currentIndex + 1} dari ${steps.length}`;
            prevButton.disabled = currentIndex === 0;
            prevButton.classList.toggle('opacity-50', currentIndex === 0);
            prevButton.classList.toggle('cursor-not-allowed', currentIndex === 0);
            nextButton.textContent = currentIndex + 1 >= steps.length ? 'Selesai' : 'Lanjut';

            clearHighlights();
            const target = document.querySelector(`[data-tour-id="${step.target}"]`);
            if (target) {
                target.classList.add('ring-2', 'ring-sky-400', 'ring-offset-2');
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        };

        const open = () => {
            overlay.classList.remove('hidden');
            currentIndex = 0;
            render();
        };

        const close = () => {
            overlay.classList.add('hidden');
            clearHighlights();
            try {
                localStorage.setItem('dw_contract_template_tour_seen', '1');
            } catch (error) {
                // noop
            }
        };

        const next = () => {
            if (currentIndex + 1 >= steps.length) {
                close();
                return;
            }

            currentIndex += 1;
            render();
        };

        const prev = () => {
            if (currentIndex === 0) {
                return;
            }

            currentIndex -= 1;
            render();
        };

        startButton.addEventListener('click', open);
        closeButton.addEventListener('click', close);
        nextButton.addEventListener('click', next);
        prevButton.addEventListener('click', prev);

        if (backdrop) {
            backdrop.addEventListener('click', close);
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !overlay.classList.contains('hidden')) {
                close();
            }
        });

        window.dwContractTour = { open, close, next, prev };

        try {
            if (!localStorage.getItem('dw_contract_template_tour_seen')) {
                open();
            }
        } catch (error) {
            open();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();
</script>

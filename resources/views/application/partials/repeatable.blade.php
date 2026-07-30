@php
  // expected: $title, $name, $items (array of associative arrays), $fields
  $items = is_array($items ?? null) ? $items : [];
  $fields = is_array($fields ?? null) ? $fields : [];

  // normalize numeric keys
  $items = array_values($items);
@endphp

<section class="card p-4 space-y-3" data-repeatable="{{ $name }}">
  <div class="flex items-center justify-between gap-3">
    <div>
      <div class="text-sm text-muted uppercase tracking-wider">{{ $title }}</div>
      <div class="text-xs text-muted">Bisa ditambahkan lebih dari satu.</div>
    </div>
    <button type="button" class="btn-ghost" data-add>
      + Tambah
    </button>
  </div>

  <div class="space-y-3" data-list>
    @forelse($items as $i => $row)
      <div class="rounded-xl ring-1 ring-white/10 bg-white/5 p-3">
        <div class="grid grid-cols-12 gap-3">
          @foreach($fields as $f)
            @php
              $k = $f['key'];
              $label = $f['label'] ?? $k;
              $ph = $f['placeholder'] ?? '';
              $val = is_array($row) ? ($row[$k] ?? '') : '';
            @endphp
            <div class="col-span-12 md:col-span-{{ count($fields) >= 3 ? 4 : (count($fields) == 2 ? 6 : 12) }}">
              <label class="text-sm text-muted">{{ $label }}</label>
              <input class="input w-full" name="{{ $name }}[{{ $i }}][{{ $k }}]" value="{{ old($name.'.'.$i.'.'.$k, $val) }}" placeholder="{{ $ph }}">
            </div>
          @endforeach
        </div>

        <div class="pt-2 flex justify-end">
          <button type="button" class="btn-danger" data-remove>Hapus</button>
        </div>
      </div>
    @empty
      <div class="text-sm text-muted" data-empty>Belum ada data.</div>
    @endforelse
  </div>

  {{-- template row (for JS add) --}}
  <template data-template>
    <div class="rounded-xl ring-1 ring-white/10 bg-white/5 p-3">
      <div class="grid grid-cols-12 gap-3">
        @foreach($fields as $f)
          @php
            $k = $f['key'];
            $label = $f['label'] ?? $k;
            $ph = $f['placeholder'] ?? '';
          @endphp
          <div class="col-span-12 md:col-span-{{ count($fields) >= 3 ? 4 : (count($fields) == 2 ? 6 : 12) }}">
            <label class="text-sm text-muted">{{ $label }}</label>
            <input class="input w-full" name="__NAME__[__IDX__][{{ $k }}]" value="" placeholder="{{ $ph }}">
          </div>
        @endforeach
      </div>

      <div class="pt-2 flex justify-end">
        <button type="button" class="btn-danger" data-remove>Hapus</button>
      </div>
    </div>
  </template>
</section>

@once
  @push('scripts')
    <script>
      (function () {
        const sections = document.querySelectorAll('[data-repeatable]');
        sections.forEach(section => {
          const name = section.getAttribute('data-repeatable');
          const list = section.querySelector('[data-list]');
          const addBtn = section.querySelector('[data-add]');
          const tpl = section.querySelector('template[data-template]');

          const currentCount = () => list.querySelectorAll(':scope > div').length;

          const reindex = () => {
            // keep indices stable after remove to match Laravel array
            const rows = list.querySelectorAll(':scope > div');
            rows.forEach((row, idx) => {
              row.querySelectorAll('input[name]').forEach(inp => {
                // name like educations[3][school]
                inp.name = inp.name
                  .replace(new RegExp('^' + name + '\\[\\d+\\]'), name + '[' + idx + ']');
              });
            });
          };

          const addRow = () => {
            const idx = currentCount();
            const html = tpl.innerHTML
              .replaceAll('__NAME__', name)
              .replaceAll('__IDX__', String(idx));

            const wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();

            // remove empty label if any
            const empty = list.querySelector('[data-empty]');
            if (empty) empty.remove();

            list.appendChild(wrapper.firstElementChild);
          };

          addBtn?.addEventListener('click', addRow);

          section.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-remove]');
            if (!btn) return;
            const row = btn.closest(':scope [data-list] > div');
            if (row) row.remove();
            reindex();

            if (currentCount() === 0) {
              const empty = document.createElement('div');
              empty.className = 'text-sm text-muted';
              empty.setAttribute('data-empty', '');
              empty.textContent = 'Belum ada data.';
              list.appendChild(empty);
            }
          });
        });
      })();
    </script>
  @endpush
@endonce

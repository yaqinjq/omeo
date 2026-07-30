@extends('layouts.app')

@section('content')
@php
  $activeTab = $tab ?? 'new';
  $tabCounts = $counts ?? ['new' => 0, 'active' => 0, 'tested' => 0, 'done' => 0, 'rejected' => 0, 'blocked' => 0];
  $sortValue = $sort ?? request('sort', 'latest_test');
  $testTypeValue = $testType ?? request('test_type', 'all');
  $isTestedTab = $activeTab === 'tested';
  $isNewTab = $activeTab === 'new';
  $isHrdSide = in_array(auth()->user()->role ?? '', ['admin','hrd'], true);
  $departmentGroups = collect($formGroups ?? collect());
  $availableForms = $departmentGroups
      ->flatMap(fn ($group) => collect($group['types'] ?? [])->flatMap(fn ($typeGroup) => collect($typeGroup['forms'] ?? [])))
      ->values();
  $testTypeOptions = $testTypeOptions ?? ['all' => 'Semua Test'];
  $assignmentStatusClasses = [
    \App\Models\FormAssignment::STATUS_LOCKED => 'bg-slate-100 text-slate-700 border-slate-200',
    \App\Models\FormAssignment::STATUS_OPENED => 'bg-blue-50 text-blue-700 border-blue-200',
    \App\Models\FormAssignment::STATUS_SUBMITTED => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    \App\Models\FormAssignment::STATUS_EXPIRED => 'bg-amber-50 text-amber-700 border-amber-200',
  ];
  $typeBadgeClasses = [
    \App\Models\AssessmentForm::TYPE_IQ => 'bg-blue-100 text-blue-700',
    \App\Models\AssessmentForm::TYPE_DISC => 'bg-purple-100 text-purple-700',
    \App\Models\AssessmentForm::TYPE_TIU => 'bg-emerald-100 text-emerald-700',
    \App\Models\AssessmentForm::TYPE_DIFERENSIAL => 'bg-amber-100 text-amber-700',
    \App\Models\AssessmentForm::TYPE_FAT => 'bg-rose-100 text-rose-700',
    \App\Models\AssessmentForm::TYPE_CUSTOM => 'bg-slate-100 text-slate-700',
  ];
@endphp

<div class="bg-white border rounded-lg p-4 space-y-4">
  <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
    <div>
      <div class="text-lg font-semibold">Recruitment Kandidat</div>
      <div class="text-xs text-slate-500">Governance recruitment, aktivasi test dinamis dari Form Dinamis, dan keputusan final HRD.</div>
    </div>
    <div class="flex flex-col gap-2 md:flex-row md:flex-wrap md:items-center">
      @if($isHrdSide && $activeTab === 'done')
        <a class="px-3 py-2 rounded border text-sm text-center hover:bg-slate-50" href="{{ route('hrd.passed-candidates.index') }}">Shortcut Kirim Kontrak Kandidat Accepted</a>
      @endif
      <a class="px-3 py-2 rounded bg-slate-900 text-white text-sm w-full md:w-auto text-center" href="{{ route('candidates.create') }}">+ Tambah</a>
    </div>
  </div>

  <div class="border-b border-slate-200">
    <nav class="-mb-px flex flex-wrap gap-2">
      @php
        $tabLinks = [
          'new' => 'Baru Lolos Administrasi',
          'active' => 'Test Aktif',
          'tested' => 'Sudah Test',
          'done' => 'Accepted',
          'rejected' => 'Reject',
          'blocked' => 'Block',
        ];
      @endphp
      @foreach($tabLinks as $key => $label)
        @php $isActive = $activeTab === $key; @endphp
        <a
          href="{{ route('candidates.index', array_merge(request()->except('page', 'tab'), ['tab' => $key])) }}"
          class="inline-flex items-center gap-2 px-3 py-2 border-b-2 text-sm font-medium {{ $isActive ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}"
        >
          <span>{{ $label }}</span>
          <span class="px-2 py-0.5 rounded-full text-xs {{ $isActive ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }}">{{ $tabCounts[$key] ?? 0 }}</span>
        </a>
      @endforeach
    </nav>
  </div>

  @if($isHrdSide && $activeTab === 'done')
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
      Kandidat accepted tetap dikelola dari halaman recruitment ini. Jika HRD perlu langsung mengirim kontrak Daily Worker, gunakan shortcut operasional di atas.
    </div>
  @endif

  <form method="GET" action="{{ route('candidates.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
    <input type="hidden" name="tab" value="{{ $activeTab }}">
    <div class="md:col-span-2">
      <input
        type="text"
        name="search"
        value="{{ $search ?? request('search') }}"
        placeholder="Cari nama, email, atau NIK"
        class="w-full border rounded p-2"
      >
    </div>
    <div>
      <select name="sort" class="w-full border rounded p-2">
        <option value="latest_test" @selected($sortValue === 'latest_test' || $sortValue === 'latest')>Terbaru</option>
        <option value="oldest_test" @selected($sortValue === 'oldest_test')>Terlama</option>
        <option value="name_asc" @selected($sortValue === 'name_asc')>A-Z</option>
        <option value="name_desc" @selected($sortValue === 'name_desc')>Z-A</option>
      </select>
    </div>
    <div>
      <select name="test_type" class="w-full border rounded p-2" @disabled(!$isTestedTab)>
        @foreach($testTypeOptions as $value => $label)
          <option value="{{ $value }}" @selected($testTypeValue === $value)>{{ $value === 'all' ? $label : \App\Models\AssessmentForm::labelFor((string) $value) }}</option>
        @endforeach
      </select>
    </div>
    <button class="px-3 py-2 rounded border bg-slate-50 hover:bg-slate-100">Filter</button>
  </form>

  @if($isHrdSide && $isNewTab)
    @if($availableForms->isEmpty())
      <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Belum ada master test aktif di Form Dinamis. Aktifkan atau buat form test dulu dari menu <b>Form Dinamis</b>.
      </div>
    @else
      <form method="POST" action="{{ route('candidates.bulk-activate-tests') }}" class="border rounded-lg p-4 bg-blue-50 space-y-4" id="bulkTestActivationForm">
        @csrf
        <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="text-sm font-semibold text-slate-900">Bulk Aktivasi Test Dinamis</div>
            <div class="text-xs text-slate-600">Pilih kandidat di tabel, lalu aktifkan satu atau beberapa test dari master Form Dinamis berdasarkan departemen audience.</div>
          </div>
          <div class="text-xs text-slate-600" id="bulkTestSelectionInfo">0 kandidat dipilih | 0 test dipilih</div>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
          @foreach($departmentGroups as $group)
            <div class="rounded-lg border border-blue-100 bg-white p-4 space-y-3">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <div class="text-sm font-semibold text-slate-900">{{ $group['label'] }}</div>
                  <div class="text-xs text-slate-500">{{ collect($group['types'] ?? [])->sum(fn ($typeGroup) => collect($typeGroup['forms'] ?? [])->count()) }} master test aktif</div>
                </div>
                <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-medium {{ ($group['department_id'] ?? null) ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700' }}">
                  {{ ($group['department_id'] ?? null) ? 'Khusus Departemen' : 'Umum' }}
                </span>
              </div>
              <div class="space-y-3">
                @foreach($group['types'] as $typeGroup)
                  <div class="space-y-2">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $typeGroup['label'] }}</div>
                    @foreach($typeGroup['forms'] as $form)
                      <label class="flex items-start gap-2 rounded border border-slate-200 px-3 py-2 hover:bg-slate-50">
                        <input type="checkbox" name="form_ids[]" value="{{ $form->id }}" class="bulk-form-checkbox mt-1 rounded border-slate-300" data-form-name="{{ $form->name }}" data-group-label="{{ $group['label'] }}">
                        <span class="min-w-0">
                          <span class="block text-sm font-medium text-slate-800">{{ $form->name }}</span>
                          <span class="block text-xs text-slate-500">{{ $form->code }} | {{ $form->duration_minutes ?? '-' }} menit</span>
                        </span>
                      </label>
                    @endforeach
                  </div>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>

        <div class="rounded-lg border border-blue-100 bg-white px-4 py-3 text-xs text-slate-600" id="bulkTestSummaryNames">
          Belum ada test yang dipilih.
        </div>

        <div class="flex justify-end">
          <button class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Aktifkan Test Terpilih</button>
        </div>
      </form>
    @endif
  @endif

  @if($isHrdSide)
    <form method="POST" action="{{ route('candidates.bulk-status') }}" class="border rounded-lg p-3 bg-slate-50 space-y-3" id="bulkStatusForm">
      @csrf
      <div class="text-sm font-semibold">Bulk Action Governance</div>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div>
          <label class="block text-xs text-slate-600 mb-1">Aksi</label>
          <select name="action" id="bulkActionSelect" class="border rounded p-2 w-full" required>
            <option value="">Pilih Aksi Bulk</option>
            <option value="accept">Accept</option>
            <option value="reject">Reject</option>
            <option value="block">Block</option>
            <option value="restore">Restore</option>
          </select>
        </div>
        <div class="md:col-span-2 flex items-end text-xs text-slate-600" id="bulkSelectionInfo">
          0 kandidat dipilih
        </div>
        <div class="flex items-end">
          <button class="px-3 py-2 rounded bg-slate-900 text-white hover:bg-slate-800 w-full">Jalankan</button>
        </div>
      </div>
    </form>
  @endif

  <div class="overflow-auto border rounded bg-white">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-100">
        <tr>
          @if($isHrdSide)
            <th class="text-left p-2 w-10"><input type="checkbox" id="selectAllCandidates" class="rounded border-slate-300"></th>
          @endif
          <th class="text-left p-2 w-20">Foto</th>
          <th class="text-left p-2 min-w-[220px]">Kandidat</th>
          <th class="text-left p-2 min-w-[220px]">Lamaran</th>
          <th class="text-left p-2">Status</th>
          <th class="text-left p-2 min-w-[280px]">Test Aktif / Status</th>
          <th class="text-left p-2">Aktivitas Test Terakhir</th>
          <th class="text-left p-2">Assessment</th>
          <th class="text-left p-2 min-w-[360px]">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($candidates as $c)
          @php
            $disc = $c->assessment->disc_result ?? [];
            $discSummary = !empty($disc)
              ? ('D:' . (int)($disc['D'] ?? 0) . ' I:' . (int)($disc['I'] ?? 0) . ' S:' . (int)($disc['S'] ?? 0) . ' C:' . (int)($disc['C'] ?? 0))
              : '-';
            $activityAt = $c->latest_test_activity_at ? \Illuminate\Support\Carbon::parse($c->latest_test_activity_at)->format('d/m/Y H:i') : '-';
            $restoreDeadline = $c->restore_deadline instanceof \Illuminate\Support\Carbon ? $c->restore_deadline->format('d/m/Y H:i') : null;
            $initials = collect(explode(' ', trim((string) $c->full_name)))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
            $assignments = $c->formAssignments->sortByDesc(fn ($assignment) => $assignment->updated_at?->timestamp ?? $assignment->id)->values();
          @endphp
          <tr class="border-t align-top">
            @if($isHrdSide)
              <td class="p-2"><input type="checkbox" value="{{ $c->id }}" class="candidate-checkbox rounded border-slate-300"></td>
            @endif
            <td class="p-2">
              @if($c->applicant_photo_path)
                <img src="{{ asset('storage/' . ltrim($c->applicant_photo_path, '/')) }}" alt="Foto {{ $c->full_name }}" class="h-12 w-12 rounded-full object-cover border border-slate-200">
              @else
                <div class="h-12 w-12 rounded-full border border-slate-200 bg-slate-100 text-slate-600 flex items-center justify-center text-xs font-semibold">{{ $initials !== '' ? $initials : 'NA' }}</div>
              @endif
            </td>
            <td class="p-2 font-medium">
              <div>{{ $c->full_name }}</div>
              <div class="text-xs text-slate-500">{{ $c->email ?: '-' }}</div>
              <div class="text-xs text-slate-500">NIK: {{ $c->nik ?: '-' }}</div>
              @if(in_array($c->status, ['rejected', 'blocked'], true) && $restoreDeadline)
                <div class="text-[11px] text-amber-600 mt-1">Batas restore: {{ $restoreDeadline }}</div>
              @endif
            </td>
            <td class="p-2">
              <div class="text-sm font-medium text-slate-800">{{ $c->application_position_name ?? '-' }}</div>
              <div class="text-xs text-slate-500">Departemen: {{ $c->application_department_name ?? '-' }}</div>
              <div class="text-xs text-slate-500">Outlet: {{ $c->application_outlet_name ?? '-' }}</div>
            </td>
            <td class="p-2">
              <div>{{ $c->status }}</div>
              @if($c->status === 'blocked')
                <span class="inline-flex mt-1 px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-xs">Blacklist aktif</span>
              @endif
            </td>
            <td class="p-2">
              @if($assignments->isEmpty())
                <div class="text-xs text-slate-500">Belum ada test aktif.</div>
              @else
                <div class="space-y-2">
                  @foreach($assignments->take(4) as $assignment)
                    @php
                      $type = (string) ($assignment->form->type ?? \App\Models\AssessmentForm::TYPE_CUSTOM);
                      $statusClass = $assignmentStatusClasses[$assignment->status] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                      $typeClass = $typeBadgeClasses[$type] ?? 'bg-slate-100 text-slate-700';
                    @endphp
                    <div class="rounded border border-slate-200 p-2">
                      <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $typeClass }}">{{ \App\Models\AssessmentForm::labelFor($type) }}</span>
                        <span class="font-medium text-slate-800">{{ $assignment->form->name ?? 'Form Test' }}</span>
                      </div>
                      <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <span class="px-2 py-0.5 rounded-full border font-semibold {{ $statusClass }}">{{ $assignment->statusLabel() }}</span>
                        <span class="text-slate-500">Dibuka: {{ $assignment->opened_at?->format('d/m/Y H:i') ?? '-' }}</span>
                        @if($assignment->expires_at)
                          <span class="text-slate-500">Deadline: {{ $assignment->expires_at->format('d/m/Y H:i') }}</span>
                        @endif
                      </div>
                    </div>
                  @endforeach
                  @if($assignments->count() > 4)
                    <div class="text-[11px] text-slate-500">+{{ $assignments->count() - 4 }} assignment test lainnya</div>
                  @endif
                </div>
              @endif
            </td>
            <td class="p-2">{{ $activityAt }}</td>
            <td class="p-2">
              <div>{{ $c->assessment->status ?? 'in_process' }}</div>
              <div class="text-xs text-slate-500 mt-1">IQ: {{ $c->assessment->iq_score ?? '-' }}</div>
              <div class="text-xs text-slate-500">DISC: {{ $discSummary }}</div>
            </td>
            <td class="p-2">
              <div class="space-y-2">
                <div class="flex flex-wrap gap-2">
                  <a class="inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" href="{{ route('candidates.show',$c) }}">Detail</a>
                  <a class="inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" href="{{ route('candidates.profile',$c) }}">Profil</a>
                  <a class="inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" href="{{ route('candidates.edit',$c) }}">Edit</a>
                  <form method="POST" action="{{ route('candidates.accept',$c) }}">@csrf<button class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Accept</button></form>
                  <form method="POST" action="{{ route('candidates.reject',$c) }}">@csrf<button class="inline-flex items-center rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">Reject</button></form>
                  <form method="POST" action="{{ route('candidates.block',$c) }}">@csrf<button class="inline-flex items-center rounded-md border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Block</button></form>
                  @if($c->can_restore)
                    <form method="POST" action="{{ route('candidates.restore',$c) }}">@csrf<button class="inline-flex items-center rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">Restore</button></form>
                  @endif
                </div>

                @if($isHrdSide && $isNewTab && $availableForms->isNotEmpty())
                  <div class="rounded-md border border-blue-100 bg-blue-50/70 p-3 space-y-2">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-blue-700">Aktivasi Test Individu</div>
                    <form method="POST" action="{{ route('candidates.tests.open', $c) }}" class="space-y-2" onsubmit="const selected = Array.from(this.querySelector('select').selectedOptions); if (selected.length === 0) { alert('Pilih minimal satu test dari master Form Dinamis.'); return false; }">
                      @csrf
                      <select name="form_ids[]" class="border rounded p-2 text-xs w-full" multiple size="{{ min(8, max(4, $availableForms->count())) }}">
                        @foreach($departmentGroups as $group)
                          @foreach($group['types'] as $typeGroup)
                            <optgroup label="{{ $group['label'] }} | {{ $typeGroup['label'] }}">
                              @foreach($typeGroup['forms'] as $form)
                                <option value="{{ $form->id }}">{{ $form->name }} ({{ $form->duration_minutes ?? '-' }} menit)</option>
                              @endforeach
                            </optgroup>
                          @endforeach
                        @endforeach
                      </select>
                      <button class="rounded bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Aktifkan Test Terpilih</button>
                    </form>
                  </div>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="{{ $isHrdSide ? 9 : 8 }}" class="p-6 text-center text-slate-500">Tidak ada kandidat pada tab ini.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="text-xs text-slate-500">
    Aktivasi test kandidat di tab Baru Lolos Administrasi sekarang memakai master Form Dinamis secara penuh. IQ dan DISC lama tetap kompatibel, tetapi HRD juga bisa mengaktifkan FAT, TIU, Diferensial, atau test custom lainnya dari sumber yang sama.
  </div>

  <div class="mt-2">{{ $candidates->links() }}</div>
</div>

@if($isHrdSide)
<script>
  (function () {
    const selectAll = document.getElementById('selectAllCandidates');
    const checkboxes = Array.from(document.querySelectorAll('.candidate-checkbox'));
    const statusForm = document.getElementById('bulkStatusForm');
    const testForm = document.getElementById('bulkTestActivationForm');
    const statusInfoEl = document.getElementById('bulkSelectionInfo');
    const testInfoEl = document.getElementById('bulkTestSelectionInfo');
    const testSummaryEl = document.getElementById('bulkTestSummaryNames');
    const actionSelect = document.getElementById('bulkActionSelect');
    const bulkFormCheckboxes = Array.from(document.querySelectorAll('.bulk-form-checkbox'));

    if (!selectAll || checkboxes.length === 0) {
      return;
    }

    const selectedCandidates = () => checkboxes.filter((cb) => cb.checked);
    const selectedForms = () => bulkFormCheckboxes.filter((cb) => cb.checked);

    const appendHiddenCandidateIds = (form) => {
      form.querySelectorAll('input[data-bulk-id="1"]').forEach((el) => el.remove());
      selectedCandidates().forEach((cb) => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'candidate_ids[]';
        hidden.value = cb.value;
        hidden.setAttribute('data-bulk-id', '1');
        form.appendChild(hidden);
      });
    };

    const syncState = () => {
      const checkedCount = selectedCandidates().length;
      selectAll.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
      selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;

      if (statusInfoEl) {
        statusInfoEl.textContent = `${checkedCount} kandidat dipilih`;
      }

      if (testInfoEl) {
        testInfoEl.textContent = `${checkedCount} kandidat dipilih | ${selectedForms().length} test dipilih`;
      }

      if (testSummaryEl) {
        const names = selectedForms().slice(0, 6).map((checkbox) => checkbox.getAttribute('data-form-name')).filter(Boolean);
        testSummaryEl.textContent = names.length > 0
          ? `Test dipilih: ${names.join(', ')}${selectedForms().length > names.length ? ` +${selectedForms().length - names.length} lainnya` : ''}`
          : 'Belum ada test yang dipilih.';
      }
    };

    selectAll.addEventListener('change', function () {
      checkboxes.forEach((cb) => {
        cb.checked = selectAll.checked;
      });
      syncState();
    });

    checkboxes.forEach((cb) => cb.addEventListener('change', syncState));
    bulkFormCheckboxes.forEach((cb) => cb.addEventListener('change', syncState));

    if (statusForm) {
      statusForm.addEventListener('submit', function (event) {
        if (selectedCandidates().length === 0) {
          event.preventDefault();
          alert('Pilih minimal satu kandidat untuk bulk action.');
          return;
        }

        if (!actionSelect || !actionSelect.value) {
          event.preventDefault();
          alert('Pilih aksi bulk terlebih dahulu.');
          return;
        }

        appendHiddenCandidateIds(statusForm);
      });
    }

    if (testForm) {
      testForm.addEventListener('submit', function (event) {
        if (selectedCandidates().length === 0) {
          event.preventDefault();
          alert('Pilih minimal satu kandidat untuk aktivasi test.');
          return;
        }

        if (selectedForms().length === 0) {
          event.preventDefault();
          alert('Pilih minimal satu test dari master Form Dinamis.');
          return;
        }

        appendHiddenCandidateIds(testForm);
      });
    }

    syncState();
  })();
</script>
@endif
@endsection

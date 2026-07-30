@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl p-4 sm:p-6">
  <div class="rounded-2xl border bg-white dark:bg-gray-800 dark:border-gray-700 shadow-sm">
    <div class="border-b px-6 py-4 dark:border-gray-700">
      <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Settings Retensi Recruitment</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">Atur masa retensi auto purge untuk kandidat gagal.</p>
    </div>

    <div class="p-6">
      @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
          {{ session('success') }}
        </div>
      @endif

      @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ route('settings.recruitment-retention.update') }}" class="space-y-5">
        @csrf

        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Auto Reject Applicant Belum Lengkap (hari)</label>
          <input type="number" min="1" max="365" name="applicant_incomplete_auto_reject_days" value="{{ old('applicant_incomplete_auto_reject_days', $settings['applicant_incomplete_auto_reject_days']) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100">
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Applicant Talent Pool yang belum lengkap melewati batas ini akan otomatis ditolak oleh scheduler.</p>
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Retensi Failed Test (hari)</label>
          <input type="number" min="1" max="365" name="retention_failed_test_days" value="{{ old('retention_failed_test_days', $settings['retention_failed_test_days']) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100">
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Retensi Rejected (hari)</label>
          <input type="number" min="1" max="365" name="retention_rejected_days" value="{{ old('retention_rejected_days', $settings['retention_rejected_days']) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100">
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Retensi Blacklist/Blocked (hari)</label>
          <input type="number" min="1" max="365" name="retention_blacklist_days" value="{{ old('retention_blacklist_days', $settings['retention_blacklist_days']) }}" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100">
        </div>

        <div class="flex justify-end">
          <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Simpan Pengaturan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

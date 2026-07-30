@extends('layouts.app')

@section('page_title', 'Landing Page')

@section('content')
<div class="space-y-5">
  <div class="card p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Pengaturan Landing Page</h1>
        <p class="mt-1 text-sm text-muted">Konten public website utama. Akses dibatasi untuk superadmin.</p>
      </div>
      <a href="{{ route('landing') }}" target="_blank" class="btn-outline">Preview Landing</a>
    </div>
  </div>

  <form method="POST" action="{{ route('dashboard.landing-page.update') }}" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @method('PUT')

    <div class="card p-5">
      <h2 class="text-lg font-bold text-slate-900 dark:text-white">Website / Branding</h2>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <label class="block text-sm font-semibold">Nama Website
          <input name="website_name" value="{{ old('website_name', $setting->value('website_name')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" required>
        </label>
        <label class="block text-sm font-semibold">Short Tagline
          <input name="short_tagline" value="{{ old('short_tagline', $setting->value('short_tagline')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold">Email Kantor
          <input type="email" name="office_email" value="{{ old('office_email', $setting->value('office_email')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold">Alamat Kantor
          <input name="office_address" value="{{ old('office_address', $setting->value('office_address')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold md:col-span-2">Footer Description
          <textarea name="footer_description" rows="3" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">{{ old('footer_description', $setting->value('footer_description')) }}</textarea>
        </label>
        <label class="block text-sm font-semibold md:col-span-2">Copyright Text
          <input name="copyright_text" value="{{ old('copyright_text', $setting->value('copyright_text')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <div>
          <label class="block text-sm font-semibold">Logo Website</label>
          @if($setting->logo_url)
            <img src="{{ $setting->logo_url }}" alt="Logo saat ini" class="mt-2 h-14 w-auto rounded-xl border border-slate-200 bg-white p-2">
          @endif
          <input type="file" name="logo" accept="image/*" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
        </div>
      </div>
    </div>

    <div class="card p-5">
      <h2 class="text-lg font-bold text-slate-900 dark:text-white">Hero Section</h2>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <label class="block text-sm font-semibold">Badge
          <input name="hero_badge" value="{{ old('hero_badge', $setting->value('hero_badge')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold">Headline
          <input name="hero_headline" value="{{ old('hero_headline', $setting->value('hero_headline')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold">Highlight Headline
          <input name="hero_highlight" value="{{ old('hero_highlight', $setting->value('hero_highlight')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold">Label Tombol Utama
          <input name="primary_button_label" value="{{ old('primary_button_label', $setting->value('primary_button_label')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold">Link Tombol Utama
          <input name="primary_button_url" value="{{ old('primary_button_url', $setting->value('primary_button_url')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" placeholder="/dashboard">
        </label>
        <label class="block text-sm font-semibold">Label Tombol Kedua
          <input name="secondary_button_label" value="{{ old('secondary_button_label', $setting->value('secondary_button_label')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold">Link Tombol Kedua
          <input name="secondary_button_url" value="{{ old('secondary_button_url', $setting->value('secondary_button_url')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" placeholder="/karir">
        </label>
        <label class="block text-sm font-semibold md:col-span-2">Subheadline
          <textarea name="hero_subheadline" rows="4" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">{{ old('hero_subheadline', $setting->value('hero_subheadline')) }}</textarea>
        </label>
        <div>
          <label class="block text-sm font-semibold">Hero Image</label>
          @if($setting->hero_image_url)
            <img src="{{ $setting->hero_image_url }}" alt="Hero image saat ini" class="mt-2 h-28 w-auto rounded-xl border border-slate-200 object-cover">
          @endif
          <input type="file" name="hero_image" accept="image/*" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
        </div>
        <div>
          <label class="block text-sm font-semibold">Hero Background</label>
          @if($setting->hero_background_url)
            <img src="{{ $setting->hero_background_url }}" alt="Background saat ini" class="mt-2 h-28 w-auto rounded-xl border border-slate-200 object-cover">
          @endif
          <input type="file" name="hero_background" accept="image/*" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
        </div>
      </div>
    </div>

    <div class="card p-5">
      <h2 class="text-lg font-bold text-slate-900 dark:text-white">CTA Section</h2>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <label class="block text-sm font-semibold">Judul CTA
          <input name="cta_title" value="{{ old('cta_title', $setting->value('cta_title')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold">Label Tombol CTA
          <input name="cta_button_label" value="{{ old('cta_button_label', $setting->value('cta_button_label')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold md:col-span-2">Link Tombol CTA
          <input name="cta_button_url" value="{{ old('cta_button_url', $setting->value('cta_button_url')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold md:col-span-2">Deskripsi CTA
          <textarea name="cta_description" rows="3" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">{{ old('cta_description', $setting->value('cta_description')) }}</textarea>
        </label>
      </div>
    </div>

    <div class="flex justify-end">
      <button class="btn-primary" type="submit">Simpan Pengaturan</button>
    </div>
  </form>
</div>
@endsection

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $post->seo_title ?: $post->title . ' | ' . $landingSetting->value('website_name') }}</title>
  <meta name="description" content="{{ $post->seo_description ?: \Illuminate\Support\Str::limit(strip_tags($post->description ?: $post->title), 155) }}">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    tailwind.config = { theme: { extend: { colors: { brand: '#E4A853', dark: { 900: '#120F0D', 800: '#1E1915', 700: '#2A241E' } } } } }
  </script>
  <style>
    .glass{background:rgba(30,25,21,.72);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.08)}
    .glass-card{background:linear-gradient(145deg,rgba(255,255,255,.06),rgba(255,255,255,.015));border:1px solid rgba(255,255,255,.1);box-shadow:0 8px 32px rgba(0,0,0,.3)}
  </style>
  <script type="application/ld+json">
  {!! json_encode([
      '@context' => 'https://schema.org',
      '@type' => 'JobPosting',
      'title' => $post->title,
      'description' => trim(strip_tags(($post->description ?? '') . "\n" . ($post->qualifications ?? '') . "\n" . ($post->benefits ?? ''))),
      'datePosted' => optional($post->published_at)->toDateString(),
      'validThrough' => optional($post->closing_at)->toDateString(),
      'employmentType' => strtoupper(str_replace('-', '_', $post->employment_type)),
      'hiringOrganization' => [
          '@type' => 'Organization',
          'name' => $landingSetting->value('website_name'),
      ],
      'jobLocation' => [
          '@type' => 'Place',
          'address' => [
              '@type' => 'PostalAddress',
              'addressLocality' => $post->location ?: 'Indonesia',
              'addressCountry' => 'ID',
          ],
      ],
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
  </script>
</head>
<body class="bg-dark-900 text-gray-100 antialiased">
  <div class="fixed inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(228,168,83,.18),transparent_35%),linear-gradient(to_bottom,#120F0D,#1E1915)]"></div>

  <nav class="sticky top-0 z-40">
    <div class="glass mx-auto mt-4 flex h-16 max-w-5xl items-center justify-between rounded-3xl px-4 sm:px-6">
      <a href="{{ route('landing') }}" class="flex items-center gap-2">
        <span class="grid h-8 w-8 place-items-center rounded-full bg-brand font-bold text-dark-900">O</span>
        <span class="font-bold">{{ $landingSetting->value('website_name') }}</span>
      </a>
      <a href="{{ route('careers.index') }}" class="rounded-full border border-white/10 px-4 py-2 text-sm hover:bg-white/10">Semua Lowongan</a>
    </div>
  </nav>

  <main class="mx-auto max-w-5xl px-4 py-14 sm:px-6">
    <article class="glass-card rounded-[2rem] p-6 md:p-10">
      <div class="flex flex-wrap items-center gap-2 text-xs text-gray-400">
        <span class="rounded-full bg-brand/10 px-3 py-1 font-semibold text-brand">{{ $post->department?->name ?? 'General' }}</span>
        <span class="rounded-full bg-white/5 px-3 py-1">{{ $post->employment_type }}</span>
        <span class="rounded-full bg-white/5 px-3 py-1">{{ $post->location ?: 'Banyak Lokasi' }}</span>
      </div>
      <h1 class="mt-5 text-4xl font-extrabold tracking-tight md:text-6xl">{{ $post->title }}</h1>
      <p class="mt-4 text-gray-400">Dipublikasikan {{ optional($post->published_at)->format('d M Y') ?: 'segera' }} @if($post->closing_at) • Closing {{ $post->closing_at->format('d M Y') }} @endif</p>

      <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_260px]">
        <div class="space-y-8">
          <section>
            <h2 class="text-xl font-bold text-brand">Deskripsi Pekerjaan</h2>
            <div class="prose prose-invert mt-3 max-w-none whitespace-pre-line text-gray-300">{{ $post->description ?: 'Detail pekerjaan akan diinformasikan oleh Tim HR.' }}</div>
          </section>
          <section>
            <h2 class="text-xl font-bold text-brand">Kualifikasi</h2>
            <div class="prose prose-invert mt-3 max-w-none whitespace-pre-line text-gray-300">{{ $post->qualifications ?: 'Kualifikasi akan diinformasikan oleh Tim HR.' }}</div>
          </section>
          @if(filled($post->benefits))
            <section>
              <h2 class="text-xl font-bold text-brand">Benefit</h2>
              <div class="prose prose-invert mt-3 max-w-none whitespace-pre-line text-gray-300">{{ $post->benefits }}</div>
            </section>
          @endif
        </div>
        <aside class="h-fit rounded-3xl border border-white/10 bg-white/5 p-5">
          <div class="text-sm text-gray-400">Siap melamar posisi ini?</div>
          <a href="{{ $post->apply_link }}" class="mt-4 flex w-full items-center justify-center gap-2 rounded-full bg-brand px-5 py-3 font-bold text-dark-900 hover:bg-white">
            {{ $post->apply_button_label ?: 'Lamar Posisi' }}
            <i data-lucide="arrow-right" class="h-4 w-4"></i>
          </a>
          <p class="mt-3 text-xs leading-5 text-gray-500">Default apply diarahkan ke flow registrasi/application form existing agar proses kandidat tetap aman.</p>
        </aside>
      </div>
    </article>
  </main>

  <script>lucide.createIcons();</script>
</body>
</html>

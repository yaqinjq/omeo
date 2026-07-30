<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Profile Kandidat</title>
<style>
@page { margin: 16px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #0f172a; }
.watermark { position: fixed; top: 38%; left: 12%; transform: rotate(-24deg); font-size: 54px; color: rgba(148, 163, 184, .14); font-weight: 800; z-index: 0; }
.section { position: relative; z-index: 1; border: 1px solid #dbe4f0; border-radius: 10px; padding: 10px; margin-bottom: 12px; background: #ffffff; }
.title { font-size: 15px; font-weight: 800; color: #0f172a; }
.subtitle { font-size: 11px; font-weight: 700; color: #1d4ed8; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .4px; }
.grid { width: 100%; border-collapse: separate; border-spacing: 10px; }
.grid td { width: 50%; vertical-align: top; }
.kv { width: 100%; border-collapse: collapse; }
.kv td { padding: 3px 0; vertical-align: top; }
.kv td:first-child { width: 170px; color: #475569; }
.table { width: 100%; border-collapse: collapse; }
.table th,.table td { border: 1px solid #dbe4f0; padding: 5px; text-align: left; vertical-align: top; }
.table th { background: #eef4ff; color: #1e3a8a; font-size: 9.5px; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; font-size: 9px; font-weight: 700; }
.note { font-size: 9px; color: #64748b; }
.salary { font-size: 12px; font-weight: 700; color: #991b1b; }
.doc-list li { margin-bottom: 3px; }
.page-break { page-break-before: always; }
.doc-grid { width: 100%; border-collapse: separate; border-spacing: 10px; }
.doc-grid td { width: 50%; vertical-align: top; }
.doc-card { border: 1px solid #dbe4f0; border-radius: 10px; padding: 8px; background: #f8fafc; }
.doc-label { font-size: 10px; font-weight: 700; color: #334155; margin-bottom: 6px; text-transform: uppercase; }
.doc-preview { width: 100%; height: 220px; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: #fff; text-align: center; }
.doc-preview-inner { width: 100%; height: 220px; text-align: center; }
.doc-preview-inner td { vertical-align: middle; text-align: center; }
.doc-preview img { display: block; width: auto; height: auto; max-width: 100%; max-height: 208px; margin: 0 auto; }
.doc-empty { height: 220px; display: table; width: 100%; color: #64748b; font-size: 10px; }
.doc-empty span { display: table-cell; vertical-align: middle; padding: 12px; }
.hero-meta { font-size: 9px; color: #475569; margin-top: 2px; }
.hero-line { height: 4px; margin-top: 8px; border-radius: 999px; background: #dbeafe; }
</style>
</head>
<body>
@php
$personal = $profile?->personal_json ?? [];
$address = $profile?->address_json ?? [];
$families = $profile?->families ?? [];
$educations = $profile?->educations ?? [];
$languages = $profile?->languages ?? [];
$courses = $profile?->courses ?? [];
$organizations = $profile?->organizations ?? [];
$workExperiences = $profile?->work_experiences ?? [];
$medicalHistories = $profile?->medical_histories ?? [];
$socialMedias = $profile?->social_medias ?? [];
$referenceContacts = $profile?->reference_contacts ?? data_get($personal, 'reference_contacts', []);
$emergencyContacts = data_get($personal, 'emergency_contacts', []);
$medicalMeta = $profile?->medical_json ?? [];
$graduationDocs = (array) data_get($personal, 'graduation_documents', []);
$salaryExpectation = data_get($personal, 'salary_expectation');
$salaryFormatted = is_numeric($salaryExpectation) ? 'Rp ' . number_format((float) $salaryExpectation, 0, ',', '.') : '-';
$photoAsset = data_get($documentAssets, 'photo', []);
$ktpAsset = data_get($documentAssets, 'ktp', []);
$cvAsset = data_get($documentAssets, 'cv', []);
$renderAssetPreview = static function (array $asset, string $fallbackText): string {
    if (!empty($asset['thumbnail_data_uri'])) {
        $src = htmlspecialchars((string) $asset['thumbnail_data_uri'], ENT_QUOTES, 'UTF-8');
        return '<table class="doc-preview-inner"><tr><td><img src="' . $src . '" alt="Preview dokumen"></td></tr></table>';
    }

    if (!empty($asset['pdf_preview_pages'][0])) {
        $src = htmlspecialchars((string) $asset['pdf_preview_pages'][0], ENT_QUOTES, 'UTF-8');
        return '<table class="doc-preview-inner"><tr><td><img src="' . $src . '" alt="Preview PDF"></td></tr></table>';
    }

    $message = trim((string) ($asset['preview_message'] ?? $fallbackText));
    return '<div class="doc-empty"><span>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</span></div>';
};
@endphp
<div class="watermark">OMEO HR SUITE CONFIDENTIAL</div>
<div class="section">
  <table style="width:100%; border-collapse:collapse;">
    <tr>
      <td><div class="title">Profil Kandidat untuk Review Manajemen</div><div class="hero-meta">Application form, dokumen utama, dan ringkasan hasil test kandidat</div><div class="note">Dicetak: {{ $generatedAt->format('d/m/Y H:i') }}</div><div class="hero-line"></div></td>
      <td style="text-align:right;"><span class="badge">{{ strtoupper((string)($candidate->status ?? 'applied')) }}</span></td>
    </tr>
  </table>
</div>

<div class="section">
  <div class="subtitle">Dokumen Visual Kandidat</div>
  <table class="doc-grid">
    <tr>
      <td>
        <div class="doc-card">
          <div class="doc-label">Pas Foto</div>
          <div class="doc-preview">{!! $renderAssetPreview($photoAsset, 'Pas foto belum tersedia atau tidak bisa dirender sebagai gambar.') !!}</div>
        </div>
      </td>
      <td>
        <div class="doc-card">
          <div class="doc-label">Scan KTP</div>
          <div class="doc-preview">{!! $renderAssetPreview($ktpAsset, 'Scan KTP belum tersedia atau format file bukan gambar.') !!}</div>
        </div>
      </td>
    </tr>
  </table>
</div>

<table class="grid">
<tr>
<td>
<div class="section"><div class="subtitle">Tab 1 - Data Pribadi</div><table class="kv">
<tr><td>Nama Lengkap</td><td>: {{ data_get($personal, 'full_name', '-') }}</td></tr>
<tr><td>Email</td><td>: {{ data_get($personal, 'email', $profile?->user?->email ?: '-') }}</td></tr>
<tr><td>NIK</td><td>: {{ data_get($personal, 'ktp_number', '-') }}</td></tr>
<tr><td>Tempat, Tgl Lahir</td><td>: {{ data_get($personal, 'place_of_birth', '-') }}, {{ data_get($personal, 'date_of_birth', '-') }}</td></tr>
<tr><td>Jam Lahir</td><td>: {{ data_get($personal, 'time_of_birth', '-') }}</td></tr>
<tr><td>Gender</td><td>: {{ data_get($personal, 'gender', '-') }}</td></tr>
<tr><td>Agama</td><td>: {{ data_get($personal, 'religion', '-') }}</td></tr>
<tr><td>Status Nikah</td><td>: {{ data_get($personal, 'marital_status', '-') }}</td></tr>
<tr><td>WhatsApp</td><td>: {{ data_get($personal, 'whatsapp', '-') }}</td></tr>
<tr><td>Telepon / HP</td><td>: {{ data_get($personal, 'phone_number', '-') }}</td></tr>
<tr><td>Posisi Dilamar</td><td>: {{ data_get($personal, 'applied_position_name', $appliedPosition ?: '-') }}</td></tr>
<tr><td>Ekspektasi Gaji</td><td>: <span class="salary">{{ $salaryFormatted }}</span></td></tr>
</table></div>
<div class="section"><div class="subtitle">Tab 2 - Alamat</div><table class="kv">
<tr><td>Alamat KTP</td><td>: {{ data_get($address, 'ktp_address', '-') }}</td></tr>
<tr><td>RT/RW KTP</td><td>: {{ data_get($address, 'ktp_rt', '-') }} / {{ data_get($address, 'ktp_rw', '-') }}</td></tr>
<tr><td>Kel/Kec KTP</td><td>: {{ data_get($address, 'ktp_kelurahan', '-') }} / {{ data_get($address, 'ktp_kecamatan', '-') }}</td></tr>
<tr><td>Kota/Kab KTP</td><td>: {{ data_get($address, 'ktp_city', '-') }}</td></tr>
<tr><td>Alamat Domisili</td><td>: {{ data_get($address, 'domicile_address', '-') }}</td></tr>
<tr><td>RT/RW Domisili</td><td>: {{ data_get($address, 'domicile_rt', '-') }} / {{ data_get($address, 'domicile_rw', '-') }}</td></tr>
<tr><td>Kel/Kec Domisili</td><td>: {{ data_get($address, 'domicile_kelurahan', '-') }} / {{ data_get($address, 'domicile_kecamatan', '-') }}</td></tr>
<tr><td>Kota/Kab Domisili</td><td>: {{ data_get($address, 'domicile_city', '-') }}</td></tr>
</table></div>
<div class="section"><div class="subtitle">Tab 3 - Preferensi Kerja</div><table class="kv">
<tr><td>Ruang Lingkup</td><td>: {{ data_get($personal, 'preferred_job_scope', '-') }} {{ data_get($personal, 'preferred_job_scope_other') }}</td></tr>
<tr><td>Lingkungan Kerja</td><td>: {{ data_get($personal, 'preferred_work_environment', '-') }} {{ data_get($personal, 'preferred_work_environment_other') }}</td></tr>
<tr><td>Luar Kota</td><td>: {{ data_get($personal, 'willing_out_of_town', '-') }}</td></tr>
<tr><td>Luar Jawa</td><td>: {{ data_get($personal, 'willing_outside_java', '-') }}</td></tr>
<tr><td>Shift / Lembur</td><td>: {{ data_get($personal, 'willing_shift', '-') }} / {{ data_get($personal, 'willing_overtime', '-') }}</td></tr>
<tr><td>Merokok</td><td>: {{ data_get($personal, 'is_smoker', '-') }}</td></tr>
<tr><td>Keahlian Komputer</td><td>: {{ data_get($personal, 'has_computer_skill', '-') }}</td></tr>
<tr><td>Kacamata</td><td>: {{ data_get($personal, 'wears_glasses', '-') }} (R: {{ data_get($personal, 'glasses_right_eye', '-') }}, L: {{ data_get($personal, 'glasses_left_eye', '-') }})</td></tr>
</table></div>
</td>
<td>
<div class="section"><div class="subtitle">Tab 4 - Essay & Final</div><table class="kv">
<tr><td>Alasan Bergabung</td><td>: {{ data_get($personal, 'join_reason', '-') }}</td></tr>
<tr><td>Saudara/Teman di Perusahaan</td><td>: {{ data_get($personal, 'company_relation_note', '-') }}</td></tr>
<tr><td>Target Karir</td><td>: {{ data_get($personal, 'career_goal', '-') }}</td></tr>
<tr><td>Info Tambahan</td><td>: {{ data_get($personal, 'additional_information', '-') }}</td></tr>
<tr><td>Siap Bergabung</td><td>: {{ data_get($personal, 'available_start_date', '-') }}</td></tr>
<tr><td>Pernyataan Kejujuran</td><td>: {{ data_get($personal, 'honesty_statement', '-') }}</td></tr>
</table></div>
<div class="section"><div class="subtitle">Tab 5 - Kesehatan</div><table class="kv">
<tr><td>Berat / Tinggi</td><td>: {{ data_get($medicalMeta, 'weight_kg', '-') }} kg / {{ data_get($medicalMeta, 'height_cm', '-') }} cm</td></tr>
<tr><td>Kecelakaan</td><td>: {{ data_get($medicalMeta, 'had_accident', '-') }} {{ data_get($medicalMeta, 'accident_year', '-') }} {{ data_get($medicalMeta, 'accident_type', '-') }} {{ data_get($medicalMeta, 'accident_effect', '-') }}</td></tr>
<tr><td>Riwayat Polisi</td><td>: {{ data_get($medicalMeta, 'police_record', '-') }} {{ data_get($medicalMeta, 'police_record_case', '-') }} {{ data_get($medicalMeta, 'police_record_year', '-') }} {{ data_get($medicalMeta, 'police_record_location', '-') }}</td></tr>
<tr><td>Psikotes</td><td>: {{ data_get($medicalMeta, 'psychology_test', '-') }} {{ data_get($medicalMeta, 'psychology_test_year', '-') }} {{ data_get($medicalMeta, 'psychology_test_location', '-') }} {{ data_get($medicalMeta, 'psychology_test_purpose', '-') }}</td></tr>
</table></div>
<div class="section"><div class="subtitle">Tab 6 - Dokumen</div><ul class="doc-list">
<li>Pas Foto: {{ data_get($personal, 'photo_path') ? 'Ada' : 'Belum ada' }}</li>
<li>Scan KTP: {{ data_get($personal, 'ktp_path') ? 'Ada' : 'Belum ada' }}</li>
<li>CV: {{ data_get($personal, 'cv_path') ? 'Ada' : 'Belum ada' }}</li>
<li>SKCK: {{ data_get($personal, 'skck_latest_path') ? 'Ada' : 'Belum ada' }}</li>
<li>Ijazah: {{ data_get($graduationDocs, 'diploma_path') ? 'Ada' : 'Belum ada' }}</li>
<li>Transkrip: {{ data_get($graduationDocs, 'transcript_path') ? 'Ada' : 'Belum ada' }}</li>
<li>Akta Kelahiran: {{ data_get($graduationDocs, 'birth_certificate_path') ? 'Ada' : 'Belum ada' }}</li>
<li>Dokumen Pendukung: {{ count((array) data_get($graduationDocs, 'supporting_files', [])) }} file</li>
<li>Preview CV: {{ !empty($cvAsset['thumbnail_data_uri']) || !empty($cvAsset['pdf_preview_pages']) ? 'Tersedia' : 'Tidak tersedia' }}</li>
</ul></div>
</td>
</tr>
</table>
<div class="section page-break"><div class="subtitle">Tab Detail - Keluarga & Kontak</div><table class="table"><thead><tr><th>Hubungan</th><th>Nama</th><th>Gender</th><th>Tgl Lahir</th><th>Pendidikan</th><th>Pekerjaan</th><th>Catatan</th></tr></thead><tbody>@forelse($families as $row)<tr><td>{{ data_get($row, 'relation', '-') }}</td><td>{{ data_get($row, 'name', '-') }}</td><td>{{ data_get($row, 'gender', '-') }}</td><td>{{ data_get($row, 'dob', '-') }}</td><td>{{ data_get($row, 'education', '-') }}</td><td>{{ data_get($row, 'job', '-') }}</td><td>{{ data_get($row, 'status_note', '-') }}</td></tr>@empty<tr><td colspan="7">Belum ada data keluarga.</td></tr>@endforelse</tbody></table><div style="height:10px"></div><table class="table"><thead><tr><th>Nama</th><th>Hubungan</th><th>HP</th><th>Alamat</th></tr></thead><tbody>@forelse($emergencyContacts as $row)<tr><td>{{ data_get($row, 'name', '-') }}</td><td>{{ data_get($row, 'relation', '-') }}</td><td>{{ data_get($row, 'phone', '-') }}</td><td>{{ data_get($row, 'address', '-') }}</td></tr>@empty<tr><td colspan="4">Belum ada kontak darurat.</td></tr>@endforelse</tbody></table></div>
<div class="section"><div class="subtitle">Tab Detail - Pendidikan, Bahasa, Kursus</div><table class="table"><thead><tr><th>Jenjang</th><th>Institusi</th><th>Jurusan</th><th>Masuk</th><th>Lulus</th><th>Nilai/IPK</th></tr></thead><tbody>@forelse($educations as $row)<tr><td>{{ data_get($row, 'level', '-') }}</td><td>{{ data_get($row, 'school', '-') }}</td><td>{{ data_get($row, 'major', '-') }}</td><td>{{ data_get($row, 'year_in', '-') }}</td><td>{{ data_get($row, 'year_out', '-') }}</td><td>{{ data_get($row, 'gpa', '-') }}</td></tr>@empty<tr><td colspan="6">Belum ada data pendidikan.</td></tr>@endforelse</tbody></table><div style="height:10px"></div><table class="table"><thead><tr><th>Bahasa</th><th>Lisan</th><th>Tulisan</th></tr></thead><tbody>@forelse($languages as $row)<tr><td>{{ data_get($row, 'language', '-') }}</td><td>{{ data_get($row, 'speaking', '-') }}</td><td>{{ data_get($row, 'writing', '-') }}</td></tr>@empty<tr><td colspan="3">Belum ada data bahasa.</td></tr>@endforelse</tbody></table><div style="height:10px"></div><table class="table"><thead><tr><th>Pelatihan</th><th>Penyelenggara</th><th>Tahun</th><th>Sertifikat</th></tr></thead><tbody>@forelse($courses as $row)<tr><td>{{ data_get($row, 'name', '-') }}</td><td>{{ data_get($row, 'organizer', '-') }}</td><td>{{ data_get($row, 'year', '-') }}</td><td>{{ data_get($row, 'certificate', '-') }}</td></tr>@empty<tr><td colspan="4">Belum ada data kursus.</td></tr>@endforelse</tbody></table></div>
<div class="section"><div class="subtitle">Tab Detail - Pengalaman, Referensi, Organisasi, Kesehatan</div><table class="table"><thead><tr><th>Perusahaan</th><th>Jabatan</th><th>Mulai</th><th>Selesai</th><th>Gaji</th><th>Alasan</th></tr></thead><tbody>@forelse($workExperiences as $row)<tr><td>{{ data_get($row, 'company', '-') }}</td><td>{{ data_get($row, 'position', '-') }}</td><td>{{ data_get($row, 'date_start', '-') }}</td><td>{{ data_get($row, 'date_end', '-') }}</td><td>{{ data_get($row, 'salary', '-') }}</td><td>{{ data_get($row, 'reason', '-') }}</td></tr>@empty<tr><td colspan="6">Belum ada data pengalaman kerja.</td></tr>@endforelse</tbody></table><div style="height:10px"></div><table class="table"><thead><tr><th>Nama</th><th>Hubungan</th><th>Perusahaan</th><th>HP</th></tr></thead><tbody>@forelse($referenceContacts as $row)<tr><td>{{ data_get($row, 'name', '-') }}</td><td>{{ data_get($row, 'relation', '-') }}</td><td>{{ data_get($row, 'company', '-') }}</td><td>{{ data_get($row, 'phone', '-') }}</td></tr>@empty<tr><td colspan="4">Belum ada referensi.</td></tr>@endforelse</tbody></table><div style="height:10px"></div><table class="table"><thead><tr><th>Organisasi</th><th>Jabatan</th><th>Tahun</th></tr></thead><tbody>@forelse($organizations as $row)<tr><td>{{ data_get($row, 'name', '-') }}</td><td>{{ data_get($row, 'role', '-') }}</td><td>{{ data_get($row, 'year', '-') }}</td></tr>@empty<tr><td colspan="3">Belum ada organisasi.</td></tr>@endforelse</tbody></table><div style="height:10px"></div><table class="table"><thead><tr><th>Penyakit</th><th>Tahun</th><th>Rawat Inap</th><th>Catatan</th></tr></thead><tbody>@forelse($medicalHistories as $row)<tr><td>{{ data_get($row, 'illness', '-') }}</td><td>{{ data_get($row, 'year', '-') }}</td><td>{{ data_get($row, 'hospitalized', '-') }}</td><td>{{ data_get($row, 'note', '-') }}</td></tr>@empty<tr><td colspan="4">Belum ada data kesehatan.</td></tr>@endforelse</tbody></table><div style="height:10px"></div><table class="table"><thead><tr><th>Platform</th><th>Handle</th></tr></thead><tbody>@forelse($socialMedias as $row)<tr><td>{{ data_get($row, 'platform', '-') }}</td><td>{{ data_get($row, 'handle', '-') }}</td></tr>@empty<tr><td colspan="2">Belum ada social media.</td></tr>@endforelse</tbody></table></div>
</body>
</html>


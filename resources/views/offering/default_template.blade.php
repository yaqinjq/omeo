{{--
  TEMPLATE REFERENSI OFFERING LETTER (V9.1)
  -------------------------------------------------
  Cara pakai cepat:
  1) Buka menu Offering Letter Template (CMS) yang sudah ada.
  2) Copy isi file ini (HTML) ke field content_html.
  3) Sesuaikan path logo & tanda tangan.

  Placeholder yang akan otomatis diganti oleh OfferingController:
  {NIK}, {FULL_NAME}, {EMAIL}, {ROLE}, {POSITION}, {DEPARTMENT}, {JOIN_DATE}, {PROBATION_END_DATE}, {STATUS_EMPLOYMENT}, {TODAY}
--}}

<div style="font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; line-height: 1.6; color: #111;">
  <table width="100%" style="margin-bottom: 16px;">
    <tr>
      <td style="width: 140px;">
        {{-- Ganti sesuai logo brand Anda --}}
        <img src="{{ public_path('images/brand-logo.png') }}" style="height: 56px;" alt="Logo">
      </td>
      <td style="text-align:right;">
        <div style="font-size: 14px; font-weight: bold;">OFFERING LETTER</div>
        <div style="font-size: 12px;">Tanggal: {TODAY}</div>
      </td>
    </tr>
  </table>

  <p>Yth. <strong>{FULL_NAME}</strong><br>
  Email: {EMAIL}</p>

  <p>Dengan hormat,</p>

  <p>
    Sehubungan dengan proses rekrutmen yang telah Anda ikuti, kami dengan senang hati menyampaikan penawaran kerja dengan detail sebagai berikut:
  </p>

  <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 12px 0 18px;">
    <tr>
      <td style="padding: 6px 8px; border: 1px solid #ddd; width: 34%;">NIK</td>
      <td style="padding: 6px 8px; border: 1px solid #ddd;">{NIK}</td>
    </tr>
    <tr>
      <td style="padding: 6px 8px; border: 1px solid #ddd;">Posisi</td>
      <td style="padding: 6px 8px; border: 1px solid #ddd;">{POSITION}</td>
    </tr>
    <tr>
      <td style="padding: 6px 8px; border: 1px solid #ddd;">Departemen</td>
      <td style="padding: 6px 8px; border: 1px solid #ddd;">{DEPARTMENT}</td>
    </tr>
    <tr>
      <td style="padding: 6px 8px; border: 1px solid #ddd;">Mulai Bekerja</td>
      <td style="padding: 6px 8px; border: 1px solid #ddd;">{JOIN_DATE}</td>
    </tr>
    <tr>
      <td style="padding: 6px 8px; border: 1px solid #ddd;">Akhir Probation</td>
      <td style="padding: 6px 8px; border: 1px solid #ddd;">{PROBATION_END_DATE}</td>
    </tr>
    <tr>
      <td style="padding: 6px 8px; border: 1px solid #ddd;">Status</td>
      <td style="padding: 6px 8px; border: 1px solid #ddd;">{STATUS_EMPLOYMENT}</td>
    </tr>
  </table>

  <p>
    Mohon memberikan konfirmasi penerimaan penawaran ini kepada HRD. Dokumen pendukung dan informasi lanjutan akan kami sampaikan setelah konfirmasi.
  </p>

  <p>Hormat kami,</p>

  <div style="margin-top: 36px;">
    {{-- Ganti sesuai file tanda tangan (bisa dari storage) --}}
    <img src="{{ public_path('images/signature.png') }}" style="height: 60px;" alt="Tanda tangan">
    <div style="font-weight: bold; margin-top: 4px;">HR Department</div>
    <div style="font-size: 11px; color: #444;">OMEŌ HR Suite</div>
  </div>

  <hr style="border:0; border-top:1px solid #eee; margin: 18px 0;">
  <div style="font-size: 10px; color: #666;">
    Catatan: Dokumen ini dihasilkan otomatis oleh sistem.
  </div>
</div>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Slip Gaji - {{ $employee->full_name }}</title>
<style>
@page { size: A4; margin: 16mm; }
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; margin: 0; padding: 0; }

.hdr { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1e3a8a; margin-bottom: 14px; padding-bottom: 8px; }
.hdr td { vertical-align: middle; padding: 0; }
.hdr-right { text-align: right; font-size: 9px; color: #374151; }
.app-name { font-size: 15px; font-weight: bold; color: #1e3a8a; }
.app-sub  { font-size: 9px; color: #4b5563; margin-top: 2px; }

h1 { font-size: 15px; font-weight: bold; color: #0f172a; margin: 4px 0 10px; text-align: center; }

.info-tbl { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 9.5px; }
.info-tbl td { border: 1px solid #d1d5db; padding: 5px 8px; vertical-align: top; }
.info-tbl .lbl { color: #4b5563; width: 130px; background: #f9fafb; }
.info-tbl .val { color: #111827; font-weight: bold; }

.sec-title {
    font-size: 10px; font-weight: bold; color: #1e3a8a;
    border-left: 3px solid #1e3a8a; padding-left: 6px;
    margin: 12px 0 6px;
}

.comp-tbl { width: 100%; border-collapse: collapse; margin-bottom: 6px; font-size: 9.5px; }
.comp-tbl th {
    background: #1e3a8a; color: #ffffff; border: 1px solid #1e3a8a;
    padding: 5px 8px; text-align: left; font-size: 9px;
}
.comp-tbl th.amt { text-align: right; }
.comp-tbl td { border: 1px solid #d1d5db; padding: 5px 8px; }
.comp-tbl td.amt { text-align: right; }
.comp-tbl tr.total-row td { background: #eff6ff; font-weight: bold; }

.thp-box {
    margin-top: 16px; padding: 14px 16px; background: #1e3a8a; color: #ffffff;
    border-radius: 4px; display: block;
}
.thp-label { font-size: 10px; opacity: 0.85; }
.thp-value { font-size: 18px; font-weight: bold; margin-top: 2px; }

.footer { text-align: center; font-size: 8px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 6px; }
.disclaimer { font-size: 8px; color: #9ca3af; margin-top: 4px; font-style: italic; }
</style>
</head>
<body>

<table class="hdr">
<tr>
    <td>
        <div class="app-name">{{ config('app.name', 'OMEO') }}</div>
        <div class="app-sub">Slip Gaji Karyawan</div>
    </td>
    <td class="hdr-right">
        Dicetak: {{ now()->format('d M Y H:i') }}
    </td>
</tr>
</table>

<h1>SLIP GAJI — {{ strtoupper($periodeLabel) }}</h1>

<table class="info-tbl">
<tr>
    <td class="lbl">Nama Karyawan</td>
    <td class="val">{{ strtoupper($employee->full_name) }}</td>
    <td class="lbl">No. Komp</td>
    <td class="val">{{ $employee->nokom ?? $employee->employee_number ?? '-' }}</td>
</tr>
<tr>
    <td class="lbl">Jabatan</td>
    <td class="val">{{ strtoupper($employee->jabatan ?? '-') }}</td>
    <td class="lbl">Departemen</td>
    <td class="val">{{ strtoupper($employee->department?->name ?? '-') }}</td>
</tr>
<tr>
    <td class="lbl">Outlet</td>
    <td class="val">{{ $outletNames->isNotEmpty() ? strtoupper($outletNames->implode(', ')) : '-' }}</td>
    <td class="lbl">Rekening Bank</td>
    <td class="val">
        @if($bankAccount)
            {{ $bankAccount->bank_name }} — {{ $bankAccount->account_number }}
        @else
            Belum terdaftar
        @endif
    </td>
</tr>
</table>

<div class="sec-title">Pendapatan</div>
<table class="comp-tbl">
<thead>
<tr><th>Komponen</th><th class="amt">Jumlah (Rp)</th></tr>
</thead>
<tbody>
<tr><td>Gaji Pokok</td><td class="amt">{{ number_format($gajiPokok, 0, ',', '.') }}</td></tr>
<tr><td>Tunjangan</td><td class="amt">{{ number_format($tunjangan, 0, ',', '.') }}</td></tr>
<tr><td>Uang Hadir</td><td class="amt">{{ number_format($attd, 0, ',', '.') }}</td></tr>
<tr><td>HR</td><td class="amt">{{ number_format($hr, 0, ',', '.') }}</td></tr>
<tr><td>Lembur (OT)</td><td class="amt">{{ number_format($ot, 0, ',', '.') }}</td></tr>
<tr><td>S. Expense</td><td class="amt">{{ number_format($sExpense, 0, ',', '.') }}</td></tr>
<tr class="total-row"><td>Total Pendapatan</td><td class="amt">{{ number_format($totalPendapatan, 0, ',', '.') }}</td></tr>
</tbody>
</table>

<div class="sec-title">Potongan</div>
<table class="comp-tbl">
<thead>
<tr><th>Komponen</th><th class="amt">Jumlah (Rp)</th></tr>
</thead>
<tbody>
<tr><td>BPJS Ketenagakerjaan (Karyawan)</td><td class="amt">{{ number_format($bpjsTk, 0, ',', '.') }}</td></tr>
<tr><td>BPJS Kesehatan (Karyawan)</td><td class="amt">{{ number_format($bpjsKes, 0, ',', '.') }}</td></tr>
<tr><td>Potongan Lain</td><td class="amt">{{ number_format($potonganLain, 0, ',', '.') }}</td></tr>
<tr class="total-row"><td>Total Potongan</td><td class="amt">{{ number_format($totalPotongan, 0, ',', '.') }}</td></tr>
</tbody>
</table>

<div class="thp-box">
    <div class="thp-label">TAKE HOME PAY</div>
    <div class="thp-value">Rp {{ number_format($takeHomePay, 0, ',', '.') }}</div>
</div>

<div class="disclaimer">
    Slip gaji ini dihasilkan otomatis dari data payroll yang sudah diimport ke sistem. Hubungi HRD/Finance apabila ada komponen yang perlu diklarifikasi.
</div>

<div class="footer">
    Dokumen ini sah tanpa tanda tangan basah — dicetak dari sistem {{ config('app.name', 'OMEO') }}.
</div>

</body>
</html>

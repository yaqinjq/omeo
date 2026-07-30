<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Appraisal - Bulk Export</title>
<style>
@page { size: A4; margin: 14mm 14mm 16mm 14mm; }
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #111827; line-height: 1.35; margin: 0; padding: 0; }

/* ── HEADER ── */
.hdr { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1e3a8a; margin-bottom: 10px; padding-bottom: 6px; }
.hdr td { vertical-align: middle; padding: 0; }
.hdr-right { text-align: right; font-size: 8px; color: #374151; }
.app-name { font-size: 13px; font-weight: bold; color: #1e3a8a; }
.app-sub  { font-size: 8.5px; color: #4b5563; margin-top: 1px; }

/* ── TYPOGRAPHY ── */
h1 { font-size: 14px; font-weight: bold; color: #0f172a; margin: 10px 0 3px; }
.doc-meta { font-size: 8.5px; color: #4b5563; margin-bottom: 10px; line-height: 1.6; }

.sec-title {
    font-size: 9px; font-weight: bold; color: #1e3a8a;
    border-left: 3px solid #1e3a8a; padding-left: 5px;
    margin: 10px 0 6px;
}

/* ── INFO TABLE ── */
.info-tbl { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 8.5px; }
.info-tbl td { border: 1px solid #d1d5db; padding: 3.5px 6px; vertical-align: top; }
.info-tbl .lbl { color: #4b5563; width: 120px; background: #f9fafb; }
.info-tbl .val { color: #111827; font-weight: bold; }

/* ── MATRIX TABLE ── */
.mx-tbl { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
.mx-tbl th {
    background: #1e3a8a; color: #ffffff;
    border: 1px solid #1e3a8a; padding: 3px 4px;
    text-align: center; font-size: 7.5px; font-weight: bold;
}
.mx-tbl th.crit-hdr { text-align: left; }
.mx-tbl td {
    border: 1px solid #d1d5db; padding: 3px 5px;
    text-align: center; font-size: 8px;
}
.mx-tbl td.crit-lbl { text-align: left; background: #f9fafb; font-size: 8px; }
.mx-tbl tr.avg-row td { background: #eff6ff; font-weight: bold; }
.score-hi { color: #065f46; }
.score-lo { color: #991b1b; }

/* ── NARRATIVE TABLE ── */
.narr-tbl { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 8px; }
.narr-tbl th {
    background: #1e3a8a; color: white; border: 1px solid #1e3a8a;
    padding: 4px 5px; text-align: left; font-size: 7.5px;
}
.narr-tbl td {
    border: 1px solid #d1d5db; padding: 4px 5px;
    vertical-align: top; word-wrap: break-word;
}
.narr-tbl tr:nth-child(even) td { background: #f9fafb; }

/* ── DECISION TABLE ── */
.dec-tbl { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 8.5px; }
.dec-tbl td { border: 1px solid #d1d5db; padding: 5px 7px; vertical-align: top; }
.dec-tbl .lbl { width: 130px; color: #4b5563; background: #f9fafb; font-size: 8px; }
.proposed-item { padding: 1.5px 0; }
.proposed-sel { font-weight: bold; color: #1e3a8a; }
.proposed-off { color: #9ca3af; }

/* ── APPROVAL TABLE ── */
.appr-tbl { width: 100%; border-collapse: collapse; margin-top: 8px; }
.appr-tbl td {
    width: 25%; border: 1px solid #d1d5db; text-align: center;
    padding: 6px 4px; vertical-align: top; height: 70px;
}
.appr-lbl { font-size: 8px; font-weight: bold; color: #374151; margin-bottom: 4px; }
.appr-space { height: 52px; }

/* ── UTILS ── */
.page-break { page-break-before: always; }
.part-hdr { font-size: 8.5px; color: #374151; margin-bottom: 4px; }
.footer { text-align: center; font-size: 7.5px; color: #9ca3af; margin-top: 14px; border-top: 1px solid #e5e7eb; padding-top: 5px; }

/* ── BULK COVER ── */
.cover { text-align: center; padding-top: 120px; }
.cover h1 { font-size: 20px; }
.cover p { font-size: 10px; color: #4b5563; margin-top: 6px; }
</style>
</head>
<body>

<div class="cover">
    <div class="app-name">{{ $appName }}</div>
    <h1>Laporan Appraisal — Export Bulk</h1>
    <p>{{ $employeesData->count() }} karyawan | Dicetak {{ now()->format('d M Y H:i') }}</p>
</div>

@foreach($employeesData as $data)
<div class="page-break"></div>
@include('appraisals.pdf.partials.employee_report_body', $data)
@endforeach

</body>
</html>

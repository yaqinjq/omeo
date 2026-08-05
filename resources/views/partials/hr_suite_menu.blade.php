@php
  use App\Models\User;
  use Illuminate\Support\Facades\DB;
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Facades\Schema;

  $user = auth()->user();
  $role = $user instanceof User ? $user->normalizedRole() : strtolower(trim((string) ($user->role ?? '')));
  $isSuperAdmin = $user instanceof User && $user->isSuperAdmin();
  $hrdPermissionHints = ['users_roles.manage', 'employees.manage', 'candidates.manage', 'contracts.manage', 'appraisals.manage', 'finance.view'];
  $isHrd = $user instanceof User
    ? ($isSuperAdmin || $user->hasRole(['admin','hrd','manager']) || $user->hasAnyPermission($hrdPermissionHints))
    : in_array($role, ['admin','hrd','manager'], true);
  $isApplicant = $user && $user->employee_id === null;
  $isProbation = $user && ($role === 'probation');
  $isTrainingTrainer = $user instanceof User && $user->isApprovedTrainer();

  $unread = 0;
  if (Schema::hasTable('hr_notifications') && Schema::hasColumn('hr_notifications', 'is_read')) {
    $unread = (int) DB::table('hr_notifications')
      ->where(function ($q) use ($user) {
        $q->whereNull('user_id');
        if ($user) {
          $q->orWhere('user_id', $user->id);
        }
      })
      ->where('is_read', 0)
      ->count();
  }

  $icon = function (string $name): string {
    $icons = [
      'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M3 13h8V3H3v10zm10 8h8V11h-8v10zM3 21h8v-6H3v6zm10-10h8V3h-8v8z"/></svg>',
      'form' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>',
      'test' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
      'contract' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>',
      'training' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M2 7l10-4 10 4-10 4L2 7z"/><path d="M6 10v6c0 1.5 3 3 6 3s6-1.5 6-3v-6"/></svg>',
      'appraisal' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M3 3v18h18"/><path d="M7 14l3-3 3 2 4-5"/></svg>',
      'notif' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"/><path d="M9 17a3 3 0 0 0 6 0"/></svg>',
      'employee' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>',
      'candidate' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
      'folder' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
      'iq' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5h.01M14.5 9.5h.01M8.5 15c1.5 2 5.5 2 7 0"/></svg>',
      'disc' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><circle cx="12" cy="12" r="9"/><path d="M12 3v18M3 12h18"/></svg>',
      'template' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 8h10M7 12h10M7 16h6"/></svg>',
      'department' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/></svg>',
      'position' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M12 2v20M2 12h20"/></svg>',
      'outlet' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M3 10l9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10z"/></svg>',
      'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>',
      'verification' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M9 12l2 2 4-4"/><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
      'onboarding' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>',
      'offering' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>',
      'attendance' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M8 2v4M16 2v4M3 10h18"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M8 14h3v3H8zM13 14h3v3h-3z"/></svg>',
      'report' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M3 3v18h18"/><path d="M7 15l3-3 3 2 4-5"/></svg>',
      'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.2a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.2a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.2a1.6 1.6 0 0 0 1 1.5h.1a1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9c0 .7.4 1.3 1.1 1.5.1 0 .2.1.4.1h.2a2 2 0 1 1 0 4H21c-.7 0-1.3.4-1.5 1z"/></svg>',
      'profile' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
      'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>',
      'finance' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><path d="M12 12v4"/><path d="M9 14h6"/></svg>',
      'bpjs' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>',
      'company_group' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><rect x="2" y="7" width="6" height="14"/><rect x="9" y="3" width="6" height="18"/><rect x="16" y="10" width="6" height="11"/></svg>',
      'legal_entity'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
      'region'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><circle cx="12" cy="10" r="3"/><path d="M12 2a8 8 0 0 1 8 8c0 5-8 14-8 14S4 15 4 10a8 8 0 0 1 8-8z"/></svg>',
      'bpjs_rates'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 1 0 0 7h5a3.5 3.5 0 1 1 0 7H6"/></svg>',
      'reconcile'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.05"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>',
    ];

    return $icons[$name] ?? $icons['folder'];
  };

  $routeItem = function (string $route, string $label, string $iconName, array $params = []) use ($icon, $user, $isSuperAdmin) {
    if (!Route::has($route)) return null;
    if (!$isSuperAdmin && $user instanceof User && ! $user->canAccessRoute($route)) return null;
    return ['href' => route($route, $params), 'label' => $label, 'ico' => $icon($iconName), 'route' => $route, 'active' => request()->routeIs($route . '*')];
  };

  $urlItem = function (string $url, string $label, string $iconName) use ($icon) {
    return ['href' => url($url), 'label' => $label, 'ico' => $icon($iconName), 'route' => null];
  };

  $isItemActive = function (array $item): bool {
    if (!empty($item['route']) && request()->routeIs($item['route'])) {
      return true;
    }

    $path = trim((string) parse_url($item['href'] ?? '', PHP_URL_PATH), '/');
    $currentPath = trim(request()->path(), '/');

    return $path !== '' && $path === $currentPath;
  };

  $userMenu = [];
  if ($l = $routeItem('dashboard', 'Dashboard', 'dashboard')) $userMenu[] = $l;
  if ($l = $routeItem('my-training.index', 'My Training', 'training')) $userMenu[] = $l;
  if ($isApplicant) {
    if ($l = $routeItem('application-form.edit', 'Application Form', 'form')) $userMenu[] = $l;
    if ($l = $routeItem('applicant.tests.index', 'Tes Seleksi', 'test')) $userMenu[] = $l;
    if ($l = $routeItem('applicant.contracts.index', 'Kontrak / Inbox', 'contract')) $userMenu[] = $l;
  } else {
    if ($isTrainingTrainer && ($l = $routeItem('trainer.events.index', 'Trainer Events', 'report'))) $userMenu[] = $l;
    if ($l = $routeItem('employee-profile.show', 'Profil Saya', 'profile')) $userMenu[] = $l;
    if ($l = $routeItem('appraisals.my', 'Appraisal Saya', 'appraisal')) $userMenu[] = $l;
    if ($l = $routeItem('appraisals.guide', 'Panduan Skor Penilaian', 'template')) $userMenu[] = $l;
    if ($l = $routeItem('attendance.index', 'Presensi Saya', 'attendance')) $userMenu[] = $l;
    if (Route::has('employee.attendance.export.index')) {
      $userMenu[] = [
        'href'   => route('employee.attendance.export.index'),
        'label'  => 'Export Kehadiran',
        'ico'    => $icon('attendance'),
        'route'  => 'employee.attendance.export.index',
        'active' => request()->routeIs('employee.attendance.export.*'),
      ];
    }
    if ($isProbation && ($l = $routeItem('probation-onboarding.edit', 'Kelengkapan Payroll', 'onboarding'))) $userMenu[] = $l;
  }

  if (Route::has('hr-notifications.index')) {
    $userMenu[] = ['href' => route('hr-notifications.index'), 'label' => 'Inbox Notifikasi', 'ico' => $icon('notif'), 'badge' => $unread, 'route' => 'hr-notifications.index'];
  }

  $hrdGroups = [];

  $recruitment = [];
  if ($l = $routeItem('dashboard', 'Dashboard', 'dashboard')) $recruitment[] = $l;
  if ($l = $routeItem('hrd.applicants.index', 'Talent Pool Applicants', 'candidate')) $recruitment[] = $l;
  if ($l = $routeItem('candidates.index', 'Recruitment Kandidat', 'candidate')) $recruitment[] = $l;
  if ($l = $routeItem('walkin.index', 'Walk-In Interview', 'candidate')) $recruitment[] = $l;
  if (count($recruitment)) $hrdGroups[] = ['label' => 'Recruitment', 'items' => $recruitment];

  $employees = [];
  if ($l = $routeItem('employees.index', 'Master Karyawan', 'employee')) $employees[] = $l;
  if ($l = $routeItem('hr.employee-import.index', 'Import Karyawan XLS', 'folder')) $employees[] = $l;
  if ($l = $routeItem('hrd.probation-verifications.index', 'Verifikasi Data Karyawan', 'verification')) $employees[] = $l;
  if ($l = $routeItem('hrd.attendance.index', 'Rekap Presensi', 'report')) $employees[] = $l;
  if ($l = $routeItem('departments.index', 'Master Departemen', 'department')) $employees[] = $l;
  if ($l = $routeItem('positions.index', 'Master Posisi', 'position')) $employees[] = $l;
  if ($l = $routeItem('outlets.index', 'Master Outlet', 'outlet')) $employees[] = $l;
  if ($l = $routeItem('master-shifts.index', 'Master Shift Kerja', 'clock')) $employees[] = $l;
  if (count($employees)) $hrdGroups[] = ['label' => 'Employees', 'items' => $employees];

  $trainingLms = [];
  if ($l = $routeItem('my-training.index', 'My Training', 'training')) $trainingLms[] = $l;
  if ($isTrainingTrainer && ($l = $routeItem('trainer.events.index', 'Trainer Events', 'report'))) $trainingLms[] = $l;
  if ($l = $routeItem('training-materials.index', 'Master Training', 'training')) $trainingLms[] = $l;
  if ($l = $routeItem('training-programs.index', 'Training Programs', 'training')) $trainingLms[] = $l;
  if ($l = $routeItem('training-events.index', 'Training Events', 'report')) $trainingLms[] = $l;
  if ($l = $routeItem('training-trainers.index', 'Trainer Internal', 'employee')) $trainingLms[] = $l;
  if (count($trainingLms)) $hrdGroups[] = ['label' => 'Training', 'items' => $trainingLms];

  $assessmentBank = [];
  if ($l = $routeItem('hrd.forms.index', 'Form Dinamis', 'form')) $assessmentBank[] = $l;
  if ($l = $routeItem('hrd.import.iq.index', 'Import Soal IQ', 'iq')) $assessmentBank[] = $l;
  if ($l = $routeItem('hrd.import.disc.index', 'Import Soal DISC', 'disc')) $assessmentBank[] = $l;
  if ($l = $routeItem('hrd.import.choice.index', 'Import Soal TIU', 'iq', ['type' => 'tiu'])) $assessmentBank[] = $l;
  if ($l = $routeItem('hrd.import.choice.index', 'Import Soal Diferensial', 'iq', ['type' => 'diferensial'])) $assessmentBank[] = $l;
  if ($l = $routeItem('hrd.import.choice.index', 'Import Soal FAT', 'iq', ['type' => 'fat'])) $assessmentBank[] = $l;
  if (count($assessmentBank)) $hrdGroups[] = ['label' => 'Assessment & Import', 'items' => $assessmentBank];

  $appraisal = [];
  if ($l = $routeItem('appraisal-periods.index', 'Grouping Legacy Appraisal', 'template')) $appraisal[] = $l;
  if ($l = $routeItem('appraisal-indicators.index', 'Kriteria Penilaian', 'appraisal')) $appraisal[] = $l;
  if ($l = $routeItem('appraisal-criteria-templates.index', 'Template Kriteria per Kategori', 'template')) $appraisal[] = $l;
  if ($l = $routeItem('appraisals.guide', 'Panduan Skor Penilaian', 'template')) $appraisal[] = $l;
  if ($l = $routeItem('appraisals.weight-config', 'Bobot Formula Appraisal', 'settings')) $appraisal[] = $l;
  if ($l = $routeItem('appraisals.assignment', 'Assignment Probation', 'verification')) $appraisal[] = $l;
  if ($l = $routeItem('appraisals.index', 'Monitoring Appraisal', 'appraisal')) $appraisal[] = $l;
  if ($l = $routeItem('appraisals.report', 'Laporan Appraisal', 'report')) $appraisal[] = $l;
  if (count($appraisal)) $hrdGroups[] = ['label' => 'Appraisal HRD', 'items' => $appraisal];

  $contracts = [];
  if ($l = $routeItem('hrd.contracts.index', 'Inbox Kontrak DW', 'contract')) $contracts[] = $l;
  if ($l = $routeItem('hrd.contract-templates.index', 'Template Kontrak DW', 'template')) $contracts[] = $l;
  if (count($contracts)) $hrdGroups[] = ['label' => 'Contracts', 'items' => $contracts];

  // Finance + Master Lanjutan — hanya untuk role finance dan superadmin
  if ($user instanceof User && ($user->isSuperAdmin() || $user->hasPermission('finance.view'))) {

    // Sub-grup: Dashboard & Import
    $financeCore = [];
    if ($l = $routeItem('finance.bpjs.index',            'Dashboard BPJS',        'bpjs'))    $financeCore[] = $l;
    if ($l = $routeItem('finance.import.index',          'Import Payroll',         'finance')) $financeCore[] = $l;
    if ($l = $routeItem('finance.annual-summary.index',      'Summary Gaji Tahunan',    'bpjs'))       $financeCore[] = $l;
    if ($l = $routeItem('finance.slip-gaji.index',            'Slip Gaji',               'finance'))    $financeCore[] = $l;
    if ($l = $routeItem('finance.bpjs-reconciliation.index',         'Rekonsiliasi BPJS',          'reconcile'))  $financeCore[] = $l;
    if ($l = $routeItem('finance.payroll-bpjs-reconciliation.index', 'Rekon BPJS vs Payroll',       'reconcile'))  $financeCore[] = $l;
    if (count($financeCore)) {
      $hrdGroups[] = ['label' => 'Finance', 'items' => $financeCore];
    }

    // Sub-grup: Master Data BPJS
    $financeMaster = [];
    if ($user->hasPermission('finance.bpjs.rates.manage') || $user->isSuperAdmin()) {
      if ($l = $routeItem('finance.bpjs-rates.index', 'Tarif BPJS Regional', 'bpjs_rates')) $financeMaster[] = $l;
    }
    if ($user->hasPermission('finance.bpjs.legal.manage') || $user->isSuperAdmin()) {
      if ($l = $routeItem('finance.bpjs-legal.index', 'Akun Legal BPJS', 'legal_entity')) $financeMaster[] = $l;
    }
    if ($user->hasPermission('finance.bpjs.view') || $user->isSuperAdmin()) {
      if ($l = $routeItem('finance.bpjs-assignments.index', 'BPJS Assignment', 'bpjs')) $financeMaster[] = $l;
      if ($l = $routeItem('finance.bpjs-assignments.cross-billing', 'Cross-billing BPJS', 'bpjs')) $financeMaster[] = $l;
    }
    if (count($financeMaster)) {
      $hrdGroups[] = ['label' => 'Master BPJS', 'items' => $financeMaster];
    }

    // Sub-grup: Master Struktur Perusahaan
    $financeStruct = [];
    if ($user->hasPermission('master.company_groups.manage') || $user->isSuperAdmin()) {
      if ($l = $routeItem('master.company-groups.index', 'Group Perusahaan',      'company_group')) $financeStruct[] = $l;
    }
    if ($user->hasPermission('master.legal_entities.manage') || $user->isSuperAdmin()) {
      if ($l = $routeItem('master.legal-entities.index', 'Entitas Legal (PT/CV)', 'legal_entity'))  $financeStruct[] = $l;
    }
    if ($user->hasPermission('master.regions.manage') || $user->isSuperAdmin()) {
      if ($l = $routeItem('master.regions.index', 'Regional & UMR', 'region')) $financeStruct[] = $l;
    }
    if (count($financeStruct)) {
      $hrdGroups[] = ['label' => 'Master Perusahaan', 'items' => $financeStruct];
    }

    // Sub-grup: Pengaturan Finance
    $financeConfig = [];
    if ($l = $routeItem('finance.department-mappings.index', 'Mapping Departemen', 'department')) $financeConfig[] = $l;
    if ($l = $routeItem('finance.settings.index', 'Setting Finance', 'settings')) $financeConfig[] = $l;
    if (count($financeConfig)) {
      $hrdGroups[] = ['label' => 'Pengaturan Finance', 'items' => $financeConfig];
    }
  }

  $settings = [];
  if (Route::has('hr-notifications.index')) {
    $settings[] = ['href' => route('hr-notifications.index'), 'label' => 'Inbox Notifikasi', 'ico' => $icon('notif'), 'badge' => $unread, 'route' => 'hr-notifications.index'];
  }
  if ($l = $routeItem('settings.general', 'Settings', 'settings')) $settings[] = $l;
  if ($user instanceof User && (bool) ($user->is_super_admin ?? false)) {
    if ($l = $routeItem('dashboard.landing-page.edit', 'Landing Page', 'settings')) $settings[] = $l;
    if ($l = $routeItem('dashboard.hr-team.index', 'Tim HR Landing', 'employee')) $settings[] = $l;
  }
  if ($l = $routeItem('dashboard.careers.index', 'Career Portal', 'candidate')) $settings[] = $l;
  if ($l = $routeItem('settings.recruitment-retention.edit', 'Retensi Recruitment', 'settings')) $settings[] = $l;
  if ($l = $routeItem('hrd.users.index', 'Users & Roles', 'settings')) $settings[] = $l;
  if ($l = $routeItem('hrd.roles.index', 'Roles & Permissions', 'settings')) $settings[] = $l;
  if ($user instanceof User && (bool) ($user->is_super_admin ?? false)) {
    if ($l = $routeItem('settings.api-docs', 'API Documentation', 'settings')) $settings[] = $l;
  }
  if (($user instanceof User && $user->hasRole(['admin', 'manager', 'finance']))
      && ($l = $routeItem('changelog.index', 'Changelog & Docs', 'report'))) {
    $settings[] = $l;
  }
  if (count($settings)) $hrdGroups[] = ['label' => 'Settings', 'items' => $settings];
@endphp

<div class="sidebar-section-label mb-2 text-xs uppercase text-muted">Menu</div>
<nav class="flex flex-col gap-2 text-sm">
  @if(!$isHrd)
    @foreach($userMenu as $item)
      @php
        $active = $isItemActive($item);
      @endphp
      <a class="menu-item {{ $active ? 'is-active' : '' }}" href="{{ $item['href'] }}">
        <span class="menu-ico">{!! $item['ico'] !!}</span>
        <span class="menu-label flex-1">{{ $item['label'] }}</span>
        @if(!empty($item['badge']))
          <span class="badge bg-brand/20 text-brand ring-1 ring-white/10">{{ $item['badge'] }}</span>
        @endif
      </a>
    @endforeach
  @else
    <div class="flex flex-col gap-1">
      @foreach($hrdGroups as $group)
      @php
          $groupHasActive = collect($group['items'])->contains('active', true);
      @endphp
      <div x-data="{ open: {{ $groupHasActive ? 'true' : 'false' }} }"
           class="mb-1">

          {{-- Group header — klik untuk expand/collapse --}}
          <button @click="open = !open"
                  type="button"
                  class="w-full flex items-center justify-between
                         px-3 py-2 rounded-lg text-left
                         text-xs font-semibold uppercase tracking-wider
                         {{ $groupHasActive
                             ? 'text-indigo-700 bg-indigo-50'
                             : 'text-gray-400 hover:text-gray-600 hover:bg-gray-50' }}
                         transition-colors duration-150">
              <span>{{ $group['label'] }}</span>
              <svg class="w-3.5 h-3.5 transition-transform duration-200"
                   :class="open ? 'rotate-180' : ''"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                        stroke-width="2.5" d="M19 9l-7 7-7-7"/>
              </svg>
          </button>

          {{-- Group items — expand/collapse dengan Alpine.js --}}
          <div x-show="open"
               x-transition:enter="transition-all duration-200 ease-out"
               x-transition:enter-start="opacity-0 -translate-y-1"
               x-transition:enter-end="opacity-100 translate-y-0"
               x-transition:leave="transition-all duration-150 ease-in"
               x-transition:leave-start="opacity-100 translate-y-0"
               x-transition:leave-end="opacity-0 -translate-y-1"
               class="mt-0.5 space-y-0.5 pl-1">
              @foreach($group['items'] as $item)
              <a class="menu-item {{ ($item['active'] ?? false) ? 'is-active' : '' }}"
                 href="{{ $item['href'] }}">
                  <span class="menu-ico">{!! $item['ico'] !!}</span>
                  <span class="menu-label flex-1">{{ $item['label'] }}</span>
                  @if(!empty($item['badge']))
                    <span class="badge bg-brand/20 text-brand ring-1 ring-white/10">{{ $item['badge'] }}</span>
                  @endif
              </a>
              @endforeach
          </div>

      </div>
      @endforeach
    </div>
  @endif

  <div class="sidebar-account-label mt-4 text-xs uppercase tracking-wider text-muted">Akun</div>
  @if(Route::has('profile.edit'))
    @php
      $accountItem = ['href' => route('profile.edit'), 'label' => 'Akun Login', 'ico' => $icon('profile'), 'route' => 'profile.edit'];
    @endphp
    <a class="menu-item {{ $isItemActive($accountItem) ? 'is-active' : '' }}" href="{{ $accountItem['href'] }}">
      <span class="menu-ico">{!! $accountItem['ico'] !!}</span>
      <span class="menu-label">Akun Login</span>
    </a>
  @endif

  <form method="POST" action="{{ route('logout') }}">
    @csrf
    <button class="menu-item w-full text-left" type="submit">
      <span class="menu-ico">{!! $icon('logout') !!}</span>
      <span class="menu-label">Logout</span>
    </button>
  </form>
</nav>





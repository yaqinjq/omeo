<?php

namespace App\Http\Controllers;

use App\Exports\EmployeesExport;
use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBpjsAssignment;
use App\Models\EmployeeSalaryHistory;
use App\Models\Outlet;
use App\Models\Position;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Services\ApplicantProfileResolver;
use App\Services\EmployeeProfileService;
use App\Services\PayrollProfileTargetService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly PayrollProfileTargetService $payrollProfileTargetService,
        private readonly ApplicantProfileResolver $applicantProfileResolver,
        private readonly EmployeeProfileService $employeeProfileService
    ) {
    }

    public function index(Request $request)
    {
        try {
            [$positions, $departments, $outlets] = $this->masterOptions();
            $viewMode = $request->query('view') === 'all' ? 'all' : 'compact';

            $employees = $this->buildEmployeeQuery($request)
                ->paginate($this->getPerPage($viewMode === 'all' ? 20 : 15))
                ->withQueryString();

            return view('employees.index', [
                'employees' => $employees,
                'positions' => $positions,
                'departments' => $departments,
                'outlets' => $outlets,
                'brandOptions' => $this->brandOptions(),
                'viewMode' => $viewMode,
                'filterOptions' => $request->only([
                    'q',
                    'department_id',
                    'brand_name',
                    'outlet_id',
                    'join_date_from',
                    'join_date_to',
                    'status',
                    'position_id',
                    'latest_school',
                    'tenure',
                ]),
                'tenureOptions' => $this->tenureOptions(),
                'hasBankAccountsTable' => Schema::hasTable('employee_bank_accounts'),
                'hasApplicantProfilesTable' => Schema::hasTable('applicant_profiles'),
            ]);
        } catch (\Throwable $e) {
            Log::error('employees.index FAILED', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => auth()->id(),
                'query' => $request->query(),
            ]);

            throw $e;
        }
    }

    public function export(Request $request)
    {
        $payloads = $this->buildEmployeeQuery($request)
            ->get()
            ->map(fn (Employee $employee) => $this->mapExportPayload($employee))
            ->values();

        $columns = $this->employeeExportColumns($payloads);
        $rows = $payloads
            ->map(fn (array $payload) => $this->flattenEmployeeExportPayload($payload, $columns))
            ->values();

        return Excel::download(
            new EmployeesExport($rows, collect($columns)->pluck('heading')->all()),
            'master-karyawan-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportHris()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        set_time_limit(120);

        $employees = \DB::table('employees')
            ->leftJoin('table_data_diri_user', \DB::raw('table_data_diri_user.no_ktp COLLATE utf8mb4_unicode_ci'), '=', 'employees.nik')
            ->leftJoin('table_alamat_user', 'table_alamat_user.id_data_diri', '=', 'table_data_diri_user.id_data_diri')
            ->leftJoin('table_agama', 'table_agama.id_agama', '=', 'table_data_diri_user.id_agama')
            ->leftJoin('table_status_pernikahan', 'table_status_pernikahan.id_status_pernikahan', '=', 'table_data_diri_user.id_status_pernikahan')
            ->leftJoin('table_kabupaten', 'table_kabupaten.id_kabupaten', '=', 'table_alamat_user.id_kabupaten')
            ->leftJoin('table_provinsi', 'table_provinsi.id_provinsi', '=', 'table_alamat_user.id_provinsi')
            ->select(
                'employees.*',
                'table_data_diri_user.tempat_lahir',
                'table_data_diri_user.tanggal_lahir',
                'table_data_diri_user.gender',
                'table_status_pernikahan.nama_status_pernikahan',
                'table_agama.nama_agama',
                'table_alamat_user.alamat_ktp',
                'table_kabupaten.nama_kabupaten',
                'table_provinsi.nama_provinsi'
            )
            ->orderBy('employees.employee_number')
            ->get();

        // Fallback: karyawan yang kosong di table_data_diri_user → coba applicant_profiles
        $emptyBirth = $employees->filter(fn ($e) => empty($e->tempat_lahir));
        if ($emptyBirth->count() > 0 && \Illuminate\Support\Facades\Schema::hasTable('applicant_profiles')) {
            $niks   = $emptyBirth->pluck('nik')->filter()->unique()->values()->all();
            $emails = $emptyBirth->pluck('email_private')
                ->filter()->map(fn ($e) => mb_strtolower($e))->unique()->values()->all();

            $apProfiles = \App\Models\ApplicantProfile::query()
                ->where(function ($q) use ($niks, $emails): void {
                    if ($niks !== []) {
                        $ph = implode(',', array_fill(0, count($niks), '?'));
                        $q->orWhereRaw(
                            "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(personal_json, '$.ktp_number')), '') IN ($ph)",
                            $niks
                        );
                    }
                    if ($emails !== []) {
                        $ph = implode(',', array_fill(0, count($emails), '?'));
                        $q->orWhereRaw(
                            "LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(personal_json, '$.email')), '')) IN ($ph)",
                            $emails
                        );
                    }
                })
                ->get();

            $apByNik   = $apProfiles
                ->filter(fn ($p) => !empty(data_get($p->personal_json, 'ktp_number')))
                ->keyBy(fn ($p) => (string) data_get($p->personal_json, 'ktp_number'));
            $apByEmail = $apProfiles
                ->filter(fn ($p) => !empty(data_get($p->personal_json, 'email')))
                ->keyBy(fn ($p) => mb_strtolower((string) data_get($p->personal_json, 'email')));

            foreach ($employees as $emp) {
                if (!empty($emp->tempat_lahir)) {
                    continue;
                }
                $ap = $apByNik->get((string) ($emp->nik ?? ''))
                    ?? $apByEmail->get(mb_strtolower((string) ($emp->email_private ?? '')));
                if (!$ap) {
                    continue;
                }
                $personal = is_array($ap->personal_json) ? $ap->personal_json : [];
                $address  = is_array($ap->address_json)  ? $ap->address_json  : [];
                $emp->tempat_lahir           = $personal['place_of_birth'] ?? '';
                $emp->tanggal_lahir          = $personal['date_of_birth']  ?? '';
                $emp->gender                 = $personal['gender']         ?? '';
                $emp->nama_agama             = $personal['religion']       ?? '';
                $emp->nama_status_pernikahan = $personal['marital_status'] ?? '';
                $emp->alamat_ktp             = $address['ktp_address']     ?? '';
                $emp->nama_kabupaten         = $address['ktp_city']        ?? '';
                $emp->nama_provinsi          = $address['ktp_province']    ?? '';
            }
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('IMPORT');

        $headers = [
            'A' => 'NAMA LENGKAP',
            'B' => 'NOMOR KK',
            'C' => 'NOMOR KTP',
            'D' => 'TEMPAT LAHIR',
            'E' => 'TANGGAL LAHIR',
            'F' => 'GENDER',
            'G' => 'MARITAL STATUS',
            'H' => 'RELIGION',
            'I' => 'NO TELP',
            'J' => 'NO HP',
            'K' => 'JOIN DATE',
            'L' => 'POSITION CODE',
            'M' => 'FLAGPROBATION',
            'N' => 'CCOMPANYCODE',
            'O' => 'CPROJECTCODE',
            'P' => 'CBISNISDIVISIONCODE',
            'Q' => 'CDEPTCODE',
            'R' => 'CDIVISI',
            'S' => 'NEMPLOYEESTATUS',
            'T' => 'CGOLONGAN',
            'U' => 'LOCPAYROLL',
            'V' => 'EMAIL',
            'W' => 'ALAMAT',
            'X' => 'KOTA',
            'Y' => 'PROVINSI',
            'Z' => 'SITE LOCATION',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . '1', $label);
        }

        $sheet->getStyle('A1:Z1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1E40AF'],
            ],
        ]);

        $row = 2;
        foreach ($employees as $emp) {
            $sheet->setCellValue('A' . $row, $emp->full_name ?? '');
            $sheet->setCellValue('B' . $row, $emp->kk_number ?? '');
            $sheet->setCellValue('C' . $row, $emp->nik ?? '');
            $sheet->setCellValue('D' . $row, $emp->tempat_lahir ?? '');
            $sheet->setCellValue('E' . $row, $emp->tanggal_lahir
                ? \Carbon\Carbon::parse($emp->tanggal_lahir)->format('d/m/Y') : '');
            $sheet->setCellValue('F' . $row, $emp->gender ?? '');
            $sheet->setCellValue('G' . $row, $emp->nama_status_pernikahan ?? '');
            $sheet->setCellValue('H' . $row, $emp->nama_agama ?? '');
            $sheet->setCellValue('I' . $row, ''); // no telp — dikosongkan, diisi HRD
            $sheet->setCellValue('J' . $row, $emp->phone_number ?? '');
            $sheet->setCellValue('K' . $row, $emp->join_date
                ? \Carbon\Carbon::parse($emp->join_date)->format('d/m/Y') : '');
            $sheet->setCellValue('L' . $row, $emp->jabatan ?? '');
            $sheet->setCellValue('M' . $row, ''); // flagprobation — dikosongkan
            $sheet->setCellValue('N' . $row, ''); // ccompanycode — dikosongkan
            $sheet->setCellValue('O' . $row, ''); // cprojectcode — dikosongkan
            $sheet->setCellValue('P' . $row, ''); // cbisnisdivisioncode — dikosongkan
            $sheet->setCellValue('Q' . $row, ''); // cdeptcode — dikosongkan
            $sheet->setCellValue('R' . $row, ''); // cdivisi — dikosongkan
            $sheet->setCellValue('S' . $row, ''); // nemployeestatus — dikosongkan
            $sheet->setCellValue('T' . $row, ''); // cgolongan — dikosongkan
            $sheet->setCellValue('U' . $row, ''); // locpayroll — dikosongkan
            $sheet->setCellValue('V' . $row, $emp->email_private ?? '');
            $sheet->setCellValue('W' . $row, $emp->alamat_ktp ?? '');
            $sheet->setCellValue('X' . $row, $emp->nama_kabupaten ?? '');
            $sheet->setCellValue('Y' . $row, $emp->nama_provinsi ?? '');
            $sheet->setCellValue('Z' . $row, ''); // site location — dikosongkan
            $row++;
        }

        foreach (array_keys($headers) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'Export_HRIS_Karyawan_' . now()->format('Ymd') . '.xlsx';

        while (ob_get_level()) {
            ob_end_clean();
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'hris_') . '.xlsx';
        $writer->save($tempFile);

        return response()->download(
            $tempFile,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function create()
    {
        [$positions, $departments, $outlets] = $this->masterOptions();

        return view('employees.create', [
            'employee' => new Employee(),
            'positions' => $positions,
            'departments' => $departments,
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(), $this->messages(), $this->attributes());
        $salaryPayload = $this->extractSalaryPayload($request);
        $accountSummary = null;

        if (! $this->hasEmployeeCurrentSalaryColumn()) {
            unset($data['current_salary']);
        }

        DB::transaction(function () use ($data, $salaryPayload, &$accountSummary): void {
            $employee = Employee::query()->create($data);
            $accountSummary = $this->syncEmployeeAccount($employee, $data, true);
            $this->syncLinkedUserName($employee);
            $this->storeSalaryHistory($employee, $salaryPayload, true);
        });

        $redirect = redirect()->route('employees.index')->with('success', 'Karyawan berhasil ditambahkan.');

        if (! empty($accountSummary['message'])) {
            $redirect->with('warning', $accountSummary['message']);
        }

        return $redirect;
    }

    public function edit(Employee $employee)
    {
        [$positions, $departments, $outlets] = $this->masterOptions();

        return view('employees.edit', compact('employee', 'positions', 'departments', 'outlets'));
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate($this->rules($employee->id), $this->messages(), $this->attributes());
        $salaryPayload = $this->extractSalaryPayload($request);
        $accountSummary = null;
        $hasSalaryColumn = $this->hasEmployeeCurrentSalaryColumn();
        $salaryChanged = $hasSalaryColumn
            && array_key_exists('current_salary', $data)
            && $this->salaryValuesDiffer($employee->current_salary, $data['current_salary']);

        if (! $hasSalaryColumn) {
            unset($data['current_salary']);
        }

        DB::transaction(function () use ($employee, $data, $salaryPayload, $salaryChanged, &$accountSummary): void {
            $employee->update($data);
            $accountSummary = $this->syncEmployeeAccount($employee, $data, false);
            $this->syncLinkedUserName($employee);
            $this->storeSalaryHistory($employee, $salaryPayload, $salaryChanged);
        });

        $redirect = redirect()->route('employees.index')->with('success', 'Karyawan berhasil diupdate.');

        if (! empty($accountSummary['message'])) {
            $redirect->with('warning', $accountSummary['message']);
        }

        return $redirect;
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Karyawan dihapus (soft delete).');
    }

    public function show(Employee $employee)
    {
        return view('employees.show', [
            'snapshot'        => $this->employeeProfileService->getEmployeeSnapshotForEmployee($employee),
            'bpjsAssignments' => Schema::hasTable('employee_bpjs_assignments')
                ? $employee->bpjsAssignments()->with('legalEntity', 'creator')->get()
                : collect(),
            'legalEntities'   => Schema::hasTable('legal_entities')
                ? \App\Models\LegalEntity::where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_name'])
                : collect(),
            'assignments'     => Schema::hasTable('employee_assignments')
                ? $employee->assignments()->with(['outlet', 'payrollOutlet', 'legalEntity', 'creator'])->get()
                : collect(),
            'outletOptions'   => \App\Models\Outlet::orderBy('name')->get(['id', 'name', 'outlet_type']),
        ]);
    }

    public function promote(Employee $employee)
    {
        if ($employee->status_employment !== 'probation') {
            return back()->with('error', 'Karyawan ini bukan dalam status probation.');
        }

        $employee->update(['status_employment' => 'permanent']);

        $user = User::where('email', $employee->email_private)->first();
        if ($user && $user->role === User::ROLE_PROBATION) {
            $user->update(['role' => User::ROLE_EMPLOYEE]);
        }

        return back()->with('success',
            $employee->full_name . ' berhasil dipromosikan menjadi Karyawan Tetap.'
        );
    }

    private function rules(?int $employeeId = null): array
    {
        $rules = [
            'nik' => ['required', 'string', 'max:50'],
            'employee_number' => ['nullable', 'string', 'max:100'],
            'external_id' => ['nullable', 'string', 'max:100'],
            'full_name' => ['required', 'string', 'max:255'],
            'email_private' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'join_date' => ['nullable', 'date'],
            'probation_end_date' => ['nullable', 'date'],
            'current_salary' => ['nullable', 'numeric', 'min:0'],
            'salary_effective_date' => ['nullable', 'date'],
            'salary_notes' => ['nullable', 'string', 'max:1000'],
            'status_employment' => ['required', Rule::in(['probation', 'contract', 'permanent', 'resigned'])],
            'department_id' => ['nullable', 'integer'],
            'position_id' => ['nullable', 'integer'],
            'outlet_id' => ['nullable', 'integer'],
        ];

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'employee_number')) {
            $rules['employee_number'][] = Rule::unique('employees', 'employee_number')->ignore($employeeId);
        }

        if (Schema::hasTable('positions')) {
            $rules['position_id'] = ['required', 'integer', Rule::exists('positions', 'id')];
        }

        if (Schema::hasTable('departments')) {
            $rules['department_id'][] = Rule::exists('departments', 'id');
        }

        if (Schema::hasTable('outlets')) {
            $rules['outlet_id'][] = Rule::exists('outlets', 'id');
        }

        return $rules;
    }

    private function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'email' => ':attribute harus berupa alamat email yang valid.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'integer' => ':attribute harus berupa angka bulat.',
            'numeric' => ':attribute harus berupa angka.',
            'min' => ':attribute minimal :min.',
            'max' => ':attribute maksimal :max karakter.',
            'unique' => ':attribute sudah digunakan.',
            'exists' => ':attribute tidak ditemukan.',
            'in' => ':attribute tidak valid.',
        ];
    }

    private function attributes(): array
    {
        return [
            'nik' => 'NIK',
            'employee_number' => 'NOKOM',
            'external_id' => 'External ID',
            'full_name' => 'Nama lengkap',
            'email_private' => 'Email',
            'phone_number' => 'No HP',
            'join_date' => 'Join Date',
            'probation_end_date' => 'Probation End Date',
            'current_salary' => 'Gaji saat ini',
            'salary_effective_date' => 'Tanggal efektif gaji',
            'salary_notes' => 'Catatan perubahan gaji',
            'status_employment' => 'Status',
            'department_id' => 'Departemen',
            'position_id' => 'Jabatan/Posisi',
            'outlet_id' => 'Outlet',
        ];
    }

    private function masterOptions(): array
    {
        $positions = collect();
        $departments = collect();
        $outlets = collect();

        if (Schema::hasTable('positions')) {
            $positions = Position::query()->orderBy('name')->get(['id', 'name']);
        }

        if (Schema::hasTable('departments')) {
            $departments = Department::query()->orderBy('name')->get(['id', 'name']);
        }

        if (Schema::hasTable('outlets')) {
            $outlets = Outlet::query()->operational()->orderBy('name')->get(['id', 'name', 'brand_name']);
        }

        return [$positions, $departments, $outlets];
    }

    private function buildEmployeeQuery(Request $request): Builder
    {
        $query = Employee::query()->with($this->employeeIndexRelations());

        if ($this->employeeHasColumn('join_date')) {
            $query->orderByDesc('join_date');
        }

        $query->orderByDesc('id');

        $search = trim((string) $request->query('q', ''));
        $searchableColumns = array_values(array_filter([
            'full_name',
            'nik',
            'employee_number',
            'email_private',
            'phone_number',
        ], fn (string $column) => $this->employeeHasColumn($column)));

        if ($search !== '' && ! empty($searchableColumns)) {
            $query->where(function (Builder $builder) use ($search, $searchableColumns): void {
                foreach ($searchableColumns as $index => $column) {
                    if ($index === 0) {
                        $builder->where($column, 'like', '%' . $search . '%');
                    } else {
                        $builder->orWhere($column, 'like', '%' . $search . '%');
                    }
                }
            });
        }

        if (($departmentId = (int) $request->query('department_id')) > 0 && $this->employeeHasColumn('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if (($positionId = (int) $request->query('position_id')) > 0 && $this->employeeHasColumn('position_id')) {
            $query->where('position_id', $positionId);
        }

        if (($outletId = (int) $request->query('outlet_id')) > 0 && $this->employeeHasColumn('outlet_id')) {
            $query->where('outlet_id', $outletId);
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '' && $this->employeeHasColumn('status_employment')) {
            $query->where('status_employment', $status);
        }

        $brandName = trim((string) $request->query('brand_name', ''));
        if ($brandName !== '' && Schema::hasTable('outlets') && Schema::hasColumn('outlets', 'brand_name')) {
            $query->whereHas('outlet', function (Builder $builder) use ($brandName): void {
                $builder->where('brand_name', $brandName);
            });
        }

        $joinDateFrom = trim((string) $request->query('join_date_from', ''));
        if ($joinDateFrom !== '' && $this->employeeHasColumn('join_date')) {
            $query->whereDate('join_date', '>=', $joinDateFrom);
        }

        $joinDateTo = trim((string) $request->query('join_date_to', ''));
        if ($joinDateTo !== '' && $this->employeeHasColumn('join_date')) {
            $query->whereDate('join_date', '<=', $joinDateTo);
        }

        $latestSchool = trim((string) $request->query('latest_school', ''));
        if ($latestSchool !== '' && Schema::hasTable('applicant_profiles')) {
            $query->whereHas('user.applicantProfile', function (Builder $builder) use ($latestSchool): void {
                $builder->where('education_json', 'like', '%' . $latestSchool . '%');
            });
        }

        $tenure = trim((string) $request->query('tenure', ''));
        if ($tenure !== '' && $this->employeeHasColumn('join_date')) {
            $this->applyTenureFilter($query, $tenure);
        }

        return $query;
    }

    private function employeeIndexRelations(): array
    {
        $relations = ['user'];

        if (Schema::hasTable('positions')) {
            $relations[] = 'position';
        }

        if (Schema::hasTable('departments')) {
            $relations[] = 'department';
        }

        if (Schema::hasTable('outlets')) {
            $relations[] = 'outlet';
        }

        if (Schema::hasTable('employee_bank_accounts')) {
            $relations[] = 'bankAccounts';
        }

        if (Schema::hasTable('applicant_profiles')) {
            $relations[] = 'user.applicantProfile';
        }

        return $relations;
    }

    private function employeeDetailRelations(): array
    {
        $relations = $this->employeeIndexRelations();

        if (Schema::hasTable('employee_bank_accounts') && Schema::hasTable('employee_bank_account_files')) {
            $relations[] = 'bankAccounts.files';
        }

        if (Schema::hasTable('employee_salary_histories')) {
            $relations[] = 'salaryHistories.actor';
        }

        if (Schema::hasTable('appraisals')) {
            $relations[] = 'appraisals.period';
            $relations[] = 'appraisals.details.indicator';
        }

        if (Schema::hasTable('training_participations')) {
            $relations[] = 'trainingParticipations.material';
        }

        return $relations;
    }

    private function applyTenureFilter(Builder $query, string $tenure): void
    {
        $today = now()->startOfDay();

        if ($tenure === 'lt_6') {
            $query->whereDate('join_date', '>', $today->copy()->subMonths(6)->toDateString());
            return;
        }

        if ($tenure === '6_12') {
            $query->whereBetween('join_date', [
                $today->copy()->subMonths(12)->toDateString(),
                $today->copy()->subMonths(6)->toDateString(),
            ]);
            return;
        }

        if ($tenure === '12_24') {
            $query->whereBetween('join_date', [
                $today->copy()->subMonths(24)->toDateString(),
                $today->copy()->subMonths(12)->toDateString(),
            ]);
            return;
        }

        if ($tenure === 'gt_24') {
            $query->whereDate('join_date', '<', $today->copy()->subMonths(24)->toDateString());
        }
    }

    private function brandOptions(): array
    {
        if (! Schema::hasTable('outlets') || ! Schema::hasColumn('outlets', 'brand_name')) {
            return [];
        }

        return Outlet::query()
            ->whereNotNull('brand_name')
            ->where('brand_name', '!=', '')
            ->orderBy('brand_name')
            ->distinct()
            ->pluck('brand_name')
            ->values()
            ->all();
    }

    private function tenureOptions(): array
    {
        return [
            'lt_6' => '< 6 bulan',
            '6_12' => '6 - 12 bulan',
            '12_24' => '1 - 2 tahun',
            'gt_24' => '> 2 tahun',
        ];
    }

    private function extractSalaryPayload(Request $request): array
    {
        return [
            'current_salary' => $request->filled('current_salary') ? (string) $request->input('current_salary') : null,
            'effective_date' => $request->filled('salary_effective_date') ? (string) $request->input('salary_effective_date') : null,
            'notes' => trim((string) $request->input('salary_notes', '')),
        ];
    }

    private function storeSalaryHistory(Employee $employee, array $salaryPayload, bool $forceCreate = false): void
    {
        if (! $this->hasEmployeeCurrentSalaryColumn() || ! Schema::hasTable('employee_salary_histories')) {
            return;
        }

        $amount = $salaryPayload['current_salary'] ?? null;
        if ($amount === null || trim((string) $amount) === '') {
            return;
        }

        $latestHistory = EmployeeSalaryHistory::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();

        $effectiveDate = trim((string) ($salaryPayload['effective_date'] ?? ''));
        if ($effectiveDate === '') {
            $effectiveDate = optional($employee->join_date)->format('Y-m-d') ?: now()->toDateString();
        }

        $notes = trim((string) ($salaryPayload['notes'] ?? ''));

        if (! $forceCreate && $latestHistory) {
            $sameAmount = ! $this->salaryValuesDiffer($latestHistory->amount, $amount);
            $sameDate = optional($latestHistory->effective_date)->format('Y-m-d') === $effectiveDate;

            if ($sameAmount && $sameDate) {
                return;
            }
        }

        EmployeeSalaryHistory::query()->create([
            'employee_id' => $employee->id,
            'amount' => $amount,
            'effective_date' => $effectiveDate,
            'changed_by' => Auth::id(),
            'source' => 'employee_master',
            'notes' => $notes !== '' ? $notes : null,
        ]);
    }

    private function syncEmployeeAccount(Employee $employee, array $data, bool $sendSetupLink): array
    {
        $email = mb_strtolower(trim((string) ($data['email_private'] ?? '')));
        if ($email === '') {
            return ['user' => null, 'message' => null];
        }

        $user = $this->resolveUserForEmployee($employee, $email, trim((string) ($data['nik'] ?? '')));
        $created = false;

        if ($user && $user->employee_id && (int) $user->employee_id !== (int) $employee->id) {
            throw ValidationException::withMessages([
                'email_private' => 'Email tersebut sudah dipakai akun karyawan lain.',
            ]);
        }

        if (! $user) {
            $user = User::query()->create([
                'name' => trim((string) ($data['full_name'] ?? $employee->full_name ?: 'Karyawan')),
                'email' => $email,
                'password' => Str::random(32),
                'role' => $this->defaultRoleForEmployee($employee),
                'employee_id' => $employee->id,
            ]);
            $created = true;
        } else {
            $user->forceFill([
                'name' => trim((string) ($data['full_name'] ?? $employee->full_name ?: $user->name)),
                'employee_id' => $employee->id,
                'role' => $this->defaultRoleForEmployee($employee),
            ])->save();
        }

        $employee->setRelation('user', $user);

        if (! $created || ! $sendSetupLink) {
            return [
                'user' => $user,
                'message' => $created ? 'Akun login karyawan berhasil dibuat.' : 'Akun login karyawan yang sudah ada berhasil dihubungkan ke data employee.',
            ];
        }

        try {
            $status = Password::sendResetLink(['email' => $user->email]);

            if ($status === Password::RESET_LINK_SENT) {
                return [
                    'user' => $user,
                    'message' => 'Akun login karyawan berhasil dibuat dan link set password sudah dikirim ke email terkait.',
                ];
            }

            return [
                'user' => $user,
                'message' => 'Akun login berhasil dibuat, tetapi link set password belum terkirim. Cek konfigurasi email server.',
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'user' => $user,
                'message' => 'Akun login berhasil dibuat, tetapi pengiriman link set password gagal. Cek SMTP/mail server.',
            ];
        }
    }

    private function resolveUserForEmployee(Employee $employee, string $email, string $nik): ?User
    {
        $employee->loadMissing('user');
        if ($employee->user) {
            return $employee->user;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user) {
            return $user;
        }

        if (Schema::hasTable('applicant_profiles')) {
            $profileQuery = ApplicantProfile::query()->with('user');

            if ($nik !== '') {
                $profile = $profileQuery
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(personal_json, '$.ktp_number')) = ?", [$nik])
                    ->first();

                if ($profile?->user) {
                    return $profile->user;
                }
            }

            $profile = ApplicantProfile::query()
                ->with('user')
                ->whereHas('user', function ($query) use ($email): void {
                    $query->whereRaw('LOWER(email) = ?', [$email]);
                })
                ->first();

            if ($profile?->user) {
                return $profile->user;
            }
        }

        return null;
    }

    private function defaultRoleForEmployee(Employee $employee): string
    {
        return $employee->status_employment === 'probation'
            ? User::ROLE_PROBATION
            : User::ROLE_EMPLOYEE;
    }

    private function resolveApplicantProfileForEmployee(Employee $employee): ?ApplicantProfile
    {
        return $this->applicantProfileResolver->resolveForEmployee($employee);
    }

    private function resolveCandidateForEmployee(Employee $employee): ?Candidate
    {
        $employee->loadMissing('user');

        if ($employee->user) {
            $candidate = $employee->user->resolveCandidate();
            if ($candidate) {
                return $candidate;
            }
        }

        $query = Candidate::query();
        $hasCondition = false;

        $email = mb_strtolower(trim((string) ($employee->email_private ?? '')));
        if ($email !== '') {
            $query->orWhereRaw('LOWER(email) = ?', [$email]);
            $hasCondition = true;
        }

        $nik = trim((string) ($employee->nik ?? ''));
        if ($nik !== '') {
            $query->orWhere('nik', $nik);
            $hasCondition = true;
        }

        if (! $hasCondition) {
            return null;
        }

        return $query->latest('id')->first();
    }

    private function resolveApplicantProfileFromCandidate(Candidate $candidate): ?ApplicantProfile
    {
        return $this->applicantProfileResolver->resolveForCandidate($candidate);
    }

    private function syncLinkedUserName(Employee $employee): void
    {
        $employee->loadMissing('user');

        if ($employee->user && trim((string) $employee->full_name) !== '') {
            $employee->user->forceFill(['name' => $employee->full_name])->save();
        }
    }

    private function hasEmployeeCurrentSalaryColumn(): bool
    {
        return Schema::hasTable('employees') && Schema::hasColumn('employees', 'current_salary');
    }

    private function employeeHasColumn(string $column): bool
    {
        return Schema::hasTable('employees') && Schema::hasColumn('employees', $column);
    }

    private function salaryValuesDiffer(mixed $oldValue, mixed $newValue): bool
    {
        return number_format((float) $oldValue, 2, '.', '') !== number_format((float) $newValue, 2, '.', '');
    }

    private function latestSchoolName(mixed $educations): string
    {
        if (! is_array($educations)) {
            return '-';
        }

        $rows = collect($educations)
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['school'] ?? '')) !== '')
            ->sortByDesc(function (array $row) {
                return (int) ($row['year_out'] ?? $row['year_in'] ?? 0);
            })
            ->values();

        if ($rows->isEmpty()) {
            return '-';
        }

        $latest = $rows->first();
        $school = trim((string) ($latest['school'] ?? '-'));
        $major = trim((string) ($latest['major'] ?? ''));

        return $major !== '' ? $school . ' - ' . $major : $school;
    }

    private function buildTrainingSummary(Collection $trainingParticipations): array
    {
        return [
            'total' => $trainingParticipations->count(),
            'completed' => $trainingParticipations->where('status', 'completed')->count(),
            'in_progress' => $trainingParticipations->where('status', 'in_progress')->count(),
            'latest' => $trainingParticipations->take(5),
            'programs' => collect(),
            'events' => collect(),
        ];
    }

    private function profileChangeRequestsForEmployee(Employee $employee, ?User $linkedUser = null): Collection
    {
        $linkedUser ??= $employee->user;

        if (! Schema::hasTable('profile_change_requests') || ! $linkedUser) {
            return collect();
        }

        return ProfileChangeRequest::query()
            ->with(['reviewer:id,name'])
            ->where('user_id', $linkedUser->id)
            ->where('entity_type', ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE)
            ->latest('id')
            ->limit(10)
            ->get();
    }

    private function employeeExportBaseColumns(): array
    {
        return [
            ['key' => 'nik', 'heading' => 'NIK'],
            ['key' => 'employee_number', 'heading' => 'NOKOM'],
            ['key' => 'external_id', 'heading' => 'External ID'],
            ['key' => 'full_name', 'heading' => 'Nama Lengkap'],
            ['key' => 'email_login', 'heading' => 'Email Login'],
            ['key' => 'email_private', 'heading' => 'Email Private'],
            ['key' => 'phone_number', 'heading' => 'No Telepon'],
            ['key' => 'status_employment', 'heading' => 'Status'],
            ['key' => 'join_date', 'heading' => 'Join Date'],
            ['key' => 'probation_end_date', 'heading' => 'Akhir Probation'],
            ['key' => 'tenure_months', 'heading' => 'Masa Kerja (bulan)'],
            ['key' => 'department', 'heading' => 'Departemen'],
            ['key' => 'position', 'heading' => 'Jabatan'],
            ['key' => 'brand', 'heading' => 'Brand'],
            ['key' => 'outlet', 'heading' => 'Outlet'],
            ['key' => 'current_salary', 'heading' => 'Gaji Saat Ini'],
            ['key' => 'primary_bank', 'heading' => 'Bank Utama'],
            ['key' => 'primary_account_number', 'heading' => 'No Rekening Utama'],
            ['key' => 'primary_account_holder', 'heading' => 'Atas Nama Rekening'],
            ['key' => 'npwp_number', 'heading' => 'NPWP'],
            ['key' => 'bpjs_kes_number', 'heading' => 'BPJS Kesehatan'],
            ['key' => 'bpjs_tk_number', 'heading' => 'BPJS Ketenagakerjaan'],
            ['key' => 'sim_number', 'heading' => 'SIM'],
            ['key' => 'passport_number', 'heading' => 'Passport'],
            ['key' => 'kk_number', 'heading' => 'KK'],
            ['key' => 'payroll_verified_at', 'heading' => 'Payroll Verified At'],
            ['key' => 'latest_school', 'heading' => 'Pendidikan Terakhir'],
            ['key' => 'pending_profile_change_at', 'heading' => 'Perubahan Profile Pending'],
            ['key' => 'latest_profile_approved_at', 'heading' => 'Perubahan Profile Disetujui Terakhir'],
            ['key' => 'ktp_number', 'heading' => 'No. KTP'],
            ['key' => 'name_as_ktp', 'heading' => 'Nama Sesuai KTP'],
            ['key' => 'personal_email', 'heading' => 'Email Applicant/Profile'],
            ['key' => 'whatsapp', 'heading' => 'WhatsApp'],
            ['key' => 'place_of_birth', 'heading' => 'Tempat Lahir'],
            ['key' => 'date_of_birth', 'heading' => 'Tanggal Lahir'],
            ['key' => 'time_of_birth', 'heading' => 'Jam Lahir'],
            ['key' => 'gender', 'heading' => 'Jenis Kelamin'],
            ['key' => 'religion', 'heading' => 'Agama'],
            ['key' => 'blood_type', 'heading' => 'Golongan Darah'],
            ['key' => 'marital_status', 'heading' => 'Status Pernikahan'],
            ['key' => 'marriage_date', 'heading' => 'Tanggal Menikah'],
            ['key' => 'photo_path', 'heading' => 'File Pas Foto'],
            ['key' => 'ktp_path', 'heading' => 'File Scan KTP'],
            ['key' => 'cv_path', 'heading' => 'File CV'],
            ['key' => 'signature_path', 'heading' => 'File Tanda Tangan'],
            ['key' => 'skck_latest_path', 'heading' => 'File SKCK Terbaru'],
            ['key' => 'applied_position_name', 'heading' => 'Posisi Dilamar'],
            ['key' => 'applied_department_name', 'heading' => 'Departemen Dilamar'],
            ['key' => 'applied_outlet_name', 'heading' => 'Outlet Dilamar'],
            ['key' => 'salary_expectation', 'heading' => 'Ekspektasi Gaji'],
            ['key' => 'preferred_job_scope', 'heading' => 'Ruang Lingkup Disukai'],
            ['key' => 'preferred_job_scope_other', 'heading' => 'Ruang Lingkup Lainnya'],
            ['key' => 'preferred_work_environment', 'heading' => 'Lingkungan Kerja Disukai'],
            ['key' => 'preferred_work_environment_other', 'heading' => 'Lingkungan Kerja Lainnya'],
            ['key' => 'willing_out_of_town', 'heading' => 'Bersedia Luar Kota'],
            ['key' => 'willing_outside_java', 'heading' => 'Bersedia Luar Jawa'],
            ['key' => 'willing_shift', 'heading' => 'Bersedia Shift'],
            ['key' => 'willing_overtime', 'heading' => 'Bersedia Lembur'],
            ['key' => 'is_smoker', 'heading' => 'Merokok'],
            ['key' => 'has_computer_skill', 'heading' => 'Keahlian Komputer'],
            ['key' => 'wears_glasses', 'heading' => 'Memakai Kacamata'],
            ['key' => 'glasses_right_eye', 'heading' => 'Ukuran Mata Kanan'],
            ['key' => 'glasses_left_eye', 'heading' => 'Ukuran Mata Kiri'],
            ['key' => 'join_reason', 'heading' => 'Alasan Bergabung'],
            ['key' => 'company_relation_note', 'heading' => 'Relasi di Perusahaan'],
            ['key' => 'career_goal', 'heading' => 'Target Karir'],
            ['key' => 'additional_information', 'heading' => 'Informasi Tambahan'],
            ['key' => 'available_start_date', 'heading' => 'Tanggal Siap Bergabung'],
            ['key' => 'honesty_statement', 'heading' => 'Pernyataan Kejujuran'],
            ['key' => 'ktp_address', 'heading' => 'Alamat KTP'],
            ['key' => 'ktp_rt', 'heading' => 'RT KTP'],
            ['key' => 'ktp_rw', 'heading' => 'RW KTP'],
            ['key' => 'ktp_kelurahan', 'heading' => 'Kelurahan KTP'],
            ['key' => 'ktp_kecamatan', 'heading' => 'Kecamatan KTP'],
            ['key' => 'ktp_city', 'heading' => 'Kota/Kabupaten KTP'],
            ['key' => 'ktp_province', 'heading' => 'Provinsi KTP'],
            ['key' => 'domicile_address', 'heading' => 'Alamat Domisili'],
            ['key' => 'domicile_rt', 'heading' => 'RT Domisili'],
            ['key' => 'domicile_rw', 'heading' => 'RW Domisili'],
            ['key' => 'domicile_kelurahan', 'heading' => 'Kelurahan Domisili'],
            ['key' => 'domicile_kecamatan', 'heading' => 'Kecamatan Domisili'],
            ['key' => 'domicile_city', 'heading' => 'Kota/Kabupaten Domisili'],
            ['key' => 'domicile_province', 'heading' => 'Provinsi Domisili'],
            ['key' => 'weight_kg', 'heading' => 'Berat Badan (kg)'],
            ['key' => 'height_cm', 'heading' => 'Tinggi Badan (cm)'],
            ['key' => 'had_accident', 'heading' => 'Pernah Kecelakaan'],
            ['key' => 'accident_year', 'heading' => 'Tahun Kecelakaan'],
            ['key' => 'accident_type', 'heading' => 'Jenis Kecelakaan'],
            ['key' => 'accident_effect', 'heading' => 'Dampak Kecelakaan'],
            ['key' => 'police_record', 'heading' => 'Riwayat Kepolisian'],
            ['key' => 'police_record_case', 'heading' => 'Kasus Kepolisian'],
            ['key' => 'police_record_year', 'heading' => 'Tahun Kasus Kepolisian'],
            ['key' => 'police_record_location', 'heading' => 'Lokasi Kasus Kepolisian'],
            ['key' => 'psychology_test', 'heading' => 'Pernah Psikotes'],
            ['key' => 'psychology_test_year', 'heading' => 'Tahun Psikotes'],
            ['key' => 'psychology_test_location', 'heading' => 'Lokasi Psikotes'],
            ['key' => 'psychology_test_purpose', 'heading' => 'Tujuan Psikotes'],
            ['key' => 'sim_file', 'heading' => 'File SIM'],
            ['key' => 'npwp_file', 'heading' => 'File NPWP'],
            ['key' => 'bpjs_kes_file', 'heading' => 'File BPJS Kesehatan'],
            ['key' => 'bpjs_tk_file', 'heading' => 'File BPJS Ketenagakerjaan'],
            ['key' => 'passport_file', 'heading' => 'File Passport'],
            ['key' => 'kk_file', 'heading' => 'File KK'],
            ['key' => 'applicant_profile_completed_at', 'heading' => 'Applicant Profile Completed At'],
        ];
    }

    private function employeeExportRepeatableSections(): array
    {
        return [
            'families' => [
                'label' => 'Keluarga',
                'fields' => [
                    'kk_no' => ['label' => 'No. KK', 'aliases' => ['kk_no', 'kk_number', 'family_card_number']],
                    'nik' => ['label' => 'NIK/KTP', 'aliases' => ['nik', 'ktp_number', 'id_number']],
                    'relation' => ['label' => 'Hubungan', 'aliases' => ['relation', 'relationship']],
                    'name' => ['label' => 'Nama', 'aliases' => ['name', 'full_name']],
                    'gender' => ['label' => 'Jenis Kelamin', 'aliases' => ['gender']],
                    'dob' => ['label' => 'Tanggal Lahir', 'aliases' => ['dob', 'date_of_birth', 'birth_date']],
                    'education' => ['label' => 'Pendidikan', 'aliases' => ['education']],
                    'job' => ['label' => 'Pekerjaan', 'aliases' => ['job', 'occupation']],
                    'phone' => ['label' => 'Telepon', 'aliases' => ['phone', 'phone_number']],
                    'address' => ['label' => 'Alamat', 'aliases' => ['address']],
                    'status_note' => ['label' => 'Catatan', 'aliases' => ['status_note', 'note', 'notes']],
                ],
            ],
            'emergency_contacts' => [
                'label' => 'Kontak Darurat',
                'fields' => [
                    'name' => ['label' => 'Nama', 'aliases' => ['name', 'full_name']],
                    'relation' => ['label' => 'Hubungan', 'aliases' => ['relation', 'relationship']],
                    'phone' => ['label' => 'Telepon', 'aliases' => ['phone', 'phone_number']],
                    'address' => ['label' => 'Alamat', 'aliases' => ['address']],
                ],
            ],
            'educations' => [
                'label' => 'Pendidikan',
                'fields' => [
                    'level' => ['label' => 'Jenjang', 'aliases' => ['level', 'education_level', 'degree']],
                    'school' => ['label' => 'Sekolah/Kampus', 'aliases' => ['school', 'school_name', 'institution']],
                    'major' => ['label' => 'Jurusan', 'aliases' => ['major', 'field_of_study']],
                    'year_in' => ['label' => 'Tahun Masuk', 'aliases' => ['year_in', 'start_year']],
                    'year_out' => ['label' => 'Tahun Lulus', 'aliases' => ['year_out', 'end_year', 'graduation_year']],
                    'gpa' => ['label' => 'Nilai/IPK', 'aliases' => ['gpa', 'score']],
                ],
            ],
            'languages' => [
                'label' => 'Bahasa',
                'fields' => [
                    'language' => ['label' => 'Bahasa', 'aliases' => ['language', 'name']],
                    'speaking' => ['label' => 'Lisan', 'aliases' => ['speaking', 'proficiency', 'level']],
                    'writing' => ['label' => 'Tulisan', 'aliases' => ['writing', 'proficiency', 'level']],
                ],
            ],
            'courses' => [
                'label' => 'Kursus',
                'fields' => [
                    'name' => ['label' => 'Nama Kursus', 'aliases' => ['name', 'course_name']],
                    'organizer' => ['label' => 'Penyelenggara', 'aliases' => ['organizer', 'institution']],
                    'year' => ['label' => 'Tahun', 'aliases' => ['year', 'completion_year']],
                    'certificate' => ['label' => 'Sertifikat', 'aliases' => ['certificate']],
                ],
            ],
            'work_experiences' => [
                'label' => 'Riwayat Kerja',
                'fields' => [
                    'company' => ['label' => 'Perusahaan', 'aliases' => ['company', 'company_name']],
                    'position' => ['label' => 'Jabatan', 'aliases' => ['position', 'job_title']],
                    'date_start' => ['label' => 'Mulai', 'aliases' => ['date_start', 'start_date']],
                    'date_end' => ['label' => 'Selesai', 'aliases' => ['date_end', 'end_date']],
                    'salary' => ['label' => 'Gaji', 'aliases' => ['salary']],
                    'reason' => ['label' => 'Alasan/Catatan', 'aliases' => ['reason', 'job_description', 'description']],
                ],
            ],
            'reference_contacts' => [
                'label' => 'Referensi',
                'fields' => [
                    'name' => ['label' => 'Nama', 'aliases' => ['name']],
                    'relation' => ['label' => 'Hubungan', 'aliases' => ['relation', 'relationship']],
                    'company' => ['label' => 'Perusahaan', 'aliases' => ['company']],
                    'phone' => ['label' => 'Telepon', 'aliases' => ['phone', 'phone_number']],
                ],
            ],
            'organizations' => [
                'label' => 'Organisasi',
                'fields' => [
                    'name' => ['label' => 'Nama Organisasi', 'aliases' => ['name', 'organization_name']],
                    'role' => ['label' => 'Peran/Jabatan', 'aliases' => ['role', 'position']],
                    'year' => ['label' => 'Tahun', 'aliases' => ['year', 'period']],
                ],
            ],
            'medical_histories' => [
                'label' => 'Riwayat Medis',
                'fields' => [
                    'illness' => ['label' => 'Penyakit', 'aliases' => ['illness', 'condition', 'name']],
                    'year' => ['label' => 'Tahun', 'aliases' => ['year']],
                    'hospitalized' => ['label' => 'Rawat Inap', 'aliases' => ['hospitalized', 'status']],
                    'note' => ['label' => 'Catatan', 'aliases' => ['note', 'notes', 'description']],
                ],
            ],
            'social_medias' => [
                'label' => 'Social Media',
                'fields' => [
                    'platform' => ['label' => 'Platform', 'aliases' => ['platform', 'name']],
                    'handle' => ['label' => 'Username/Link', 'aliases' => ['handle', 'account', 'username', 'url']],
                ],
            ],
            'bank_accounts' => [
                'label' => 'Rekening',
                'fields' => [
                    'bank_name' => ['label' => 'Bank', 'aliases' => ['bank_name', 'bank']],
                    'account_number' => ['label' => 'No Rekening', 'aliases' => ['account_number']],
                    'account_holder_name' => ['label' => 'Atas Nama', 'aliases' => ['account_holder_name']],
                    'is_primary' => ['label' => 'Utama', 'aliases' => ['is_primary']],
                ],
            ],
        ];
    }

    private function employeeExportColumns(Collection $payloads): array
    {
        $columns = array_map(
            static fn (array $column): array => $column + ['type' => 'base'],
            $this->employeeExportBaseColumns()
        );

        foreach ($this->employeeExportRepeatableSections() as $sectionKey => $section) {
            $maxRows = max(1, (int) $payloads->max(
                fn (array $payload): int => count($payload['sections'][$sectionKey] ?? [])
            ));

            for ($index = 0; $index < $maxRows; $index++) {
                foreach ($section['fields'] as $fieldKey => $field) {
                    $columns[] = [
                        'type' => 'section',
                        'section' => $sectionKey,
                        'index' => $index,
                        'key' => $fieldKey,
                        'heading' => $section['label'] . ' ' . ($index + 1) . ' - ' . $field['label'],
                    ];
                }
            }
        }

        return $columns;
    }

    private function flattenEmployeeExportPayload(array $payload, array $columns): array
    {
        return collect($columns)
            ->map(function (array $column) use ($payload) {
                if (($column['type'] ?? 'base') === 'base') {
                    return $this->exportCellValue($payload['base'][$column['key']] ?? null);
                }

                return $this->exportCellValue(data_get(
                    $payload,
                    'sections.' . $column['section'] . '.' . $column['index'] . '.' . $column['key']
                ));
            })
            ->all();
    }

    private function mapExportPayload(Employee $employee): array
    {
        $employee->loadMissing('user');

        $applicantProfile = $this->resolveApplicantProfileForEmployee($employee);
        $linkedUser = $employee->user ?: $applicantProfile?->user;
        $personal = $applicantProfile?->normalizedPersonalJson() ?? [];
        $address = is_array($applicantProfile?->address_json) ? $applicantProfile->address_json : [];
        $medical = is_array($applicantProfile?->medical_json) ? $applicantProfile->medical_json : [];
        $sections = $this->normalizeEmployeeExportSections($employee, $applicantProfile);

        $payroll = $this->payrollProfileTargetService->getCurrent((int) ($linkedUser?->id ?? 0), (int) $employee->id);
        $primaryBank = collect($sections['bank_accounts'] ?? [])
            ->firstWhere('is_primary', true)
            ?: collect($sections['bank_accounts'] ?? [])->first();

        $latestSchool = Schema::hasTable('applicant_profiles')
            ? $this->latestSchoolName($sections['educations'] ?? [])
            : '-';

        $pendingRequest = null;
        $latestApproved = null;

        if (Schema::hasTable('profile_change_requests') && $linkedUser) {
            $requests = ProfileChangeRequest::query()
                ->where('user_id', $linkedUser->id)
                ->where('entity_type', ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE)
                ->latest('id')
                ->get(['status', 'submitted_at', 'reviewed_at']);

            $pendingRequest = $requests->firstWhere('status', ProfileChangeRequest::STATUS_PENDING);
            $latestApproved = $requests->firstWhere('status', ProfileChangeRequest::STATUS_APPROVED);
        }

        return [
            'base' => [
                'nik' => $this->firstFilled([$employee->nik, data_get($personal, 'ktp_number')]),
                'employee_number' => $this->employeeHasColumn('employee_number') ? $employee->employee_number : null,
                'external_id' => $this->employeeHasColumn('external_id') ? $employee->external_id : null,
                'full_name' => $this->firstFilled([$employee->full_name, data_get($personal, 'full_name'), $linkedUser?->name]),
                'email_login' => $linkedUser?->email,
                'email_private' => $this->firstFilled([$employee->email_private, data_get($personal, 'email')]),
                'phone_number' => $this->firstFilled([$employee->phone_number, data_get($personal, 'phone_number'), data_get($personal, 'whatsapp')]),
                'status_employment' => $this->employeeHasColumn('status_employment') ? $employee->status_employment : null,
                'join_date' => $this->employeeHasColumn('join_date') ? $this->formatExportDate($employee->join_date) : null,
                'probation_end_date' => $this->employeeHasColumn('probation_end_date') ? $this->formatExportDate($employee->probation_end_date) : null,
                'tenure_months' => $this->tenureInMonths($this->employeeHasColumn('join_date') ? $employee->join_date : null),
                'department' => $employee->department?->name,
                'position' => $employee->position?->name ?? ($employee->jabatan ?? null),
                'brand' => $employee->outlet?->brand_name,
                'outlet' => $employee->outlet?->name,
                'current_salary' => $this->hasEmployeeCurrentSalaryColumn() ? $employee->current_salary : null,
                'primary_bank' => $primaryBank['bank_name'] ?? null,
                'primary_account_number' => $primaryBank['account_number'] ?? null,
                'primary_account_holder' => $primaryBank['account_holder_name'] ?? null,
                'npwp_number' => $payroll['npwp_number'] ?? null,
                'bpjs_kes_number' => $payroll['bpjs_kes_number'] ?? null,
                'bpjs_tk_number' => $payroll['bpjs_tk_number'] ?? null,
                'sim_number' => $payroll['sim_number'] ?? null,
                'passport_number' => $payroll['passport_number'] ?? null,
                'kk_number' => $payroll['kk_number'] ?? null,
                'payroll_verified_at' => $this->formatExportDateTime($payroll['payroll_verified_at'] ?? null),
                'latest_school' => $latestSchool,
                'pending_profile_change_at' => $this->formatExportDateTime($pendingRequest?->submitted_at),
                'latest_profile_approved_at' => $this->formatExportDateTime($latestApproved?->reviewed_at),
                'ktp_number' => $this->firstFilled([data_get($personal, 'ktp_number'), $employee->nik]),
                'name_as_ktp' => data_get($personal, 'full_name'),
                'personal_email' => data_get($personal, 'email'),
                'whatsapp' => data_get($personal, 'whatsapp'),
                'place_of_birth' => data_get($personal, 'place_of_birth'),
                'date_of_birth' => $this->formatExportDate(data_get($personal, 'date_of_birth')),
                'time_of_birth' => data_get($personal, 'time_of_birth'),
                'gender' => data_get($personal, 'gender'),
                'religion' => data_get($personal, 'religion'),
                'blood_type' => data_get($personal, 'blood_type'),
                'marital_status' => data_get($personal, 'marital_status'),
                'marriage_date' => $this->formatExportDate(data_get($personal, 'marriage_date')),
                'photo_path' => data_get($personal, 'photo_path'),
                'ktp_path' => data_get($personal, 'ktp_path'),
                'cv_path' => data_get($personal, 'cv_path'),
                'signature_path' => data_get($personal, 'signature_path'),
                'skck_latest_path' => data_get($personal, 'skck_latest_path'),
                'applied_position_name' => $this->firstFilled([data_get($personal, 'applied_position_name'), data_get($personal, 'applied_position')]),
                'applied_department_name' => $this->firstFilled([data_get($personal, 'applied_department_name'), data_get($personal, 'preferred_department'), data_get($personal, 'applied_department')]),
                'applied_outlet_name' => $this->firstFilled([data_get($personal, 'applied_outlet_name'), data_get($personal, 'preferred_outlet'), data_get($personal, 'applied_outlet')]),
                'salary_expectation' => data_get($personal, 'salary_expectation'),
                'preferred_job_scope' => data_get($personal, 'preferred_job_scope'),
                'preferred_job_scope_other' => data_get($personal, 'preferred_job_scope_other'),
                'preferred_work_environment' => data_get($personal, 'preferred_work_environment'),
                'preferred_work_environment_other' => data_get($personal, 'preferred_work_environment_other'),
                'willing_out_of_town' => data_get($personal, 'willing_out_of_town'),
                'willing_outside_java' => data_get($personal, 'willing_outside_java'),
                'willing_shift' => data_get($personal, 'willing_shift'),
                'willing_overtime' => data_get($personal, 'willing_overtime'),
                'is_smoker' => data_get($personal, 'is_smoker'),
                'has_computer_skill' => data_get($personal, 'has_computer_skill'),
                'wears_glasses' => data_get($personal, 'wears_glasses'),
                'glasses_right_eye' => data_get($personal, 'glasses_right_eye'),
                'glasses_left_eye' => data_get($personal, 'glasses_left_eye'),
                'join_reason' => data_get($personal, 'join_reason'),
                'company_relation_note' => data_get($personal, 'company_relation_note'),
                'career_goal' => data_get($personal, 'career_goal'),
                'additional_information' => data_get($personal, 'additional_information'),
                'available_start_date' => $this->formatExportDate(data_get($personal, 'available_start_date')),
                'honesty_statement' => data_get($personal, 'honesty_statement'),
                'ktp_address' => data_get($address, 'ktp_address'),
                'ktp_rt' => data_get($address, 'ktp_rt'),
                'ktp_rw' => data_get($address, 'ktp_rw'),
                'ktp_kelurahan' => data_get($address, 'ktp_kelurahan'),
                'ktp_kecamatan' => data_get($address, 'ktp_kecamatan'),
                'ktp_city' => data_get($address, 'ktp_city'),
                'ktp_province' => data_get($address, 'ktp_province'),
                'domicile_address' => data_get($address, 'domicile_address'),
                'domicile_rt' => data_get($address, 'domicile_rt'),
                'domicile_rw' => data_get($address, 'domicile_rw'),
                'domicile_kelurahan' => data_get($address, 'domicile_kelurahan'),
                'domicile_kecamatan' => data_get($address, 'domicile_kecamatan'),
                'domicile_city' => data_get($address, 'domicile_city'),
                'domicile_province' => data_get($address, 'domicile_province'),
                'weight_kg' => data_get($medical, 'weight_kg'),
                'height_cm' => data_get($medical, 'height_cm'),
                'had_accident' => data_get($medical, 'had_accident'),
                'accident_year' => data_get($medical, 'accident_year'),
                'accident_type' => data_get($medical, 'accident_type'),
                'accident_effect' => data_get($medical, 'accident_effect'),
                'police_record' => data_get($medical, 'police_record'),
                'police_record_case' => data_get($medical, 'police_record_case'),
                'police_record_year' => data_get($medical, 'police_record_year'),
                'police_record_location' => data_get($medical, 'police_record_location'),
                'psychology_test' => data_get($medical, 'psychology_test'),
                'psychology_test_year' => data_get($medical, 'psychology_test_year'),
                'psychology_test_location' => data_get($medical, 'psychology_test_location'),
                'psychology_test_purpose' => data_get($medical, 'psychology_test_purpose'),
                'sim_file' => $payroll['sim_file'] ?? null,
                'npwp_file' => $payroll['npwp_file'] ?? null,
                'bpjs_kes_file' => $payroll['bpjs_kes_file'] ?? null,
                'bpjs_tk_file' => $payroll['bpjs_tk_file'] ?? null,
                'passport_file' => $payroll['passport_file'] ?? null,
                'kk_file' => $payroll['kk_file'] ?? null,
                'applicant_profile_completed_at' => $this->formatExportDateTime($applicantProfile?->completed_at),
            ],
            'sections' => $sections,
        ];
    }

    private function normalizeEmployeeExportSections(Employee $employee, ?ApplicantProfile $profile): array
    {
        $definitions = $this->employeeExportRepeatableSections();
        $personal = $profile?->normalizedPersonalJson() ?? [];
        $sections = array_fill_keys(array_keys($definitions), []);

        $sections['families'] = $this->normalizeExportRows($profile?->families ?? [], $definitions['families']['fields']);
        $sections['emergency_contacts'] = $this->normalizeExportRows(data_get($personal, 'emergency_contacts', []), $definitions['emergency_contacts']['fields']);
        $sections['educations'] = $this->normalizeExportRows($profile?->educations ?? [], $definitions['educations']['fields']);
        $sections['languages'] = $this->normalizeExportRows($profile?->languages ?? [], $definitions['languages']['fields']);
        $sections['courses'] = $this->normalizeExportRows($profile?->courses ?? [], $definitions['courses']['fields']);
        $sections['work_experiences'] = $this->normalizeExportRows($profile?->work_experiences ?? [], $definitions['work_experiences']['fields']);
        $sections['reference_contacts'] = $this->normalizeExportRows($profile?->reference_contacts ?? [], $definitions['reference_contacts']['fields']);
        $sections['organizations'] = $this->normalizeExportRows($profile?->organizations ?? [], $definitions['organizations']['fields']);
        $sections['medical_histories'] = $this->normalizeExportRows($profile?->medical_histories ?? [], $definitions['medical_histories']['fields']);
        $sections['social_medias'] = $this->normalizeExportRows($profile?->social_medias ?? [], $definitions['social_medias']['fields']);

        if (Schema::hasTable('employee_bank_accounts')) {
            $employee->loadMissing('bankAccounts');
            $sections['bank_accounts'] = $this->normalizeExportRows(
                $employee->bankAccounts
                    ->map(fn ($account): array => [
                        'bank_name' => $account->bank_name,
                        'account_number' => $account->account_number,
                        'account_holder_name' => $account->account_holder_name,
                        'is_primary' => (bool) $account->is_primary,
                    ])
                    ->all(),
                $definitions['bank_accounts']['fields']
            );
        }

        return $sections;
    }

    private function normalizeExportRows(mixed $rows, array $fields): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $mapped = [];
            foreach ($fields as $key => $field) {
                $mapped[$key] = $this->firstFilled(array_map(
                    static fn (string $alias): mixed => data_get($row, $alias),
                    $field['aliases'] ?? [$key]
                ));
            }

            $hasAnyValue = collect($mapped)->contains(
                fn ($value): bool => $this->exportCellValue($value) !== null
            );

            if ($hasAnyValue) {
                $normalized[] = $mapped;
            }
        }

        return array_values($normalized);
    }

    private function firstFilled(array $candidates, mixed $fallback = null): mixed
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            if (is_string($candidate) && trim($candidate) === '') {
                continue;
            }

            if (is_array($candidate) && $candidate === []) {
                continue;
            }

            return $candidate;
        }

        return $fallback;
    }

    private function exportCellValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if (is_array($value)) {
            $flattened = collect($value)
                ->map(function ($item) {
                    if (is_array($item)) {
                        return collect($item)
                            ->map(function ($nestedValue, $nestedKey): ?string {
                                $value = $this->exportCellValue($nestedValue);

                                return $value === null ? null : $nestedKey . ': ' . $value;
                            })
                            ->filter(fn ($item): bool => $item !== null && trim((string) $item) !== '')
                            ->implode(', ');
                    }

                    return $this->exportCellValue($item);
                })
                ->filter(fn ($item): bool => $item !== null && trim((string) $item) !== '')
                ->implode(' | ');

            return $flattened !== '' ? $flattened : null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d H:i:s');
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function formatExportDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return ($value instanceof Carbon ? $value : Carbon::parse($value))->format('Y-m-d');
        } catch (\Throwable) {
            $value = trim((string) $value);
            return $value !== '' ? $value : null;
        }
    }

    private function formatExportDateTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return ($value instanceof Carbon ? $value : Carbon::parse($value))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            $value = trim((string) $value);
            return $value !== '' ? $value : null;
        }
    }

    private function tenureInMonths(mixed $joinDate): ?int
    {
        if (! $joinDate) {
            return null;
        }

        $date = $joinDate instanceof Carbon ? $joinDate : Carbon::parse($joinDate);

        return $date->diffInMonths(now());
    }

    private function getPerPage(int $default = 20): int
    {
        $perPage = (int) request('per_page', $default);
        return in_array($perPage, [10, 15, 20, 50, 100, 200, 9999]) ? $perPage : $default;
    }
}

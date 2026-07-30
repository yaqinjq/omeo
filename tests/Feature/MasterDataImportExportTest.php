<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Outlet;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MasterDataImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSupportTables();
    }

    public function test_department_template_export_and_import_work_safely(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        Department::query()->create(['code' => 'HRD', 'name' => 'Old Human Resource']);

        $file = UploadedFile::fake()->createWithContent('departments.csv', "code,name\nHRD,Human Resource\nOPS,Operasional\n");

        $this->actingAs($hrd)
            ->post(route('departments.import'), ['file' => $file])
            ->assertRedirect(route('departments.index'))
            ->assertSessionHas('import_summary.created', 1)
            ->assertSessionHas('import_summary.updated', 1)
            ->assertSessionHas('import_summary.failed', 0);

        $this->assertDatabaseHas('departments', ['code' => 'HRD', 'name' => 'Human Resource']);
        $this->assertDatabaseHas('departments', ['code' => 'OPS', 'name' => 'Operasional']);

        Excel::fake();
        Carbon::setTestNow('2026-03-30 10:00:00');

        $this->actingAs($hrd)->get(route('departments.template'))->assertOk();
        $this->actingAs($hrd)->get(route('departments.export'))->assertOk();

        Excel::assertDownloaded('template-import-departemen.xlsx');
        Excel::assertDownloaded('master-departemen-20260330-100000.xlsx');
        Carbon::setTestNow();
    }

    public function test_position_template_export_and_import_handle_case_insensitive_duplicates(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        Position::query()->create(['name' => 'crew outlet', 'level' => 1]);

        $file = UploadedFile::fake()->createWithContent('positions.csv', "name,level\nCrew Outlet,2\nBarista,1\n");

        $this->actingAs($hrd)
            ->post(route('positions.import'), ['file' => $file])
            ->assertRedirect(route('positions.index'))
            ->assertSessionHas('import_summary.created', 1)
            ->assertSessionHas('import_summary.updated', 1)
            ->assertSessionHas('import_summary.failed', 0);

        $this->assertDatabaseHas('positions', ['name' => 'Crew Outlet', 'level' => 2]);
        $this->assertDatabaseHas('positions', ['name' => 'Barista', 'level' => 1]);
        $this->assertSame(2, Position::query()->count());

        Excel::fake();
        Carbon::setTestNow('2026-03-30 11:00:00');

        $this->actingAs($hrd)->get(route('positions.template'))->assertOk();
        $this->actingAs($hrd)->get(route('positions.export'))->assertOk();

        Excel::assertDownloaded('template-import-posisi.xlsx');
        Excel::assertDownloaded('master-posisi-20260330-110000.xlsx');
        Carbon::setTestNow();
    }

    public function test_outlet_import_preview_and_confirm_update_existing_and_create_new_rows(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        Outlet::query()->create([
            'name' => 'OFFICE KK',
            'brand_name' => 'MKO GROUP',
            'external_id' => null,
            'location' => 'Surabaya Lama',
            'latitude' => -7.2969793,
            'longitude' => 112.7198096,
            'radius_meters' => 1500,
            'geofence_radius_m' => 1500,
            'timezone' => 'Asia/Jakarta',
            'work_start_time' => '08:00',
            'work_end_time' => '17:00',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'outlets.csv',
            "name,brand_name,external_id,location,maps_reference,latitude,longitude,radius_meters,timezone,work_start_time,work_end_time\n" .
            "OFFICE KK,MKO GROUP,,Surabaya Baru,,,,1500,Asia/Jakarta,08:00,22:00\n" .
            "Outlet Baru,MEO,OUT-002,Surabaya,,-7.286971,112.739687,60,Asia/Jakarta,09:00,18:00\n"
        );

        $response = $this->actingAs($hrd)->post(route('outlets.import'), ['file' => $file]);

        $response->assertRedirect(route('outlets.index'));
        $this->assertSame(1, data_get(session('outlet_import_preview'), 'summary.creates'));
        $this->assertSame(1, data_get(session('outlet_import_preview'), 'summary.updates'));
        $this->assertDatabaseCount('outlets', 1);

        $confirm = $this->actingAs($hrd)->post(route('outlets.import.confirm'), [
            'preview_token' => data_get(session('outlet_import_preview'), 'token'),
        ]);

        $confirm->assertRedirect(route('outlets.index'))
            ->assertSessionHas('import_summary.created', 1)
            ->assertSessionHas('import_summary.updated', 1)
            ->assertSessionHas('import_summary.failed', 0);

        $this->assertDatabaseHas('outlets', [
            'name' => 'OFFICE KK',
            'brand_name' => 'MKO GROUP',
            'work_end_time' => '22:00',
        ]);

        $this->assertDatabaseHas('outlets', [
            'name' => 'Outlet Baru',
            'external_id' => 'OUT-002',
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
        ]);
    }

    public function test_outlet_import_preview_normalizes_hhmmss_and_datetime_strings(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);

        $file = UploadedFile::fake()->createWithContent(
            'outlets-time.csv',
            "name,brand_name,timezone,work_start_time,work_end_time\n" .
            "Outlet Waktu,MEO,Asia/Jakarta,08:00:00,2026-04-14 22:00:00\n"
        );

        $this->actingAs($hrd)->post(route('outlets.import'), ['file' => $file])
            ->assertRedirect(route('outlets.index'));

        $preview = session('outlet_import_preview');
        $this->assertSame(0, data_get($preview, 'summary.failed'));
        $this->assertSame('08:00', data_get($preview, 'creates.0.work_start_time'));
        $this->assertSame('22:00', data_get($preview, 'creates.0.work_end_time'));

        $this->actingAs($hrd)->post(route('outlets.import.confirm'), [
            'preview_token' => data_get($preview, 'token'),
        ])->assertRedirect(route('outlets.index'));

        $this->assertDatabaseHas('outlets', [
            'name' => 'Outlet Waktu',
            'work_start_time' => '08:00',
            'work_end_time' => '22:00',
        ]);
    }

    public function test_outlet_import_preview_normalizes_excel_numeric_time_from_xlsx(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $file = $this->makeOutletSpreadsheet([
            ['Outlet Excel', 'MEO', 'OUT-XL-1', 'Surabaya', '', '', '', 50, 'Asia/Jakarta', 8 / 24, 22 / 24],
        ]);

        $this->actingAs($hrd)->post(route('outlets.import'), ['file' => $file])
            ->assertRedirect(route('outlets.index'));

        $preview = session('outlet_import_preview');
        $this->assertSame('08:00', data_get($preview, 'creates.0.work_start_time'));
        $this->assertSame('22:00', data_get($preview, 'creates.0.work_end_time'));

        $this->actingAs($hrd)->post(route('outlets.import.confirm'), [
            'preview_token' => data_get($preview, 'token'),
        ])->assertRedirect(route('outlets.index'));

        $this->assertDatabaseHas('outlets', [
            'name' => 'Outlet Excel',
            'external_id' => 'OUT-XL-1',
            'work_start_time' => '08:00',
            'work_end_time' => '22:00',
        ]);
    }

    public function test_outlet_crud_rejects_duplicate_external_id_for_new_records(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        Outlet::query()->create([
            'name' => 'Outlet Existing',
            'brand_name' => 'MEO',
            'external_id' => 'OUT-DUP-1',
            'timezone' => 'Asia/Jakarta',
        ]);

        $this->actingAs($hrd)
            ->post(route('outlets.store'), [
                'name' => 'Outlet Baru',
                'brand_name' => 'MEO',
                'external_id' => 'OUT-DUP-1',
                'timezone' => 'Asia/Jakarta',
                'radius_meters' => 50,
            ])
            ->assertSessionHasErrors('external_id');

        $this->assertDatabaseCount('outlets', 1);
    }

    public function test_outlet_update_allows_unchanged_legacy_duplicate_external_id(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $outlet = Outlet::query()->create([
            'name' => 'Outlet Legacy A',
            'brand_name' => 'MEO',
            'external_id' => 'OUT-LEGACY-DUP',
            'timezone' => 'Asia/Jakarta',
        ]);
        Outlet::query()->create([
            'name' => 'Outlet Legacy B',
            'brand_name' => 'MEO',
            'external_id' => 'OUT-LEGACY-DUP',
            'timezone' => 'Asia/Jakarta',
        ]);

        $this->actingAs($hrd)
            ->put(route('outlets.update', $outlet), [
                'name' => 'Outlet Legacy A Updated',
                'brand_name' => 'MEO',
                'external_id' => 'OUT-LEGACY-DUP',
                'location' => 'Surabaya',
                'timezone' => 'Asia/Jakarta',
                'radius_meters' => 50,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('outlets', [
            'id' => $outlet->id,
            'name' => 'Outlet Legacy A Updated',
            'external_id' => 'OUT-LEGACY-DUP',
        ]);
    }

    public function test_outlet_import_rejects_external_id_that_is_duplicated_in_existing_master_data(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        Outlet::query()->create([
            'name' => 'Outlet Duplicate A',
            'brand_name' => 'MEO',
            'external_id' => 'OUT-DUP-MASTER',
            'timezone' => 'Asia/Jakarta',
        ]);
        Outlet::query()->create([
            'name' => 'Outlet Duplicate B',
            'brand_name' => 'MEO',
            'external_id' => 'OUT-DUP-MASTER',
            'timezone' => 'Asia/Jakarta',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'outlets-duplicate-existing.csv',
            "name,brand_name,external_id,timezone\nOutlet Import,MEO,OUT-DUP-MASTER,Asia/Jakarta\n"
        );

        $this->actingAs($hrd)->post(route('outlets.import'), ['file' => $file])
            ->assertRedirect(route('outlets.index'));

        $preview = session('outlet_import_preview');
        $this->assertSame(1, data_get($preview, 'summary.failed'));
        $this->assertSame(0, data_get($preview, 'summary.creates'));
        $this->assertSame(0, data_get($preview, 'summary.updates'));
        $this->assertStringContainsString('External ID outlet ini sudah dipakai', data_get($preview, 'row_errors.0.message'));
    }

    public function test_outlet_import_preview_rejects_when_required_header_is_missing_with_clear_message(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);

        $file = UploadedFile::fake()->createWithContent(
            'outlets-invalid.csv',
            "name,external_id,timezone\nOutlet Minimal,OUT-001,Asia/Jakarta\n"
        );

        $this->actingAs($hrd)
            ->from(route('outlets.index'))
            ->post(route('outlets.import'), ['file' => $file])
            ->assertRedirect(route('outlets.index'))
            ->assertSessionHasErrors('file');

        $this->assertStringContainsString('brand_name', session('errors')->first('file'));
    }

    public function test_outlet_import_confirm_rejects_invalid_preview_token(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);

        $this->actingAs($hrd)
            ->post(route('outlets.import.confirm'), ['preview_token' => 'invalid-token'])
            ->assertRedirect(route('outlets.index'))
            ->assertSessionHas('error');
    }

    public function test_outlet_import_cancel_clears_preview_without_changing_database(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $file = UploadedFile::fake()->createWithContent(
            'outlets-cancel.csv',
            "name,brand_name,timezone,work_start_time,work_end_time\nOutlet Cancel,MEO,Asia/Jakarta,08:00,22:00\n"
        );

        $this->actingAs($hrd)->post(route('outlets.import'), ['file' => $file])
            ->assertRedirect(route('outlets.index'));

        $this->assertNotNull(session('outlet_import_preview'));

        $this->actingAs($hrd)->post(route('outlets.import.cancel'))
            ->assertRedirect(route('outlets.index'))
            ->assertSessionHas('warning');

        $this->assertNull(session('outlet_import_preview'));
        $this->assertDatabaseCount('outlets', 0);
    }

    private function makeOutletSpreadsheet(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'name', 'brand_name', 'external_id', 'location', 'maps_reference', 'latitude', 'longitude', 'radius_meters', 'timezone', 'work_start_time', 'work_end_time',
        ], null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'outlets-xlsx-');
        if ($path === false) {
            throw new \RuntimeException('Gagal membuat file temporary untuk test xlsx.');
        }

        $xlsxPath = $path . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxPath);

        return new UploadedFile($xlsxPath, 'outlets.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function ensureSupportTables(): void
    {
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->nullable();
                $table->string('name')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->unsignedInteger('level')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('outlets')) {
            Schema::create('outlets', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('location')->nullable();
                $table->string('brand_name')->nullable();
                $table->string('external_id')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->integer('radius_meters')->nullable();
                $table->integer('geofence_radius_m')->nullable();
                $table->string('timezone')->nullable();
                $table->time('work_start_time')->nullable();
                $table->time('work_end_time')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('outlet_permits')) {
            Schema::create('outlet_permits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('outlet_id');
                $table->string('permit_type')->nullable();
                $table->timestamps();
            });
        }
    }
}

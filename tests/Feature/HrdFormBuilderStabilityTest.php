<?php

namespace Tests\Feature;

use App\Models\AssessmentForm;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrdFormBuilderStabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSupportTables();
    }

    public function test_form_builder_core_pages_load_for_hrd(): void
    {
        $hrd = User::factory()->create([
            'role' => User::ROLE_HRD,
        ]);

        $form = AssessmentForm::query()->create([
            'code' => 'FORM-IQ-001',
            'name' => 'IQ Stabilitas',
            'type' => AssessmentForm::TYPE_IQ,
            'duration_minutes' => 30,
            'is_active' => true,
            'created_by' => $hrd->id,
        ]);

        $this->actingAs($hrd)
            ->get(route('hrd.forms.index'))
            ->assertOk()
            ->assertSeeText('Form Dinamis Assessment');

        $this->actingAs($hrd)
            ->get(route('hrd.forms.create'))
            ->assertOk()
            ->assertSeeText('Buat Form Dinamis');

        $this->actingAs($hrd)
            ->get(route('hrd.forms.edit', $form))
            ->assertOk()
            ->assertSeeText('Builder Form: IQ Stabilitas');
    }

    public function test_form_import_pages_load_for_hrd(): void
    {
        $hrd = User::factory()->create([
            'role' => User::ROLE_HRD,
        ]);

        $this->actingAs($hrd)
            ->get(route('hrd.import.iq.index'))
            ->assertOk();

        $this->actingAs($hrd)
            ->get(route('hrd.import.disc.index'))
            ->assertOk();

        $this->actingAs($hrd)
            ->get(route('hrd.import.choice.index', ['type' => AssessmentForm::TYPE_TIU]))
            ->assertOk();
    }

    private function ensureSupportTables(): void
    {
        if (! Schema::hasTable('forms')) {
            Schema::create('forms', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->nullable();
                $table->string('name')->nullable();
                $table->text('description')->nullable();
                $table->string('type')->nullable();
                $table->integer('duration_minutes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('form_questions')) {
            Schema::create('form_questions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('form_id');
                $table->integer('position')->default(1);
                $table->text('question_text')->nullable();
                $table->string('question_image_path')->nullable();
                $table->string('question_type')->nullable();
                $table->boolean('is_required')->default(false);
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('form_options')) {
            Schema::create('form_options', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('question_id');
                $table->integer('position')->default(1);
                $table->string('option_text')->nullable();
                $table->string('value')->nullable();
                $table->integer('weight')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }
}

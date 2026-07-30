<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('form_questions') || ! Schema::hasColumn('form_questions', 'question_type')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE form_questions MODIFY question_type ENUM('short_text','paragraph','radio','checkbox','dropdown','rating','linear_scale','image_upload','file_upload') NOT NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('form_questions') || ! Schema::hasColumn('form_questions', 'question_type')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE form_questions MODIFY question_type ENUM('short_text','paragraph','radio','checkbox','dropdown','rating','linear_scale') NOT NULL");
    }
};

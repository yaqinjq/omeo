<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bpjs_official_bills', function (Blueprint $table) {
            if (! Schema::hasColumn('bpjs_official_bills', 'pdf_path')) {
                $table->string('pdf_path', 500)->nullable()->after('source_file_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bpjs_official_bills', function (Blueprint $table) {
            if (Schema::hasColumn('bpjs_official_bills', 'pdf_path')) {
                $table->dropColumn('pdf_path');
            }
        });
    }
};

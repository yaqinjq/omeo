<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->boolean('is_builder_mode')->default(true)->after('is_active');
            $table->text('letterhead_html')->nullable()->after('logo_path');
            $table->string('document_title')->nullable()->after('letterhead_html');
            $table->longText('opening_paragraph')->nullable()->after('document_title');
            $table->longText('main_content')->nullable()->after('opening_paragraph');
            $table->longText('closing_paragraph')->nullable()->after('main_content');
            $table->json('signatories_json')->nullable()->after('closing_paragraph');
        });
    }

    public function down(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropColumn([
                'is_builder_mode',
                'letterhead_html',
                'document_title',
                'opening_paragraph',
                'main_content',
                'closing_paragraph',
                'signatories_json',
            ]);
        });
    }
};

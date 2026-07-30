<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('department_category_mappings', function (Blueprint $table) {
            $table->id();
            $table->enum('match_type', ['sub_dept', 'position'])
                  ->comment('sub_dept = level 1 default, position = level 2 override');
            $table->string('match_value', 150)
                  ->comment('Nilai Sub Dept atau Posisi yang dicocokkan (case-insensitive)');
            $table->enum('category', ['bar','kitchen','service','office','prive','lainnya'])
                  ->comment('Kategori summary: BPJS dan SC dihitung langsung dari kolom angka, tidak via mapping ini');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['match_type','match_value']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('department_category_mappings');
    }
};

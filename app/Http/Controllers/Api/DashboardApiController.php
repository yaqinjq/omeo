<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardApiController extends Controller
{
    public function summary()
    {
        $schema = DB::getSchemaBuilder();
        $safeCount = function(string $t) use ($schema) {
            if (!$schema->hasTable($t)) return 0;
            return (int) DB::table($t)->count();
        };

        return response()->json([
            'employees_total' => $safeCount('employees'),
            'training_materials' => $safeCount('training_materials'),
            'training_participations' => $safeCount('training_participations'),
            'candidates_total' => $safeCount('candidates'),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppraisalApiController extends Controller
{
    public function submit(Request $request)
    {
        abort(501, 'Endpoint ini belum tersedia.');

        $data = $request->validate([
            'appraisal_id' => ['required','integer'],
            'items' => ['required','array'],
            'items.*.appraisal_indicator_id' => ['required','integer'],
            'items.*.score' => ['required','numeric'],
            'items.*.comment' => ['nullable','string'],
        ]);

        $schema = DB::getSchemaBuilder();
        if (!$schema->hasTable('appraisal_results') || !$schema->hasTable('appraisal_result_items') || !$schema->hasTable('appraisal_indicators')) {
            return response()->json(['ok'=>false,'message'=>'Missing appraisal tables.'], 400);
        }

        $resultId = DB::table('appraisal_results')->insertGetId([
            'appraisal_id' => $data['appraisal_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $total = 0.0;
        foreach ($data['items'] as $it) {
            $ind = DB::table('appraisal_indicators')->where('id',$it['appraisal_indicator_id'])->first();
            $weight = $ind->weight ?? 1;
            $score = (float)$it['score'];
            $total += $score * (float)$weight;

            DB::table('appraisal_result_items')->insert([
                'appraisal_result_id' => $resultId,
                'appraisal_indicator_id' => $it['appraisal_indicator_id'],
                'score' => $score,
                'comment' => $it['comment'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('appraisal_results')->where('id',$resultId)->update(['final_score'=>$total,'updated_at'=>now()]);

        return response()->json(['ok'=>true,'result_id'=>$resultId,'final_score'=>$total]);
    }
}

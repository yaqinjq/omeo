<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appraisal;
use App\Models\AppraisalDetail;
use App\Services\AppraisalScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppraisalController extends Controller
{
    /**
     * Submit form appraisal (dynamic).
     * Payload:
     * - appraisal_id
     * - items: [{appraisal_indicator_id, score, comment}]
     */
    public function submit(Request $request, AppraisalScoringService $scoring)
    {
        $data = $request->validate([
            'appraisal_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.appraisal_indicator_id' => ['required', 'integer'],
            'items.*.score' => ['required', 'numeric'],
            'items.*.comment' => ['nullable', 'string'],
        ]);

        $appraisal = Appraisal::findOrFail($data['appraisal_id']);

        DB::transaction(function () use ($data, $appraisal, $scoring) {
            AppraisalDetail::where('appraisal_id', $appraisal->id)->delete();

            foreach ($data['items'] as $it) {
                AppraisalDetail::create([
                    'appraisal_id' => $appraisal->id,
                    'appraisal_indicator_id' => $it['appraisal_indicator_id'],
                    'score' => $it['score'],
                    'comment' => $it['comment'] ?? null,
                ]);
            }

            $final = $scoring->calculateFinalScore($appraisal);

            $appraisal->update([
                'final_score' => $final,
                'status' => 'submitted',
                'date_appraised' => now(),
            ]);
        });

        return response()->json(['ok' => true]);
    }
}

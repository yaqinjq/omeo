<?php

namespace Tests\Unit;

use App\Models\AssessmentForm;
use App\Models\FormOption;
use App\Models\FormQuestion;
use App\Services\FormScoringService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FormScoringServiceTest extends TestCase
{
    public function test_compute_iq_score_from_option_weight(): void
    {
        $form = new AssessmentForm();
        $form->type = AssessmentForm::TYPE_IQ;

        $question = new FormQuestion();
        $question->id = 10;
        $question->question_type = FormQuestion::TYPE_RADIO;

        $optionOne = new FormOption();
        $optionOne->id = 1;
        $optionOne->weight = 0;

        $optionTwo = new FormOption();
        $optionTwo->id = 2;
        $optionTwo->weight = 5;

        $question->setRelation('options', new Collection([$optionOne, $optionTwo]));

        $service = new FormScoringService();
        $result = $service->compute($form, new Collection([$question]), ['q_10' => 2]);

        $this->assertSame(5, $result['score']);
        $this->assertSame(5, $result['iq_score']);
        $this->assertSame(AssessmentForm::TYPE_IQ, $result['category']);
    }

    public function test_compute_weighted_score_for_tiu(): void
    {
        $form = new AssessmentForm();
        $form->type = AssessmentForm::TYPE_TIU;

        $question = new FormQuestion();
        $question->id = 15;
        $question->question_type = FormQuestion::TYPE_RADIO;

        $optionOne = new FormOption();
        $optionOne->id = 21;
        $optionOne->weight = 3;

        $optionTwo = new FormOption();
        $optionTwo->id = 22;
        $optionTwo->weight = 7;

        $question->setRelation('options', new Collection([$optionOne, $optionTwo]));

        $service = new FormScoringService();
        $result = $service->compute($form, new Collection([$question]), ['q_15' => 22]);

        $this->assertSame(7, $result['score']);
        $this->assertSame(AssessmentForm::TYPE_TIU, $result['category']);
        $this->assertArrayNotHasKey('iq_score', $result);
    }

    public function test_compute_disc_dual_axis_graphs(): void
    {
        $form = new AssessmentForm();
        $form->type = AssessmentForm::TYPE_DISC;

        $question = new FormQuestion();
        $question->id = 11;
        $question->question_type = FormQuestion::TYPE_RADIO;
        $question->settings = ['disc_mode' => 'dual_axis'];

        $optionD = new FormOption();
        $optionD->id = 7;
        $optionD->meta = ['disc_axis' => 'D', 'disc_axis_most' => 'D', 'disc_axis_least' => 'C'];

        $optionI = new FormOption();
        $optionI->id = 8;
        $optionI->meta = ['disc_axis' => 'I', 'disc_axis_most' => 'I', 'disc_axis_least' => 'S'];

        $question->setRelation('options', new Collection([$optionD, $optionI]));

        $service = new FormScoringService();
        $result = $service->compute($form, new Collection([$question]), ['q_11' => ['most' => 7, 'least' => 8]]);

        $this->assertSame(1, $result['disc_most_axis']['D']);
        $this->assertSame(1, $result['disc_least_axis']['S']);
        $this->assertSame(1, $result['disc_axis']['D']);
        $this->assertSame(-1, $result['disc_axis']['S']);
        $this->assertSame('D', data_get($result, 'summary.dominant_axis'));
        $this->assertArrayHasKey('graphs', $result);
    }

    public function test_compute_disc_legacy_single_axis_remains_supported(): void
    {
        $form = new AssessmentForm();
        $form->type = AssessmentForm::TYPE_DISC;

        $question = new FormQuestion();
        $question->id = 12;
        $question->question_type = FormQuestion::TYPE_RADIO;

        $optionC = new FormOption();
        $optionC->id = 9;
        $optionC->meta = ['disc_axis' => 'C'];

        $question->setRelation('options', new Collection([$optionC]));

        $service = new FormScoringService();
        $result = $service->compute($form, new Collection([$question]), ['q_12' => 9]);

        $this->assertSame(1, $result['disc_legacy_axis']['C']);
        $this->assertSame(1, $result['disc_axis']['C']);
    }
}

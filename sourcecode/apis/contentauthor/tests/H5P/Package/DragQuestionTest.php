<?php

namespace Tests\H5P\Package;

use App\H5PLibrary;
use Tests\TestCase;
use Tests\db\TestH5PSeeder;
use App\Libraries\H5P\Packages\DragQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DragQuestionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function alterSemantics()
    {
        $this->seed(TestH5PSeeder::class);
        $library = H5PLibrary::find(199);
        $expectedSemantics = json_decode($library->semantics);
        $newSemantics = json_decode($library->semantics);

        $dragQuestion = new DragQuestion();
        $dragQuestion->alterSemantics($newSemantics);

        $this->assertEquals($expectedSemantics, $newSemantics);

        $expectedSemantics = json_decode($library->semantics);
        $fullScreenObject = $expectedSemantics[5]->fields[8];
        $fullScreenObject->widget = 'showWhen';
        $fullScreenObject->showWhen = (object)[
            'detach' => true,
            'rules' => []
        ];
        $expectedSemantics[5]->fields[8] = $fullScreenObject;

        $newSemantics = json_decode($library->semantics);

        config(['h5p.H5P_DragQuestion.disableFullscreen' => true]);

        $dragQuestion = new DragQuestion();
        $dragQuestion->alterSemantics($newSemantics);

        $this->assertEquals($expectedSemantics, $newSemantics);
    }
}

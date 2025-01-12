<?php

namespace Tests\API;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Controllers\API\Handler\ContentTypeHandler;
use App\Libraries\H5P\Packages\MultiChoice;
use App\Libraries\H5P\Packages\QuestionSet;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\db\TestH5PSeeder;
use Tests\TestCase;

use Tests\Traits\WithFaker;

class ContentTypeControllerTest extends TestCase
{
    use WithFaker, RefreshDatabase, WithoutMiddleware;

    public function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();
        $this->seed(TestH5PSeeder::class);
        $this->setUpFaker();
    }

    /**
     * @test
     */
    public function ContentTypeController_validRequest_thenSuccess()
    {
        $authId = $this->faker->uuid;
        $title = $this->faker->sentence;
        $question = $this->faker->sentence;
        $options = $this->faker->sentences(3);
        $contentId = $this->faker->numberBetween(1, 1000);

        $contentHandler = $this->createPartialMock(ContentTypeHandler::class, [
            'storeQuestionset'
        ]);
        $contentHandler->method('storeQuestionset')->willReturn(['id' => $contentId]);
        app()->instance(ContentTypeHandler::class, $contentHandler);

        $data = [
            'authId' => $authId,
            'license' => "BY",
            'sharing' => false,
            'title' => $title,
            'type' => QuestionSet::$machineName,
            'questions' => [
                [
                    'type' => MultiChoice::$machineName,
                    'text' => $question,
                    'answers' => [
                        [
                            'text' => $options[0],
                            'correct' => true,
                        ],
                        [
                            'text' => $options[1],
                            'correct' => false,
                        ],
                        [
                            'text' => $options[2],
                            'correct' => true,
                        ],
                    ]
                ]
            ]
        ];

        $response = $this->postJson("api/v1/contenttypes/questionsets", $data);
        $response
            ->assertSuccessful()
            ->assertExactJson([
                'id' => $contentId,
                'type' => QuestionSet::$machineName
            ]);
    }

    /**
     * @test
     */
    public function ContentTypeConrollerJSON_invalidData_thenFailure()
    {
        $this->postJson("api/v1/contenttypes/questionsets")
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson([ // Test "shape" of response
                'message' => true,
                'errors' => [
                    'title' => [true],
                    'sharing' => [true],
                    'license' => [true],
                    'questions' => [true],
                ]
            ]);
    }

    /**
     * @test
     */
    public function ContentTypeConrollerPOST_invalidData_thenFailure()
    {
        $this->post("api/v1/contenttypes/questionsets")
            ->assertStatus(Response::HTTP_FOUND);
    }

}

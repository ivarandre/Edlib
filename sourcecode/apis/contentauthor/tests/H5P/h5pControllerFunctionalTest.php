<?php

namespace Tests\H5P;

use App\Events\ContentCreated;
use App\Events\ContentCreating;
use App\Events\ContentDeleted;
use App\Events\ContentDeleting;
use App\Events\ContentUpdated;
use App\Events\ContentUpdating;
use App\H5PContent;
use App\H5pLti;
use App\Http\Controllers\H5PController;
use App\Libraries\H5P\h5p;
use Cerpus\VersionClient\VersionClient;
use Cerpus\VersionClient\VersionData;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Ramsey\Uuid\Uuid;
use stdClass;
use Tests\db\TestH5PSeeder;
use Tests\TestCase;
use Tests\Traits\MockLicensingTrait;
use Tests\Traits\MockMQ;
use Tests\Traits\ResetH5PStatics;


class h5pControllerFunctionalTest extends TestCase
{
    use RefreshDatabase, MockLicensingTrait, ResetH5PStatics, MockMQ;

    protected $fakedEvents = [
        ContentCreating::class,
        ContentCreated::class,
        ContentUpdating::class,
        ContentUpdated::class,
        ContentDeleting::class,
        ContentDeleted::class,
    ];

    public function assertPreConditions(): void
    {
        $this->seed(TestH5PSeeder::class);
    }

    /**
     * @test
     *
     */
    public function addAuthorToParameters1()
    {
        $this->withSession(["name" => "user"]);
        $params = H5PController::addAuthorToParameters('{"params": {}}');

        $this->assertEquals('{"params":{},"metadata":{"authors":[{"name":"user","role":"Author"}]}}', $params);
    }

    /**
     * @test
     *
     */
    public function addAuthorToParameters2()
    {
        $this->withSession(["name" => "user"]);
        $params = H5PController::addAuthorToParameters('{"params":{},"metadata":{"authors":[{"name":"user 2","role":"Author"}]}}');

        $this->assertEquals('{"params":{},"metadata":{"authors":[{"name":"user 2","role":"Author"}]}}', $params);
    }

    /**
     * @test
     *
     */
    public function addAuthorToParameters3()
    {
        $this->withSession(["name" => ""]);
        $params = H5PController::addAuthorToParameters('{"params":[]}');

        $this->assertEquals('{"params":[]}', $params);
    }

    /**
     * @test
     *
     */
    public function storeContent()
    {
        Event::fake($this->fakedEvents);

        $this->setUpLicensing();
        $request = new Request([
            'title' => "H5P Title",
            "library" => "H5P.Flashcards 1.1",
            "parameters" => '{"params":{"cards":[{"image":{"path":"","mime":"image/jpeg","copyright":{"license":"U"},"width":3840,"height":2160},"text":"Hvor er ørreten?","answer":"Her!","tip":""}],"progressText":"Card @card of @total","next":"Next","previous":"Previous","checkAnswerText":"Check","showSolutionsRequiresInput":true},"metadata":{"title": "H5P Title"}}',
        ]);

        /** @var h5p $h5p */
        $h5p = new h5p(DB::connection()->getPdo());
        $h5p->setEditorFilesDir(sys_get_temp_dir().DIRECTORY_SEPARATOR."tmpTest");

        app()->instance(h5p::class, $h5p);
        app()->instance('requestId', Uuid::uuid4()->toString());

        $versionClient = $this->getMockBuilder(VersionClient::class)
            ->setMethods(["createVersion"])
            ->getMock();

        $versionClient->method("createVersion")
            ->willReturnCallback(function () {
                $responseData = new stdClass();
                $responseData->id = "abcdefghijklmnopqrstuvwxyz";

                $versionData = new VersionData();
                $versionData->populate($responseData);
                return $versionData;
            });
        app()->instance(VersionClient::class, $versionClient);

        $this->withSession(["authId" => "user_1"]);

        /** @var H5pLti $h5pLti */
        $h5pLti = $this->getMockBuilder(H5pLti::class)->getMock();
        $h5pController = new H5PController($h5pLti);

        /** @var Response $response */
        $response = $h5pController->store($request);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $responseData = json_decode($response->getContent());
        $this->assertObjectHasAttribute('url', $responseData);
        $this->assertEquals("http://localhost/h5p/1", $responseData->url);

        $this->assertDatabaseHas("h5p_contents", ["id" => 1]);
        $this->assertDatabaseMissing("h5p_contents", ["id" => 2]);

        $h5pContent = H5PContent::find(1);
        $this->assertEquals($h5pContent->version_id, "abcdefghijklmnopqrstuvwxyz");
    }

    /**
     * @test
     * @throws Exception
     */
    public function updateContent()
    {
        Event::fake($this->fakedEvents);
        $core = resolve(\H5PCore::class);
        $this->setUpLicensing();

        $request = new Request([
            'title' => "H5P Title",
            "library" => "H5P.Flashcards 1.1",
            "parameters" => '{"params":{"cards":[{"image":{"path":"","mime":"image/jpeg","copyright":{"license":"U"},"width":3840,"height":2160},"text":"Hvor er ørreten?","answer":"Her!","tip":""}],"progressText":"Card @card of @total","next":"Next","previous":"Previous","checkAnswerText":"Check","showSolutionsRequiresInput":true},"metadata":{"title": "H5P Title"}}',
        ]);

        app()->singleton(h5p::class, function () {
            /** @var h5p $h5p */
            $h5p = new h5p(DB::connection()->getPdo());
            $h5p->setEditorFilesDir(sys_get_temp_dir().DIRECTORY_SEPARATOR."tmpTest");
            return $h5p;
        });
        app()->instance('requestId', Uuid::uuid4()->toString());

        $versionClient = $this->getMockBuilder(VersionClient::class)
            ->setMethods(["createVersion"])
            ->getMock();

        $versionClient->expects($this->at(0))
            ->method("createVersion")
            ->willReturnCallback(function () {
                $responseData = new stdClass();
                $responseData->id = "AAAAAAAAAA";

                $versionData = new VersionData();
                $versionData->populate($responseData);
                return $versionData;
            });

        $versionClient->expects($this->at(1))
            ->method("createVersion")
            ->willReturnCallback(function () {
                $responseData = new stdClass();
                $responseData->id = "BBBBBBBBBB";

                $versionData = new VersionData();
                $versionData->populate($responseData);
                return $versionData;
            });
        app()->instance(VersionClient::class, $versionClient);

        $this->withSession(["authId" => "user_1"]);

        /** @var H5pLti $h5pLti */
        $h5pLti = $this->getMockBuilder(H5pLti::class)->getMock();
        $h5pController = new H5PController($h5pLti);

        /** @var Response $response */
        $response = $h5pController->store($request);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $this->assertDatabaseHas("h5p_contents", ["id" => 1]);
        $this->assertDatabaseMissing("h5p_contents", ["id" => 2]);

        $h5pContent = H5PContent::find(1);
        $this->assertEquals("AAAAAAAAAA", $h5pContent->version_id);

        $request = new Request([
            'title' => "Updated H5P Title",
            "library" => "H5P.Flashcards 1.1",
            "parameters" => '{"params":{"cards":[{"image":{"path":"","mime":"image/jpeg","copyright":{"license":"U"},"width":3840,"height":2160},"text":"Hvor er ørreten?","answer":"Her!","tip":""}],"progressText":"Card @card of @total","next":"Next","previous":"Previous","checkAnswerText":"Check","showSolutionsRequiresInput":true},"metadata":{"title": "Updated H5P Title"}}',
        ]);

        $response = $h5pController->update($request, $h5pContent, $core);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $responseData = json_decode($response->getContent());
        $this->assertObjectHasAttribute('url', $responseData);
        $this->assertEquals("http://localhost/h5p/2", $responseData->url);

        $this->assertDatabaseHas("h5p_contents", ["id" => 2]);
        $this->assertDatabaseMissing("h5p_contents", ["id" => 3]);

        $updatedH5pContent = H5PContent::find(2);
        $this->assertEquals($updatedH5pContent->version_id, "BBBBBBBBBB");
        $this->assertEquals($updatedH5pContent->title, "Updated H5P Title");
    }
}

<?php

use App\ApiModels\User;
use App\Article;
use App\H5pLti;
use App\Libraries\H5P\Interfaces\H5PAdapterInterface;
use Tests\TestCase;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Tests\Traits\MockAuthApi;
use Tests\Traits\MockResourceApi;
use Tests\Traits\MockLicensingTrait;
use Tests\Traits\MockVersioningTrait;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ArticleTest extends TestCase
{
    use RefreshDatabase, MockLicensingTrait, MockVersioningTrait, MockResourceApi, MockAuthApi;

    public function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();
    }

    public function testEditArticleAccessDenied()
    {
        $this->setUpResourceApi();
        $this->setUpLicensing();
        $authId = Str::uuid();
        $someOtherId = Str::uuid();

        $article = Article::factory()->create(['owner_id' => $authId]);

        $this->withSession(['authId' => $someOtherId])
            ->get(route('article.edit', $article->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testCreateArticle()
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->setUpLicensing('PRIVATE', false);
        Event::fake();
        $authId = Str::uuid();

        $testAdapter = $this->createStub(H5PAdapterInterface::class);
        $testAdapter->method('enableDraftLogic')->willReturn(false);
        $testAdapter->method('getAdapterName')->willReturn("UnitTest");
        app()->instance(H5PAdapterInterface::class, $testAdapter);

        $this->withSession(['authId' => $authId])
            ->post(route('article.store'), [
                'title' => "Title",
                'content' => "Content"
            ]);
        $this->assertDatabaseHas('articles', ['title' => 'Title', 'content' => 'Content', 'is_published' => 1]);

    }

    public function testCreateArticleWithMathContent()
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->setUpLicensing('PRIVATE', false);
        Event::fake();
        $authId = Str::uuid();

        $testAdapter = $this->createStub(H5PAdapterInterface::class);
        $testAdapter->method('enableDraftLogic')->willReturn(false);
        $testAdapter->method('getAdapterName')->willReturn("UnitTest");
        app()->instance(H5PAdapterInterface::class, $testAdapter);

        $this->withSession(['authId' => $authId])
            ->post(route('article.store'), [
                'title' => "Title",
                'content' => '<section class=" ndla-section"><math display="block"><mrow><mmultiscripts><mi>F</mi><mn>3</mn><none/><mprescripts/><mn>2</mn><none/></mmultiscripts></mrow></math></section>'
            ])
            ->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('articles', [
            'title' => 'Title',
            'content' => '<section class="ndla-section"><math display="block"><mrow><mmultiscripts><mi>F</mi><mn>3</mn><none><mprescripts><mn>2</mn><none></mmultiscripts></mrow></math></section>',
            'is_published' => 1,
        ]);

        return;
    }

    public function testCreateAndEditArticleWithIframeContent()
    {
        $this->setupVersion();
        $this->setUpLicensing('PRIVATE', false);
        Event::fake();
        $authId = Str::uuid();

        $testAdapter = $this->createStub(H5PAdapterInterface::class);
        $testAdapter->method('enableDraftLogic')->willReturn(false);
        $testAdapter->method('getAdapterName')->willReturn("UnitTest");
        app()->instance(H5PAdapterInterface::class, $testAdapter);

        $this->withSession(['authId' => $authId])
            ->post(route('article.store'), [
                'title' => "Title",
                'content' => '<section class=" ndla-section"><header class=" ndla-header"><h1 class=" ndla-h1">Overskrift </h1></header></section><section class="ndla-introduction ndla-section">Innhold</section><section class=" ndla-section"><iframe src="https://www.youtube.com/embed/RAbVTreF3lA" width="560" height="315" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen="allowfullscreen" class="oerlearningorg_resource ndla-iframe"></iframe></section>',
                'is_published' => 1,
            ])
            ->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('articles', [
            'title' => 'Title',
            'content' => '<section class="ndla-section"><header class="ndla-header"><h1 class="ndla-h1">Overskrift </h1></header></section><section class="ndla-introduction ndla-section">Innhold</section><section class="ndla-section"><iframe src="https://www.youtube.com/embed/RAbVTreF3lA" width="560" height="315" allowfullscreen class="oerlearningorg_resource ndla-iframe"></iframe></section>',
            'is_published' => 1,
        ]);

        $this->put(route('article.update', Article::first()), [
            'title' => "Updated title",
            'content' => '<section class=" ndla-section"><header class=" ndla-header"><h1 class="ndla-h1">Mer om forenkling av rasjonale uttrykk </h1></header></section><section class="ndla-introduction ndla-section">Hvordan skal vi trekke sammen (addere og subtrahere) rasjonale uttrykk som også inneholder andregradsuttrykk?</section><section class="ndla-section"><iframe src="https://www.youtube.com/embed/RAbVTreF3lA" width="560" height="315" allowfullscreen class="oerlearningorg_resource ndla-iframe"></iframe></section>',
            'is_published' => 1,
        ])
            ->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('articles', [
            'title' => 'Updated title',
            'content' => '<section class="ndla-section"><header class="ndla-header"><h1 class="ndla-h1">Mer om forenkling av rasjonale uttrykk </h1></header></section><section class="ndla-introduction ndla-section">Hvordan skal vi trekke sammen (addere og subtrahere) rasjonale uttrykk som også inneholder andregradsuttrykk?</section><section class="ndla-section"><iframe src="https://www.youtube.com/embed/RAbVTreF3lA" width="560" height="315" allowfullscreen class="oerlearningorg_resource ndla-iframe"></iframe></section>',
            'is_published' => 1,
        ]);

        return;
    }

    public function testEditArticle()
    {
        $this->setUpLicensing('BY', true);
        $this->setupVersion();
        $this->setupAuthApi([
            'getUser' => new User("1", "this", "that", "this@that.com")
        ]);
        Event::fake();
        $authId = Str::uuid();
        $article = App\Article::factory()->create(['owner_id' => $authId, 'is_published' => 1]);

        $testAdapter = $this->createStub(H5PAdapterInterface::class);
        $testAdapter->method('enableDraftLogic')->willReturn(false);
        $testAdapter->method('getAdapterName')->willReturn("UnitTest");
        app()->instance(H5PAdapterInterface::class, $testAdapter);

        $this->withSession(['authId' => $authId])
            ->put(route('article.update', $article->id), [
                'title' => "Title",
                'content' => "Content"
            ])->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('articles', ['title' => 'Title', 'content' => 'Content', 'is_published' => 1]);

        $newArticle = Article::where('title', "Title")
            ->where('content', "Content")
            ->where('is_published', 1)
            ->first();

        $this->get(route('article.show', $newArticle->id))
            ->assertSee($newArticle->title)
            ->assertSee($newArticle->content);
    }

    public function testEditArticleWithDraftEnabled()
    {
        $this->setUpLicensing('BY', true);
        $this->setupVersion();
        $this->setupAuthApi([
            'getUser' => new User("1", "this", "that", "this@that.com")
        ]);

        $this->mockH5pLti();
        $testAdapter = $this->createStub(H5PAdapterInterface::class);
        $testAdapter->method('enableDraftLogic')->willReturn(true);
        $testAdapter->method('getAdapterName')->willReturn("UnitTest");
        app()->instance(H5PAdapterInterface::class, $testAdapter);

        Event::fake();
        $authId = Str::uuid();
        $this->withSession(['authId' => $authId])
            ->post(route('article.store'), [
                'title' => "New article",
                'content' => "New content",
                'requestToken' => Str::uuid(),
                'lti_message_type' => "ltirequest",
                'ext_use_draft_logic' => 1,
                'isPublished' => 0,
            ])
            ->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('articles', ['title' => 'New article', 'content' => 'New content', 'is_published' => 0]);

        $article = Article::where('title', 'New article')->first();
        $this->withSession(['authId' => $authId])
            ->put(route('article.update', $article->id), [
                'title' => "Title",
                'content' => "Content",
                'requestToken' => Str::uuid(),
                'lti_message_type' => "ltirequest",
                'ext_use_draft_logic' => 1,
                'isPublished' => 0,
            ])->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('articles', ['title' => 'Title', 'content' => 'Content', 'is_published' => 0]);
        $article = Article::where('title', 'Title')->first();

        $this->withSession(['authId' => $authId])
            ->put(route('article.update', $article->id), [
                'title' => "Title",
                'content' => "Content",
                'requestToken' => Str::uuid(),
                'lti_message_type' => "ltirequest",
                'ext_use_draft_logic' => 1,
                'isPublished' => 1,
            ])->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('articles', ['title' => 'Title', 'content' => 'Content', 'is_published' => 1]);
        $article = Article::where('title', 'Title')
            ->where('content', "Content")
            ->where('is_published', 1)
            ->first();
        $this->get(route('article.show', $article->id))
            ->assertSee($article->title)
            ->assertSee($article->content);
    }

    private function mockH5pLti()
    {
        $h5pLti = $this->getMockBuilder(H5pLti::class)->getMock();
        app()->instance(H5pLti::class, $h5pLti);
    }

    public function testViewArticle()
    {
        $this->setupVersion();
        $this->setUpLicensing('BY', true);
        $article = Article::factory()->create(['is_published' => 1]);

        $this->get(route('article.show', $article->id))
            ->assertSee($article->title)
            ->assertSee($article->content);
    }

    public function testMustBeLoggedInToCreateArticle()
    {
        $this->setUpLicensing('BY', true);

        $_SERVER['QUERY_STRING'] = 'forTestingPurposes';
        $this->get(route('article.create'))
            ->assertStatus(Response::HTTP_FOUND);
    }

}

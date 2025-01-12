<?php

namespace Tests\Article;

use App\ApiModels\User;
use App\Article;
use Faker\Factory;
use Tests\TestCase;
use App\ContentLock;
use Tests\Traits\MockAuthApi;
use Tests\Traits\MockMQ;
use Illuminate\Support\Str;
use App\ArticleCollaborator;
use Illuminate\Http\Response;
use Tests\Traits\MockResourceApi;
use Tests\Traits\MockLicensingTrait;
use Tests\Traits\MockVersioningTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ArticleLockTest extends TestCase
{
    use RefreshDatabase, MockMQ, MockLicensingTrait, MockVersioningTrait, MockResourceApi, MockAuthApi;

    public function testArticleHasLockWhenUserEdits()
    {
        $this->withoutMiddleware();
        $this->setUpLicensing();
        $this->setUpVersion();
        $this->setupAuthApi([
            'getUser' => new User("1", "aren", "aren", "none@none.com")
        ]);

        $faker = Factory::create();
        $authId = Str::uuid();
        $authName = $faker->name;
        $authEmail = $faker->email;
        $article = Article::factory()->create(['owner_id' => $authId]);

        $this->withSession(['authId' => $authId, 'email' => $authEmail, 'name' => $authName, 'verifiedEmails' => [$authEmail]])
            ->get(route('article.edit', $article->id));
        $this->assertDatabaseHas('content_locks', ['content_id' => $article->id, 'email' => $authEmail, 'name' => $authName]);

    }

    /**
     * @test
     */
    public function LockIsRemovedOnSave()
    {
        $this->setUpLicensing();
        $this->setUpVersion();
        $this->setupAuthApi([
            'getUser' => new User("1", "aren", "aren", "none@none.com")
        ]);

        $faker = Factory::create();
        $authId = Str::uuid();
        $authName = $faker->name;
        $authEmail = $faker->email;
        $article = Article::factory()->create(['owner_id' => $authId]);

        $this->withSession([
            'authId' => $authId,
            'email' => $authEmail,
            'name' => $authName,
            'verifiedEmails' => [$authEmail]
        ])
            ->get(route('article.edit', $article->id));

        $this->assertDatabaseHas('content_locks', ['content_id' => $article->id, 'auth_id' => $authId]);

        $this->put(route('article.update', $article->id), [
            'title' => "NewTitle",
            'content' => '<div>Hello World!</div>',
        ]);

        $this->assertDatabaseMissing('content_locks', ['content_id' => $article->id]);
    }

    /**
     * @test
     */
    public function CanOnlyHaveOneLock()
    {
        $this->setUpLicensing();
        $this->setUpVersion();

        $faker = Factory::create();
        $authId = Str::uuid();
        $authName = "John Doe";
        $authEmail = $faker->email;

        $article = Article::factory()->create(['owner_id' => $authId]);

        $this->setupAuthApi([
            'getUser' => new User("1", $authName, $authName, $authEmail)
        ]);

        $authId2 = Str::uuid();
        $authName2 = $faker->name;
        $authEmail2 = $faker->email;

        $articleCollaborator = ArticleCollaborator::factory()->make(['email' => $authEmail2]);
        $article->collaborators()->save($articleCollaborator);

        $this->withSession(['authId' => $authId, 'email' => $authEmail, 'name' => $authName, 'verifiedEmails' => [$authEmail]])
            ->get(route('article.edit', $article->id));

        $this->assertDatabaseHas('content_locks', ['content_id' => $article->id, 'auth_id' => $authId])
            ->assertCount(1, ContentLock::all());

        // Try to edit as another user
        $this->withSession(['authId' => $authId2, 'email' => $authEmail2, 'name' => $authName2, 'verifiedEmails' => [$authEmail2]])
            ->get(route('article.edit', $article->id))
            ->assertSee($authName);
        $this->assertCount(1, ContentLock::all());
    }

    /** @test */
    public function forkArticle_thenFail()
    {
        $this->setUpResourceApi();
        $this->setUpLicensing('PRIVATE', false);
        $this->setUpVersion();

        $faker = Factory::create();
        $authId = Str::uuid();

        $article = Article::factory()->create(['owner_id' => $authId]);

        $authId2 = Str::uuid();
        $authName2 = $faker->name;
        $authEmail2 = $faker->email;

        // Try to fork as another user
        $this->withSession(['authId' => $authId2, 'email' => $authEmail2, 'name' => $authName2, 'verifiedEmails' => [$authEmail2]])
            ->get(route('article.edit', $article->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** @test */
    public function forkArticle_thenSuccess()
    {
        $this->setUpResourceApi();
        $this->setupAuthApi([
            'getUser' => new User("1", "aren", "aren", "none@none.com")
        ]);

        $this->setUpLicensing('PRIVATE', true);
        $this->setUpVersion();

        $faker = Factory::create();
        $authId = Str::uuid();

        $article = Article::factory()->create(['owner_id' => $authId]);

        $authId2 = Str::uuid();
        $authName2 = $faker->name;
        $authEmail2 = $faker->email;

        // Try to fork as another user
        $this->withSession(['authId' => $authId2, 'email' => $authEmail2, 'name' => $authName2, 'verifiedEmails' => [$authEmail2]])
            ->get(route('article.edit', $article->id))
            ->assertSee($article->title);
    }
}

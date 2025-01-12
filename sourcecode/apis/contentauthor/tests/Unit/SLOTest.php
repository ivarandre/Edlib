<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Session;

class SLOTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testSLO()
    {
        $returnUrl = "http://localhost";

        $params = [
            'returnUrl' => $returnUrl,
        ];

        $url = '/slo?' . http_build_query($params);

        $this->withSession(['userId' => 1]);

        $this->get($url)
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect($returnUrl);

        $this->assertFalse(Session::get('userId', false));
    }
}

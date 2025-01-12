<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Http\Response;

class SafariRedirectTest extends TestCase
{
    public function testSafariRedirect()
    {
        $redirectTo = "http://localhost";
        $nextRedirect = "http://edstep.com/home";

        $redirectParams = [
            'caVisited' => 'true',
            'redirect' => $nextRedirect
        ];

        $params = [
            'redirect' => $redirectTo,
            'nextRedirect' => $nextRedirect
        ];

        $url = '/hack/safari?' . http_build_query($params);

        $this->get($url)
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect($redirectTo . '?' . http_build_query($redirectParams))
            ->assertSessionHas('caVisited', true);
    }
}

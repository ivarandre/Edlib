<?php

namespace App\Http\Middleware;

use App\Http\Libraries\AuthJwtParser;
use App\Libraries\Auth\ContentAuthorAuthenticationHandler;
use Cerpus\AuthCore\TokenResponse;
use Cerpus\LaravelAuth\Service\CerpusAuthService;
use Cerpus\LaravelAuth\Service\JWTValidationService;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CerpusAuth extends AuthJwtParser
{
    private $request;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $jwtService = new JWTValidationService();
        $authorize = trim($request->header('Authorization', ''));
        $prefix = 'Bearer';
        if (strlen($authorize) > strlen($prefix) && substr($authorize, 0, strlen($prefix)) === $prefix) {
            $authorize = substr($authorize, strlen($prefix));
            $bearerToken = trim($authorize);
            if ($authorize !== $bearerToken) {
                $validJwt = $jwtService->validateJwt($bearerToken);
                if ($validJwt !== null && in_array($validJwt->getType(), ['cerpus', 'ndla', 'edlib'])) {
                    $payload = $validJwt->getPayload();
                    $authId = $payload->sub;
                    Session::put('authId', $authId);
                    if (isset($payload->app_metadata) && $payload->app_metadata) {
                        $appMetadata = $payload->app_metadata;
                        Session::put('name', $this->getBestName($appMetadata));
                        Session::put('email', $this->getEmail($appMetadata));
                        Session::put('verifiedEmails', $this->getVerifiedEmails($appMetadata));
                        Session::put('isAdmin', $this->getAdmin($appMetadata));
                    }
                    if (isset($payload->payload) && $payload->payload && isset($payload->payload->user) && $payload->payload->user) {
                        $user = $payload->payload->user;
                        Session::put('name', $this->getBestName($user));
                        Session::put('email', $this->getEmail($user));
                        Session::put('verifiedEmails', $this->getVerifiedEmails($user));
                        Session::put('isAdmin', $user->isAdmin === 1);
                    }
                    return $next($request);
                } else {
                    /**
                     * @var $cerpusAuthService CerpusAuthService
                     */
                    $cerpusAuthService = \App::make(CerpusAuthService::class);
                    $tokenResponse = new TokenResponse();
                    $tokenResponse->access_token = $bearerToken;
                    $identityRequest = $cerpusAuthService->getIdentityRequest($tokenResponse);
                    $identityResponse = $identityRequest->execute();
                    if ($identityResponse) {
                        $cerpusAuthService->getAccessTokenManager()->setAccessToken($bearerToken);
                        /**
                         * @var $contentAuthorAuthenticationHandler ContentAuthorAuthenticationHandler
                         */
                        $contentAuthorAuthenticationHandler = \App::make(ContentAuthorAuthenticationHandler::class);
                        $contentAuthorAuthenticationHandler->perRequestAuthentication($identityResponse);

                        return $next($request);
                    }
                }
            }
        }

        $isLoggedIn = Session::get('authId');

        if ($isLoggedIn) {
            return $next($request);
        }
        $this->request = $request;
        return $this->handleAuth();
    }

    private function handleAuth()
    {
        if ($this->requestContainsAuthAnswer()) {
            return $this->logInUser();
        }
        return $this->doOAuth();
    }

    private function requestContainsAuthAnswer()
    {
        return false;
    }

    private function logInUser()
    {
        $userId = $this->getUserIdFromAuthResponse();
        return Auth::loginUsingId($userId);
    }

    private function getUserIdFromAuthResponse()
    {
        return Session::get('userId');
    }

    private function doOAuth()
    {
        /**
         * @var $cerpusAuthService CerpusAuthService
         */
        $cerpusAuthService = \App::make(CerpusAuthService::class);

        $afterOAuthUrl = $this->request->url() . '?' . $_SERVER['QUERY_STRING'];
        Session::put('afterOAuthUrl', $afterOAuthUrl);

        $authorize = $cerpusAuthService->startFlow()
            ->setSingleSignoutEndpoint(route('slo'))
            ->setRequirements('v1')
            ->setSuccessUrl($afterOAuthUrl)
            ->authorizeUrl(route('oauth2.return'));

        return redirect($authorize);
    }
}

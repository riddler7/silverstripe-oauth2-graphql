<?php

namespace Riddler7\Oauth2GraphQL;

use AdvancedLearning\Oauth2Server\Exceptions\AuthenticationException;
use AdvancedLearning\Oauth2Server\Models\Client;
use function is_null;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Auth\AuthenticatorInterface;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Member;
use function substr;

class Authenticator implements AuthenticatorInterface
{
    public function authenticate(HTTPRequest $request)
    {
        $authenticator = Injector::inst()->get(\AdvancedLearning\Oauth2Server\Services\Authenticator::class);

        try {
            $request = $authenticator->authenticate($request);

            if ($userId = $request->getHeader('oauth_user_id')) {
                return Member::get()->filter(['Email' => $userId])->first();
            }
        } catch (AuthenticationException $exception) {
            throw new ValidationException($exception->getMessage(), $exception->getCode() ?: 403);
        }
    }

    public function isApplicable(HTTPRequest $request)
    {
        return !is_null($this->getToken($request));
    }

    /**
     * Extract the token from the authorization header.
     *
     * @param HTTPRequest $request The request container the token.
     *
     * @return null|string
     */
    protected function getToken(HTTPRequest $request): ?string
    {
        if ($authHeader = $request->getHeader('Authorization')) {
            if (stripos($authHeader, 'Bearer ') === 0) {
                return substr($authHeader, 6);
            }
        }

        return null;
    }
}

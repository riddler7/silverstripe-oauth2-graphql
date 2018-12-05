<?php

namespace Riddler7\Oauth2GraphQL;

use Exception;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\GraphQL\Controller as GraphQLController;
use SilverStripe\GraphQL\Manager;
use SilverStripe\Versioned\Versioned;

class Controller extends GraphQLController
{
    private static $cors = [
        'Allow-Credentials' => ''
    ];

    /**
     * Handles requests to the index action (e.g. /graphql)
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function index(HTTPRequest $request)
    {
        $stage = $request->param('Stage');
        if ($stage && in_array($stage, [Versioned::DRAFT, Versioned::LIVE])) {
            Versioned::set_stage($stage);
        }
        // Check for a possible CORS preflight request and handle if necessary
        // Refer issue 66:  https://github.com/silverstripe/silverstripe-graphql/issues/66
        if ($request->httpMethod() === 'OPTIONS') {
            return $this->handleOptions($request);
        }

        // Main query handling
        try {
            $manager = $this->getManager();
            $manager->addContext('token', $this->getToken());
            $method = null;
            if ($request->isGET()) {
                $method = 'GET';
            } elseif ($request->isPOST()) {
                $method = 'POST';
            }
            $manager->addContext('httpMethod', $method);

            // Check and validate user for this request
            $member = $this->getRequestUser($request);

            if ($member) {
                $manager->setMember($member);
            }

            $this->addOauthContexts($request, $manager);

            // Parse input
            list($query, $variables) = $this->getRequestQueryVariables($request);

            // Run query
            $result = $manager->query($query, $variables);
        } catch (Exception $exception) {
            $error = ['message' => $exception->getMessage()];

            if (Director::isDev()) {
                $error['code'] = $exception->getCode();
                $error['file'] = $exception->getFile();
                $error['line'] = $exception->getLine();
                $error['trace'] = $exception->getTrace();
            }

            $result = [
                'errors' => [$error]
            ];
        }

        $response = $this->addCorsHeaders($request, new HTTPResponse(json_encode($result)));
        return $response->addHeader('Content-Type', 'application/json');
    }

    /**
     * Update default to add Allow-Credentials
     *
     * @param HTTPRequest  $request
     * @param HTTPResponse $response
     *
     * @return HTTPResponse
     */
    public function addCorsHeaders(HTTPRequest $request, HTTPResponse $response)
    {
        $response = parent::addCorsHeaders($request, $response);

        $corsConfig = Config::inst()->get(static::class, 'cors');

        if ($corsConfig['Enabled']) {
            $response->addHeader('Access-Control-Allow-Credentials', $corsConfig['Allow-Credentials']);
        }

        return $response;
    }

    /**
     * Add contexts provided by oauth.
     *
     * @param HTTPRequest $request
     * @param Manager    $manager
     */
    protected function addOauthContexts(HTTPRequest $request, Manager $manager)
    {
        if (!empty($request->getHeader('oauth_scopes'))) {
            // split comma list into an array for easier consumption
            $manager->addContext('oauthScopes', $request->getHeader('oauth_scopes'));
        }

        if (!empty($request->getHeader('oauth_client_id'))) {
            $manager->addContext('oauthClientIdentifier', $request->getHeader('oauth_client_id'));

            // request must have an oauth client
        } else {
            throw new Exception('A valid client is required', 403);
        }
    }

    /**
     * Use static for config.
     *
     * @param HTTPRequest $request
     *
     * @return HTTPResponse
     */
    protected function handleOptions(HTTPRequest $request)
    {
        $response = HTTPResponse::create();
        $corsConfig = Config::inst()->get(static::class, 'cors');
        if ($corsConfig['Enabled']) {
            // CORS config is enabled and the request is an OPTIONS pre-flight.
            // Process the CORS config and add appropriate headers.
            $this->addCorsHeaders($request, $response);
        } else {
            // CORS is disabled but we have received an OPTIONS request.  This is not a valid request method in this
            // situation.  Return a 405 Method Not Allowed response.
            $this->httpError(405, "Method Not Allowed");
        }
        return $response;
    }

    /**
     * Prevent error when there is no member. It is up to resolvers to determine whether login is required.
     *
     * @param HTTPRequest $request
     *
     * @return \SilverStripe\Security\Member
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function getRequestUser(HTTPRequest $request)
    {
        // if permissions required, pass off to parent to enforce member
        if ($request->param('Permissions')) {
            return parent::getRequestUser($request);
        }

        $authenticator = $this->getAuthHandler()->getAuthenticator($request);

        return $authenticator ? $authenticator->authenticate($request) : null;
    }

}

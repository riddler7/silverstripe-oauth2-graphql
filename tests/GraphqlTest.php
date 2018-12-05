<?php

namespace AdvancedLearning\Oauth2Server\Tests;

use AdvancedLearning\Oauth2Server\Models\Client;
use AdvancedLearning\Oauth2Server\Repositories\AccessTokenRepository;
use AdvancedLearning\Oauth2Server\Repositories\ClientRepository;
use AdvancedLearning\Oauth2Server\Repositories\RefreshTokenRepository;
use AdvancedLearning\Oauth2Server\Repositories\ScopeRepository;
use AdvancedLearning\Oauth2Server\Repositories\UserRepository;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Lcobucci\JWT\Parser;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use Riddler7\Oauth2GraphQL\Controller;
use Riddler7\Oauth2GraphQL\Helpers\OauthContext;
use Riddler7\Oauth2GraphQL\Tests\BlankMutation;
use Riddler7\Oauth2GraphQL\Tests\BlankQuery;
use Riddler7\Oauth2GraphQL\Tests\BlankType;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\GraphQL\Manager;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use function file_get_contents;
use function file_put_contents;
use function sys_get_temp_dir;

class GraphqlTest extends SapphireTest
{
    use CryptTrait, OauthContext;

    protected static $fixture_file = 'tests/OAuthFixture.yml';

    protected static $privateKeyFile = 'private.key';

    protected static $publicKeyFile = 'public.key';

    /**
     * Setup test environment.
     */
    public function setUp()
    {
        parent::setUp();

        // copy private key so we can set correct permissions, file gets removed when tests finish
        $path = $this->getPrivateKeyPath();
        file_put_contents($path, file_get_contents(__DIR__ . '/' . self::$privateKeyFile));
        chmod($path, 0660);
        Environment::setEnv('OAUTH_PRIVATE_KEY_PATH', $path);

        // copy public key
        $path = $this->getPublicKeyPath();
        file_put_contents($path, file_get_contents(__DIR__ . '/' . self::$publicKeyFile));
        chmod($path, 0660);
        Environment::setEnv('OAUTH_PUBLIC_KEY_PATH', $path);

        Security::force_database_is_ready(true);

        $this->setEncryptionKey('lxZFUEsBCJ2Yb14IF2ygAHI5N4+ZAUXXaSeeJm6+twsUmIen');
    }

    public function testGraphQLMember()
    {
        $userRepository = new UserRepository();
        $refreshRepository = new RefreshTokenRepository();

        $server = $this->getAuthorisationServer();
        $server->enableGrantType(
            new PasswordGrant($userRepository, $refreshRepository),
            new \DateInterval('PT1H')
        );

        $client = $this->objFromFixture(Client::class, 'webapp');
        $member = $this->objFromFixture(Member::class, 'member1');

        $request = (new ServerRequest(
            'POST',
            '',
            ['Content-Type' => 'application/json']
        ))->withParsedBody([
            'grant_type' => 'password',
            'client_id' => $client->Identifier,
            'client_secret' => $client->Secret,
            'scope' => 'members',
            'username' => $member->Email,
            'password' => 'password1'
        ]);

        $response = new Response();
        $response = $server->respondToAccessTokenRequest($request, $response);

        $data = json_decode((string)$response->getBody(), true);
        $token = $data['access_token'];

        // check for fn/ln
        $decoded = (new Parser())->parse($token);

        $this->assertEquals('My', $decoded->getClaim('fn'), 'First name should be correctly set');
        $this->assertEquals('Test', $decoded->getClaim('ln'), 'Last name should be correctly set');

        // create request
        $request = new HTTPRequest('GET', '/');
        $request->addHeader('authorization', 'Bearer ' . $token);
        // fake server port
        $_SERVER['SERVER_PORT'] = 443;

        $authMember = (new \Riddler7\Oauth2GraphQL\Authenticator())->authenticate($request);

        $this->assertEquals($member->ID, $authMember->ID, 'Member should exist in DB');
    }

    public function testGraphQLContexts()
    {
        $userRepository = new UserRepository();
        $refreshRepository = new RefreshTokenRepository();

        $server = $this->getAuthorisationServer();
        $server->enableGrantType(
            new PasswordGrant($userRepository, $refreshRepository),
            new \DateInterval('PT1H')
        );

        $client = $this->objFromFixture(Client::class, 'webapp');
        $member = $this->objFromFixture(Member::class, 'member1');

        $request = (new ServerRequest(
            'POST',
            '',
            ['Content-Type' => 'application/json']
        ))->withParsedBody([
            'grant_type' => 'password',
            'client_id' => $client->Identifier,
            'client_secret' => $client->Secret,
            'scope' => 'members',
            'username' => $member->Email,
            'password' => 'password1'
        ]);

        $response = new Response();
        $response = $server->respondToAccessTokenRequest($request, $response);

        $data = json_decode((string)$response->getBody(), true);
        $token = $data['access_token'];

        // create request
        $request = new HTTPRequest('GET', '/grqphql');
        $request->addHeader('authorization', 'Bearer ' . $token);
        // fake server port
        $_SERVER['SERVER_PORT'] = 443;

        // var to store context
        $context = [];

        // setup blank schema
        Config::modify()->set(Manager::class, 'schemas', [
            'myschema' => [
                'types' => [
                    'Blank' => BlankType::class
                ],
                'queries' => [
                    'BlankQuery' => BlankQuery::class
                ],
                'mutations' => [
                    'BlankMutation' => BlankMutation::class
                ]
            ]
        ]);

        $manager = new Manager('myschema');

        // extract the context
        $manager->addMiddleware(new GraphQLSchemaExtractor(function ($currentContext) use (&$context) {
            $context = $currentContext;
        }));

        $controller = new Controller($manager);
        $response = $controller->index($request);

        $this->assertEquals($client->Identifier, $context['oauthClientIdentifier']);
        $this->assertEquals(1, count($context['oauthScopes']));
        $this->assertEquals('members', $context['oauthScopes'][0]);

        // test the context helper
        $this->assertEquals(
            true,
            $this->hasOauthClient($context),
            'Context should contain a client'
        );
        $this->assertEquals(
            true,
            $this->hasScope($context, 'members'),
            'Context should have a \'members\' scope'
        );
        $this->assertEquals(
            false,
            $this->hasScope($context, 'admin'),
            'Context should not have an \'admin\' scope'
        );
        $this->assertEquals(
            true,
            $this->hasScopes($context,
                ['members'])
        );
        $this->assertEquals(
            false,
            $this->hasScopes($context,
                ['admin'])
        );
        $this->assertEquals(
            $client->ID,
            $this->getOauthClient($context)->ID,
            'The ids for the Oauth Client should match'
        );
    }

    /**
     * Setup the Authorization Server.
     *
     * @return AuthorizationServer
     */
    protected function getAuthorisationServer()
    {
        // Init our repositories
        $clientRepository = new ClientRepository(); // instance of ClientRepositoryInterface
        $scopeRepository = new ScopeRepository(); // instance of ScopeRepositoryInterface
        $accessTokenRepository = new AccessTokenRepository(); // instance of AccessTokenRepositoryInterface

        // Path to public and private keys
        $privateKey = $this->getPrivateKeyPath();
        $encryptionKey = $this->encryptionKey;

        // Setup the authorization server
        $server = new AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $scopeRepository,
            $privateKey,
            $encryptionKey
        );

        return $server;
    }

    /**
     * Get the resource server.
     *
     * @return \League\OAuth2\Server\ResourceServer
     */
    protected function getResourceServer()
    {
        // Init our repositories
        $accessTokenRepository = new AccessTokenRepository(); // instance of AccessTokenRepositoryInterface

        // Path to authorization server's public key
        $publicKeyPath = $this->getPublicKeyPath();

        // Setup the authorization server
        $server = new \League\OAuth2\Server\ResourceServer(
            $accessTokenRepository,
            $publicKeyPath
        );

        return $server;
    }

    /**
     * Get the full path the private key.
     *
     * @return string
     */
    protected function getPrivateKeyPath()
    {
        return sys_get_temp_dir() . '/' . self::$privateKeyFile;
    }

    /**
     * Get the full path the public key.
     *
     * @return string
     */
    protected function getPublicKeyPath()
    {
        return sys_get_temp_dir() . '/' . self::$publicKeyFile;
    }

    /**
     * Cleanup test environment.
     */
    protected function tearDown()
    {
        parent::tearDown();
        // remove private key after tests have finished
        unlink($this->getPrivateKeyPath());
        // remove public key after tests have finished
        unlink($this->getPublicKeyPath());
    }

    /**
     * Generates a response with an access token using the client grant.
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function generateClientAccessToken()
    {
        $server = $this->getAuthorisationServer();
        // Enable the client credentials grant on the server
        $server->enableGrantType(
            new ClientCredentialsGrant(),
            new \DateInterval('PT1H') // access tokens will expire after 1 hour
        );

        $client = $this->objFromFixture(Client::class, 'webapp');

        $request = $this->getClientRequest($client);

        $response = new Response();
        return $server->respondToAccessTokenRequest($request, $response);
    }

    /**
     * Get PSR7 request object to be used for a client grant.
     *
     * @param Client $client
     *
     * @return ServerRequest
     */
    protected function getClientRequest(Client $client)
    {
        // setup server vars
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

        return (new ServerRequest(
            'POST',
            '',
            ['Content-Type' => 'application/json']
        ))->withParsedBody([
            'grant_type' => 'client_credentials',
            'client_id' => $client->Identifier,
            'client_secret' => $client->Secret,
            'scope' => 'members'
        ]);
    }

}

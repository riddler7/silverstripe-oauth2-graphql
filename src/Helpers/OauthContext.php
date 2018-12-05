<?php

namespace Riddler7\Oauth2GraphQL\Helpers;

use AdvancedLearning\Oauth2Server\Models\Client;

trait OauthContext
{
    /**
     * Name of the oauth client id in the graphql context.
     *
     * @var string
     */
    protected static $oauthClientKey = 'oauthClientIdentifier';

    /**
     * Name of the oauth scopes in the graphql context.
     *
     * @var string
     */
    protected static $oauthScopesKey = 'oauthScopes';

    /**
     * Determine if the scope has a valid client.
     *
     * @param array $context
     *
     * @return bool
     */
    public function hasOauthClient(array $context)
    {
        return !empty($context[self::$oauthClientKey]);
    }

    /**
     * Return the model for the Oauth Client.
     *
     * @param array $context
     *
     * @return null|\SilverStripe\ORM\DataObject
     */
    public function getOauthClient(array $context)
    {
        if (!$this->hasOauthClient($context)) {
            return null;
        }

        return Client::get()->filter(['Identifier' => $context[self::$oauthClientKey]])->first();
    }

    /**
     * Determine whether the a scope has been granted.
     *
     * @param array  $context
     * @param string $scope
     *
     * @return bool
     */
    public function hasScope(array $context, string $scope)
    {
        return !empty($context[self::$oauthScopesKey]) && in_array($scope, $context[self::$oauthScopesKey]);
    }

    /**
     * Determnie whether all the scopes have been granted.
     *
     * @param array $context
     * @param array $scopes
     *
     * @return bool
     */
    public function hasScopes(array $context, array $scopes)
    {
        $has = true;

        foreach ($scopes as $scope) {
            // stop once we get the first false result
            if (!$this->hasScope($context, $scope)) {
                return false;
            }
        }

        return $has;
    }
}

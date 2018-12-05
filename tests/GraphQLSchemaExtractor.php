<?php

namespace AdvancedLearning\Oauth2Server\Tests;

use GraphQL\Schema;
use SilverStripe\GraphQL\Middleware\QueryMiddleware;

class GraphQLSchemaExtractor implements QueryMiddleware
{
    protected $callback;

    public function __construct($callback)
    {
        $this->callback = $callback;
    }

    public function process(Schema $schema, $query, $context, $params, callable $next)
    {
        if ($this->callback) {
            call_user_func($this->callback, $context);
        }

        return $next($schema, $query, $context, $params);
    }
}

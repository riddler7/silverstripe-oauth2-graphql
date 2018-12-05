<?php

namespace Riddler7\Oauth2GraphQL\Tests;

use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\TypeCreator;

class BlankType extends TypeCreator
{
    public function attributes()
    {
        return [
            'name' => 'Blank'
        ];
    }

    public function fields()
    {
        return [
            'ID' => [
                'type' => Type::id()
            ]
        ];
    }
}

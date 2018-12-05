<?php

namespace Riddler7\Oauth2GraphQL\Tests;

use SilverStripe\GraphQL\QueryCreator;

class BlankQuery extends QueryCreator
{
    public function attributes()
    {
        return [
            'name' => 'BlankQuery'
        ];
    }

    public function type()
    {
        return $this->manager->getType('Blank');
    }
}

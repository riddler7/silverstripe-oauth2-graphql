<?php

namespace Riddler7\Oauth2GraphQL\Tests;

use SilverStripe\GraphQL\MutationCreator;

class BlankMutation extends MutationCreator
{
    public function attributes()
    {
        return [
            'name' => 'BlankMutation'
        ];
    }

    public function type()
    {
        return $this->manager->getType('Blank');
    }
}

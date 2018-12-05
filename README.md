# SilverStripe Oauth2 Server

[![Build Status](https://travis-ci.org/riddler7/silverstripe-oauth2-graphql.svg?branch=master)](https://travis-ci.org/riddler7/silverstripe-oauth2-graphql)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/riddler7/silverstripe-oauth2-graphql/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/riddler7/silverstripe-oauth2-graphql/?branch=master)
[![codecov](https://codecov.io/gh/riddler7/silverstripe-oauth2-graphql/branch/master/graph/badge.svg)](https://codecov.io/gh/riddler7/silverstripe-oauth2-graphql)

Adds support for oauth2 authentication (using advanced-learning/silverstripe-oauth2-server).

## Requirements

* `silverstripe/framework` ^4.0
* `advanced-learning/silverstripe-oauth2-server`
* `PHP >= 7.1`

## Installation

Install with [Composer](https://getcomposer.org):

```shell
composer require riddler7/silverstripe-oauth-graphql
```

## Usage

Adds the oauth to the context in graphql. To enable you need to configure your own graphql endpoint to use the controller
from this module as it overwrites the index method of the default graphql controller to allow it access the the request
add add contexts.

```yaml
SilverStripe\Control\Director:
  rules:
    mygraphqlurl:
      Controller: 'Riddler7\Oauth2GraphQL\Controller'
```

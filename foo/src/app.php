<?php

$autoloader = require_once __DIR__.'/../vendor/autoload.php';

use RLW\Application;
use Symfony\Component\HttpFoundation\Request;

$app = new Application;

use Foo\Webservice\WebserviceFoo;

$ws = new Foo\Webservice\WebserviceFoo;

$app->route('/foo', $ws);

$app->route('/bar', function(Request $request) {
  return $request->query->all();
});

return $app;
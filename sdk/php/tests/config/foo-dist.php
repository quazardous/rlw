<?php

define('FOO_BASE_URL', 'http://localhost/path/to/foo/web');

if (getenv('FOO_PROXY_MODE')) {
  // test RLW in proxy mode aka using webservice object directly
  require_once __DIR__.'/../../../../foo/vendor/autoload.php';
}
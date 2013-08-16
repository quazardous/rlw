<?php
namespace Foo\Webservice;

use RLW\Webservice\WebserviceAbstract;

class WebserviceFoo extends WebserviceAbstract {

  protected $requestHandlersClassMap = array(
      'foo/#main' => "RequestHandler\\RequestHandlerFooDefault",
      'foo/bar'   => "RequestHandler\\RequestHandlerFooDefault",
      'foo/foo'   => "RequestHandler\\RequestHandlerFooDefault",
      'foo/boo'   => "RequestHandler\\RequestHandlerFooDefault",
      'foo/far'   => "RequestHandler\\RequestHandlerFooDefault",
  );
  
  public function canAccess() {
    if (isset($this->requests['#main']['#request']['blockme'])
        && $this->requests['#main']['#request']['blockme']) return false;
    return true;
  }
}
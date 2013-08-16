<?php
namespace Foo\Webservice\RequestHandler;

class RequestHandlerFooDefault extends RequestHandlerFooAbstract {
  public function execute() {
    $data = (object)array(
        '#name' => $this->_request['#name'],
        '#request' => (object) $this->_request['#request'],
        '#requires' => $this->_request['#requires'],
        );
    $this->setResponseData($data);
    $this->setStatus(200, 'Success');
    return true;
  }
  
  public function canAccess() {
    if ($this->blockme) return false;
    return true;
  }
}
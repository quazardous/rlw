<?php
namespace Foo\Webservice\RequestHandler\Foo;

class Alter extends BaseAbstract {
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
  
  public function alterRequests(&$requests) {
  	foreach ($requests as $tag => &$request) {
  		if ($tag == 'bar') {
  			$request['#request']['foo'] = 'two';
  		}
  	}
  }
}
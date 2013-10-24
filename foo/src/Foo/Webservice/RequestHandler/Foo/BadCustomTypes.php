<?php
namespace Foo\Webservice\RequestHandler\Foo;

class BadCustomTypes extends BaseAbstract {
	
	protected $_requestParameterDefinitions = array(
			'struct1' => array('type' => '<type1>'),
	);
	
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
  
}
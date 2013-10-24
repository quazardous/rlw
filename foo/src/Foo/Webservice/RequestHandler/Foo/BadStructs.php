<?php
namespace Foo\Webservice\RequestHandler\Foo;

class BadStructs extends BaseAbstract {
	
	protected $_requestParameterDefinitions = array(
			'freeStruct' => array(
					'type' => 'struct',
					),
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
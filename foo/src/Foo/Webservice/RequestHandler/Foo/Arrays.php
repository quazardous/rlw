<?php
namespace Foo\Webservice\RequestHandler\Foo;

class Arrays extends BaseAbstract {
	
	protected $_requestParameterDefinitions = array(
			'freeStringsArray' => array(
					'type' => 'array',
					'nested' => 'string',),
			'sizeStringsArray' => array(
					'type' => 'array',
					'min' => 3,
					'max' => 10,
					'nested' => 'string',),
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
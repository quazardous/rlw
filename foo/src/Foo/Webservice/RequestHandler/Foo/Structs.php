<?php
namespace Foo\Webservice\RequestHandler\Foo;

class Structs extends BaseAbstract {
	
	protected $_requestParameterDefinitions = array(
			'freeStruct' => array(
					'type' => 'struct',
					'struct' => array(
							'a' => array('type' => 'string', 'mandatory' => true),
							'b' => 'numeric',
			),),
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
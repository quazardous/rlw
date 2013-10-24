<?php
namespace Foo\Webservice\RequestHandler\Foo;

class CustomTypes extends BaseAbstract {
	
	protected $_requestParameterDefinitions = array(
			'struct2' => array('type' => '<type2>'),
			'struct3' => array('type' => '<type3>'),
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
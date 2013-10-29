<?php
namespace Foo\Webservice\RequestHandler\Foo;

class Base extends BaseAbstract {

	protected $_requestParameterDefinitions = array(
		'r' => array('type' => 'numeric'),
		'foo' => array('type' => 'string'),
		'boo' => array('type' => 'string'),
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
  
  public function canAccess() {
    if (isset($this->blockme) && $this->blockme) return false;
    return true;
  }
}
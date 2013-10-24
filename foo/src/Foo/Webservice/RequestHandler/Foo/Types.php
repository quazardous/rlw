<?php
namespace Foo\Webservice\RequestHandler\Foo;

class Types extends BaseAbstract {
	
	protected $_requestParameterDefinitions = array(
			'freeString' => array('type' => 'string'),
			'mandatoryString' => array('type' => 'string', 'mandatory' => true),
			'sizeString' => array('type' => 'string', 'min' => 5, 'max' => 10),
			'patternString' => array('type' => 'string', 'pattern' => '/^xyz/'),
			'freeNumeric' => array('type' => 'numeric'),
			'positiveNumeric' => array('type' => 'numeric', 'min' => 0),
			'defaultString' => array('type' => 'string', 'default' => 'xyz'),
			'freeTag' => array('type' => 'tag', 'tags' => array('one', 'two', 'three')),
			'freeBoolean' => array('type' => 'boolean'),
			'null' => 'null', // null parameter
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
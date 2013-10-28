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
			'upperFreeTag' => array('type' => 'tag', 'tags' => array('one', 'two', 'three'), 'transform' => 'upper'),
			'freeBoolean' => array('type' => 'boolean'),
			'freeMixed' => array('type' => 'mixed', 'valid_callback' => 'validFreeMixed'),
			'null' => 'null', // null parameter
	);
	
	protected function validFreeMixed($value) {
		return ($value === "xyz" || $value === array("xyz"));
	}
	
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
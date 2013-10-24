<?php
namespace Foo\Webservice;

use RLW\Webservice\WebserviceAbstract;

class WebserviceFoo extends WebserviceAbstract {

  protected $_requestHandlersClassMap = array(
      'foo/#main' => "RequestHandler\\Foo\\Base",
      'foo/bar'   => "RequestHandler\\Foo\\Base",
      'foo/foo'   => "RequestHandler\\Foo\\Base",
      'foo/boo'   => "RequestHandler\\Foo\\Base",
      'foo/far'   => "RequestHandler\\Foo\\Base",
  		'foo/alter' => "RequestHandler\\Foo\\Alter",
  		'foo/types' => "RequestHandler\\Foo\\Types",
  		'foo/bad/custom/types' => "RequestHandler\\Foo\\BadCustomTypes",
  		'foo/custom/types' => "RequestHandler\\Foo\\CustomTypes",
  		'foo/arrays' => "RequestHandler\\Foo\\Arrays",
  		'foo/bad/arrays' => "RequestHandler\\Foo\\BadArrays",
  		'foo/structs' => "RequestHandler\\Foo\\Structs",
  		'foo/bad/structs' => "RequestHandler\\Foo\\BadStructs",
  		'foo/shared' => "RequestHandler\\Foo\\Shared",
  );
  
  protected $_typeDefinitions = array(
  	'type2' => array(
  		'type' => 'struct',
  		'struct' => array(
  			'foo' => array('type' => 'string'),
  		),
  	),
  	'type3' => array(
  			'type' => 'struct',
  			'struct' => array(
  					'foo' => array('type' => 'string'),
  			),
  	),
  );
  
  protected $_sharedRequestParameterDefinitions = array(
  	'shared1' => 'string',
  	'shared2' => 'string',
  );
  
  public function prepareCustomStructTypeDataType3(&$value) {
  	if (!is_array($value)) {
  		$value = array('foo' => $value);
  	}
  }
  
  public function canAccess() {
    if ($this->getRequestHandler('#main')->blockme) return false;
    return true;
  }
}
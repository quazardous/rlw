<?php
namespace RLW\Webservice\RequestHandler;

use RLW\Webservice\WebserviceAbstract;
use RLW\Webservice\WebserviceException;

abstract class RequestHandlerAbstract {
  
  /**
   * @var WebserviceAbstract
   */
  protected $_ws;
  
  /**
   * @return \RLW\Webservice\WebserviceAbstract
   */
  public function getWS() {
    return $this->_ws;
  }
  
  /**
   * Description of the request params.
   * @var array(
   *   'param1' => array(
   *      'type' => numeric|string|boolean|array|struct|tag|<type>|null|mixed,
   *      // default is string,
   *      // you can use null to disable a shared parameter
   *      // mixed means what you wants
   *      'struct' => array of fields|params definitions for struct, // struct can be nested in an array
   *      'mandatory' => true|false, // default is false
   *      'default' => value, // default value, default is to not set the value at all
   *      'nested' => array(...)|'type', // definition of the nested type for arrays
   *      'pattern' => "/pattern/", // preg_match() pattern for string
   *      'min' => min, // min size for numeric, array and string
   *      'max' => max, // max size for numeric, array and string
   *      'tags' => array(tags), // list of tags for tag type
   *      'prepare_callback' => 'method_name', // a prepare callback
   *      'valid_callback' => 'method_name', // a valid callback
   *      'case_sensitive' => true|false, // for tags, default false
   *      'transform' => 'lower'|'upper', // basic functions
   *      'cast' => true|false, // try basic cast for string and numeric, for array put the value in an array, default is false
   *   ),
   *   'param2' => 'type', 
   *   ...
   * )
   * @see \RLW\Webservice\WebserviceAbstract
   */
  protected $_requestParameterDefinitions = array();
  
  /**
   * Strictly use definition to valid params.
   */
  protected $_validRequest = true;
  
  protected function transformRequestParameterValue(&$value, $definition, $path) {
  	$type = $definition['type'];
  	switch ($type) {
  		case 'string': case 'tag':
  			if (isset($definition['transform'])) {
  				switch ($definition['transform']) {
  					case 'lower':
  						$value = strtolower($value);
  						break;
  					case 'upper':
  						$value = strtoupper($value);
  						break;
  				}
  			}
  			break;
  	}
    
  }
  
  /**
   * Validate a request param value.
   * @param string $path
   * @param mixed $value
   * @param string $path
   * @param mixed $parent object
   * @throws WebserviceException
   * @return boolean
   */
  protected function validRequestParameter(&$value, $definition, $path, $parent) {
  	$this->prepareRequestParameterValue($value, $definition, $path, $parent);
  	$type = $definition['type'];
  	switch ($type) {
  		case 'string':
  			if (!empty($definition['cast'])) {
  				if (in_array(gettype($value), array('boolean', 'integer', 'double', 'NULL'))) {
  					$value = (string) $value;
  				}
  			}
  			if (!is_string($value)) {
  				$this->setStatus(400, "{$path} : not a string");
  				return false;
  			}
  			if (isset($definition['min']) && strlen($value) < $definition['min']) {
  				$this->setStatus(400, "{$path} : minimum length is {$definition['min']}");
  				return false;
  			}
  			if (isset($definition['max']) && strlen($value) > $definition['max']) {
  				$this->setStatus(400, "{$path} : maximum length is {$definition['max']}");
  				return false;
  			}
  			if (isset($definition['pattern']) && (!preg_match($definition['pattern'], $value))) {
  				$this->setStatus(400, "{$path} : must match pattern {$definition['pattern']}");
  				return false;
  			}
  			$this->transformRequestParameterValue($value, $definition, $path);
  			break;

  		case 'numeric':
  			if (!empty($definition['cast'])) {
  				if (in_array(gettype($value), array('boolean', 'string', 'NULL'))) {
  					if (is_string($value)) {
  						$value = doubleval($value);
  					}
  					else {
  						$value = (string) $value;
  					}
  				}
  			}
  			if (!is_numeric($value)) {
  				$this->setStatus(400, "{$path} : not a numeric");
  				return false;
  			}
  			if (isset($definition['min']) && $value < $definition['min']) {
  				$this->setStatus(400, "{$path} : minimum value is {$definition['min']}");
  				return false;
  			}
  			if (isset($definition['max']) && $value > $definition['max']) {
  				$this->setStatus(400, "{$path} : maximum value is {$definition['max']}");
  				return false;
  			}
  			break;
  			
  		case 'boolean':
   			if ($value === true) $value = 1;
  			elseif ($value === false) $value = 0;
  			if (!in_array($value, array(0, 1), true)) {
  				$this->setStatus(400, "{$path} : not a boolean");
  				return false;
  			}
  			break;
  			
  		case 'array':
  			if (!empty($definition['cast'])) {
  				if (!is_array($value)) {
  					$value = array($value);
  				}
  			}
  			if (empty($definition['nested'])) {
  				throw new WebserviceException("array requires a nested type", WebserviceException::no_nested_type);
  			}
  			if (!is_array($value)) {
  				$this->setStatus(400, "{$path} : not an array");
  				return false;
  			}
  			if (isset($definition['min']) && count($value) < $definition['min']) {
  				$this->setStatus(400, "{$path} : minimum length is {$definition['min']}");
  				return false;
  			}
  			if (isset($definition['max']) && count($value) > $definition['max']) {
  				$this->setStatus(400, "{$path} : maximum length is {$definition['max']}");
  				return false;
  			}
  			foreach ($value as $i => &$v) {
  				if (!$this->validRequestParameter($v, $definition['nested'], "{$path}[{$i}]", $value)) {
  					return false;
  				}
  			}
  			break;
  			
  		case 'tag':
  			if (!is_string($value)) {
  				$this->setStatus(400, "{$path} : not a tag");
  				return false;
  			}
  			if (empty($definition['tags']) || (!is_array($definition['tags']))) {
  				throw new WebserviceException("tag requires a list of tags", WebserviceException::no_tags);
  			}
  			$testValue = $value;
  			if (empty($definition['case_sensitive'])) {
  				$testValue = strtolower($value);
  			}
  			if (!in_array($testValue, $definition['tags'], true)) {
  				$this->setStatus(400, "{$path} : ".((string)$value)." not in the list");
  				return false;
  			}
  			$this->transformRequestParameterValue($value, $definition, $path);
  			break;
  			
  		case 'struct':
  			if (empty($definition['struct']) || (!is_array($definition['struct']))) {
  				throw new WebserviceException("struct requires a struct definition", WebserviceException::no_struct);
  			}
  			if(!$this->validRequestParameterStruct($value, $definition['struct'], $path, (object)$value)) {
  				return false;
  			}
  			break;
  			
  		case 'mixed':
  			break;
  			
  		default:
  			throw new WebserviceException("{$type} : unknown type", WebserviceException::unknown_request_parameter_type);
  	}
  	if (!$this->validRequestParameterValue($value, $definition, $path, $parent)) {
  		if ($this->getStatusCode() == 200) {
  			// default status for invalid parameter
  			$this->setStatus(400, "{$path} : parameter is not valid");
  		}
  		return false;
  	}
  	return true;
  }
  
  /**
   * Valid the request/struct params
   * @param array $params
   * @param array $definitions
   * @param string $path
   * @param mixed $parent object
   * @return boolean
   */
  protected function validRequestParameterStruct(&$data, &$definitions, $path, $parent) {
  	if (!(is_array($data) || is_object($data))) {
  		$this->setStatus(400, "{$path} : parameter is not a struct");
  		return false;
  	}
  	
  	if ($path) $path .= '.';
  	
  	$data = (array)$data;
  	
  	foreach ($definitions as $name => $definition) {
  		if (array_key_exists('default', $definition) && !array_key_exists($name, $data)) {
  			$data[$name] = $definition['default'];
  		}
  	}
  	
  	foreach ($data as $name => &$value) {
  		if ((!isset($definitions[$name]))||($definitions[$name]['type'] == 'null')) {
  			$this->setStatus(400, "{$path}{$name} : unknown parameter");
  			return false;
  		}
  		if ($value !== null) {
  			if (!$this->validRequestParameter($value, $definitions[$name], "{$path}{$name}", (object)$data)) {
  				return false;
  			}
  		}
  	}
  	
  	foreach ($definitions as $name => $definition) {
  		if ($definition['mandatory'] && ((!isset($data[$name])) || $data[$name] === null || $data[$name] === '')) {
  			$this->setStatus(400, "{$path}{$name} : parameter is mandatory");
  			return false;
  		}
  	}
  	
  	if ($path) {
  		// for sub structs turn into object
  		$data = (object)$data;
  	}
  	
  	return true;
  }
  
  /**
   * Allow handlers to alter/prepare the parameter value.
   * @param mixed $data
   * @param array $definition
   * @param string $path
   * @param mixed $parent
   */
	protected function prepareRequestParameterValue(&$data, $definition, $path, $parent) {
		if (isset($definition['prepare_callback']) && method_exists($this, $definition['prepare_callback'])) {
			$f = $definition['prepare_callback'];
			$this->$f($data, $definition, $path, $parent, $this);
		}
		else {
			$this->getWS()->prepareRequestParameterValue($data, $definition, $path, $parent, $this);
		}
	}
	
	/**
	 * Allow handlers to valid the parameter value.
	 * @param mixed $data
	 * @param array $definition
	 * @param string $path
	 * @param mixed $parent
   * 
   * @return boolean
   */
	protected function validRequestParameterValue($data, $definition, $path, $parent) {
		if (isset($definition['valid_callback']) && method_exists($this, $definition['valid_callback'])) {
			$f = $definition['valid_callback'];
			return $this->$f($data, $definition, $path, $parent, $this);
		}
		return $this->getWS()->validRequestParameterValue($data, $definition, $path, $parent, $this);
	}
  
  protected $_request;
  
  public function setRequest($request) {
    $this->_request = $request;
  }
  
  public function getRequest() {
  	return $this->_request;
  }
  
  public function __construct(WebserviceAbstract $ws) {
    $this->_ws = $ws;
    $this->_requestParameterDefinitions += $this->getWS()->getSharedRequestParameterDefinitions();
    $this->initParameterDefinitions($this->_requestParameterDefinitions);
  }
  
  protected function initParameterDefinitions(&$definitions) {
  	foreach ($definitions as $name => &$definition) {
  		$this->initParameterDefinition($definition);
  	}
  }
  
  protected function initParameterDefinition(&$definition) {
  	if (!is_array($definition)) {
  		$definition = array('type' => $definition);
  	}
  	
  	if (preg_match('/^\<(.+)\>$/', $definition['type'], $matches)) {
  		// get custom types
  		$typedef = $this->getWS()->getTypeDefinition($matches[1]);
  		if (!$typedef) {
  			throw new WebserviceException("{$matches[1]} : unknown custom type", WebserviceException::unknown_request_parameter_custom_type);
  		}
  		unset($definition['type']);
  		$definition = array_merge($typedef, $definition);
  		unset($typedef);
  	}
  	
  	$definition += array(
  			'type' => 'string',
  			'mandatory' => false,
  	);
  	if ($definition['type']=='array' && isset($definition['nested'])) {
  		$this->initParameterDefinition($definition['nested']);
  	}
  	if ($definition['type']=='struct' && isset($definition['struct'])) {
  		$this->initParameterDefinitions($definition['struct']);
  	}
  	if ($definition['type']=='tag' && empty($definition['case_sensitive']) && isset($definition['tags'])) {
  			foreach($definition['tags'] as &$tag) {
  				$tag = strtolower($tag);
  			}
  	}
  }
  
  /**
   * Main request function.
   * 
   * @return boolean true if request is successfull
   */
  abstract public function execute();
  
  /**
   * Called after execution and just before response building.
   * ie. you can set response data.
   */
  public function finalize() {
    //nothing
  }
  
  protected function setStatus($code, $message, $details = null) {
    $this->_statusCode = $code;
    $this->_statusMessage = $message;
    $this->_statusDetails = $details;
  }
  
  protected function setStatusDetails($details) {
    $this->_statusDetails = $details;
  }
  
  protected $_statusCode = 200;
  protected $_statusMessage = 'Success';
  protected $_statusDetails;
  protected $_responseData;

  public function getStatusCode() {
  	return $this->_statusCode;
  }
  
  public function getStatus() {
    return $this->getWS()->buildStatus(
      $this->_statusCode,
      $this->_statusMessage,
      $this->_statusDetails);
  }
  
  public function getResponseData() {
    return $this->_responseData;
  }
  
  protected function setResponseData($data) {
    $this->_responseData = $data;
  }
  
  /**
   * Check access at request level
   * @return boolean
   */
  public function canAccess() {
  	// default : all is OK
    return true;
  }
  
  /**
   * Validate stuff. You must set the status code and message with setStatus().
   * @return boolean
   */
  public function isValid() {
  	// default : all is OK
  	return true;
  }
  
  /**
   * Validate parameters.
   * @return boolean
   */
  public function areParametersValid() {
  	if ($this->_validRequest) {
  		return $this->validRequestParameterStruct($this->_request['#request'], $this->_requestParameterDefinitions, '', $this);
  	}
  	return true;
  }
  
  /**
   * Alter the requests before execution
   * @param array $requests
   */
  public function alterRequests(&$requests) {
    // nothing
  }
  
  public function &__get($name) {
    if (!isset($this->_request['#request'][$name])) {
    	$this->_request['#request'][$name] = null;
    }
    return $this->_request['#request'][$name];
  }
  
  public function __set($name, $value) {
  	$this->_request['#request'][$name] = $value;
  }
  
  public function __unset($name) {
  	unset($this->_request['#request'][$name]);
  }
  
  public function __isset($name) {
  	return isset($this->_request['#request'][$name]);
  }
  
  public function getTag() {
    return $this->_request['#tag'];
  }
  
  public function getName() {
    return $this->_request['#name'];
  }
}
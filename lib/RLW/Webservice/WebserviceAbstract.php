<?php
namespace RLW\Webservice;

use RLW\Tools;

use RLW\Webservice\RequestHandler\RequestHandlerAbstract;

abstract class WebserviceAbstract {
	
  /**
   * Associative array of api/(sub)request name to request handler class.
   * api/#main stands for the main request.
   * @var array
   */
  protected $_requestHandlerClassMap = array();
  
  /**
   * Shared types definitions.
   * @see RLW\Webservice\RequestHandler\RequestHandlerAbstract
   * @var array
   */
  protected $_typeDefinitions = array();
  
  /**
   * Shared request parameter sefinitions
   * @see RLW\Webservice\RequestHandler\RequestHandlerAbstract::$_requestParameterDefinitions
   * @var array
   */
  protected $_sharedRequestParameterDefinitions = array();
  
  public function getTypeDefinition($type) {
  	if (isset($this->_typeDefinitions[$type])) {
  		if (isset($this->_typeDefinitions[$type]['type']) && !isset($this->_typeDefinitions[$type]['prepare_callback'])) {
  			$this->_typeDefinitions[$type]['prepare_callback'] = 'prepareCustomTypeData'.ucfirst($type);
  		}
  		if (isset($this->_typeDefinitions[$type]['type']) && !isset($this->_typeDefinitions[$type]['valid_callback'])) {
  			$this->_typeDefinitions[$type]['valid_callback'] = 'validCustomTypeData'.ucfirst($type);
  		}
  		return $this->_typeDefinitions[$type];
  	}
  	return null;
  }
  
  public function getSharedRequestParameterDefinitions() {
  	return $this->_sharedRequestParameterDefinitions;
  }
  
  /**
   * Allow handlers to alter/prepare the parameter value.
   * @param mixed $data
   * @param array $definition
   * @param string $path
   * @param mixed $parent object
   * @param RequestHandlerAbstract $request
   */
  public function prepareRequestParameterValue(&$data, $definition, $path, $parent, RequestHandlerAbstract $request) {
  	if (isset($definition['prepare_callback']) && method_exists($this, $definition['prepare_callback'])) {
  		$f = $definition['prepare_callback'];
  		$this->$f($data, $definition, $path, $parent, $request);
  	}
  }
  
  /**
   * Allow handlers to valid the parameter value.
   * @param mixed $data
   * @param array $definition
   * @param string $path
   * @param mixed $parent object
   * @param RequestHandlerAbstract $request
   *
   * @return boolean
   */
  public function validRequestParameterValue($data, $definition, $path, $parent, RequestHandlerAbstract $request) {
  	if (isset($definition['valid_callback']) && method_exists($this, $definition['valid_callback'])) {
  		$f = $definition['valid_callback'];
  		return $this->$f($data, $definition, $path, $parent, $request);
  	}
  	return true;
  }  
  
  /**
   * Associative array tag => Request Handler Object
   * @var array
   */
  protected $_requestHandlers = array();
  
  protected function buildException(\Exception $e) {
    if (defined('DEBUG') && DEBUG) {
      return (object)array(
          'type' => get_class($e),
          'code' => $e->getCode(),
          'message' => $e->getMessage(),
          'file' => $e->getFile(),
          'line' => $e->getLine(),
          'trace' => $e->getTraceAsString(),
      );
    }
    return (object)array(
        'type' => get_class($e),
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
    );
  }
  
  /**
   * Get all (sub) requests and and do some validation stuff.
   * 
   * @param array $request
   * @return array ordered (sub) requests (from #requires)
   */
  protected function preprareRequests(array $allRequests) {
    
    $main = array(
        '#request' => $allRequests['#request'],
        );
    unset($allRequests['#request']);
    if (isset($allRequests['#requires'])) {
      $main['#requires'] = $allRequests['#requires'];
      unset($allRequests['#requires']);
    }
    
    foreach (array_keys($allRequests) as $tag) {
      if (!preg_match('/^[a-z][a-z0-9_\/-]*$/i', $tag)) {
        throw new WebserviceException("{$tag} : invalid sub request tag pattern.", WebserviceException::invalid_subrequest_tag);
      }
    }
    
    $allRequests['#main'] = $main;
        
    // make clean structure
    foreach ($allRequests as $tag => &$request) {
      if (!is_array($request)) $request = array();
      if (!isset($request['#name'])) $request['#name'] = $tag;
      $request['#tag'] = $tag;
      if (!isset($request['#requires'])) $request['#requires'] = array();
      if (!is_array($request['#requires'])) $request['#requires'] = array($request['#requires']);
      if (!isset($request['#request'])) $request['#request'] = array();
      $list = $request;
      foreach ($list as $k => $v) {
        if ($k{0} == '#') continue;
        $request['#request'][$k] = $v;
        unset($request[$k]);
      }
    }
    unset($request);
    
    // initialize request handlers and give the possibility to the handlers to alter the request 
    $this->alterRequests($allRequests);
    $list = $allRequests;
    foreach ($list as $tag => $request) {
      $handler = $this->initRequestHandler($request['#tag'], $request['#name']);
      $handler->setRequest($allRequests[$tag]);
      $handler->alterRequests($allRequests);
    }
    unset($request);

    // prepare topological sort 
    $nodeids = $edges = array();
    foreach ($allRequests as $tag => $request) {
      $nodeids[$tag] = $tag;
      foreach ($request['#requires'] as $reqtag) {
        $edges[] = array($reqtag, $tag);
      }
    }
    unset($request);
    
    foreach ($edges as $edge) {
      if (!isset($nodeids[$edge[0]])) {
        throw new WebserviceException("{$edge[0]} : unknown (sub)request", WebserviceException::unknown_subrequest_tag);
      }
    }
    
    $nodeids = array_values($nodeids);
      
    $ordered = Tools::topological_sort($nodeids, $edges);
    
    if ($ordered === null) {
      throw new WebserviceException('Loop in required (sub)requests', WebserviceException::loop_in_required_subrequests);
    }
    
    $res = array();
    foreach ($ordered as $tag) {
      $res[$tag] = $allRequests[$tag];
      // set definitive data
      $this->getRequestHandler($tag)->setRequest($allRequests[$tag]);
    }
    
    return $res;
  }
  
  /**
   * Current api and request
   * @var string
   */
  protected $_api;
  protected $_requests = array();
  
  public function getRequests() {
    return $this->_requests;
  }
  
  public function isRequest($name) {
    foreach ($this->_requests as $request) {
      if ($request['#name'] == $name) return true;
    }
    return false;
  }
  
  protected function getCurrentNamespace() {
    $class = get_class($this);
    $class = explode('\\', $class);
    array_pop($class);
    return implode('\\', $class);
  }
  
  /**
   * Init base request handler object.
   * @param string $tag
   * @param string $name
   * @throws WebserviceException
   * @return \RLW\Webservice\RequestHandler\RequestHandlerAbstract
   */
  protected function initRequestHandler($tag, $name) {
  	$id = $this->_api . '/' . $name;
  	if (!isset($this->_requestHandlersClassMap[$id])) {
  		throw new WebserviceException("{$name} : unknown (sub)request name", WebserviceException::unknown_subrequest_name);
  	}
  	$class = $this->_requestHandlersClassMap[$id];
  	if ($class{0} != '\\') $class = $this->getCurrentNamespace() . '\\' . $class;
  	$class = trim($class, '\\');
  	$this->_requestHandlers[$tag] = new $class($this);
  	return $this->_requestHandlers[$tag];
  }
  
  /**
   * Get request handler from request tag
   * @param string $tag
   * @throws WebserviceException
   * @return \RLW\Webservice\RequestHandler\RequestHandlerAbstract
   */
  public function getRequestHandler($tag) {
    if (!isset($this->_requestHandlers[$tag])) {
      throw new WebserviceException("{$tag} : cannot find (sub)request", WebserviceException::logic_inconsistency);
    }
    return $this->_requestHandlers[$tag];
  }
  
  /**
   * Keep track of successfull (or not) requests
   * @var array
   */
  protected $_requestsSuccess = array();
  
  protected function canExecuteRequestTag($tag) {
    foreach ($this->_requests[$tag]['#requires'] as $reqtag) {
      if (!$this->_requestsSuccess[$reqtag]) return false;
    }
    return true;
  }
  
  public function api($api, $apiRequest, $returnException = true) {
    try {
      $this->_api = ltrim($api, '/');
      $this->_requests = $this->preprareRequests($apiRequest);
      
      if (!$this->canAccess()) {
        return $this->buildResponse($this->buildStatus(401, 'Unauthorized'));
      }
      
      $this->init();
      
      $subRequestResponses = array();
      foreach ($this->_requests as $tag => $dummy) {
      	$this->_requestsSuccess[$tag] = false;
      	$handler = $this->getRequestHandler($tag);
      	    	
        if (!$this->canExecuteRequestTag($tag)) {
        	$status = $this->buildStatus(406, 'Required (sub) requests not satisfied');
        }
        elseif (!$handler->canAccess()) {
        	$status = $this->buildStatus(401, 'Unauthorized');
        }
        elseif (!$handler->areParametersValid()) {
        	$status = $handler->getStatus();
        }
        elseif (!$handler->isValid()) {
        	$status = $handler->getStatus();
        }
        elseif (!$handler->execute()) {
        	$status = $handler->getStatus();
        }
        else {
        	$this->_requestsSuccess[$tag] = true;
        }

        if(!$this->_requestsSuccess[$tag]) {
        	$subRequestResponses[$tag] = $this->buildResponse($status);
        }
      }
      
      // postpone successfull response building
      foreach ($this->_requests as $tag => $dummy) {
        if ($this->_requestsSuccess[$tag]) {
          $handler = $this->getRequestHandler($tag);
          $handler->finalize();
          $subRequestResponses[$tag] = $this->buildResponse($handler->getStatus(), $handler->getResponseData());
        }
      }
      
      $main = (array)$subRequestResponses['#main'];
      unset($subRequestResponses['#main']);
      $main += $subRequestResponses;

      return (object)$main;

    } catch (\Exception $e) {
      if ($returnException) {
        return (object)array(
            '#exception' => $this->buildException($e),
            );
      }
      throw $e;
    }
  }
  
  protected function buildResponse($status, $data = null) {
    $ret = array(
        '#status'=> $status,
        '#data'=> empty($data) ? null : $data,
    );
    return (object)$ret;
  }
  
  public function buildStatus($code, $message, $details = null) {
    $status = (object)array(
        'code' => $code,
        'message' => $message,
    );
    
    if ($details) $status->details = $details;
    
    return $status;
  }
  
  /**
   * Check access at webservice level
   * @return boolean
   */
  public function canAccess() {
    return true;
  }
  
  /**
   * Override with init stuff
   */
  public function init() {

  }
  
  /**
   * Alter the requests before execution
   * @param array $requests
   */
  protected function alterRequests(&$requests) {
    // nothing
  }
  
  public function catchErrors($errors) { 
    if (count($errors)) {
      $list = array();
      foreach ($errors as $error) {
        $list[] = $error->getMessage();
      }
      $list = array_unique($list);
      return $list;
    }
    
  }
}
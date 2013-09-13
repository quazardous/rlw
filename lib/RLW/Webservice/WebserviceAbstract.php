<?php
namespace RLW\Webservice;

use RLW\Tools;

use RequestHandler\RequestHandlerAbstract;

abstract class WebserviceAbstract {
  
  /**
   * Associative array of api/(sub)request name to request handler class.
   * api/#main stands for the main request.
   * @var array
   */
  protected $requestHandlerClassMap = array();
  
  /**
   * Associative array tag => Request Handler Object
   * @var array
   */
  protected $requestHandler = array();
  
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

    
    
    // give the possibility to the handlers to alter the request
    $list = $allRequests;
    $this->alterRequests($allRequests);
    foreach ($list as $request) {
      $this->getRequestHandler($request)->alterRequests($allRequests);
    }
    unset($request);

    // prepare topological sort 
    $nodeids = $edges = array();
    foreach ($allRequests as $tag => &$request) {
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
    }
    
    return $res;
  }
  
  /**
   * Current api and request
   * @var string
   */
  protected $api;
  protected $requests = array();
  
  public function getRequests() {
    return $this->requests;
  }
  
  public function isRequest($name) {
    foreach ($this->requests as $request) {
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
   * Get request handler from request struct or tag
   * @param array|string $tag
   * @throws WebserviceException
   * @return \RLW\Webservice\RequestHandler\RequestHandlerAbstract
   */
  public function getRequestHandler($tag) {
    if (is_array($tag)) {
      $orireq = $request = $tag;
      $tag = $request['#tag'];
    }
    else {
      if (isset($this->requests[$tag])) {
        $request = $this->requests[$tag];
      }
      else {
        $request = false;
      }
    }
    $id = false;
    if ($request) {
      $name = $request['#name'];
      $id = $this->api . '/' . $name;
    }
    
    if (!isset($this->requestHandlers[$tag])) {
      if (!$id) {
        throw new WebserviceException("{$tag} : cannot determine (sub)request name", WebserviceException::logic_inconsistency);
      }
      if (!isset($this->requestHandlersClassMap[$id])) {
        throw new WebserviceException("{$name} : unknown (sub)request name", WebserviceException::unknown_subrequest_name);
      }
      $class = $this->requestHandlersClassMap[$id];
      if ($class{0} != '\\') $class = $this->getCurrentNamespace() . '\\' . $class;
      $class = trim($class, '\\');
      $this->requestHandlers[$tag] = new $class($this);
    }
    if (isset($orireq)) $this->requestHandlers[$tag]->setRequest($orireq);
    return $this->requestHandlers[$tag];
  }
  
  /**
   * Keep track of successfull (or not) requests
   * @var array
   */
  protected $requestsSuccess = array();
  
  protected function canExecuteRequestTag($tag) {
    foreach ($this->requests[$tag]['#requires'] as $reqtag) {
      if (!$this->requestsSuccess[$reqtag]) return false;
    }
    return true;
  }
  
  public function api($api, $request, $returnException = true) {
    try {
      $this->api = ltrim($api, '/');
      $this->requests = $this->preprareRequests($request);
      
      if (!$this->canAccess()) {
        return $this->buildResponse($this->buildStatus(401, 'Unauthorized'));
      }
      
      $this->init();
      
      $subRequestResponses = array();
      foreach ($this->requests as $tag => $request) {
        if ($this->canExecuteRequestTag($tag)) {
          $handler = $this->getRequestHandler($request);
          if ($handler->canAccess()) {
            if ($handler->isValid()) {
              $this->requestsSuccess[$tag] = $handler->execute();
              
              if (!$this->requestsSuccess[$tag]) {
                $subRequestResponses[$tag] = $this->buildResponse($handler->getStatus());
              }
            }
            else {
              $this->requestsSuccess[$tag] = false;
              $subRequestResponses[$tag] = $this->buildResponse($handler->getStatus());
            }
          }
          else {
            $this->requestsSuccess[$tag] = false;
            $subRequestResponses[$tag] = $this->buildResponse($this->buildStatus(401, 'Unauthorized'));
          }
        }
        else {
          $this->requestsSuccess[$tag] = false;
          $subRequestResponses[$tag] = $this->buildResponse($this->buildStatus(406, 'Required (sub) requests not satisfied'));
        }
      }
      
      // postpone successfull response building
      foreach ($this->requests as $tag => $request) {
        if ($this->requestsSuccess[$tag]) {
          $handler = $this->getRequestHandler($request);
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
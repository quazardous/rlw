<?php

class RLWException extends Exception {
  const INIT = 10;
  const CURL = 100;
  const API_ERROR = 1000;
  const API_EXCEPTION = 1001;
}

class RLWRequestBase {

  protected $_request;

  public function __construct($request = null) {
    $this->_request = $request;
  }

  public function __set($name, $value) {
    $this->_request[$name] = $value;
  }
  
  public function &__get($name) {
    if (!isset($this->_request[$name])) $this->_request[$name] = null;
    return $this->_request[$name];
  }

  public function getRequest() {
    return (array)$this->_request;
  }
  
}

class RLWRequest extends RLWRequestBase {
  /**
   * @var RLW
   */
  protected $_ws;
  
  /**
   * @return RLW
   */
  public function getWS() {
    return $this->_ws;
  }
  
  protected $_url;
  
  public function __construct(RLW $ws, $url, $request = null) {
    parent::__construct($request);
    $this->_ws = $ws;
    $this->_url = $url;
  }
  
  protected $_subRequests = array();
  
  protected $_subIdx = 0;
  
  /**
   * @param string $name
   * @param array|string $request array of data or tag
   * @param array|string $requires
   * @return RLWRequest
   */
  public function subRequest($name, $request = false, $requires = false) {
     if (is_array($request) || is_object($request)) {
      $request = (array)$request;
      if (isset($request['#tag'])) {
        $tag = $request['#tag'];
        unset($request['#tag']);
      }
      else {
        $tag = $name;
      }
    }
    elseif ($request === null) {
      $request = array();
      $tag = 'sub' . $this->_subIdx; // must be a string
      $this->_subIdx++;
    }
    elseif ($request === false) {
      $request = array();
      $tag = $name;
    }
    elseif(is_string($request)) {
      $tag = $request; // must be a string
      $request = array();
    }
    else {
      $request = array();
      $tag = $name;
    }
    if ($tag !== $name) {
      $request['#name'] = $name;
    }
    if ($requires) {
      $request['#requires'] = $requires;
    }
    $this->_subRequests[$tag] = new RLWRequestBase($request);
    return $this->_subRequests[$tag];
  }
    
  /**
   * Execute the request
   * @param string $post force methode post
   * @throws RLWException
   * @return mixed
   */
  public function execute($post = null) {
    
    if ($post === null) {
      if ($this->getWS()->forcePost()) {
        $post = true;
      }
    }
    
    if ($this->getWS()->proxy()) {
      return $this->proxyExecute($this->getWS()->proxy());
    }
    
    // get default params
    $request = array_merge($this->getWS()->getRequest(), $this->_request);
    
    $url = $this->getWS()->getBaseUrl() . '/' . $this->_url;
    if (count($this->_subRequests)||$post) {
      $post = array('#request' => $this->_request);
      foreach ($this->_subRequests as $tag => $subRequest) {
        $post[$tag] = $subRequest->getRequest();
      }
      $post = json_encode($post);
    }
    elseif ($this->_request) {
      $url .= '?' . http_build_query($this->_request);
    }
    $res = $this->getWS()->httpRequest($url, $post);
    if ($res['data'] === false) {
      throw new RLWException('Curl error !', RLWException::CURL);
    }
    $ret = json_decode($res['data'], false);
    if ($ret === null) {
      throw new RLWException('API error (status = ' . $res['status'] . ")\n"  . $res['data'], RLWException::API_ERROR);
    }
    $this->catchException($ret);
    return $ret;
  }
  
  /**
   * Execute the request using the proxy class (aka the server webservice object).
   */
  public function proxyExecute($proxy) {
    $request = array('#request' => (array)$this->_request);
    foreach ($this->_subRequests as $tag => $subRequest) {
      $request[$tag] = $subRequest->getRequest();
    }
    try {
      return $proxy->api($this->_url, $request, false);
    }
    catch (\Exception $e) {
      throw new RLWException(
          sprintf("API %s : %s (%d)",
              get_class($e),
              $e->getMessage(),
              $e->getCode()),
              RLWException::API_EXCEPTION);
    }
    return $ret;
  }
  
  protected function catchException($ret) {
    if (isset($ret->{'#exception'})) {
      $trace = "";
      if ($this->getWS()->debug()) {
        if (isset($ret->{'#exception'}->file)) $trace .= "\n\n" . $ret->{'#exception'}->file . ':' . $ret->{'#exception'}->line . "\n\n";
        if (isset($ret->{'#exception'}->trace)) $trace .= $ret->{'#exception'}->trace;
      }
      throw new RLWException(
          sprintf("API %s : %s (%d)%s",
              $ret->{'#exception'}->type,
              isset($ret->{'#exception'}->message) ? $ret->{'#exception'}->message : '***',
              $ret->{'#exception'}->code,
              $trace),
              RLWException::API_EXCEPTION);
    }
  }
}

class RLW extends RLWRequestBase {
  protected $_options;
  public function __construct($options = array()) {
    if (class_exists('\\RLW\\Webservice\\WebserviceAbstract') && $options instanceof \RLW\Webservice\WebserviceAbstract) {
      $options = array('proxy' => $options);
    }
    else if (!is_array($options)) {
      $options = array('base_url' => $options);
    }
    
    if (!isset($options['proxy']) && !isset($options['base_url'])) {
      throw new Exception('base_url is not defined !', RLWException::INIT);
    }
    $this->_options = $options;
  }
  
  public function getBaseUrl() {
    return $this->_options['base_url'];
  }
  
  public function debug($debug = null) {
    if ($debug !== null) $this->_options['debug'] = $debug;
    return isset($this->_options['debug']) ? $this->_options['debug'] : false;
  }
  
  public function createRequest($url, $request = null) {
    // add default args
    $request = array_merge($this->getRequest(), (array)$request);
    return new RLWRequest($this, $url, $request);
  }
  
  public function httpRequest($url, $post = null) {
    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, $url);
    
    if (isset($this->_options['curl_proxy'])) {
      curl_setopt($c, CURLOPT_PROXY, $this->_options['curl_proxy']);
      if (isset($this->_options['curl_proxy_userpwd'])) {
        curl_setopt($c, CURLOPT_PROXYUSERPWD, $this->_options['curl_proxy_userpwd']);
      }
      curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
    }
    
    if (isset($this->_options['curl_connecttimeout'])) {
    	curl_setopt($c, CURLOPT_CONNECTTIMEOUT , $this->_options['curl_connecttimeout']);
    }
    
    if (isset($this->_options['curl_timeout'])) {
    	curl_setopt($c, CURLOPT_TIMEOUT, $this->_options['curl_timeout']);
    }
    
    if ($post) {
      curl_setopt ($c, CURLOPT_POST, 1);
      curl_setopt ($c, CURLOPT_POSTFIELDS, $post);
    }
    curl_setopt($c, CURLOPT_ENCODING, ''); // allow gzip/deflate if available
    curl_setopt ($c, CURLOPT_RETURNTRANSFER, 1);
    $ret = curl_exec ($c);
    $http_status = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close ($c);
    
    return array('data' => $ret, 'status' => $http_status);
  }
  
  public function proxy() {
    if (isset($this->_options['proxy']) && $this->_options['proxy'] instanceof \RLW\Webservice\WebserviceAbstract) {
      return $this->_options['proxy'];
    }
    return false;
  }
  
  public function forcePost($force = null) {
    if ($force !== null) {
      $this->_options['force_post'] = $force ? true : false;
    }
    if (isset($this->_options['force_post']) && $this->_options['force_post']) {
      return true;
    }
    return false;
  }
}
<?php
namespace RLW\Webservice\RequestHandler;

use RLW\Webservice\WebserviceAbstract;

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
  
  protected $_request;
  
  public function setRequest($request) {
    $this->_request = $request;
  }
  
  public function __construct(WebserviceAbstract $ws) {
    $this->_ws = $ws;
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
    return true;
  }
  
  /**
   * Validate stuff. You must set the status code and message with setStatus().
   * @return boolean
   */
  public function isValid() {
    return true;
  }
  
  /**
   * Alter the requests before execution
   * @param array $requests
   */
  public function alterRequests(&$requests) {
    // nothing
  }
  
  public function __get($name) {
    if (isset($this->_request['#request'][$name])) return $this->_request['#request'][$name];
    return null;
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
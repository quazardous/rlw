<?php
namespace RLW;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use RLW\Webservice\WebserviceAbstract;

class Application extends \Pimple {
  protected function parseRequest(Request $request) {
    $get = array( '#request' => $request->query->all() );
    $post = json_decode($request->getContent(), true);
    if (empty($post)) $post = array();
    return array_merge_recursive($get, $post);
  }
  
  protected function renderResponse($data) {
    $response = new Response(
          json_encode($data),
          isset($data->{'#exception'}) ? 500 : (isset($data->{'#status'}->code) ? $data->{'#status'}->code : 200),
          array('Content-Type' => 'application/json')
        );
    $response->send();
  }
  
  protected $_routes = array();
  
  /**
   * Add a new route.
   * @param string $url
   * @param string|callback $action
   */
  public function route($url, $action) {
    $this->_routes[$url] = $action;
  }
  
  public function run() {
    $request = Request::createFromGlobals();
    if (!isset($this->_routes[$request->getPathInfo()])) {
      $response = new Response('Not found', 404);
      $response->send();
      return;
    }
    $action = $this->_routes[$request->getPathInfo()];
    
    if ($action instanceof WebserviceAbstract) {
      $this->renderResponse($action->api($request->getPathInfo(), $this->parseRequest($request)));
    }
    elseif (is_callable($action)) {
      $this->renderResponse(call_user_func($action, $request));
    }
    else {
      $this->renderResponse($action);
    }
  }
}
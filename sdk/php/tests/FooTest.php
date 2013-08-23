<?php

require_once __DIR__ . '/config/foo.php';

require_once __DIR__ . '/../src/RLW.php';

class FooTest extends PHPUnit_Framework_TestCase {
 
  public function testNewRLW()
  {
    if (getenv('FOO_PROXY_MODE')) {
      $ws = new RLW(new \Foo\Webservice\WebserviceFoo);
    }
    else {
      $ws = new RLW(FOO_BASE_URL);
    }
    $this->assertTrue($ws instanceof RLW);
    return $ws;
  }
  
  /**
   * @depends testNewRLW
   * @expectedException RLWException
   * NB exception code is different in proxy mode (1000 vs 1001)
   */
  public function testApiUnkown(RLW $ws)
  {
    $request = $ws->createRequest('unknown');
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute();
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFoo(RLW $ws)
  {
    $args = array('r' => rand());
    $request = $ws->createRequest('foo', (object)$args);
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute();
    $this->assertEquals(200, $res->{'#status'}->code);
    $this->assertEquals($args, (array)$res->{'#data'}->{'#request'});
  }

  /**
   * @depends testNewRLW
   */
  public function testApiFooAlternativeArgsSetting(RLW $ws)
  {
    $args = array('r' => rand());
    $request = $ws->createRequest('foo');
    $request->r = $args['r'];
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute();
    $this->assertEquals(200, $res->{'#status'}->code);
    $this->assertEquals($args, (array)$res->{'#data'}->{'#request'});
  }  
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooPost(RLW $ws)
  {
    $args = array('r' => rand());
    $request = $ws->createRequest('foo', $args);
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute(true);
    $this->assertEquals(200, $res->{'#status'}->code);
    $this->assertEquals($args, (array)$res->{'#data'}->{'#request'});
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooSub(RLW $ws)
  {
    $args = array('r' => rand());
    $request = $ws->createRequest('foo', $args);
    $request->subRequest('bar', array('foo'=>'bar'));
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute();
    $this->assertEquals(200, $res->bar->{'#status'}->code);
    $this->assertEquals('bar', $res->bar->{'#data'}->{'#request'}->foo);
  }

  /**
   * @depends testNewRLW
   * @expectedException RLWException
   * @expectedExceptionCode 1001
   * @expectedExceptionMessage API RLW\Webservice\WebserviceException : unknown : unknown (sub)request name (4)
   */
  public function testApiFooSubUnknown(RLW $ws)
  {
    $request = $ws->createRequest('foo');
    $request->subRequest('unknown');
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute();
  } 
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooSubRequires(RLW $ws)
  {
    $request = $ws->createRequest('foo');
    $request->r = rand();
    $request->subRequest('bar', false, 'foo');
    $request->subRequest('foo');
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute();
    $this->assertEquals(200, $res->bar->{'#status'}->code);
    $this->assertEquals(array('foo'), $res->bar->{'#data'}->{'#requires'});
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooSubRequiresMultiple(RLW $ws)
  {
    $args = array('r' => rand());
    $request = $ws->createRequest('foo', $args);
    $request->subRequest('boo');
    $request->subRequest('far');
    $request->subRequest('bar', false, 'foo');
    $request->subRequest('foo', false, array('boo', 'far'));
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute();
    $this->assertEquals(200, $res->bar->{'#status'}->code);
    $tmp = (array) $res;
    unset($tmp['#status']);
    unset($tmp['#data']);
    $tmp = array_keys($tmp);
    // subrequest should be sorted by dependencies
    $this->assertEquals($tmp, array('boo', 'far', 'foo', 'bar'), 'Invalid order');
  }

  /**
   * @depends testNewRLW
   * @expectedException RLWException
   * @expectedExceptionCode 1001
   * @expectedExceptionMessage API RLW\Webservice\WebserviceException : foo : unknown (sub)request (2)
   */
  public function testApiFooSubRequiresUnknown(RLW $ws)
  {
    $args = array('r' => rand());
    $request = $ws->createRequest('foo', $args);
    $request->subRequest('bar', false, 'foo');
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute();
  }  
  
  /**
   * @depends testNewRLW
   * @expectedException RLWException
   * @expectedExceptionCode 1001
   * @expectedExceptionMessage API RLW\Webservice\WebserviceException : Loop in required (sub)requests (1)
   */
  public function testApiFooSubRequiresLoop(RLW $ws)
  {
    $args = array('r' => rand());
    $request = $ws->createRequest('foo', $args);
    $request->subRequest('boo', array(), 'bar');
    $request->subRequest('bar', array(), 'foo');
    $request->subRequest('foo', array(), 'boo');
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute();
  }
  
  /**
   * @depends testNewRLW
   * @expectedException RLWException
   * @expectedExceptionCode 1001
   * @expectedExceptionMessage API RLW\Webservice\WebserviceException : Loop in required (sub)requests (1)
   */
  public function testApiFooSubRequiresLoop2(RLW $ws)
  {
    $args = array('r' => rand());
    $request = $ws->createRequest('foo', $args);
    $request->subRequest('foo', array(), 'bar');
    $request->subRequest('bar', array(), 'foo');
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute();
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceAccessBlocked(RLW $ws)
  {
    $request = $ws->createRequest('foo');
    $request->blockme = true;
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute();
    $this->assertEquals(401, $res->{'#status'}->code);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooRequestAccessBlocked(RLW $ws)
  {
    $request = $ws->createRequest('foo');
    $this->assertTrue($request instanceof RLWRequest);
    $subreq = $request->subRequest('bar');
    $subreq->blockme = true;
    $res = $request->execute();
    $this->assertEquals(401, $res->bar->{'#status'}->code);
    $this->assertEquals(200, $res->{'#status'}->code);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooDoubleSubs(RLW $ws)
  {
    $args = array('r' => rand());
    $request = $ws->createRequest('foo', $args);
    $bar1 = $request->subRequest('bar', 'bar1');
    $bar1->foo = 'foo';
    $bar2 = $request->subRequest('bar', 'bar2');
    $bar2->boo = 'boo';
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute();
    $this->assertEquals(200, $res->bar1->{'#status'}->code);
    $this->assertEquals(200, $res->bar2->{'#status'}->code);  
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooAutoTags(RLW $ws)
  {
    $args = array('r' => rand());
    $request = $ws->createRequest('foo', $args);
    $bar1 = $request->subRequest('bar', null);
    $bar1->foo = 'foo';
    $bar2 = $request->subRequest('bar', null);
    $bar2->boo = 'boo';
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute();
    $this->assertEquals(200, $res->sub0->{'#status'}->code);
    $this->assertEquals(200, $res->sub1->{'#status'}->code);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooDefaultsArgs(RLW $ws)
  {
    $ws->foo = 'bar';
    $request = $ws->createRequest('foo');
    $this->assertTrue($request instanceof RLWRequest);
    $res = $request->execute();
    $this->assertEquals('bar', $res->{'#data'}->{'#request'}->foo);
  }
}
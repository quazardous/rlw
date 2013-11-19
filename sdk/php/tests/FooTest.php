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
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceAlterRequest(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$bar = $request->subRequest('bar');
  	$bar->foo = 'one';
  	$alter = $request->subRequest('alter');
  	$res = $request->execute();
  	$this->assertEquals(200, $res->bar->{'#status'}->code);
  	$this->assertEquals('two', $res->bar->{'#data'}->{'#request'}->foo);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesMandatory(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('mandatoryString : parameter is mandatory', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesMandatoryNull(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = null;
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('mandatoryString : parameter is mandatory', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesMandatoryEmptyString(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = '';
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('mandatoryString : parameter is mandatory', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesMandatoryZero(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = 0;
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('mandatoryString : not a string', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesMandatoryOk(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals("xyz", $res->types->{'#data'}->{'#request'}->mandatoryString);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesNull(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->null = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('null : unknown parameter', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceSharedOK(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('shared');
  	$types->shared1 = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(200, $res->shared->{'#status'}->code);
  	$this->assertEquals("xyz", $res->shared->{'#data'}->{'#request'}->shared1);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceSharedDisabled(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('shared');
  	$types->shared2 = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->shared->{'#status'}->code);
  	$this->assertEquals('shared2 : unknown parameter', $res->shared->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesStringLenMin(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->sizeString = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('sizeString : minimum length is 5', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesStringLenMax(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->sizeString = "xyz0123456789";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('sizeString : maximum length is 10', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesStringLenOK(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->sizeString = "xyz0123";
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals("xyz0123", $res->types->{'#data'}->{'#request'}->sizeString);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesStringTruncateOK(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->truncateString = "xyz0123456789";
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals("xyz0123456", $res->types->{'#data'}->{'#request'}->truncateString);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesFreeString(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeString = "xyz0123";
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals("xyz0123", $res->types->{'#data'}->{'#request'}->freeString);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesPatternStringKO(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->patternString = "!xyz0123";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('patternString : must match pattern /^xyz/', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesPatternStringOK(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->patternString = "xyz0123";
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals("xyz0123", $res->types->{'#data'}->{'#request'}->patternString);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesPositiveNumericBadType(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->positiveNumeric = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('positiveNumeric : not a numeric', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesPositiveNumericKO(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->positiveNumeric = -1;
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('positiveNumeric : minimum value is 0', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesPositiveNumericOK(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->positiveNumeric = 0;
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals(0, $res->types->{'#data'}->{'#request'}->positiveNumeric);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesPositiveNumericOKFloat(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->positiveNumeric = 0.1;
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals(0.1, $res->types->{'#data'}->{'#request'}->positiveNumeric);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesdefaultString(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals("xyz", $res->types->{'#data'}->{'#request'}->defaultString);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesTagKO(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeTag = 'KO';
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('freeTag : KO not in the list', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesTagOK(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeTag = 'two';
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals('two', $res->types->{'#data'}->{'#request'}->freeTag);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesTagCaseInsensitiveOK(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeTag = 'Two';
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals('Two', $res->types->{'#data'}->{'#request'}->freeTag);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesTagUpperOK(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->upperFreeTag = 'Two';
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals('TWO', $res->types->{'#data'}->{'#request'}->upperFreeTag);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesBooleanKO(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeBoolean = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('freeBoolean : not a boolean', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesBooleanTrue(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeBoolean = true;
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals(true, $res->types->{'#data'}->{'#request'}->freeBoolean);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesBooleanFalse(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeBoolean = false;
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals(false, $res->types->{'#data'}->{'#request'}->freeBoolean);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesBoolean1(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeBoolean = 1;
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals(1, $res->types->{'#data'}->{'#request'}->freeBoolean);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesBoolean0(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeBoolean = 0;
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals(0, $res->types->{'#data'}->{'#request'}->freeBoolean);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesBooleanKO2(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeBoolean = 2;
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('freeBoolean : not a boolean', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesMixedKO(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->freeMixed = 1;
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('freeMixed : parameter is not valid', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesMixedOK1(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeMixed = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals("xyz", $res->types->{'#data'}->{'#request'}->freeMixed);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesMixedOK2(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeMixed = array("xyz");
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  	$this->assertEquals(array("xyz"), $res->types->{'#data'}->{'#request'}->freeMixed);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesDateKO(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->freeDate = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('freeDate : not a date', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesDateOK2(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeDate = "2013-02-31T23:59:59Z";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('freeDate : not a date', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesDateOK(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeDate = "2013-02-31";
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesDatetimeKO(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeDatetime = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('freeDatetime : not a datetime', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesDatetimeKO2(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeDatetime = "2013-02-31";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('freeDatetime : not a datetime', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesDatetimeOK1(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->freeDatetime = "2013-02-31T23:59:59Z";
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesDateCastKO(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->flexDatetime = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->types->{'#status'}->code);
  	$this->assertEquals('flexDatetime : not a datetime', $res->types->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesDateCastOK(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('types');
  	$types->mandatoryString = "xyz";
  	$types->flexDatetime = "2013-02-31";
  	$res = $request->execute();
  	$this->assertEquals(200, $res->types->{'#status'}->code);
  }
  
  /**
   * @depends testNewRLW
   * @expectedException RLWException
   * @expectedExceptionCode 1001
   * @expectedExceptionMessage API RLW\Webservice\WebserviceException : struct requires a struct definition (11)
   */
  public function testApiFooWebserviceTypesStructNoStruct(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('bad/structs');
  	$types->freeStruct = (object)array();
  	$res = $request->execute();
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesStructBadValue(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('structs');
  	$types->freeStruct = (object)array();
  	$res = $request->execute();
  	$this->assertEquals(400, $res->structs->{'#status'}->code);
  	$this->assertEquals('freeStruct.a : parameter is mandatory', $res->structs->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesStructBadValue2(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('structs');
  	$types->freeStruct = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->structs->{'#status'}->code);
  	$this->assertEquals('freeStruct : parameter is not a struct', $res->structs->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesStructBadStructValue(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('structs');
  	$types->freeStruct = (object)array('a' => 1);
  	$res = $request->execute();
  	$this->assertEquals(400, $res->structs->{'#status'}->code);
  	$this->assertEquals('freeStruct.a : not a string', $res->structs->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesStructOK(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('structs');
  	$types->freeStruct = (object)array('a' => "xyz");
  	$res = $request->execute();
  	$this->assertEquals(200, $res->structs->{'#status'}->code);
  	$this->assertEquals((object)array('a' => "xyz"), $res->structs->{'#data'}->{'#request'}->freeStruct);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesStructKOUnknown(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('structs');
  	$types->freeStruct = (object)array('a' => "xyz", 'c' => 1);
  	$res = $request->execute();
  	$this->assertEquals(400, $res->structs->{'#status'}->code);
  	$this->assertEquals('freeStruct.c : unknown parameter', $res->structs->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceTypesKOUnknown(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$types = $request->subRequest('structs');
  	$types->unknown = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->structs->{'#status'}->code);
  	$this->assertEquals('unknown : unknown parameter', $res->structs->{'#status'}->message);
  }

  /**
   * @depends testNewRLW
   * @expectedException RLWException
   * @expectedExceptionCode 1001
   * @expectedExceptionMessage API RLW\Webservice\WebserviceException : array requires a nested type (9)
   */
  public function testApiFooWebserviceArraysNoNested(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$arrays = $request->subRequest('bad/arrays');
  	$arrays->freeStringsArray = "bad";
  	$res = $request->execute();
  }  
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceArraysBad(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$arrays = $request->subRequest('arrays');
  	$arrays->freeStringsArray = "bad";
  	$res = $request->execute();
  	$this->assertEquals(400, $res->arrays->{'#status'}->code);
  	$this->assertEquals('freeStringsArray : not an array', $res->arrays->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceArraysBadItems(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$arrays = $request->subRequest('arrays');
  	$arrays->freeStringsArray = array("xyz", "xyz", 1);
  	$res = $request->execute();
  	$this->assertEquals(400, $res->arrays->{'#status'}->code);
  	$this->assertEquals('freeStringsArray[2] : not a string', $res->arrays->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceArraysLooseOK(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$arrays = $request->subRequest('arrays');
  	$arrays->looseStringsArray = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(200, $res->arrays->{'#status'}->code);
  	$this->assertEquals(array("xyz"), $res->arrays->{'#data'}->{'#request'}->looseStringsArray);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceArraysOK(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$arrays = $request->subRequest('arrays');
  	$arrays->freeStringsArray = array("xyz", "xyz", "xyz");
  	$res = $request->execute();
  	$this->assertEquals(200, $res->arrays->{'#status'}->code);
  	$this->assertEquals(array("xyz", "xyz", "xyz"), $res->arrays->{'#data'}->{'#request'}->freeStringsArray);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceArraysSizeMin(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$arrays = $request->subRequest('arrays');
  	$arrays->sizeStringsArray = array("xyz", "xyz");
  	$res = $request->execute();
  	$this->assertEquals(400, $res->arrays->{'#status'}->code);
  	$this->assertEquals('sizeStringsArray : minimum length is 3', $res->arrays->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceArraysSizeMax(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$arrays = $request->subRequest('arrays');
  	$arrays->sizeStringsArray = array("xyz", "xyz", "xyz", "xyz", "xyz", "xyz", "xyz", "xyz", "xyz", "xyz", "xyz", "xyz");
  	$res = $request->execute();
  	$this->assertEquals(400, $res->arrays->{'#status'}->code);
  	$this->assertEquals('sizeStringsArray : maximum length is 10', $res->arrays->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   * @expectedException RLWException
   * @expectedExceptionCode 1001
   * @expectedExceptionMessage API RLW\Webservice\WebserviceException : type1 : unknown custom type (12)
   */
  public function testApiFooWebserviceCustomTypeUndefinedType(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$custom = $request->subRequest('bad/custom/types');
  	$custom->struct1 = array("foo" => "xyz");
  	$res = $request->execute();
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceCustomTypeKO(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$custom = $request->subRequest('custom/types');
  	$custom->struct2 = array("foo" => "xyz", "bad" => 1);
  	$res = $request->execute();
  	$this->assertEquals(400, $res->{'custom/types'}->{'#status'}->code);
  	$this->assertEquals('struct2.bad : unknown parameter', $res->{'custom/types'}->{'#status'}->message);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceCustomTypeOK(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$custom = $request->subRequest('custom/types');
  	$custom->struct2 = array("foo" => "xyz");
  	$res = $request->execute();
  	$this->assertEquals(200, $res->{'custom/types'}->{'#status'}->code);
  	$this->assertEquals((object)array("foo" => "xyz"), $res->{'custom/types'}->{'#data'}->{'#request'}->struct2);
  }
  
  /**
   * @depends testNewRLW
   */
  public function testApiFooWebserviceCustomTypePrepare(RLW $ws)
  {
  	$request = $ws->createRequest('foo');
  	$this->assertTrue($request instanceof RLWRequest);
  	$custom = $request->subRequest('custom/types');
  	$custom->struct3 = "xyz";
  	$res = $request->execute();
  	$this->assertEquals(200, $res->{'custom/types'}->{'#status'}->code);
  	$this->assertEquals((object)array("foo" => "xyz"), $res->{'custom/types'}->{'#data'}->{'#request'}->struct3);
  }
}
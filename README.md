RLW
===
Rest Like Webservice bootstrap

Goal
====
Provide a lightweight bootstrap/framework for REST like webservice/JSON API.

REST like philosophy
====================
Say you want to handle blog Posts.

You'll have to provide a getPost() request and a putPost() request. And maybe you'll have a addComment() request. But at the end those three requests will probably return the Post object...

With RLW you can define a main post() request and add subrequests : put() and addComment().

RLW manages (sub)requests dependencies. So if a Post does not exist subrequests will be cancelled.

One of the side effects is to save some HTTP calls : you can "package" many actions in one request.

Bootsrap
========

The ./foo folder shows a basic webservice (used with PHPUnit tests).

Webservice class
----------------

The core is the Foo\Webservice\WebserviceFoo class. It provides the (sub)request/class map.

    protected $_requestHandlersClassMap = array(
      'foo/#main' => "RequestHandler\\RequestHandlerFooDefault",
      'foo/bar'   => "RequestHandler\\RequestHandlerFooDefault",
      'foo/foo'   => "RequestHandler\\RequestHandlerFooDefault",
      'foo/boo'   => "RequestHandler\\RequestHandlerFooDefault",
      'foo/far'   => "RequestHandler\\RequestHandlerFooDefault",
    );

Request class
-------------

You must implement at least the execute() method wich must return TRUE if successfull.
Mainly you will do your stuff an set status and data to return.

 * setStatus(): define the ststus (default 200/success)
 * setResponseData(): specify some data to return
 * canAccess(): you can tell RLW if this request can be executed
 * isValid(): you can tell RLW if request is correct
 * alterRequests(): you can alter all (sub)requests...

Data Validation
---------------

You can describe the input data and RLW takes care of the data validation.

    protected $_requestParameterDefinitions = array(
      'freeStringsArray' => array(
         'type' => 'array',
         'nested' => 'string',),
      'sizeStringsArray' => array(
          'type' => 'array',
          'min' => 3,
          'max' => 10,
          'nested' => 'string',),
      );

Type definition
---------------

You can define a custom user type.

    protected $_typeDefinitions = array(
      'my_struct' => array(
        'type' => 'struct',
        'struct' => array(
          'foo' => array('type' => 'string'),
          ),
      ),
    );

And use it.

     protected $_requestParameterDefinitions = array(
      'field' => array(
         'type' => '<my_struct>',
         'mandatory' => true,),
     );

PHP SDK
=======
RLW comes with a basic PHP SDK.

Basic request:

    require_once "rlw/sdk/php/src/RLW.php";
    $ws = new RLW('http://mysite.com/my/api');
    $request = $ws->createRequest('foo');
    $request->r = rand();
    $res = $request->execute();
    ...

With a subrequest:

    require_once "rlw/sdk/php/src/RLW.php";
    $ws = new RLW('http://mysite.com/my/api');
    $request = $ws->createRequest('foo');
    $request->r = rand();
    $bar = $request->subRequest('bar');
    $bar->x = 'something';
    $res = $request->execute();
    ...

see rlw/sdk/php/tests/FooTest.php for more examples.

Syntax
======

Here is the raw HTTP syntax.

Request
-------

Request can be GET params (for simple one) or POST JSON.

In example:

GET request: /api/foo/?bar=1

is equivalent to

POST request: /api/foo/

sending:

    {
      #request: {bar: 1}
    }

If API call has both GET and POST/JSON, API will merge request but POST will take over GET.

Subrequest
----------

POST API calls can have “sub” requests:

    {
      #request: {bar: 1},
      mySubRequestTag: {
        #name: 'mySubRequest',
        ...
      }
    }

You can ommit the #name:

    {
      #request: {bar: 1},
      mySubRequest: {...}
    }

Equivalent to:

    {
      #request: {bar: 1},
      mySubRequest: {
        #name: 'mySubRequest',
        ...
      }
    }

response to sub requests are sent like:

    {
      #status: <responseStatus>,
      #data: <responseData>,
      mySubRequestTag: <response>,
    }
    
status for sub request and request are not linked.

Response
--------

Response is in JSON.

    {
      #status: {
        code: <int>,
        message: <string>,
        [details: [<string] ]
      },
      #data: <responseData>
    }

Requests dependency
-------------------

(Sub) request can define a #requires key to specify ”(sub) request success dependency”. If one of the specified (sub) request is not successful, the current (sub) request is not executed.

    #requires < string | array<string> > [optional] : [list of] sub request tag to be successful

You can refer to the main request with a '#main' tag. You ca put the #requires in the main request. The API will perform a topoligical sort to determine the right order of execution.

Example:

    {
      #request: {bar: 1},
      #requires : "myOtherSubRequest",
      myFirstSubRequest: {...}    
      myOtherSubRequest: {"#requires": "myFirstSubRequest", ...},
    }

Exceptions
----------

API can raise an exception if something goes wrong. The exception replaces the normal response.

Example:

    {
      "#exception": {
        type: "Exception",
        code: 0,
        message: "Something is wrong"
      }
    }


Folders
=======
 + foo: dummy webservice for example
 + sdk: PHP basic SDK


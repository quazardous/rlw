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

Syntax
======

Request
-------

Request can be GET params (for simple one) or POST JSON.

In example :

GET request : /api/foo/?bar=1

is equivalent to

POST request : /api/foo/

sending :

    {
      #request: {bar: 1}
    }

If API call has both GET and POST/JSON, API will merge request but POST will take over GET.

Subrequest
----------

POST API calls can have “sub” requests :

    {
      #request: {bar: 1},
      mySubRequestTag: {
        #name: 'mySubRequest',
        ...
      }
    }

You can ommit the #name :

    {
      #request: {bar: 1},
      mySubRequest: {...}
    }

Equivalent to :

    {
      #request: {bar: 1},
      mySubRequest: {
        #name: 'mySubRequest',
        ...
      }
    }

response to sub requests are sent like :

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

Example :

    {
      #request: {bar: 1},
      #requires : "myOtherSubRequest",
      myFirstSubRequest: {...}    
      myOtherSubRequest: {"#requires": "myFirstSubRequest", ...},
    }

Exceptions
----------

API can raise an exception if something goes wrong. The exception replaces the normal response.

Example :

    {
      "#exception": {
        type: "Exception",
        code: 0,
        message: "Something is wrong"
      }
    }


Folders
=======
 + foo : dummy webservice for example
 + sdk : PHP basic SDK

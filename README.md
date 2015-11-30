# magicdoc
Magicdoc automatically generates @method documentation for known magic method
options from a JSON document. The top level elements of the JSON file will
be scanned, and used to create @method lines in the destination class file.

## Impetus
When connecting to an external api, it returned a JSON document with many
properties. As the api had many endpoints, and many possible returnable properties,
it would not be a good use of time to concretely implement every single one.

Instead, an approach using the `__call` magic method was used.  The JSON document
was read into an internal array, and the `__call` method was implemented to
look at the name of the called method and any parameters provided to find
the desired property.

This left the issue of having many possible available properties, and wanting
IDE hinting on them, solved by magicdoc.

## Configuration
Magicdoc expects a magicdoc.json file. This file should be an array of objects,
each object representing a json document and a class that it will be mapped to.

```
[
  {
    "source":"example.json",
    "destination":"src/LinusShops/ExampleClass.php",
    "types" : {
      "items" : "Item[]"
    },
    "parameters" : {
      "items" : "$parameter1, $parameter2"
    }
  }
]
```

`source`: required, specifies the location of the json file to map

`destination`: required, the location of the destination class

`types`: optional, allows specifying types for certain elements. Magicdoc will infer basic types (string, bool, integer), but this allows to specify objects.

`parameters`: optional, allows specifying a list of arguments. Magicdoc will default to a standard variadic parameter (...$parameters) 

Finally, the destination class must have the `{{magicdoc_start}}` and `{{magicdoc_end}}`
tags added in the location you want the @method listing to appear. Generally, this
is in the class docblock.

## Url configuration
Magicdoc can also read directly from your api.

```
[
  {
    "source":{
      "type": "url",
      "url": "http://example.com/endpoint",
      "headers": {
        "Authorization": "Some token",
        "Content-Type":"application/json"
      },
      "body":"{\"option\":\"hello\"}",
      "method":"POST"
    },
    "destination":"src/LinusShops/Example.php",
    "options":{
      "bust_wrapper_array":true
    }
  }
]
```
`source`: specifies the options to pass to make the api request. The following
options are not required- headers, body, method (defaults to GET).

`options`: specifies actions to take with the received json, mostly preprocessing
`options=>bust_wrapper_array`: if your API wraps returned objects in an array, you
can set this option to pop the first object out of the array to be used for
documentation generation.

## Usage
Run `magicdoc` from the directory containing your magicdoc.json.

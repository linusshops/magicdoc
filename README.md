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
the desired property.  By using magic methods instead of object properties,
it allowed descent into the JSON document without needing intermediary
variables or loops.

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

## Magento Model IDE Helper
Magicdoc can also generate an IDE helper for magic getters/setters in Magento models.

```
{
    "source":{
      "type": "magento",
      "model": "linus_example/product",
      "id": 1
    }
}
```

Magicdoc expects the magicdoc.json file to be in the root of your magento installation.

The provided id should be a valid instance of the model, as magicdoc will inspect
the types of the data contained to determine accurate return types. It will
then generate a `__mage_ide_helper.php` file, which should be placed in the 
root of your magento project.

## Usage
Run `magicdoc` from the directory containing your magicdoc.json.

##License
MIT

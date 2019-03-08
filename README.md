# YAML Library for PHP - WORK IN PROGRESS !!!

[![Build Status](https://travis-ci.org/dallgoot/yaml.svg?branch=master)](https://travis-ci.org/dallgoot/yaml) [![Maintainability](https://api.codeclimate.com/v1/badges/dfae4b8e665a1d728e3d/maintainability)](https://codeclimate.com/github/dallgoot/yaml/maintainability) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/dallgoot/yaml/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/dallgoot/yaml/?branch=master) [![Code Coverage](https://scrutinizer-ci.com/g/dallgoot/yaml/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/dallgoot/yaml/?branch=master)

PHP library to load and parse YAML file to coherent PHP datatypes equivalent

## Features:

- define *appropriate* PHP datatypes for values :
    - YamlObject for each Yaml Document
    - object for mappings
    - array for sequences
    - Compact syntax
    - tagged values as Tag Object
    - JSON, DateTime, etc.
- recover from some parsing errors
- tolerance to tabulations
- DEFINE debug levels :
  - 1 : print each line Node Type class
  - 2 : print Loader global map structure
  - 3 : print each document NodeList

## Support:

- YAML specifications [version 1.2](http://yaml.org/spec/1.2/spec.html)
- multiple documents in a content (file or string)
- comments (option : enabled by default)
- compact syntax for mapping and sequences
- multi-line values (simple|double quoted or not, compact mapping|sequence or JSON)
- references (option : enabled by default)
- tags with behaviour customization (overriding for common, or specifying for custom) via Closures settings.

## What's different from other PHP Yaml libraries ?

- coherent data types (see [coherence.md](./documentation/coherence.md) for explanations)
- support multiple documents in one YAML content (string or file)
- JSON format validation (if valid as per PHP function *json_encode*)
- complex mapping (Note: keys are JSON encoded strings)
- real reference behaviour : changing reference value modify other reference calls

## Todo

- DUMPER:
  - implement/verify Dumper::Options
  - finish implementation
  - quote strings that are not valid values in YAML syntax
  - set up tests
- Code coverage : Units tests for each classes methods
- implement specific unit test for each YAML spec. invalid cases (what must not happen)
- define levels for Exceptions
- verify YAML DEFINITIONS files before launching tests
- double check references/anchors changes in YamlObject
- Documentation : (doxygen ?)
  - build classes docs
  - Examples of each function of the API
- docker-compose for easy testing
- Benchmarks against other libs
- verify LICENSE nature and set up in docblocks accordingly
- set up @link to github in docblocks
- verify TODOS in code

## Improvements

- better/more precise errors identification (Yaml validation) with explanation in YAML content
- Unicode checking (???)
- OPTION : parse dates as DateTime
- OPTION: Force renaming key names that are not valid PHP property name
- directives : currently ignored, should handle tag determination : local tag associated with global URI tag directive
- TAG : function for 'php/object' that provides the correct namespace to build

## Performances

    TBD

## Thanks

https://www.json2yaml.com/convert-yaml-to-json

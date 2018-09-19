# Environment Package

[![Build Status](https://travis-ci.org/rancoud/Environment.svg?branch=master)](https://travis-ci.org/rancoud/Environment) [![Coverage Status](https://coveralls.io/repos/github/rancoud/Environment/badge.svg?branch=master)](https://coveralls.io/github/rancoud/Environment?branch=master)

Read Environment file (.env).  
Not using `getenv()` and `putenv`  

## Installation
```php
composer require rancoud/environment
```

## .env File example
```
# type of variables used
STRING=STRING
STRING_QUOTES=" STRING QUOTES "
INTEGER=9
FLOAT=9.0

BOOL_TRUE=TRUE
BOOL_FALSE=FALSE
NULL_VALUE=NULL

# variable in value
HOME=/user/www
CORE=$HOME/core
; $HOME won't be interpreted
USE_DOLLAR_IN_STRING="$HOME"

#comment line 1
;comment line 2

# import another env file use @ and the filename
@database.env

# multilines
RGPD="
i understand

    enough of email for \"me\"    

thanks
"
```


## How to use it?
```php
$env = new Environment(__DIR__);
$env->get('a', 'defaultvalue');

// check if a variable exists
$env->exists('a');

// check if value is set with allowed values
$env->allowedValues('a', ['a']);

// force using cache (if not exist it will be created)
$env->enableCache();
```

## Environment Constructor
### Settings
#### Mandatory
| Parameter | Type | Description |
| --- | --- | --- |
| folder | string OR array | folder to seek .env file |

#### Optionnals
| Parameter | Type | Default value | Description |
| --- | --- | --- | --- |
| filename | string | .env | custom name of .env file |

## Environment Methods
### General Commands  
* load():void  
* get(name: string, [default: mixed = null]): mixed|null  
* getAll(): arrray  
* exists(name: string): bool  
* allowedValues(name: string, values: array): bool

### Cache File  
* enableCache(): void  
* disableCache(): void  
* flushCache(): void  

### Multilines endline interpretation
* setEndline(endline: string): void  
* getEndline(): string  

## How to Dev
`./run_all_commands.sh` for php-cs-fixer and phpunit and coverage  
`./run_php_unit_coverage.sh` for phpunit and coverage  
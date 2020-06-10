# Environment Package

![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/rancoud/environment)
[![Packagist Version](https://img.shields.io/packagist/v/rancoud/environment)](https://packagist.org/packages/rancoud/environment)
[![Packagist Downloads](https://img.shields.io/packagist/dt/rancoud/environment)](https://packagist.org/packages/rancoud/environment)
[![Composer dependencies](https://img.shields.io/badge/dependencies-0-brightgreen)](https://github.com/rancoud/Environment/blob/master/composer.json)
[![Build Status](https://travis-ci.org/rancoud/Environment.svg?branch=master)](https://travis-ci.org/rancoud/Environment)
[![Coverage Status](https://coveralls.io/repos/github/rancoud/Environment/badge.svg?branch=master)](https://coveralls.io/github/rancoud/Environment?branch=master)
[![composer.lock](https://poser.pugx.org/rancoud/environment/composerlock)](https://packagist.org/packages/rancoud/environment)

Read Environment file (.env).  
Can complete or override data from `getenv()` / `$_ENV` / `$_SERVER`  

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
Warning, call constructor will not load values, you can:  
* use `load()` function
* use [any functions](when-load-is-called)  that automatically call `load()` inside  

### Simple example
```php
// search .env file
$env = new Environment(__DIR__);
$values = $env->getAll();
$value = $env->get('a', 'defaultvalue');
```

### Check keys and values
```php
// check if a key exists
$env = new Environment(__DIR__);
$isExists = $env->exists('key1');
$isExists = $env->exists(['key1', 'key2']);

// check if value is set with allowed values
$isAllowed = $env->allowedValues('key1', ['value1', NULL, 'value2']);
```

### Complete and Override values
Complete is for filling values belong to keys having empty string or no values.  
Override is for erasing values belong to keys.  
The treatment given by the flags is always in the same order:
1. `getenv()`
2. `$_ENV`
3. `$_SERVER`

```php
$env = new Environment(__DIR__);

// complete with only getenv()
$env->complete(Environment::GETENV);

// complete with $_ENV then $_SERVER
$env->complete(Environment::SERVER | Environment::ENV);

// override with getenv() will erase values
$env->override(Environment::GETENV);
```

### Enable cache
The file cached will not contains informations from `getenv()` / `$_ENV` / `$_SERVER`  
```php
// force using cache (if not exist it will be created)
$env = new Environment(__DIR__);
$env->enableCache();
$values = $env->getAll();
```

### When load() is called?
For simplicity `load()` is automatically called when using thoses functions:  
* get
* getAll
* exists
* complete

### Multiline
You can check what kind of endline it using, by default it's `PHP_EOL`  
You can change it with for using `<br>`  
```php
// force using cache (if not exist it will be created)
$env = new Environment(__DIR__);
$env->setEndline('<br />');
```

### Include another .env
Inside .env file you can include another .env file with the `@` operator at the begining of the line

### Constructor variations
```php
// search .env file
$env = new Environment(__DIR__);

// search dev.env file
$env = new Environment(__DIR__, 'dev.env');

// search .env file in folders __DIR__ then '/usr'
$env = new Environment([__DIR__, '/usr']);

// search dev.env file in folders __DIR__ then '/usr'
$env = new Environment([__DIR__, '/usr'], 'dev.env');
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
| filename | string | .env | custom name of .env file (don't forget to add file extension) |

## Environment Methods
### General Commands  
* load():void  
* get(name: string, [default: mixed = null]): mixed|null  
* getAll(): arrray  
* exists(name: string|array): bool  
* allowedValues(name: string, values: array): bool  

### Cache File  
* enableCache(): void  
* disableCache(): void  
* flushCache(): void  

### Env variables
* complete(flags: Environment::GETENV | Environment::ENV | Environment::SERVER): void
* override(flags: Environment::GETENV | Environment::ENV | Environment::SERVER): void

### Multilines endline interpretation
* setEndline(endline: string): void  
* getEndline(): string  

## How to Dev
`./run_all_commands.sh` for php-cs-fixer and phpunit and coverage  
`./run_php_unit_coverage.sh` for phpunit and coverage  
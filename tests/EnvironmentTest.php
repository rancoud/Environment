<?php

declare(strict_types=1);

namespace Rancoud\Environment\Test;

use PHPUnit\Framework\TestCase;
use Rancoud\Environment\Environment;
use Rancoud\Environment\EnvironmentException;
use ReflectionClass;

/**
 * Class EnvironmentTest.
 */
class EnvironmentTest extends TestCase
{
    protected $fileEnvContent = [
        'STRING' => 'STRING',
        'STRING_QUOTES' => ' STRING QUOTES ',
        'INTEGER' => 9,
        'FLOAT' => 9.0,
        'BOOL_TRUE' => TRUE,
        'BOOL_FALSE' => FALSE,
        'NULL_VALUE' => NULL
    ];

    protected $fileVariableEnvContent = [
        'HOME' => '/user/www',
        'CORE' => '/user/www/core',
        'USE_DOLLAR_IN_STRING' => '$HOME'
    ];
    
    protected $fileMultilinesEnvContent = [
        'RGPD' => "
i understand

    enough of email fo \"me\"    

thanks
",
        'one' => "one \" two",
        'tow' => "t\"w\"o",
        'test' => "testA  
Btest ok   a
ok"
    ];

    protected function getProtectedValue(Environment $env, string $name)
    {
        $reflexion = new ReflectionClass(Environment::class);
        $prop = $reflexion->getProperty($name);
        $prop->setAccessible(true);
        return $prop->getValue($env);
    }
    
    public function testConstruct()
    {
        $folders = ['a', 'b'];
        $filename = 'a.env';

        $env = new Environment($folders, $filename);

        static::assertEquals($folders, $this->getProtectedValue($env, 'folders'));
        static::assertEquals($filename, $this->getProtectedValue($env, 'filename'));

        $folders = 'a';

        $env = new Environment($folders);

        static::assertEquals(['a'], $this->getProtectedValue($env, 'folders'));
        static::assertEquals('.env', $this->getProtectedValue($env, 'filename'));
    }

    public function testLoad()
    {
        $folders = [__DIR__];

        $env = new Environment($folders);
        $env->load();

        static::assertEquals($this->fileEnvContent, $env->getAll());
        static::assertSame($this->fileEnvContent['STRING'], $env->get('STRING'));
        static::assertSame($this->fileEnvContent['STRING_QUOTES'], $env->get('STRING_QUOTES'));
        static::assertSame($this->fileEnvContent['INTEGER'], $env->get('INTEGER'));
        static::assertSame($this->fileEnvContent['FLOAT'], $env->get('FLOAT'));
        static::assertSame($this->fileEnvContent['BOOL_TRUE'], $env->get('BOOL_TRUE'));
        static::assertSame($this->fileEnvContent['BOOL_FALSE'], $env->get('BOOL_FALSE'));
    }

    public function testLoadException()
    {
        static::expectException(EnvironmentException::class);
        static::expectExceptionMessage('Env file not found');
        $folders = [__DIR__];
        $filename = 'file_not_exist.env';

        $env = new Environment($folders, $filename);
        $env->load();
    }
    
    public function testGetAllAutoload()
    {
        $folders = [__DIR__];

        $env = new Environment($folders);

        static::assertEquals($this->fileEnvContent, $env->getAll());
    }

    public function testGetAutoload()
    {
        $folders = [__DIR__];

        $env = new Environment($folders);

        static::assertEquals($this->fileEnvContent['STRING'], $env->get('STRING'));
    }

    public function testGetDefault()
    {
        $folders = [__DIR__];

        $env = new Environment($folders);

        static::assertEquals('dev', $env->get('env', 'dev'));
    }

    public function testExists()
    {
        $folders = [__DIR__];

        $env = new Environment($folders);

        static::assertTrue($env->exists('STRING'));
        static::assertFalse($env->exists('ERROR'));
    }

    public function testAllowedValues()
    {
        $folders = [__DIR__];

        $env = new Environment($folders);

        static::assertTrue($env->allowedValues('STRING', ['a', 'b', $this->fileEnvContent['STRING']]));
        static::assertFalse($env->allowedValues('STRING', ['a', 'b', mb_strtolower($this->fileEnvContent['STRING'])]));
        static::assertFalse($env->allowedValues('STRING', ['a', 'b']));
    }

    public function testAllowedValuesException()
    {
        static::expectException(EnvironmentException::class);
        static::expectExceptionMessage('Variable env doesn\'t exist');

        $folders = [__DIR__];

        $env = new Environment($folders);

        static::assertTrue($env->allowedValues('env', []));
    }

    public function testVariablesInEnvFile()
    {
        $folders = [__DIR__];
        $fileVariable = 'variables.env';

        $env = new Environment($folders, $fileVariable);
        $env->load();

        static::assertEquals($this->fileVariableEnvContent, $env->getAll());
        static::assertSame($this->fileVariableEnvContent['HOME'], $env->get('HOME'));
        static::assertSame($this->fileVariableEnvContent['CORE'], $env->get('CORE'));
        static::assertSame($this->fileVariableEnvContent['USE_DOLLAR_IN_STRING'], $env->get('USE_DOLLAR_IN_STRING'));
    }

    public function testMissingVariablesInEnvFileException()
    {
        static::expectException(EnvironmentException::class);
        static::expectExceptionMessage('Missing variable in value $TEST');

        $folders = [__DIR__];
        $fileVariable = 'missing_variables.env';

        $env = new Environment($folders, $fileVariable);
        $env->load();
    }

    public function testIncludeEnvFile()
    {
        $folders = [__DIR__];
        $fileVariable = 'root.env';

        $env = new Environment($folders, $fileVariable);
        $env->load();

        static::assertEquals($this->fileEnvContent, $env->getAll());
        static::assertSame($this->fileEnvContent['STRING'], $env->get('STRING'));
        static::assertSame($this->fileEnvContent['STRING_QUOTES'], $env->get('STRING_QUOTES'));
        static::assertSame($this->fileEnvContent['INTEGER'], $env->get('INTEGER'));
        static::assertSame($this->fileEnvContent['FLOAT'], $env->get('FLOAT'));
        static::assertSame($this->fileEnvContent['BOOL_TRUE'], $env->get('BOOL_TRUE'));
        static::assertSame($this->fileEnvContent['BOOL_FALSE'], $env->get('BOOL_FALSE'));
    }
    
    public function testMissingIncludeEnvFileException()
    {
        static::expectException(EnvironmentException::class);
        static::expectExceptionMessage('Missing env file missing.env');

        $folders = [__DIR__];
        $fileVariable = 'missing_include.env';

        $env = new Environment($folders, $fileVariable);
        $env->load();
    }

    public function testInfiniteIncludeEnvFileException()
    {
        static::expectException(EnvironmentException::class);
        static::expectExceptionMessage('Max recursion env file!');

        $folders = [__DIR__];
        $fileVariable = 'recursion_1.env';

        $env = new Environment($folders, $fileVariable);
        $env->load();
    }

    private function erasePreviousFile(string $filepath)
    {
        if(file_exists($filepath)){
            unlink($filepath);
        }
    } 
    
    public function testUseCacheFile()
    {
        $filepath = __DIR__ . DIRECTORY_SEPARATOR . 'root.env.cache.php';
        $this->erasePreviousFile($filepath);

        $folders = [__DIR__];
        $fileVariable = 'root.env';

        $env = new Environment($folders, $fileVariable);
        $env->enableCache();
        
        $env->load();

        static::assertEquals($this->fileEnvContent, $env->getAll());
        static::assertSame($this->fileEnvContent['STRING'], $env->get('STRING'));
        static::assertSame($this->fileEnvContent['STRING_QUOTES'], $env->get('STRING_QUOTES'));
        static::assertSame($this->fileEnvContent['INTEGER'], $env->get('INTEGER'));
        static::assertSame($this->fileEnvContent['FLOAT'], $env->get('FLOAT'));
        static::assertSame($this->fileEnvContent['BOOL_TRUE'], $env->get('BOOL_TRUE'));
        static::assertSame($this->fileEnvContent['BOOL_FALSE'], $env->get('BOOL_FALSE'));
        
        static::assertFileExists($filepath);
        $data = include($filepath);
        static::assertEquals($this->fileEnvContent, $data);
    }

    public function testUseCacheFileAlreadyCreate()
    {
        $filepath = __DIR__ . DIRECTORY_SEPARATOR . 'root.env.cache.php';
        $this->erasePreviousFile($filepath);

        $folders = [__DIR__];
        $fileVariable = 'root.env';

        $env = new Environment($folders, $fileVariable);
        $env->enableCache();

        $env->load();

        static::assertEquals($this->fileEnvContent, $env->getAll());
        static::assertSame($this->fileEnvContent['STRING'], $env->get('STRING'));
        static::assertSame($this->fileEnvContent['STRING_QUOTES'], $env->get('STRING_QUOTES'));
        static::assertSame($this->fileEnvContent['INTEGER'], $env->get('INTEGER'));
        static::assertSame($this->fileEnvContent['FLOAT'], $env->get('FLOAT'));
        static::assertSame($this->fileEnvContent['BOOL_TRUE'], $env->get('BOOL_TRUE'));
        static::assertSame($this->fileEnvContent['BOOL_FALSE'], $env->get('BOOL_FALSE'));

        static::assertFileExists($filepath);
        $data = include($filepath);
        static::assertEquals($this->fileEnvContent, $data);

        $data['STRING'] = 'TESTALPHA';
        $content = '<?php return ';
        $content .= var_export($data, true);
        $content .= ';';
        file_put_contents($filepath, $content);
        
        $env = new Environment($folders, $fileVariable);
        $env->enableCache();

        $env->load();

        static::assertEquals($data, $env->getAll());
        static::assertSame('TESTALPHA', $env->get('STRING'));
        static::assertSame($this->fileEnvContent['STRING_QUOTES'], $env->get('STRING_QUOTES'));
        static::assertSame($this->fileEnvContent['INTEGER'], $env->get('INTEGER'));
        static::assertSame($this->fileEnvContent['FLOAT'], $env->get('FLOAT'));
        static::assertSame($this->fileEnvContent['BOOL_TRUE'], $env->get('BOOL_TRUE'));
        static::assertSame($this->fileEnvContent['BOOL_FALSE'], $env->get('BOOL_FALSE'));

        $env = new Environment($folders, $fileVariable);
        $env->disableCache();

        $env->load();

        static::assertEquals($this->fileEnvContent, $env->getAll());
        static::assertSame($this->fileEnvContent['STRING'], $env->get('STRING'));
        static::assertSame($this->fileEnvContent['STRING_QUOTES'], $env->get('STRING_QUOTES'));
        static::assertSame($this->fileEnvContent['INTEGER'], $env->get('INTEGER'));
        static::assertSame($this->fileEnvContent['FLOAT'], $env->get('FLOAT'));
        static::assertSame($this->fileEnvContent['BOOL_TRUE'], $env->get('BOOL_TRUE'));
        static::assertSame($this->fileEnvContent['BOOL_FALSE'], $env->get('BOOL_FALSE'));

        $env = new Environment($folders, $fileVariable);
        $env->enableCache();
        $env->flushCache();

        $env->load();

        static::assertEquals($this->fileEnvContent, $env->getAll());
        static::assertSame($this->fileEnvContent['STRING'], $env->get('STRING'));
        static::assertSame($this->fileEnvContent['STRING_QUOTES'], $env->get('STRING_QUOTES'));
        static::assertSame($this->fileEnvContent['INTEGER'], $env->get('INTEGER'));
        static::assertSame($this->fileEnvContent['FLOAT'], $env->get('FLOAT'));
        static::assertSame($this->fileEnvContent['BOOL_TRUE'], $env->get('BOOL_TRUE'));
        static::assertSame($this->fileEnvContent['BOOL_FALSE'], $env->get('BOOL_FALSE'));
    }
    
    public function testMultilines()
    {
        $filepath = __DIR__ . DIRECTORY_SEPARATOR . 'multilines.env.cache.php';
        $folders = [__DIR__];
        $fileVariable = 'multilines.env';

        $env = new Environment($folders, $fileVariable);
        $env->enableCache();
        $env->flushCache();
        $env->setEndline(PHP_EOL);

        $env->load();


        static::assertEquals($this->fileMultilinesEnvContent, $env->getAll());
        static::assertSame($this->fileMultilinesEnvContent['RGPD'], $env->get('RGPD'));
        static::assertSame($this->fileMultilinesEnvContent['one'], $env->get('one'));
        static::assertSame($this->fileMultilinesEnvContent['tow'], $env->get('tow'));
        static::assertSame($this->fileMultilinesEnvContent['test'], $env->get('test'));

        static::assertFileExists($filepath);
        $data = include($filepath);
        static::assertEquals($this->fileMultilinesEnvContent, $data);
    }

    public function testGetSetEndline()
    {
        $endline = 'toto';
        $folders = [__DIR__];
        $fileVariable = 'multilines.env';

        $env = new Environment($folders, $fileVariable);
        $env->setEndline($endline);
        
        static::assertEquals($endline, $env->getEndline());
    }

    public function testNoMultilinesEndException()
    {
        static::expectException(EnvironmentException::class);
        static::expectExceptionMessage('Key a is missing " for multilines');

        $folders = [__DIR__];
        $fileVariable = 'multilines_not_ending.env';

        $env = new Environment($folders, $fileVariable);
        $env->load();
        var_dump($env->getAll());
    }
}
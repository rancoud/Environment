<?php

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use Rancoud\Environment\Environment;
use Rancoud\Environment\EnvironmentException;
use ReflectionClass;

/**
 * Class EnvironmentTest.
 */
class EnvironmentTest extends TestCase
{
    protected array $fileEnvContent = [
        'STRING'        => 'STRING',
        'STRING_QUOTES' => ' STRING QUOTES ',
        'INTEGER'       => 9,
        'FLOAT'         => 9.0,
        'BOOL_TRUE'     => true,
        'BOOL_FALSE'    => false,
        'NULL_VALUE'    => null,
        'EMPTY_VALUE'   => ''
    ];

    protected array $fileVariableEnvContent = [
        'HOME'                 => '/user/www',
        'CORE'                 => '/user/www/core',
        'USE_DOLLAR_IN_STRING' => '$HOME'
    ];

    protected array $fileMultilinesEnvContent = [
        'ONE'  => 'one " two',
        'TWO'  => 't"w"o',
        'RGPD' => \PHP_EOL . 'i understand' . \PHP_EOL .
            \PHP_EOL .
    '    enough of email fo "me"    ' . \PHP_EOL .
            \PHP_EOL .
    'thanks' . \PHP_EOL,
        'TEST' => 'testA' . \PHP_EOL .
    'Btest ok   a' . \PHP_EOL .
    'ok'
    ];

    protected array $fileCompleteContent = [
        'GITHUB'       => 'github-action',
        'TRAVIS'       => '',
        'FROM_ENV'     => '',
        'FROM_SERVER'  => '',
        'FROM_NOWHERE' => 'oui'
    ];

    protected array $fileOverrideContent = [
        'TRAVIS'       => 'value01',
        'FROM_ENV'     => 'value02',
        'FROM_SERVER'  => 'value03',
        'FROM_NOWHERE' => 'value04'
    ];

    /**
     * @param Environment $env
     * @param string      $name
     *
     * @throws \ReflectionException
     *
     * @return mixed
     */
    protected function getProtectedValue(Environment $env, string $name)
    {
        $reflexion = new ReflectionClass(Environment::class);
        $prop = $reflexion->getProperty($name);
        $prop->setAccessible(true);

        return $prop->getValue($env);
    }

    public function testBitmasking(): void
    {
        $flags = Environment::GETENV | Environment::ENV | Environment::SERVER
               | Environment::GETENV_ALL | Environment::ENV_ALL | Environment::SERVER_ALL;

        static::assertSame(1, $flags & Environment::GETENV);
        static::assertSame(2, $flags & Environment::ENV);
        static::assertSame(4, $flags & Environment::SERVER);
        static::assertSame(8, $flags & Environment::GETENV_ALL);
        static::assertSame(16, $flags & Environment::ENV_ALL);
        static::assertSame(32, $flags & Environment::SERVER_ALL);
    }

    /**
     * @throws \ReflectionException
     */
    public function testConstruct(): void
    {
        $folders = ['a', 'b'];
        $filename = 'a.env';

        $env = new Environment($folders, $filename);

        static::assertSame($folders, $this->getProtectedValue($env, 'folders'));
        static::assertSame($filename, $this->getProtectedValue($env, 'filename'));

        $folders = 'a';

        $env = new Environment($folders);

        static::assertSame(['a'], $this->getProtectedValue($env, 'folders'));
        static::assertSame('.env', $this->getProtectedValue($env, 'filename'));
    }

    /**
     * @throws EnvironmentException
     */
    public function testLoad(): void
    {
        $folders = [__DIR__];

        $env = new Environment($folders);

        $env->load();

        static::assertSame($this->fileEnvContent, $env->getAll());
        static::assertSame($this->fileEnvContent['STRING'], $env->get('STRING'));
        static::assertSame($this->fileEnvContent['STRING_QUOTES'], $env->get('STRING_QUOTES'));
        static::assertSame($this->fileEnvContent['INTEGER'], $env->get('INTEGER'));
        static::assertSame($this->fileEnvContent['FLOAT'], $env->get('FLOAT'));
        static::assertSame($this->fileEnvContent['BOOL_TRUE'], $env->get('BOOL_TRUE'));
        static::assertSame($this->fileEnvContent['BOOL_FALSE'], $env->get('BOOL_FALSE'));
        static::assertSame($this->fileEnvContent['EMPTY_VALUE'], $env->get('EMPTY_VALUE'));
    }

    public function testLoadException(): void
    {
        $this->expectException(EnvironmentException::class);
        $this->expectExceptionMessage('Env file not found');

        $folders = [__DIR__];
        $filename = 'file_not_exist.env';

        $env = new Environment($folders, $filename);
        $env->load();
    }

    /**
     * @throws EnvironmentException
     */
    public function testGetAllAutoload(): void
    {
        $folders = [__DIR__];

        $env = new Environment($folders);

        static::assertSame($this->fileEnvContent, $env->getAll());
    }

    /**
     * @throws EnvironmentException
     */
    public function testGetAutoload(): void
    {
        $folders = [__DIR__];

        $env = new Environment($folders);

        static::assertSame($this->fileEnvContent['STRING'], $env->get('STRING'));
    }

    /**
     * @throws EnvironmentException
     */
    public function testGetDefault(): void
    {
        $folders = [__DIR__];

        $env = new Environment($folders);

        static::assertSame('dev', $env->get('env', 'dev'));
    }

    /**
     * @throws EnvironmentException
     */
    public function testExists(): void
    {
        $folders = [__DIR__];

        $env = new Environment($folders);

        static::assertTrue($env->exists('STRING'));
        static::assertTrue($env->exists(['STRING', 'STRING_QUOTES']));

        static::assertFalse($env->exists('ERROR'));
        static::assertFalse($env->exists(['STRING', 'ERROR']));
        static::assertFalse($env->exists(['ERROR', 'STRING']));
    }

    /**
     * @throws EnvironmentException
     */
    public function testAllowedValues(): void
    {
        $folders = [__DIR__];

        $env = new Environment($folders);

        static::assertTrue($env->allowedValues('STRING', ['a', 'b', $this->fileEnvContent['STRING']]));
        static::assertFalse($env->allowedValues('STRING', ['a', 'b', \mb_strtolower($this->fileEnvContent['STRING'])])); //phpcs:ignore
        static::assertFalse($env->allowedValues('STRING', ['a', 'b']));
    }

    public function testAllowedValuesException(): void
    {
        $this->expectException(EnvironmentException::class);
        $this->expectExceptionMessage('Variable env doesn\'t exist');

        $folders = [__DIR__];

        $env = new Environment($folders);

        static::assertTrue($env->allowedValues('env', []));
    }

    /**
     * @throws EnvironmentException
     */
    public function testVariablesInEnvFile(): void
    {
        $folders = [__DIR__];
        $fileVariable = 'variables.env';

        $env = new Environment($folders, $fileVariable);

        $env->load();

        static::assertSame($this->fileVariableEnvContent, $env->getAll());
        static::assertSame($this->fileVariableEnvContent['HOME'], $env->get('HOME'));
        static::assertSame($this->fileVariableEnvContent['CORE'], $env->get('CORE'));
        static::assertSame($this->fileVariableEnvContent['USE_DOLLAR_IN_STRING'], $env->get('USE_DOLLAR_IN_STRING'));
    }

    public function testMissingVariablesInEnvFileException(): void
    {
        $this->expectException(EnvironmentException::class);
        $this->expectExceptionMessage('Missing variable in value $TEST');

        $folders = [__DIR__];
        $fileVariable = 'missing_variables.env';

        $env = new Environment($folders, $fileVariable);
        $env->load();
    }

    /**
     * @throws EnvironmentException
     */
    public function testIncludeEnvFile(): void
    {
        $folders = [__DIR__];
        $fileVariable = 'root.env';

        $env = new Environment($folders, $fileVariable);

        $env->load();

        static::assertSame($this->fileEnvContent, $env->getAll());
        static::assertSame($this->fileEnvContent['STRING'], $env->get('STRING'));
        static::assertSame($this->fileEnvContent['STRING_QUOTES'], $env->get('STRING_QUOTES'));
        static::assertSame($this->fileEnvContent['INTEGER'], $env->get('INTEGER'));
        static::assertSame($this->fileEnvContent['FLOAT'], $env->get('FLOAT'));
        static::assertSame($this->fileEnvContent['BOOL_TRUE'], $env->get('BOOL_TRUE'));
        static::assertSame($this->fileEnvContent['BOOL_FALSE'], $env->get('BOOL_FALSE'));
    }

    public function testMissingIncludeEnvFileException(): void
    {
        $this->expectException(EnvironmentException::class);
        $this->expectExceptionMessage('Missing env file missing.env');

        $folders = [__DIR__];
        $fileVariable = 'missing_include.env';

        $env = new Environment($folders, $fileVariable);
        $env->load();
    }

    public function testInfiniteIncludeEnvFileException(): void
    {
        $this->expectException(EnvironmentException::class);
        $this->expectExceptionMessage('Max recursion env file!');

        $folders = [__DIR__];
        $fileVariable = 'recursion_1.env';

        $env = new Environment($folders, $fileVariable);
        $env->load();
    }

    /**
     * @param string $filepath
     */
    private function erasePreviousFile(string $filepath): void
    {
        if (\file_exists($filepath)) {
            \unlink($filepath);
        }
    }

    /**
     * @throws EnvironmentException
     */
    public function testUseCacheFile(): void
    {
        $filepath = __DIR__ . \DIRECTORY_SEPARATOR . 'root.env.cache.php';
        $this->erasePreviousFile($filepath);

        $folders = [__DIR__];
        $fileVariable = 'root.env';

        $env = new Environment($folders, $fileVariable);
        $env->enableCache();

        $env->load();

        static::assertSame($this->fileEnvContent, $env->getAll());
        static::assertSame($this->fileEnvContent['STRING'], $env->get('STRING'));
        static::assertSame($this->fileEnvContent['STRING_QUOTES'], $env->get('STRING_QUOTES'));
        static::assertSame($this->fileEnvContent['INTEGER'], $env->get('INTEGER'));
        static::assertSame($this->fileEnvContent['FLOAT'], $env->get('FLOAT'));
        static::assertSame($this->fileEnvContent['BOOL_TRUE'], $env->get('BOOL_TRUE'));
        static::assertSame($this->fileEnvContent['BOOL_FALSE'], $env->get('BOOL_FALSE'));
        static::assertSame($this->fileEnvContent['EMPTY_VALUE'], $env->get('EMPTY_VALUE'));

        static::assertFileExists($filepath);
        /** @noinspection PhpIncludeInspection */
        $data = include $filepath;
        static::assertSame($this->fileEnvContent, $data);
    }

    /**
     * @throws EnvironmentException
     */
    public function testUseCacheFileAlreadyCreate(): void
    {
        $filepath = __DIR__ . \DIRECTORY_SEPARATOR . 'root.env.cache.php';
        $this->erasePreviousFile($filepath);

        $folders = [__DIR__];
        $fileVariable = 'root.env';

        $env = new Environment($folders, $fileVariable);
        $env->enableCache();

        $env->load();

        static::assertSame($this->fileEnvContent, $env->getAll());
        static::assertSame($this->fileEnvContent['STRING'], $env->get('STRING'));
        static::assertSame($this->fileEnvContent['STRING_QUOTES'], $env->get('STRING_QUOTES'));
        static::assertSame($this->fileEnvContent['INTEGER'], $env->get('INTEGER'));
        static::assertSame($this->fileEnvContent['FLOAT'], $env->get('FLOAT'));
        static::assertSame($this->fileEnvContent['BOOL_TRUE'], $env->get('BOOL_TRUE'));
        static::assertSame($this->fileEnvContent['BOOL_FALSE'], $env->get('BOOL_FALSE'));
        static::assertSame($this->fileEnvContent['EMPTY_VALUE'], $env->get('EMPTY_VALUE'));

        static::assertFileExists($filepath);
        /** @noinspection PhpIncludeInspection */
        $data = include $filepath;
        static::assertSame($this->fileEnvContent, $data);

        $data['STRING'] = 'TESTALPHA';
        $content = '<?php return ';
        $content .= \var_export($data, true);
        $content .= ';';
        \file_put_contents($filepath, $content);

        $env = new Environment($folders, $fileVariable);
        $env->enableCache();

        $env->load();

        static::assertSame($data, $env->getAll());
        static::assertSame('TESTALPHA', $env->get('STRING'));
        static::assertSame($this->fileEnvContent['STRING_QUOTES'], $env->get('STRING_QUOTES'));
        static::assertSame($this->fileEnvContent['INTEGER'], $env->get('INTEGER'));
        static::assertSame($this->fileEnvContent['FLOAT'], $env->get('FLOAT'));
        static::assertSame($this->fileEnvContent['BOOL_TRUE'], $env->get('BOOL_TRUE'));
        static::assertSame($this->fileEnvContent['BOOL_FALSE'], $env->get('BOOL_FALSE'));

        $env = new Environment($folders, $fileVariable);
        $env->disableCache();

        $env->load();

        static::assertSame($this->fileEnvContent, $env->getAll());
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

        static::assertSame($this->fileEnvContent, $env->getAll());
        static::assertSame($this->fileEnvContent['STRING'], $env->get('STRING'));
        static::assertSame($this->fileEnvContent['STRING_QUOTES'], $env->get('STRING_QUOTES'));
        static::assertSame($this->fileEnvContent['INTEGER'], $env->get('INTEGER'));
        static::assertSame($this->fileEnvContent['FLOAT'], $env->get('FLOAT'));
        static::assertSame($this->fileEnvContent['BOOL_TRUE'], $env->get('BOOL_TRUE'));
        static::assertSame($this->fileEnvContent['BOOL_FALSE'], $env->get('BOOL_FALSE'));
    }

    /**
     * @throws EnvironmentException
     */
    public function testMultilines(): void
    {
        $filepath = __DIR__ . \DIRECTORY_SEPARATOR . 'multilines.env.cache.php';
        $folders = [__DIR__];
        $fileVariable = 'multilines.env';

        $env = new Environment($folders, $fileVariable);
        $env->enableCache();
        $env->flushCache();

        $env->load();

        static::assertSame($this->fileMultilinesEnvContent, $env->getAll());
        static::assertSame($this->fileMultilinesEnvContent['RGPD'], $env->get('RGPD'));
        static::assertSame($this->fileMultilinesEnvContent['ONE'], $env->get('ONE'));
        static::assertSame($this->fileMultilinesEnvContent['TWO'], $env->get('TWO'));
        static::assertSame($this->fileMultilinesEnvContent['TEST'], $env->get('TEST'));

        static::assertFileExists($filepath);
        /** @noinspection PhpIncludeInspection */
        $data = include $filepath;
        static::assertSame($this->fileMultilinesEnvContent, $data);
    }

    /**
     * @throws EnvironmentException
     */
    public function testGetSetEndline(): void
    {
        $endline = '<br />';
        $folders = [__DIR__];
        $fileVariable = 'multilines.env';

        $env = new Environment($folders, $fileVariable);
        $env->setEndline($endline);

        static::assertSame($endline, $env->getEndline());

        $env->load();
        static::assertSame('testA<br />Btest ok   a<br />ok', $env->get('TEST'));
    }

    public function testNoMultilinesEndException(): void
    {
        $this->expectException(EnvironmentException::class);
        $this->expectExceptionMessage('Key "A" is missing for multilines');

        $folders = [__DIR__];
        $fileVariable = 'multilines_not_ending.env';

        $env = new Environment($folders, $fileVariable);
        $env->load();
    }

    public function testKeyIncorrectCaseException(): void
    {
        $this->expectException(EnvironmentException::class);
        $this->expectExceptionMessage('Key "aze" must be uppercase');

        $folders = [__DIR__];
        $fileVariable = 'incorrect_case.env';

        $env = new Environment($folders, $fileVariable);
        $env->load();
    }

    public function testKeyNumericForbiddenException(): void
    {
        $this->expectException(EnvironmentException::class);
        $this->expectExceptionMessage('Numeric key "11" is forbidden');

        $folders = [__DIR__];
        $fileVariable = 'incorrect_numeric_key.env';

        $env = new Environment($folders, $fileVariable);
        $env->load();
    }

    /**
     * @throws EnvironmentException
     */
    public function testComplete(): void
    {
        $_ENV['FROM_ENV'] = 'env_value';
        $_SERVER['FROM_SERVER'] = 'server_value';

        $env = new Environment(__DIR__, 'complete.env');

        $env->load();

        static::assertSame($this->fileCompleteContent, $env->getAll());
        static::assertSame($this->fileCompleteContent['GITHUB'], $env->get('GITHUB'));
        static::assertSame($this->fileCompleteContent['TRAVIS'], $env->get('TRAVIS'));
        static::assertSame($this->fileCompleteContent['FROM_ENV'], $env->get('FROM_ENV'));
        static::assertSame($this->fileCompleteContent['FROM_SERVER'], $env->get('FROM_SERVER'));
        static::assertSame($this->fileCompleteContent['FROM_NOWHERE'], $env->get('FROM_NOWHERE'));

        $env->complete(Environment::GETENV | Environment::ENV | Environment::SERVER);

        static::assertNotSame($this->fileCompleteContent, $env->getAll());
        static::assertNotSame($this->fileCompleteContent['TRAVIS'], $env->get('TRAVIS'));
        static::assertNotSame($this->fileCompleteContent['FROM_ENV'], $env->get('FROM_ENV'));
        static::assertNotSame($this->fileCompleteContent['FROM_SERVER'], $env->get('FROM_SERVER'));
        static::assertSame($this->fileCompleteContent['GITHUB'], $env->get('GITHUB'));
        static::assertSame($this->fileCompleteContent['FROM_NOWHERE'], $env->get('FROM_NOWHERE'));

        static::assertSame('alpha', $env->get('TRAVIS'));
        static::assertSame($_ENV['FROM_ENV'], $env->get('FROM_ENV'));
        static::assertSame($_SERVER['FROM_SERVER'], $env->get('FROM_SERVER'));
    }

    /**
     * @throws EnvironmentException
     */
    public function testOverride(): void
    {
        $_ENV['FROM_ENV'] = 'env_value';
        $_SERVER['FROM_SERVER'] = 'server_value';

        $env = new Environment(__DIR__, 'override.env');

        $env->load();

        static::assertSame($this->fileOverrideContent, $env->getAll());
        static::assertSame($this->fileOverrideContent['TRAVIS'], $env->get('TRAVIS'));
        static::assertSame($this->fileOverrideContent['FROM_ENV'], $env->get('FROM_ENV'));
        static::assertSame($this->fileOverrideContent['FROM_SERVER'], $env->get('FROM_SERVER'));
        static::assertSame($this->fileOverrideContent['FROM_NOWHERE'], $env->get('FROM_NOWHERE'));

        $env->override(Environment::GETENV | Environment::ENV | Environment::SERVER);

        static::assertNotSame($this->fileOverrideContent, $env->getAll());
        static::assertNotSame($this->fileOverrideContent['TRAVIS'], $env->get('TRAVIS'));
        static::assertNotSame($this->fileOverrideContent['FROM_ENV'], $env->get('FROM_ENV'));
        static::assertNotSame($this->fileOverrideContent['FROM_SERVER'], $env->get('FROM_SERVER'));
        static::assertSame($this->fileOverrideContent['FROM_NOWHERE'], $env->get('FROM_NOWHERE'));

        static::assertSame('alpha', $env->get('TRAVIS'));
        static::assertSame($_ENV['FROM_ENV'], $env->get('FROM_ENV'));
        static::assertSame($_SERVER['FROM_SERVER'], $env->get('FROM_SERVER'));
    }

    /**
     * @throws EnvironmentException
     */
    public function testCompleteNoEraseValues(): void
    {
        $_ENV['FROM_ENV'] = 'env_value';
        $_SERVER['FROM_SERVER'] = 'server_value';

        $env = new Environment(__DIR__, 'override.env');

        $env->load();

        static::assertSame($this->fileOverrideContent, $env->getAll());
        static::assertSame($this->fileOverrideContent['TRAVIS'], $env->get('TRAVIS'));
        static::assertSame($this->fileOverrideContent['FROM_ENV'], $env->get('FROM_ENV'));
        static::assertSame($this->fileOverrideContent['FROM_SERVER'], $env->get('FROM_SERVER'));
        static::assertSame($this->fileOverrideContent['FROM_NOWHERE'], $env->get('FROM_NOWHERE'));

        $env->complete(Environment::GETENV | Environment::ENV | Environment::SERVER);

        static::assertSame($this->fileOverrideContent, $env->getAll());
        static::assertSame($this->fileOverrideContent['TRAVIS'], $env->get('TRAVIS'));
        static::assertSame($this->fileOverrideContent['FROM_ENV'], $env->get('FROM_ENV'));
        static::assertSame($this->fileOverrideContent['FROM_SERVER'], $env->get('FROM_SERVER'));
        static::assertSame($this->fileOverrideContent['FROM_NOWHERE'], $env->get('FROM_NOWHERE'));
    }

    /**
     * @throws EnvironmentException
     */
    public function testCompleteAll(): void
    {
        $_ENV['FROM_ENV'] = 'env_value';
        $_SERVER['FROM_SERVER'] = 'server_value';

        $env = new Environment(__DIR__, 'complete.env');

        $env->load();

        static::assertSame($this->fileCompleteContent, $env->getAll());
        static::assertSame($this->fileCompleteContent['GITHUB'], $env->get('GITHUB'));
        static::assertSame($this->fileCompleteContent['TRAVIS'], $env->get('TRAVIS'));
        static::assertSame($this->fileCompleteContent['FROM_ENV'], $env->get('FROM_ENV'));
        static::assertSame($this->fileCompleteContent['FROM_SERVER'], $env->get('FROM_SERVER'));
        static::assertSame($this->fileCompleteContent['FROM_NOWHERE'], $env->get('FROM_NOWHERE'));

        $env->complete(Environment::GETENV_ALL | Environment::ENV_ALL | Environment::SERVER_ALL);

        static::assertNotSame($this->fileCompleteContent, $env->getAll());
        static::assertNotSame($this->fileCompleteContent['TRAVIS'], $env->get('TRAVIS'));
        static::assertNotSame($this->fileCompleteContent['FROM_ENV'], $env->get('FROM_ENV'));
        static::assertNotSame($this->fileCompleteContent['FROM_SERVER'], $env->get('FROM_SERVER'));
        static::assertSame($this->fileCompleteContent['GITHUB'], $env->get('GITHUB'));
        static::assertSame($this->fileCompleteContent['FROM_NOWHERE'], $env->get('FROM_NOWHERE'));

        static::assertSame('alpha', $env->get('TRAVIS'));
        static::assertSame($_ENV['FROM_ENV'], $env->get('FROM_ENV'));
        static::assertSame($_SERVER['FROM_SERVER'], $env->get('FROM_SERVER'));
    }

    /**
     * @throws EnvironmentException
     */
    public function testOverrideAll(): void
    {
        $_ENV['FROM_ENV'] = 'env_value';
        $_SERVER['FROM_SERVER'] = 'server_value';

        $env = new Environment(__DIR__, 'override.env');

        $env->load();

        static::assertSame($this->fileOverrideContent, $env->getAll());
        static::assertSame($this->fileOverrideContent['TRAVIS'], $env->get('TRAVIS'));
        static::assertSame($this->fileOverrideContent['FROM_ENV'], $env->get('FROM_ENV'));
        static::assertSame($this->fileOverrideContent['FROM_SERVER'], $env->get('FROM_SERVER'));
        static::assertSame($this->fileOverrideContent['FROM_NOWHERE'], $env->get('FROM_NOWHERE'));

        $env->override(Environment::GETENV_ALL | Environment::ENV_ALL | Environment::SERVER_ALL);

        static::assertNotSame([], $env->getAll());

        static::assertSame('alpha', $env->get('TRAVIS'));
        static::assertSame($_ENV['FROM_ENV'], $env->get('FROM_ENV'));
        static::assertSame($_SERVER['FROM_SERVER'], $env->get('FROM_SERVER'));
    }
}

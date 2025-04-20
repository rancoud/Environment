<?php

declare(strict_types=1);

namespace Rancoud\Environment;

/**
 * Class Environment.
 */
class Environment
{
    public const int GETENV = 0x01;

    public const int ENV = 0x02;

    public const int SERVER = 0x04;

    public const int GETENV_ALL = 0x08;

    public const int ENV_ALL = 0x10;

    public const int SERVER_ALL = 0x20;

    protected array $env = [];

    protected array $folders = [];

    protected string $currentFolder;

    protected string $filename;

    protected bool $hasLoaded = false;

    protected int $maxtDepth = 5;

    protected bool $useCacheFile = false;

    protected ?string $cacheFile = null;

    protected bool $hasToFlush = false;

    protected bool $inMultilines = false;

    protected string $tempText = '';

    protected string $tempKey;

    protected string $endline = \PHP_EOL;

    /**
     * Environment constructor.
     *
     * @param array|string $folders
     */
    public function __construct($folders, string $filename = '.env')
    {
        if (!\is_array($folders)) {
            $folders = [(string) $folders];
        }

        $this->folders = $folders;
        $this->filename = $filename;
    }

    /** @throws EnvironmentException */
    public function load(): void
    {
        $filepath = $this->findFileInFolders();

        if ($this->cacheFile !== null && \file_exists($this->cacheFile)) {
            if ($this->hasToFlush) {
                \unlink($this->cacheFile);
                $this->parse(\file_get_contents($filepath));
                $this->saveInCache();
            } else {
                $this->env = include $this->cacheFile;
            }
        } else {
            $this->parse(\file_get_contents($filepath));
            $this->saveInCache();
        }

        $this->hasLoaded = true;
    }

    /** @throws EnvironmentException */
    protected function findFileInFolders(int $currentIdx = 0): string
    {
        if ($currentIdx >= \count($this->folders)) {
            throw new EnvironmentException('Env file not found');
        }

        $filepath = $this->createFilepath($this->folders[$currentIdx], $this->filename);
        if (!\file_exists($filepath)) {
            ++$currentIdx;

            return $this->findFileInFolders($currentIdx);
        }

        if ($this->useCacheFile) {
            $this->cacheFile = $filepath . '.cache.php';
        }

        $this->currentFolder = $this->folders[$currentIdx];

        return $filepath;
    }

    protected function createFilepath(string $folder, string $filename): string
    {
        $separator = '';
        if (!\in_array($folder[-1], ['/', '\\'], true)) {
            $separator = \DIRECTORY_SEPARATOR;
        }

        return $folder . $separator . $filename;
    }

    /** @throws EnvironmentException */
    protected function parse(string $content, int $depth = 0): void
    {
        if ($depth > $this->maxtDepth) {
            throw new EnvironmentException('Max recursion env file!');
        }

        $lines = \mb_split("\n", $content);
        foreach ($lines as $line) {
            if ($this->inMultilines === false) {
                $line = \mb_ltrim($line);
                if ($this->isEmptyLine($line)) {
                    continue;
                }
            }

            $this->detectIncludingEnvFile($line, $depth);

            $parts = \mb_split('=', $line, 2);
            if (\count($parts) === 2) {
                if (\mb_strtoupper($parts[0]) !== $parts[0]) {
                    throw new EnvironmentException(\sprintf('Key "%s" must be uppercase', $parts[0]));
                }

                if (\is_numeric($parts[0])) {
                    throw new EnvironmentException(\sprintf('Numeric key "%s" is forbidden', $parts[0]));
                }

                if ($this->hasQuotes($parts[1])) {
                    $this->tempKey = $parts[0];
                    $this->extractText($parts[1]);
                } else {
                    $parts[1] = \mb_rtrim($parts[1]);
                    $this->inMultilines = false;
                    $parts[1] = $this->convertType($parts[1]);
                    if (\is_string($parts[1])) {
                        $parts[1] = $this->replaceVariables($parts[1]);
                    }
                    $this->set($parts[0], $parts[1]);
                }
            } elseif ($this->inMultilines) {
                $this->extractText($line);
            }
        }

        if (!empty($this->tempKey)) {
            throw new EnvironmentException(\sprintf('Key "%s" is missing for multilines', $this->tempKey));
        }
    }

    protected function isEmptyLine(string $line): bool
    {
        $char = \mb_substr($line, 0, 1);

        return empty($line) || $char === '#' || $char === ';';
    }

    /** @throws EnvironmentException */
    protected function detectIncludingEnvFile(string $line, int $depth): void
    {
        if (\mb_strpos($line, '@') === 0) {
            $line = \mb_rtrim($line);
            $filename = \mb_substr($line, 1, \mb_strlen($line));
            $filepath = $this->createFilepath($this->currentFolder, $filename);
            if (!\file_exists($filepath)) {
                throw new EnvironmentException(\sprintf('Missing env file %s', $filename));
            }
            $this->parse(\file_get_contents($filepath), ++$depth);
        }
    }

    protected function hasQuotes(string $line): bool
    {
        $val = \mb_strtolower($line);

        return \mb_strpos($val, '"') === 0;
    }

    protected function extractText(string $line): void
    {
        $string = $line;
        if ($this->inMultilines === false) {
            $string = \mb_substr($line, 1);
        }

        $lastPosition = 0;
        $endLine = false;
        $endText = false;
        do {
            $lastPosition = \mb_strpos($string, '"', $lastPosition);
            if ($lastPosition === false) {
                $this->tempText .= \mb_rtrim($string, "\r\n") . $this->endline;
                $endLine = true;
            } elseif (\mb_substr($string, $lastPosition - 1, 1) !== '\\') {
                $this->tempText .= \mb_substr($string, 0, $lastPosition);
                $endText = true;
            } else {
                ++$lastPosition;
            }
        } while ($endText !== true && $endLine !== true);

        if ($endText) {
            $this->tempText = \str_replace('\\"', '"', $this->tempText);
            $this->set($this->tempKey, $this->tempText);
            $this->tempKey = '';
            $this->tempText = '';
            $this->inMultilines = false;
        } elseif ($endLine) {
            $this->inMultilines = true;
        }
    }

    /** @return bool|float|int|string|null */
    protected function convertType(string $value): mixed
    {
        $val = \mb_strtolower($value);
        if ($val === 'true') {
            return true;
        }

        if ($val === 'false') {
            return false;
        }

        if ($val === 'null') {
            return null;
        }

        if (\is_numeric($val)) {
            if (\mb_strpos($val, '.') !== false) {
                return (float) $val;
            }

            return (int) $val;
        }

        return $value;
    }

    /** @throws EnvironmentException */
    protected function replaceVariables(string $value): string
    {
        foreach ($this->env as $keyEnv => $valueEnv) {
            $value = \str_replace('$' . $keyEnv, (string) $valueEnv, $value);
        }

        if (\mb_strpos($value, '$') !== false) {
            throw new EnvironmentException(\sprintf('Missing variable in value %s', $value));
        }

        return $value;
    }

    protected function set(string $key, $value): void
    {
        $this->env[$key] = $value;
    }

    protected function saveInCache(): void
    {
        if (!$this->useCacheFile) {
            return;
        }

        $content = '<?php return ';
        $content .= \var_export($this->env, true);
        $content .= ';';
        \file_put_contents($this->cacheFile, $content);
    }

    /**
     * @param  mixed|null           $default
     * @throws EnvironmentException
     * @return mixed|null
     */
    public function get(string $key, $default = null)
    {
        $this->autoload();

        if (!\array_key_exists($key, $this->env)) {
            return $default;
        }

        return $this->env[$key];
    }

    /** @throws EnvironmentException */
    public function getAll(): array
    {
        $this->autoload();

        return $this->env;
    }

    /**
     * @param array|string $keys
     *
     * @throws EnvironmentException
     */
    public function exists($keys): bool
    {
        $this->autoload();

        if (!\is_array($keys)) {
            $keys = [(string) $keys];
        }

        foreach ($keys as $key) {
            if (!\array_key_exists($key, $this->env)) {
                return false;
            }
        }

        return true;
    }

    /** @throws EnvironmentException */
    protected function autoload(): void
    {
        if (!$this->hasLoaded) {
            $this->load();
        }
    }

    /** @throws EnvironmentException */
    public function allowedValues(string $name, array $values): bool
    {
        if (!$this->exists($name)) {
            throw new EnvironmentException(\sprintf('Variable %s doesn\'t exist', $name));
        }

        return \in_array($name, $values, true);
    }

    public function enableCache(): void
    {
        $this->useCacheFile = true;
    }

    public function disableCache(): void
    {
        $this->useCacheFile = false;
    }

    public function flushCache(): void
    {
        $this->hasToFlush = true;
    }

    public function setEndline(string $endline): void
    {
        $this->endline = $endline;
    }

    public function getEndline(): string
    {
        return $this->endline;
    }

    /** @throws EnvironmentException */
    public function complete(int $flags): void
    {
        $this->autoload();

        if ($flags & static::GETENV) {
            foreach ($this->env as $k => $v) {
                if ($v === '') {
                    $value = \getenv($k);
                    if ($value !== false) {
                        $value = \is_string($value) ? $this->convertType($value) : $value;
                        $this->set($k, $value);
                    }
                }
            }
        }

        if ($flags & static::ENV) {
            foreach ($this->env as $k => $v) {
                if ($v === '' && isset($_ENV[$k])) {
                    $value = \is_string($_ENV[$k]) ? $this->convertType($_ENV[$k]) : $_ENV[$k];
                    $this->set($k, $value);
                }
            }
        }

        if ($flags & static::SERVER) {
            foreach ($this->env as $k => $v) {
                if ($v === '' && isset($_SERVER[$k])) {
                    $value = \is_string($_SERVER[$k]) ? $this->convertType($_SERVER[$k]) : $_SERVER[$k];
                    $this->set($k, $value);
                }
            }
        }

        if ($flags & static::GETENV_ALL) {
            foreach (\getenv() as $k => $v) {
                if (\array_key_exists($k, $this->env) && $this->env[$k] !== '') {
                    continue;
                }

                $v = \is_string($v) ? $this->convertType($v) : $v;
                $this->set($k, $v);
            }
        }

        if ($flags & static::ENV_ALL) {
            foreach ($_ENV as $k => $v) {
                if (\array_key_exists($k, $this->env) && $this->env[$k] !== '') {
                    continue;
                }

                $v = \is_string($v) ? $this->convertType($v) : $v;
                $this->set($k, $v);
            }
        }

        if ($flags & static::SERVER_ALL) {
            foreach ($_SERVER as $k => $v) {
                if (\array_key_exists($k, $this->env) && $this->env[$k] !== '') {
                    continue;
                }

                $v = \is_string($v) ? $this->convertType($v) : $v;
                $this->set($k, $v);
            }
        }
    }

    /** @throws EnvironmentException */
    public function override(int $flags): void
    {
        $this->autoload();

        if ($flags & static::GETENV) {
            foreach ($this->env as $k => $v) {
                $value = \getenv($k);
                if ($value !== false) {
                    $value = \is_string($value) ? $this->convertType($value) : $value;
                    $this->set($k, $this->convertType($value));
                }
            }
        }

        if ($flags & static::ENV) {
            foreach ($this->env as $k => $v) {
                if (isset($_ENV[$k])) {
                    $value = \is_string($_ENV[$k]) ? $this->convertType($_ENV[$k]) : $_ENV[$k];
                    $this->set($k, $value);
                }
            }
        }

        if ($flags & static::SERVER) {
            foreach ($this->env as $k => $v) {
                if (isset($_SERVER[$k])) {
                    $value = \is_string($_SERVER[$k]) ? $this->convertType($_SERVER[$k]) : $_SERVER[$k];
                    $this->set($k, $value);
                }
            }
        }

        if ($flags & static::GETENV_ALL) {
            foreach (\getenv() as $k => $v) {
                $v = \is_string($v) ? $this->convertType($v) : $v;
                $this->set($k, $v);
            }
        }

        if ($flags & static::ENV_ALL) {
            foreach ($_ENV as $k => $v) {
                $v = \is_string($v) ? $this->convertType($v) : $v;
                $this->set($k, $v);
            }
        }

        if ($flags & static::SERVER_ALL) {
            foreach ($_SERVER as $k => $v) {
                $v = \is_string($v) ? $this->convertType($v) : $v;
                $this->set($k, $v);
            }
        }
    }
}

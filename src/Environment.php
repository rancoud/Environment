<?php

declare(strict_types=1);

namespace Rancoud\Environment;

/**
 * Class Environment.
 */
class Environment
{
    /** @var array */
    protected $env = [];

    /** @var array */
    protected $folders = [];

    /** @var string */
    protected $currentFolder;

    /** @var string */
    protected $filename;

    /** @var bool */
    protected $hasLoaded = false;

    /** @var int */
    protected $maxtDepth = 5;

    /** @var bool */
    protected $useCacheFile = false;

    /** @var string */
    protected $cacheFile;

    /** @var bool */
    protected $hasToFlush = false;

    /** @var bool */
    protected $inMultilines = false;

    /** @var string */
    protected $tempText;

    /** @var string */
    protected $tempKey;

    /** @var string */
    protected $endline = PHP_EOL;

    /**
     * Environment constructor.
     *
     * @param array|string $folders
     * @param string       $filename
     */
    public function __construct($folders, string $filename = '.env')
    {
        if (!\is_array($folders)) {
            $folders = [$folders];
        }

        $this->folders = $folders;
        $this->filename = $filename;
    }

    /**
     * @throws EnvironmentException
     */
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

    /**
     * @param int $currentIdx
     *
     * @throws EnvironmentException
     *
     * @return string
     */
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

    /**
     * @param string $folder
     * @param string $filename
     *
     * @return string
     */
    protected function createFilepath(string $folder, string $filename): string
    {
        $separator = '';
        if (!\in_array($folder[-1], ['/', '\\'], true)) {
            $separator = \DIRECTORY_SEPARATOR;
        }

        return $folder . $separator . $filename;
    }

    /**
     * @param string $content
     * @param int    $depth
     *
     * @throws EnvironmentException
     */
    protected function parse(string $content, int $depth = 0): void
    {
        if ($depth > $this->maxtDepth) {
            throw new EnvironmentException('Max recursion env file!');
        }

        $lines = \mb_split("\n", $content);
        foreach ($lines as $line) {
            if ($this->inMultilines === false) {
                $line = \ltrim($line);
                if ($this->isEmptyLine($line)) {
                    continue;
                }
            }

            $this->detectIncludingEnvFile($line, $depth);

            $parts = \mb_split('=', $line, 2);
            if (\count($parts) === 2) {
                if ($this->hasQuotes($parts[1])) {
                    $this->tempKey = $parts[0];
                    $this->extractText($parts[1]);
                } else {
                    $parts[1] = \rtrim($parts[1]);
                    $this->inMultilines = false;
                    $parts[1] = $this->convertType($parts[1]);
                    $this->set($parts[0], $parts[1]);
                }
            } elseif ($this->inMultilines) {
                $this->extractText($line);
            }
        }

        if (!empty($this->tempKey)) {
            throw new EnvironmentException(\sprintf('Key %s is missing for multilines', $this->tempKey));
        }
    }

    /**
     * @param string $line
     *
     * @return bool
     */
    protected function isEmptyLine(string $line): bool
    {
        $char = \mb_substr($line, 0, 1);

        return empty($line) || $char === '#' || $char === ';';
    }

    /**
     * @param string $line
     * @param int    $depth
     *
     * @throws EnvironmentException
     */
    protected function detectIncludingEnvFile(string $line, int $depth): void
    {
        if (\mb_substr($line, 0, 1) === '@') {
            $line = \rtrim($line);
            $filename = \mb_substr($line, 1, \mb_strlen($line));
            $filepath = $this->createFilepath($this->currentFolder, $filename);
            if (!\file_exists($filepath)) {
                throw new EnvironmentException(\sprintf('Missing env file %s', $filename));
            }
            $this->parse(\file_get_contents($filepath), ++$depth);
        }
    }

    /**
     * @param string $line
     *
     * @return bool
     */
    protected function hasQuotes(string $line): bool
    {
        $val = \mb_strtolower($line);

        return \mb_substr($val, 0, 1) === '"';
    }

    /**
     * @param string $line
     */
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
                $this->tempText .= \rtrim($string, "\r\n") . $this->endline;
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

    /**
     * @param string $value
     *
     * @throws EnvironmentException
     *
     * @return bool|float|int|null|string
     */
    protected function convertType(string $value)
    {
        $val = \mb_strtolower($value);
        if ($val === 'true') {
            return true;
        } elseif ($val === 'false') {
            return false;
        } elseif ($val === 'null') {
            return null;
        } elseif (\mb_strpos($val, '.') !== false && \is_numeric($val)) {
            return (float) $val;
        } elseif (\is_numeric($val)) {
            return (int) $val;
        }

        return $this->replaceVariables($value);
    }

    /**
     * @param string $value
     *
     * @throws EnvironmentException
     *
     * @return string
     */
    protected function replaceVariables(string $value): string
    {
        foreach ($this->env as $keyEnv => $valueEnv) {
            $value = \str_replace('$' . $keyEnv, $valueEnv, $value);
        }

        if (\mb_strpos($value, '$') !== false) {
            throw new EnvironmentException(\sprintf('Missing variable in value %s', $value));
        }

        return $value;
    }

    /**
     * @param string $key
     * @param string $value
     */
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
     * @param string $key
     * @param null   $default
     *
     * @throws EnvironmentException
     *
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

    /**
     * @throws EnvironmentException
     *
     * @return array
     */
    public function getAll(): array
    {
        $this->autoload();

        return $this->env;
    }

    /**
     * @param string $name
     *
     * @throws EnvironmentException
     *
     * @return bool
     */
    public function exists(string $name): bool
    {
        $this->autoload();

        return \array_key_exists($name, $this->env);
    }

    /**
     * @throws EnvironmentException
     */
    protected function autoload(): void
    {
        if (!$this->hasLoaded) {
            $this->load();
        }
    }

    /**
     * @param string $name
     * @param array  $values
     *
     * @throws EnvironmentException
     *
     * @return bool
     */
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

    /**
     * @param string $endline
     */
    public function setEndline(string $endline): void
    {
        $this->endline = $endline;
    }

    /**
     * @return string
     */
    public function getEndline(): string
    {
        return $this->endline;
    }
}

<?php

namespace LocalPackages;

class Config
{
    protected $paths;

    protected $loaded = false;

    protected $file;

    const FILENAME = 'composer.localpackages.json';

    public function __construct($file)
    {
        $this->file = $file;
    }

    public static function make($file = null)
    {
        if (is_null($file)) {
            $file = sprintf('%s/%s', getcwd(), static::FILENAME);
        }

        return new static($file);
    }

    protected function readPaths()
    {
        if (! file_exists($this->file)) return [];

        $data = $this->readFromFile();

        return $this->deserializePaths($data);
    }

    public function getPaths()
    {
        if (! $this->loaded) {
            $this->paths = $this->readPaths();
            $this->loaded = true;
        }

        return $this->paths;
    }

    public function addPath($path)
    {
        // Ensure paths are loaded
        $this->getPaths();

        $this->paths[] = $path;
        $this->dump();
    }

    public function removePath($path)
    {
        // Ensure paths are loaded
        $this->getPaths();

        $this->paths = array_filter($this->paths, function ($existing) use ($path) {
            return $existing !== $path;
        });

        $this->dump();
    }

    public function hasPackages()
    {
        // Ensure paths are loaded
        $this->getPaths();

        return ! empty($this->paths);
    }

    protected function dump()
    {
        $this->writeToFile(
            $this->serializePaths($this->paths)
        );
    }

    protected function writeToFile(array $data)
    {
        file_put_contents(
            $this->file,
            json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ) . "\n"
        );
    }

    public function deserializePaths($obj)
    {
        return $obj['paths'];
    }

    public function serializePaths(array $paths)
    {
        sort($paths);

        return ['paths' => array_values($paths)];
    }

    protected function readFromFile()
    {
        return json_decode(file_get_contents($this->file), true);
    }
}

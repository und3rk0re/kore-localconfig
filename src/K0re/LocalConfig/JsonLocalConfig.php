<?php

namespace K0re\LocalConfig;

class JsonLocalConfig implements \ArrayAccess
{
    /**
     * Array with merged data
     *
     * @var array
     */
    private $mergedData = [];

    /**
     * Constructor
     *
     * @param string $filename Configuration filename without path
     * @param bool $treeScan   If true, scans for all configuration files recursively up to root (default: false)
     * @param bool $homeScan   If true, scans for configuration file in user homedir (default: true)
     */
    public function __construct($filename, $treeScan = false, $homeScan = true)
    {
        if (!is_string($filename) || empty($filename)) {
            throw new \InvalidArgumentException("filename must be non-empty string");
        }
        $treeScan = (bool) $treeScan;
        $homeScan = (bool) $homeScan;

        $files = [];
        if ($homeScan) {
            $files = array_merge($files, $this->homeDirScan($filename));
        }
        if ($treeScan) {
            $files = array_merge($files, $this->bubbleScan($filename));
        } else {
            $files = array_merge($files, $this->localScan($filename));
        }

        foreach (array_unique($files) as $configFile) {
            foreach ($this->load($configFile) as $k => $v) {
                $this->mergedData[$k] = $v;
            }
        }
    }

    /**
     * Searches for all config files instances from current folder and up to root
     *
     * @param string $filename
     * @return string[]
     */
    private function bubbleScan($filename)
    {
        $current = realpath(".") . "/";
        $result = [];
        while (true) {
            if (file_exists($current . $filename)) {
                $result[] = $current . $filename;
            }

            if ($current === "/") {
                break;
            } else {
                $current = dirname($current);
                $current = $current === "/" ? $current : $current . "/";
            }
        }

        return array_reverse($result);
    }

    /**
     * Searches for file in current folder
     *
     * @param string $filename
     * @return string[]
     */
    private function localScan($filename)
    {
        return file_exists($filename) ? [realpath($filename)] : [];
    }

    /**
     * Searches for file in user $HOME folder
     *
     * @param string $filename
     * @return string[]
     */
    private function homeDirScan($filename)
    {
        $fullPath = $_SERVER['HOME'] . '/' . $filename;
        return file_exists($fullPath) ? [realpath($fullPath)] : [];
    }

    /**
     * Load configuration data
     *
     * @param string $filename
     * @return array
     */
    private function load($filename)
    {
        $json = json_decode(file_get_contents($filename), true);

        if (null === $json) {
            if (json_last_error() === JSON_ERROR_NONE) {
                throw new \RuntimeException(
                    "Unable to decode JSON config {$filename} - seems to be empty file"
                );
            } elseif (function_exists("json_last_error_msg")) {
                throw new \RuntimeException(
                    "Unable to decode JSON config {$filename} " . json_last_error_msg(),
                    json_last_error()
                );
            } else {
                throw new \RuntimeException(
                    "Unable to decode JSON config " . $filename,
                    json_last_error()
                );
            }
        }

        if (!is_array($json)) {
            throw new \RuntimeException("Config file {$filename} contains not valid JSON");
        }

        return $json;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->mergedData);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            throw new \OutOfBoundsException("Index {$offset} does not exist");
        }

        return $this->mergedData[$offset];
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception Always throws an exception
     */
    public function offsetSet($offset, $value)
    {
        throw new \Exception("Configuration data is read-only");
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception Always throws an exception
     */
    public function offsetUnset($offset)
    {
        throw new \Exception("Configuration data is read-only");
    }
}

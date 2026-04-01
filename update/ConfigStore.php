<?php

declare(strict_types=1);

class ConfigStore
{
    /** @var string */
    private $configPath;

    public function __construct(string $configPath)
    {
        $this->configPath = $configPath;
    }

    public function exists(): bool
    {
        return is_file($this->configPath);
    }

    public function read(): array
    {
        $raw = @file_get_contents($this->configPath);
        $decoded = json_decode((string)$raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function writeAtomic(array $payload)
    {
        $tmpPath = $this->configPath . '.tmp';
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new UpdateException('Unable to encode config payload.');
        }
        if (@file_put_contents($tmpPath, $json . PHP_EOL) === false) {
            throw new UpdateException('Unable to write temporary config file.');
        }
        if (!@rename($tmpPath, $this->configPath)) {
            @unlink($tmpPath);
            throw new UpdateException('Unable to update config file atomically.');
        }
    }
}

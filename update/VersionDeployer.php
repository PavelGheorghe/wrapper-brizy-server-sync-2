<?php

declare(strict_types=1);

class VersionDeployer
{
    private RuntimeValidator $runtimeValidator;

    private string $tmpZipPath = '';
    private string $extractDir = '';
    private string $tmpVersionDir = '';

    public function __construct(RuntimeValidator $runtimeValidator)
    {
        $this->runtimeValidator = $runtimeValidator;
    }

    public function deployFromCloud(
        string $projectRoot,
        string $tmpRoot,
        string $deployUrl,
        string $appId,
        string $remoteVersion,
        CloudClient $cloudClient
    ): void {
        if (!is_dir($tmpRoot) && !@mkdir($tmpRoot, 0777, true) && !is_dir($tmpRoot)) {
            throw new UpdateException('Unable to create update temporary directory.');
        }

        $this->tmpZipPath = $tmpRoot . '/sync_v2_' . $remoteVersion . '_' . uniqid('', true) . '.zip';
        $this->extractDir = $tmpRoot . '/extract_' . $remoteVersion . '_' . uniqid('', true);
        $this->tmpVersionDir = $projectRoot . '/.version_' . $remoteVersion . '_' . uniqid('', true);
        $targetVersionDir = $projectRoot . '/' . $remoteVersion;

        $cloudClient->downloadVersionPackage($deployUrl, $remoteVersion, $appId, $this->tmpZipPath);

        if (!class_exists('ZipArchive')) {
            throw new UpdateException('ZipArchive extension is required for version updates.');
        }

        $zip = new ZipArchive();
        if ($zip->open($this->tmpZipPath) !== true) {
            throw new UpdateException('Unable to open downloaded zip package.');
        }

        if (!@mkdir($this->extractDir, 0777, true) && !is_dir($this->extractDir)) {
            $zip->close();
            throw new UpdateException('Unable to create extraction directory.');
        }

        if (!$zip->extractTo($this->extractDir)) {
            $zip->close();
            throw new UpdateException('Unable to extract downloaded package.');
        }
        $zip->close();

        $sourceDir = $this->resolveExtractedSource($this->extractDir);
        $this->copyDir($sourceDir, $this->tmpVersionDir);
        $this->runtimeValidator->validateStandaloneRuntime($this->tmpVersionDir);

        if (is_dir($targetVersionDir)) {
            try {
                $this->runtimeValidator->validateStandaloneRuntime($targetVersionDir);
            } catch (Throwable $ignored) {
                $this->rrmdir($targetVersionDir);
            }
        }

        if (!is_dir($targetVersionDir)) {
            if (!@rename($this->tmpVersionDir, $targetVersionDir)) {
                $this->copyDir($this->tmpVersionDir, $targetVersionDir);
                $this->rrmdir($this->tmpVersionDir);
            }
        } else {
            $this->rrmdir($this->tmpVersionDir);
        }

        $this->runtimeValidator->validateStandaloneRuntime($targetVersionDir);
    }

    public function cleanup(): void
    {
        if ($this->tmpVersionDir !== '' && is_dir($this->tmpVersionDir)) {
            $this->rrmdir($this->tmpVersionDir);
        }
        if ($this->extractDir !== '' && is_dir($this->extractDir)) {
            $this->rrmdir($this->extractDir);
        }
        if ($this->tmpZipPath !== '' && is_file($this->tmpZipPath)) {
            @unlink($this->tmpZipPath);
        }

        $this->tmpZipPath = '';
        $this->extractDir = '';
        $this->tmpVersionDir = '';
    }

    private function resolveExtractedSource(string $extractDir): string
    {
        $items = array_values(array_filter(scandir($extractDir) ?: [], static function ($item) {
            return $item !== '.' && $item !== '..';
        }));

        if (count($items) === 1) {
            $singlePath = $extractDir . DIRECTORY_SEPARATOR . $items[0];
            if (is_dir($singlePath)) {
                return $singlePath;
            }
        }

        return $extractDir;
    }

    private function copyDir(string $source, string $destination): void
    {
        if (!is_dir($destination) && !@mkdir($destination, 0777, true) && !is_dir($destination)) {
            throw new UpdateException('Unable to create target directory: ' . $destination);
        }

        $items = scandir($source);
        if ($items === false) {
            throw new UpdateException('Unable to scan source directory: ' . $source);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
            $destinationPath = $destination . DIRECTORY_SEPARATOR . $item;
            if (is_dir($sourcePath) && !is_link($sourcePath)) {
                $this->copyDir($sourcePath, $destinationPath);
            } else {
                if (!@copy($sourcePath, $destinationPath)) {
                    throw new UpdateException('Unable to copy file: ' . $sourcePath);
                }
            }
        }
    }

    private function rrmdir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath) && !is_link($fullPath)) {
                $this->rrmdir($fullPath);
            } else {
                @unlink($fullPath);
            }
        }

        @rmdir($path);
    }
}

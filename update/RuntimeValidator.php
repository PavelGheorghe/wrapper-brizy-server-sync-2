<?php

declare(strict_types=1);

class RuntimeValidator
{
    public function normalizeVersion(string $version): string
    {
        return trim(preg_replace('/[^0-9.]/', '', $version) ?? '');
    }

    public function isValidVersion(string $version): bool
    {
        return preg_match('/^\d+(?:\.\d+)*$/', $version) === 1;
    }

    public function validateStandaloneRuntime(string $versionDir): void
    {
        $requiredPaths = [
            '/index.php',
            '/composer.json',
            '/src/Kernel.php',
            '/config/config.json.dist',
            '/vendor/autoload.php',
        ];

        foreach ($requiredPaths as $requiredPath) {
            if (!is_file($versionDir . $requiredPath)) {
                throw new UpdateException(
                    'Downloaded package is not a full standalone runtime. Missing: ' . ltrim($requiredPath, '/')
                );
            }
        }
    }

    public function validateUpdatePrerequisites(string $projectRoot, string $tmpRoot): void
    {
        if (!is_writable($projectRoot)) {
            throw new UpdateException('Project root directory must be writable for updates.');
        }

        if (!is_dir($tmpRoot) && !@mkdir($tmpRoot, 0777, true) && !is_dir($tmpRoot)) {
            throw new UpdateException('Unable to create update temporary directory.');
        }

        if (!is_writable($tmpRoot)) {
            throw new UpdateException('Update temporary directory must be writable.');
        }

//        if (!class_exists('ZipArchive')) {
//            throw new UpdateException('ZipArchive extension is required for version updates.');
//        }
    }
}

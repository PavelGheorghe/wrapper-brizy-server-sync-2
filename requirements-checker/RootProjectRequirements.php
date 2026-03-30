<?php

declare(strict_types=1);

namespace RequirementsChecker;

class RootProjectRequirements extends RequirementCollection
{
    public function __construct(string $rootDir, string $activeVersion)
    {
        $this->addRequirement(
            is_writable($rootDir),
            sprintf('%s directory must be writable', $rootDir),
            sprintf(
                'Change the permissions of "<strong>%s</strong>" directory so that the web server can write into it.',
                $rootDir
            )
        );

        $versionDir = $rootDir . '/' . $activeVersion;
        $this->addRequirement(
            is_writable($versionDir),
            sprintf('%s directory must be writable', $versionDir),
            sprintf(
                'Change the permissions of "<strong>%s</strong>" directory so that the web server can write into it (Symfony cache, logs, etc.).',
                $versionDir
            )
        );

        $updateLockPath = $rootDir . '/update.lock';
        $this->addRequirement(
            $this->isUpdateLockPathWritable($updateLockPath),
            sprintf('%s must be writable (or creatable in a writable directory)', $updateLockPath),
            sprintf(
                'Ensure "<strong>%s</strong>" exists and is writable by the web server, or that the wrapper root allows creating it — it is used for update locking.',
                $updateLockPath
            )
        );

        $this->addRequirement(
            class_exists('ZipArchive'),
            'ZipArchive extension must be available',
            'Install and enable the <strong>PHP zip extension</strong> (ext-zip).'
        );

        $this->addRequirement(
            $this->isCommandAvailable('zip'),
            '"zip" command must be available',
            'Install the <strong>zip</strong> package so the binary is available in PATH.'
        );

        $this->addRequirement(
            $this->isCommandAvailable('unzip'),
            '"unzip" command must be available',
            'Install the <strong>unzip</strong> package so the binary is available in PATH.'
        );
    }

    private function isUpdateLockPathWritable(string $lockPath): bool
    {
        if (is_file($lockPath)) {
            return is_writable($lockPath);
        }

        $parent = dirname($lockPath);

        return is_dir($parent) && is_writable($parent);
    }

    private function isCommandAvailable(string $command): bool
    {
        $shellExecAvailable = function_exists('shell_exec') && stripos((string)ini_get('disable_functions'), 'shell_exec') === false;
        if ($shellExecAvailable) {
            $output = shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null');
            if (is_string($output) && trim($output) !== '') {
                return true;
            }
        }

        $fallbackPaths = [
            '/usr/bin/' . $command,
            '/bin/' . $command,
            '/usr/local/bin/' . $command,
        ];

        foreach ($fallbackPaths as $path) {
            if (is_file($path) && is_executable($path)) {
                return true;
            }
        }

        return false;
    }
}

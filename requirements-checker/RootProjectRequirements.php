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

    }

    private function isUpdateLockPathWritable(string $lockPath): bool
    {
        if (is_file($lockPath)) {
            return is_writable($lockPath);
        }

        $parent = dirname($lockPath);

        return is_dir($parent) && is_writable($parent);
    }

}

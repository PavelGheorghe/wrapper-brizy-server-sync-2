<?php

declare(strict_types=1);

/**
 * Automatic Cloud sync version check: at most once per 86400 seconds (see last_sync_version_check in config.json).
 * Call only after root requirements have passed.
 */
function brz_run_daily_sync_version_check(string $rootConfigPath, array &$rootConfig, string &$activeVersion, string &$activeIndexPath)
{
    $lastSyncCheckRaw = trim((string)($rootConfig['last_sync_version_check'] ?? ''));
    $lastSyncCheckTs = $lastSyncCheckRaw === '' ? false : strtotime($lastSyncCheckRaw);
    $syncVersionCheckDue = $lastSyncCheckTs === false || (time() - $lastSyncCheckTs) >= 86400;

    if (!$syncVersionCheckDue) {
        return;
    }

    require_once __DIR__ . '/update/update_bootstrap.php';

    $projectRoot = __DIR__;
    $lockPath = $projectRoot . '/update.lock';
    $tmpRoot = $projectRoot . '/var/update';
    $dailyCheckLock = new UpdateLock();
    $dailyLockAcquired = false;

    try {
        try {
            $dailyCheckLock->acquire($lockPath);
            $dailyLockAcquired = true;
        } catch (UpdateException $dailyLockException) {
            if ($dailyLockException->getHttpStatusCode() !== 409) {
                // Lock file errors: skip check for this request without advancing last_sync_version_check.
            }
        }

        if ($dailyLockAcquired) {
            $runtimeValidator = new RuntimeValidator();
            $configStore = new ConfigStore($rootConfigPath);
            $cloudClient = new CloudClient(60);
            $versionDeployer = new VersionDeployer($runtimeValidator);
            $jsonResponder = new JsonResponder();
            $updateApp = new UpdateApp(
                $configStore,
                $runtimeValidator,
                $cloudClient,
                $versionDeployer,
                $dailyCheckLock,
                $jsonResponder,
                $projectRoot,
                $tmpRoot,
                $lockPath
            );
            $dailyAppId = (string)($rootConfig['app_id'] ?? '');
            $updateApp->runInternal($dailyAppId, true);

            $mergedConfig = $configStore->read();
            $mergedConfig['last_sync_version_check'] = gmdate('c');
            $configStore->writeAtomic($mergedConfig);

            $rootConfig = $mergedConfig;
            $activeVersion = (string)($rootConfig['active_version'] ?? '');
            if ($activeVersion === '' || preg_match('/^\d+(?:\.\d+)*$/', $activeVersion) !== 1) {
                http_response_code(503);
                header('Content-Type: text/plain');
                echo 'Invalid active_version in config.json';
                exit;
            }
            $activeIndexPath = $projectRoot . '/' . $activeVersion . '/index.php';
            if (!is_file($activeIndexPath)) {
                http_response_code(503);
                header('Content-Type: text/plain');
                echo 'Active version entrypoint not found';
                exit;
            }
        }
    } finally {
        if ($dailyLockAcquired) {
            $dailyCheckLock->release();
        }
    }
}

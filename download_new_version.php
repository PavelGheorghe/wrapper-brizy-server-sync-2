<?php

declare(strict_types=1);

require __DIR__ . '/update/update_bootstrap.php';

$projectRoot = __DIR__;
$configPath = $projectRoot . '/config.json';
$lockPath = $projectRoot . '/update.lock';
$tmpRoot = $projectRoot . '/var/update';

$runtimeValidator = new RuntimeValidator();
$configStore = new ConfigStore($configPath);
$cloudClient = new CloudClient(60);
$versionDeployer = new VersionDeployer($runtimeValidator);
$updateLock = new UpdateLock();
$jsonResponder = new JsonResponder();

$app = new UpdateApp(
    $configStore,
    $runtimeValidator,
    $cloudClient,
    $versionDeployer,
    $updateLock,
    $jsonResponder,
    $projectRoot,
    $tmpRoot,
    $lockPath
);


$requestToken = (string)($_GET['app_id'] ?? '');
$app->run($requestToken);

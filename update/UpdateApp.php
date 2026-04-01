<?php

declare(strict_types=1);

class UpdateApp
{
    /** @var ConfigStore */
    private $configStore;
    /** @var RuntimeValidator */
    private $runtimeValidator;
    /** @var CloudClient */
    private $cloudClient;
    /** @var VersionDeployer */
    private $versionDeployer;
    /** @var UpdateLock */
    private $updateLock;
    /** @var JsonResponder */
    private $jsonResponder;
    /** @var string */
    private $projectRoot;
    /** @var string */
    private $tmpRoot;
    /** @var string */
    private $lockPath;

    public function __construct(
        ConfigStore $configStore,
        RuntimeValidator $runtimeValidator,
        CloudClient $cloudClient,
        VersionDeployer $versionDeployer,
        UpdateLock $updateLock,
        JsonResponder $jsonResponder,
        string $projectRoot,
        string $tmpRoot,
        string $lockPath
    ) {
        $this->configStore = $configStore;
        $this->runtimeValidator = $runtimeValidator;
        $this->cloudClient = $cloudClient;
        $this->versionDeployer = $versionDeployer;
        $this->updateLock = $updateLock;
        $this->jsonResponder = $jsonResponder;
        $this->projectRoot = $projectRoot;
        $this->tmpRoot = $tmpRoot;
        $this->lockPath = $lockPath;
    }

    public function run(string $requestToken)
    {
        $result = $this->runInternal($requestToken, false);
        $this->jsonResponder->send($result['http_status'], $result['body']);
    }

    /**
     * Run sync version check and optional deploy. Does not send HTTP output.
     *
     * @return array{http_status: int, body: array<string, mixed>}
     */
    public function runInternal(string $requestToken, bool $lockHeldExternally): array
    {
        $config = [];
        $hasConfig = false;
        $lockAcquired = false;

        try {
            if (!$this->configStore->exists()) {
                return $this->internalErrorResult(500, 'failed', 'Missing root config.json file.', $hasConfig, $config);
            }

            $config = $this->configStore->read();
            $hasConfig = true;

            $appId = (string)($config['app_id'] ?? '');
            $deployUrl = rtrim((string)($config['deploy_url'] ?? ''), '/');
            $activeVersion = $this->runtimeValidator->normalizeVersion((string)($config['active_version'] ?? '0'));

            if ($appId === '' || $deployUrl === '') {
                return $this->internalErrorResult(500, 'failed', 'config.json must include app_id and deploy_url.', $hasConfig, $config);
            }

            if (!hash_equals($appId, $requestToken)) {
                return [
                    'http_status' => 401,
                    'body' => [
                        'status' => 'failed',
                        'message' => 'UNAUTHORIZED',
                    ],
                ];
            }

            try {
                $this->runtimeValidator->validateUpdatePrerequisites($this->projectRoot, $this->tmpRoot);
            } catch (UpdateException $updateException) {
                return $this->internalFromUpdateException($updateException, $hasConfig, $config);
            }

            if (!$lockHeldExternally) {
                try {
                    $this->updateLock->acquire($this->lockPath);
                    $lockAcquired = true;
                } catch (UpdateException $updateException) {
                    return $this->internalFromUpdateException($updateException, $hasConfig, $config);
                }
            }

            $remoteSyncVersion = $this->cloudClient->fetchRemoteSyncVersion($deployUrl, $appId);
            $remoteVersion = $this->runtimeValidator->normalizeVersion($remoteSyncVersion);
            if (!$this->runtimeValidator->isValidVersion($remoteVersion)) {
                throw new UpdateException('Cloud sync version endpoint did not provide a valid sync_version.');
            }

            if (version_compare($remoteVersion, $activeVersion, '<=')) {
                $config['last_checked_at'] = gmdate('c');
                $config['last_error'] = null;
                $this->configStore->writeAtomic($config);

                return [
                    'http_status' => 200,
                    'body' => [
                        'status' => 'no_update',
                        'active_version' => $activeVersion,
                        'remote_version' => $remoteVersion,
                    ],
                ];
            }

            $this->versionDeployer->deployFromCloud(
                $this->projectRoot,
                $this->tmpRoot,
                $deployUrl,
                $appId,
                $remoteVersion,
                $this->cloudClient
            );

            $config['active_version'] = $remoteVersion;
            $config['last_checked_at'] = gmdate('c');
            $config['last_error'] = null;
            $this->configStore->writeAtomic($config);

            return [
                'http_status' => 200,
                'body' => [
                    'status' => 'updated',
                    'previous_version' => $activeVersion,
                    'active_version' => $remoteVersion,
                ],
            ];
        } catch (UpdateException $updateException) {
            return $this->internalFromUpdateException($updateException, $hasConfig, $config);
        } catch (Throwable $throwable) {
            return $this->internalFromThrowable($throwable, $hasConfig, $config);
        } finally {
            $this->versionDeployer->cleanup();
            if ($lockAcquired) {
                $this->updateLock->release();
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array{http_status: int, body: array<string, mixed>}
     */
    private function internalFromUpdateException(UpdateException $updateException, bool $hasConfig, array $config): array
    {
        if ($hasConfig && $updateException->getHttpStatusCode() !== 409) {
            $config['last_checked_at'] = gmdate('c');
            $config['last_error'] = $updateException->getMessage();
            try {
                $this->configStore->writeAtomic($config);
            } catch (Throwable $ignored) {
            }
        }

        return [
            'http_status' => $updateException->getHttpStatusCode(),
            'body' => [
                'status' => $updateException->getResponseStatus(),
                'message' => $updateException->getMessage(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{http_status: int, body: array<string, mixed>}
     */
    private function internalFromThrowable(Throwable $throwable, bool $hasConfig, array $config): array
    {
        if ($hasConfig) {
            $config['last_checked_at'] = gmdate('c');
            $config['last_error'] = $throwable->getMessage();
            try {
                $this->configStore->writeAtomic($config);
            } catch (Throwable $ignored) {
            }
        }

        return [
            'http_status' => 500,
            'body' => [
                'status' => 'failed',
                'message' => $throwable->getMessage(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{http_status: int, body: array<string, mixed>}
     */
    private function internalErrorResult(int $httpStatus, string $responseStatus, string $message, bool $hasConfig, array $config): array
    {
        if ($hasConfig) {
            $config['last_checked_at'] = gmdate('c');
            $config['last_error'] = $message;
            try {
                $this->configStore->writeAtomic($config);
            } catch (Throwable $ignored) {
            }
        }

        return [
            'http_status' => $httpStatus,
            'body' => [
                'status' => $responseStatus,
                'message' => $message,
            ],
        ];
    }
}

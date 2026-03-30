<?php

declare(strict_types=1);

class CloudClient
{
    private int $timeoutSeconds;

    public function __construct(int $timeoutSeconds = 60)
    {
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function fetchRemoteSyncVersion(string $deployUrl, string $appId): string
    {
        $response = $this->httpGet($deployUrl . '/projects/sync/version', [
            'Accept: application/json',
            'app-id: ' . $appId,
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new UpdateException('Unable to fetch Cloud sync version, status ' . $response['status'] . '.');
        }

        $payload = json_decode((string)$response['body'], true);
        if (!is_array($payload)) {
            throw new UpdateException('Cloud sync version response is not valid JSON.');
        }

        $syncVersion = trim((string)($payload['sync_version'] ?? ''));
        if ($syncVersion === '') {
            throw new UpdateException('Cloud sync version response is missing sync_version.');
        }

        return $syncVersion;
    }

    public function downloadVersionPackage(string $deployUrl, string $version, string $appId, string $targetPath): void
    {
        $packageUrl = rtrim($deployUrl, '/') . '/projects/sync/package';
        $packageResponse = $this->httpGet($packageUrl, [
            'Accept: application/zip',
            'app-id: ' . $appId,
        ], 600);

        if ($packageResponse['status'] < 200 || $packageResponse['status'] >= 300) {
            throw new UpdateException('Cloud sync package endpoint failed with status ' . $packageResponse['status'] . '.');
        }
        if ($packageResponse['body'] === '') {
            throw new UpdateException('Cloud package download returned an empty package.');
        }
        if (@file_put_contents($targetPath, $packageResponse['body']) === false) {
            throw new UpdateException('Unable to write downloaded zip file.');
        }
    }

    /**
     * @return array{status:int,headers:array<int,string>,body:string}
     */
    private function httpGet(string $url, array $headers, ?int $timeoutOverrideSeconds = null): array
    {
        $timeout = $timeoutOverrideSeconds ?? $this->timeoutSeconds;
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers),
            ]
        ]);

        $body = @file_get_contents($url, false, $context);
        $responseHeaders = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
        $statusCode = $this->extractStatusCode($responseHeaders);

        return [
            'status' => $statusCode,
            'headers' => $responseHeaders,
            'body' => $body === false ? '' : $body,
        ];
    }

    private function extractStatusCode(array $headers): int
    {
        foreach ($headers as $headerLine) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $matches) === 1) {
                return (int)$matches[1];
            }
        }

        return 0;
    }
}

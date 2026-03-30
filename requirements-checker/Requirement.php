<?php

declare(strict_types=1);

namespace RequirementsChecker;

class Requirement
{
    private bool $fulfilled;
    private string $testMessage;
    private string $helpText;
    private string $helpHtml;
    private bool $optional;

    public function __construct(
        bool $fulfilled,
        string $testMessage,
        string $helpHtml,
        ?string $helpText = null,
        bool $optional = false
    ) {
        $this->fulfilled = $fulfilled;
        $this->testMessage = $testMessage;
        $this->helpHtml = $helpHtml;
        $this->helpText = $helpText === null ? strip_tags($helpHtml) : $helpText;
        $this->optional = $optional;
    }

    public function isFulfilled(): bool
    {
        return $this->fulfilled;
    }

    public function getTestMessage(): string
    {
        return $this->testMessage;
    }

    public function getHelpText(): string
    {
        return $this->helpText;
    }

    public function getHelpHtml(): string
    {
        return $this->helpHtml;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }
}

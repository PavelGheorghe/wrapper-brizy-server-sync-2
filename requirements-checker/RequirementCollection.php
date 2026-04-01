<?php

declare(strict_types=1);

namespace RequirementsChecker;

class RequirementCollection
{
    /** @var Requirement[] */
    private $requirements = [];

    public function add(Requirement $requirement)
    {
        $this->requirements[] = $requirement;
    }

    public function addRequirement(
        bool $fulfilled,
        string $testMessage,
        string $helpHtml,
        $helpText = null
    ) {
        $this->add(new Requirement($fulfilled, $testMessage, $helpHtml, $helpText, false));
    }

    public function addRecommendation(
        bool $fulfilled,
        string $testMessage,
        string $helpHtml,
        $helpText = null
    ) {
        $this->add(new Requirement($fulfilled, $testMessage, $helpHtml, $helpText, true));
    }

    /** @return Requirement[] */
    public function getFailedRequirements(): array
    {
        $failed = [];
        foreach ($this->requirements as $requirement) {
            if (!$requirement->isFulfilled() && !$requirement->isOptional()) {
                $failed[] = $requirement;
            }
        }

        return $failed;
    }

    /** @return Requirement[] */
    public function getFailedRecommendations(): array
    {
        $failed = [];
        foreach ($this->requirements as $requirement) {
            if (!$requirement->isFulfilled() && $requirement->isOptional()) {
                $failed[] = $requirement;
            }
        }

        return $failed;
    }
}

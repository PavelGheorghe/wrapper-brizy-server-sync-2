<?php

declare(strict_types=1);

$projectRequirements = new \RequirementsChecker\RootProjectRequirements($projectRootDir, $activeVersion);
$failedRequirements = $projectRequirements->getFailedRequirements();
$failedRecommendations = $projectRequirements->getFailedRecommendations();

if (!count($failedRequirements) && !count($failedRecommendations)) {
    return true;
}

http_response_code(503);
header('Content-Type: text/html; charset=UTF-8');

echo '<h1>Project Requirements Checker</h1> <br><br>';

if (count($failedRequirements)) {
    echo '<h2>Failed Requirements:</h2> <br><br>';
    foreach ($failedRequirements as $index => $failedRequirement) {
        $number = $index + 1;
        echo '<h4>' . $number . '. ' . htmlspecialchars($failedRequirement->getTestMessage(), ENT_QUOTES, 'UTF-8') . '</h4>';
        echo $failedRequirement->getHelpHtml() . '<br>';
        echo '===========================================<br>';
    }
}

if (count($failedRecommendations)) {
    echo '<h2>Failed Recommendations:</h2> <br><br>';
    foreach ($failedRecommendations as $index => $failedRecommendation) {
        $number = $index + 1;
        echo '<h4>' . $number . '. ' . htmlspecialchars($failedRecommendation->getTestMessage(), ENT_QUOTES, 'UTF-8') . '</h4>';
        echo $failedRecommendation->getHelpHtml() . '<br>';
        echo '===========================================<br>';
    }
}

exit;

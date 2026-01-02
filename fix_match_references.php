<?php

/**
 * Script pour remplacer toutes les références à "Match" par "FootballMatch"
 */

$files = [
    'app/symfony/src/Entity/Prediction.php',
    'app/symfony/src/Entity/Odds.php',
    'app/symfony/src/Entity/Team.php',
    'app/symfony/src/Repository/OddsRepository.php',
    'app/symfony/src/Controller/DashboardController.php',
    'app/symfony/src/Controller/FootballController.php',
    'app/symfony/src/Controller/BettingController.php',
    'app/symfony/src/Service/Prediction/ResultPredictionService.php',
    'app/symfony/src/Service/Prediction/GoalsPredictionService.php',
    'app/symfony/src/Service/Prediction/ScorerPredictionService.php',
    'app/symfony/src/Service/Prediction/HalfTimeFullTimeService.php',
    'app/symfony/src/Service/Data/OddsAggregator.php',
];

$replacements = [
    '/use App\\\\Entity\\\\Match;/' => 'use App\\Entity\\FootballMatch;',
    '/Match \$match/' => 'FootballMatch $match',
    '/MatchRepository/' => 'FootballMatchRepository',
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }

    $content = file_get_contents($file);
    $originalContent = $content;

    foreach ($replacements as $search => $replace) {
        $content = preg_replace($search, $replace, $content);
    }

    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "✓ Updated: $file\n";
    } else {
        echo "- No changes: $file\n";
    }
}

echo "\nDone!\n";
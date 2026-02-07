<?php
/**
 * Usage: php myspace.php [target_space]
 */

$targetGap = isset($argv[1]) ? (int)$argv[1] : null;

if ($targetGap === null) {
    echo "ERROR: You must specify the space to find.\n";
    echo "Usage: php " . $argv[0] . " [number]\n";
    exit(1);
}

$csvFile = "track.csv";
if (!file_exists($csvFile)) {
    die("ERROR: File '$csvFile' not found.\n");
}

// 1) Load and sanitize data
$labels = [];
$fh = fopen($csvFile, "r");
while (($line = fgets($fh)) !== false) {
    $row = str_getcsv($line);
    if (!empty($row[0])) {
        $labels[] = (int)substr($row[0], 0, 5);
    }
}
fclose($fh);

$labels = array_values(array_unique($labels));
sort($labels, SORT_NUMERIC);

// 2) Map all available gaps
$gaps = [];
for ($i = 0; $i < count($labels) - 1; $i++) {
    $size = $labels[$i + 1] - $labels[$i];
    $gaps[] = [
        'start' => $labels[$i],
        'size'  => $size
    ];
}

$totalGapsCount = count($gaps);
if ($totalGapsCount === 0) {
    die("No intervals found in the file.\n");
}

// Helper: pick random element from array
function pick_random(array $arr) {
    return $arr[random_int(0, count($arr) - 1)];
}

// 3) Selection Logic (random tra gli equivalenti)
$exactMatches = [];
$biggerGaps   = [];
$smallerGaps  = [];

foreach ($gaps as $g) {
    if ($g['size'] === $targetGap) {
        $exactMatches[] = $g;
    } elseif ($g['size'] > $targetGap) {
        $biggerGaps[] = $g;
    } else {
        $smallerGaps[] = $g;
    }
}

$selection = null;

if (!empty($exactMatches)) {
    // Priority 1: Exact match -> scegli a caso tra tutti gli exact
    $selection = pick_random($exactMatches);

} elseif (!empty($biggerGaps)) {
    // Priority 2: tra i gap più grandi, prendi la SIZE minima
    $minSize = min(array_column($biggerGaps, 'size'));
    $candidates = array_values(array_filter($biggerGaps, fn($g) => $g['size'] === $minSize));
    $selection = pick_random($candidates);

} elseif (!empty($smallerGaps)) {
    // Priority 3: tra i gap più piccoli, prendi la SIZE massima
    $maxSize = max(array_column($smallerGaps, 'size'));
    $candidates = array_values(array_filter($smallerGaps, fn($g) => $g['size'] === $maxSize));
    $selection = pick_random($candidates);
}

// 4) Final Output
if ($selection) {
    echo "--- SEARCH RESULT ---\n";
    echo "First element:    " . str_pad((string)$selection['start'], 5, "0", STR_PAD_LEFT) . "\n";
    echo "Actual space:     " . $selection['size'] . " (Target: $targetGap)\n";
    echo "Available gaps:   " . $totalGapsCount . "\n";
} else {
    echo "No suitable interval found.\n";
}


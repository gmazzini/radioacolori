<?php

/**
 * Usage: php myspace.php [target_space]
 */

// 1. Handle CLI Input
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

// 2. Load and sanitize data
$labels = [];
$fh = fopen($csvFile, "r");
while (($line = fgets($fh)) !== false) {
    $row = str_getcsv($line);
    if (!empty($row[0])) {
        // Extract first 5 chars and cast to int
        $labels[] = (int)substr($row[0], 0, 5);
    }
}
fclose($fh);

// Dedup and sort labels
$labels = array_values(array_unique($labels));
sort($labels, SORT_NUMERIC);

// 3. Map all available gaps
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

// 4. Selection Logic
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
    // Priority 1: Exact match found
    $selection = $exactMatches[0];
} elseif (!empty($biggerGaps)) {
    // Priority 2: Smallest among the larger gaps
    usort($biggerGaps, fn($a, $b) => $a['size'] <=> $b['size']);
    $selection = $biggerGaps[0];
} elseif (!empty($smallerGaps)) {
    // Priority 3: Largest among the smaller gaps
    usort($smallerGaps, fn($a, $b) => $b['size'] <=> $a['size']);
    $selection = $smallerGaps[0];
}

// 5. Final Output
if ($selection) {
    echo "--- SEARCH RESULT ---\n";
    echo "First element:    " . str_pad((string)$selection['start'], 5, "0", STR_PAD_LEFT) . "\n";
    echo "Actual space:     " . $selection['size'] . " (Target: $targetGap)\n";
    echo "Available gaps:   " . $totalGapsCount . "\n";
} else {
    echo "No suitable interval found.\n";
}

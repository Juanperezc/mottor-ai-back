<?php

/**
 * Builds database/datasets/too-many.json: one quote over the 10,000 limit of POST /imports.
 *
 * Usage (from the repository root):
 *   ./vendor/bin/sail php database/datasets/generate-too-many.php [source.json] [count]
 *
 * The source defaults to valid-multi-batch.json. Its quotes are repeated in order until
 * reaching `count` (10001 by default); every copy gets a new id PRE-2026-NNNN.
 * With the full dataset.json as source, the output is its 10,000 quotes plus PRE-2026-10001.
 */
$dir = __DIR__;
$source = $argv[1] ?? $dir.'/valid-multi-batch.json';
$count = (int) ($argv[2] ?? 10001);
$target = $dir.'/too-many.json';

$data = json_decode((string) file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);
$quotes = $data['quotes'] ?? [];

if ($quotes === []) {
    fwrite(STDERR, "The source has no quotes: {$source}\n");
    exit(1);
}

$sourceCount = count($quotes);

for ($i = $sourceCount; $i < $count; $i++) {
    $copy = $quotes[$i % $sourceCount];
    $copy['budget']['id'] = sprintf('PRE-2026-%04d', $i + 1);
    $quotes[] = $copy;
}

$data['quotes'] = array_slice($quotes, 0, $count);
$data['count'] = count($data['quotes']);

file_put_contents(
    $target,
    json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

echo "{$target}: {$data['count']} quotes\n";

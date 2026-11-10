<?php

declare(strict_types=1);

$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
$bytes = random_bytes(20);
$secret = '';
$buffer = 0;
$bits = 0;

foreach (str_split($bytes) as $byte) {
    $buffer = ($buffer << 8) | ord($byte);
    $bits += 8;

    while ($bits >= 5) {
        $bits -= 5;
        $secret .= $alphabet[($buffer >> $bits) & 31];
    }
}

if ($bits > 0) {
    $secret .= $alphabet[($buffer << (5 - $bits)) & 31];
}

echo "Recovery TOTP Secret:\n";
echo $secret . PHP_EOL;

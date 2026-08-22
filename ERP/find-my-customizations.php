<?php
/**
 * AUN — Customization Finder
 * -------------------------------------------------------------------------
 * Lists every file changed or added AFTER the UltimatePOS build date, i.e.
 * your customizations. Stock v6.9 files all carry the vendor's build date
 * (2025-10-16); anything newer was touched by you.
 *
 * HOW TO USE
 *  1. Upload this file to the ERP root (same folder as artisan / .env).
 *  2. Visit:  https://portal.smartliving.com.bd/find-my-customizations.php?key=CHANGE_THIS_KEY
 *  3. Copy the whole output and save it.
 *  4. DELETE this file from the server immediately afterwards.
 * -------------------------------------------------------------------------
 */

// ── Change this to any random string, and use the same value in the URL ──
$SECRET = 'CHANGE_THIS_KEY';

// Anything modified after this date is treated as a customization.
$CUTOFF = '2025-10-17 00:00:00';

if (($_GET['key'] ?? '') !== $SECRET) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');

// Find the Laravel root: this file may sit in the ERP root OR in public/.
// Walk upwards looking for 'artisan' (the Laravel root marker). If it is never
// found we STAY in this directory rather than wandering up the filesystem.
$root  = __DIR__;
$probe = __DIR__;
for ($i = 0; $i < 4; $i++) {
    if (file_exists($probe . '/artisan')) { $root = $probe; break; }
    $parent = dirname($probe);
    if ($parent === $probe) { break; }
    $probe = $parent;
}
$cutoffTs = strtotime($CUTOFF);

// Folders that change by themselves (caches, logs, uploads, libraries).
$skipDirs = [
    'vendor', 'node_modules', '.git', 'storage', 'bootstrap/cache',
    'public/uploads', 'public/vendor', 'public/build', '.well-known',
];

// Files that are noise, not customization.
$skipFiles = ['find-my-customizations.php', '.env', 'composer.lock', 'package-lock.json'];

$found = [];

$it = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        function ($current) use ($root, $skipDirs) {
            $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($current->getPathname(), strlen($root) + 1));
            foreach ($skipDirs as $d) {
                if ($rel === $d || strpos($rel, $d . '/') === 0) return false;
            }
            return true;
        }
    ),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($it as $file) {
    if (!$file->isFile()) continue;
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
    if (in_array(basename($rel), $skipFiles, true)) continue;

    $mt = $file->getMTime();
    if ($mt > $cutoffTs) {
        $found[$rel] = $mt;
    }
}

asort($found);

echo "UltimatePOS Customization Report\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n";
echo "Files modified after: {$CUTOFF}\n";
echo "Scanning root: " . $root . "
";
echo "Total customized files: " . count($found) . "\n";
echo str_repeat('=', 78) . "\n\n";

foreach ($found as $rel => $mt) {
    printf("%s  %s\n", date('Y-m-d H:i', $mt), $rel);
}

echo "\n" . str_repeat('=', 78) . "\n";
echo "REMEMBER: delete this file from the server now.\n";

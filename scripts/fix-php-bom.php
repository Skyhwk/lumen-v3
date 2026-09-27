<?php

$dir = __DIR__ . '/../app/Models/Greatday';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    if (strpos($content, "<?php\nnamespace") === 0) {
        $content = "<?php\n\n" . substr($content, 5);
    }
    file_put_contents($path, $content);
    echo "Fixed: $path\n";
}

<?php
header('Content-Type: text/plain');
echo "PHP: " . phpversion() . "\n";

// Test different methods
echo "\n--- shell_exec ---\n";
$r = shell_exec('echo hello');
echo "result: " . var_export($r, true) . "\n";

echo "\n--- exec ---\n";
exec('echo hello', $out, $ret);
echo "ret=$ret out=" . implode(',', $out) . "\n";

echo "\n--- popen ---\n";
$h = popen('echo hello', 'r');
if ($h) { echo "popen: " . fread($h, 1024); pclose($h); } else echo "popen: FAILED\n";

echo "\n--- Python via popen ---\n";
$h = popen('"C:\Users\aero\AppData\Local\Programs\Python\Python310\python.exe" -c "print(42)" 2>NUL', 'r');
if ($h) {
    stream_set_timeout($h, 10);
    $o = fread($h, 1024);
    echo "Python popen: " . ($o !== false ? trim($o) : "TIMEOUT/ERROR");
    pclose($h);
} else echo "Python popen: FAILED\n";

echo "\nDone.\n";

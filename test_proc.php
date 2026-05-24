<?php
$files = glob('C:\laragon\www\plat-reader\captures\captured_1_upload_*.jpg');
sort($files);
$fpath = end($files);
$python = 'python';
$script = 'C:\laragon\www\plat-reader\python\ocr_file.py';
$cmd = sprintf('"%s" "%s" "%s"', $python, $script, $fpath);
echo "CMD: $cmd\n";

$descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open($cmd, $descriptorspec, $pipes);
if (!is_resource($proc)) { echo "FAILED to start\n"; exit(1); }
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
$ret = proc_close($proc);
echo "RET: $ret\n";
echo "STDOUT: " . substr($stdout, 0, 500) . "\n";
echo "STDERR: " . substr($stderr, 0, 500) . "\n";

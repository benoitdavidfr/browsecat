<?php
/*PhpDoc:
name: testhttp.php
title: testhttp.php - test d'appels http
doc: |
journal: |
  1/11/2021:
    - création
*/
require_once __DIR__.'/httpretry.inc.php';


$host = 'localhost';
//$host = 'bdavid.alwaysdata.net';
  
// Test avec redirect en localhost ou sur Alwaysdata
//$result = HttpRetry::request("http://$host/browsecat/testserver.php?redirect=true", ['maxRetry'=> 5, 'ignore_errors'=> true]);

$start = microtime(true);
$result = Http::request("http://$host/browsecat/testserver.php?sleep=2", ['ignore_errors'=> true, 'timeout'=> 3.1]);
echo '<pre>'; print_r($result);
echo "en ",microtime(true)-$start,"s<br>\n";

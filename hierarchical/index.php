<?php
/*PhpDoc:
name: index.php
title: hierarchical/index.php - tests des relations hiérarchiques
doc: |
  
journal: |
  5/12/2021:
    - création
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../catinpgsql.inc.php';
require_once __DIR__.'/../cats.inc.php';

use Symfony\Component\Yaml\Yaml;

if (php_sapi_name()=='cli') {
  
}
else {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>hierarchical</title></head><body><pre>\n";
  if (!isset($_GET['action'])) {
    echo "</pre><ul>\n";
    echo "<li><a href='?action=stats'>stats</a></li>\n";
    echo "<li><a href='?action=list'>list</a></li>\n";
    die();
  }
  $action = $_GET['action'];
}

if (!CatInPgSql::chooseServer('local')) { // Choix du serveur 
  die("Erreur: paramètre serveur incorrect !\n");
}

if ($action == 'stats') {
  foreach ($cats as $catid => $cat) {
    $parentIdentifiers = 0;
    $aggregationInfos = 0;
    $total = 0;
    $sql = "select id,title,record from catalog$catid where type in ('dataset','series')";
    foreach (PgSql::query($sql) as $tuple) {
      $record = json_decode($tuple['record'], true);
      if (isset($record['parentIdentifier']))
        $parentIdentifiers++;
      if (isset($record['aggregationInfo']))
        $aggregationInfos++;
      $total++;
    }
    printf("%s:\n  parentIdentifiers=%d\n  aggregationInfos=%d\n  total=%d\n",
      $catid, $parentIdentifiers, $aggregationInfos, $total); 
  }
  die();
}

if ($action == 'list') {
  if (!isset($_GET['cat'])) {
    echo "</pre><ul>\n";
    foreach ($cats as $catid => $cat) {
      echo "<li><a href='?action=$_GET[action]&amp;cat=$catid'>$catid</a></li>\n";
    }
    die();
  }
  else {
    echo "</pre><ul>\n";
    $sql = "select id,title,record from catalog$_GET[cat] where type in ('dataset','series')";
    foreach (PgSql::query($sql) as $tuple) {
      $record = json_decode($tuple['record'], true);
      if (isset($record['parentIdentifier']) || isset($record['aggregationInfo'])) {
        echo "<li>",
          isset($record['parentIdentifier']) ? 'P>':'',
          isset($record['aggregationInfo']) ? 'A>':'',
          "<a href='?action=showPg&amp;cat=$_GET[cat]&amp;id=$tuple[id]'>$tuple[title]</a></li>\n";
      }
    }
  }
  die();
}

if ($action == 'showPg') {
  $sql = "select record from catalog$_GET[cat] where id='$_GET[id]'";
  $tuple = PgSql::getTuples($sql)[0] ?? null;
  if (!$tuple) {
    $sql = "select id,title,record from catalog$_GET[cat] where type in ('dataset','series')";
    $ok = false;
    foreach (PgSql::query($sql) as $tuple) {
      $record = json_decode($tuple['record'], true);
      if (isset($record['dct:identifier']) && ($record['dct:identifier'][0]['code'] == $_GET['id'])) {
        $ok = true;
        break;
      }
    }
    if (!$ok)
      die("Tuple $_GET[id] non trouvé\n");
  }
  $record = json_decode($tuple['record'], true);
  $out = Yaml::dump($record, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
  
  if (isset($record['parentIdentifier'])) {
    $parentIdentifier = $record['parentIdentifier'][0];
    $ahref = "<a href='?action=showPg&amp;cat=$_GET[cat]&amp;id=$parentIdentifier'>$parentIdentifier</a>";
    $out = preg_replace('!parentIdentifier:\n  - ([^\n]*)!', "parentIdentifier: $ahref", $out, 1);
  }
  
  while (preg_match('!aggregateDataSetIdentifier: ([^\n]*)!', $out, $matches)) {
    $childid = $matches[1];
    $ahref = "<a href='?action=showPg&amp;cat=$_GET[cat]&amp;id=$childid'>$childid</a>";
    $out = preg_replace("!aggregateDataSetIdentifier: $childid!", "AggregateDataSetIdentifier: $ahref", $out, 1);
  }
  
  echo $out;
  die();
}
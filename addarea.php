<?php
/*PhpDoc:
title: addarea.php - ajout d'un champ area
name: addarea.php
doc: |
journal: |
  8/11/2021:
    - création
*/
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';

function area(array $bbox): float {
  if (!isset($bbox['eastLon'])) return 0;
  if (!isset($bbox['westLon'])) return 0;
  if (!isset($bbox['northLat'])) return 0;
  if (!isset($bbox['southLat'])) return 0;
  return abs(($bbox['eastLon'] - $bbox['westLon']) * ($bbox['northLat'] - $bbox['southLat']));
}

if (php_sapi_name()=='cli') {
  if ($argc <= 1) {
    echo "usage: php $argv[0] {cat}|all\n";
    echo " où {cat} vaut:\n";
    foreach ($cats as $catid => $cat)
      echo " - $catid\n";
    die();
  }

  $catid = $argv[1];

  if ($catid == 'all') { // génère les cmdes pour traiter tous les catalogues
    foreach (array_keys($cats) as $catid) {
      echo "php $argv[0] $catid\n";
    }
    die();
  }
}
else { // php_sapi_name()<>'cli'
  if (!isset($_GET['cat'])) { // choix du catalogue ou actions globales
    echo "Catalogues:<ul>\n";
    foreach ($cats as $catid => $cat) {
      echo "<li><a href='?cat=$catid'>$catid</a></li>\n";
    }
    echo "</ul>\n";
    die();
  }
  $catid = $_GET['cat'];
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>addarea.php</title></head><body><pre>\n";
}

// Choisir le serveur
PgSql::open('host=pgsqlserver dbname=gis user=docker');

try {
  PgSql::query("alter table catalog$catid add area real");
}
catch (Exception $e) {
  echo '<b>',$e->getMessage(),"</b>\n\n";
}
try {
  PgSql::query("create index \"area\" ON catalog$catid (area)");
}
catch (Exception $e) {
  echo '<b>',$e->getMessage(),"</b>\n\n";
}

$sql = "select id, title, record from catalog$catid where type in ('dataset','series')";
foreach (PgSql::query($sql) as $tuple) {
  //echo "$tuple[title]\n";
  $record = json_decode($tuple['record'], true);
  //print_r($record['dcat:bbox']);
  $area = 0;
  foreach ($record['dcat:bbox'] ?? [] as $bbox) {
    $area += area($bbox);
  }
  //printf("area=%f\n", $area);
  if ($area == 0)
    PgSql::query("update catalog$catid set area=null where id='$tuple[id]'");
  else
    PgSql::query("update catalog$catid set area=$area where id='$tuple[id]'");
}
echo "Ok\n";



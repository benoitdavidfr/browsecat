<?php
/*PhpDoc:
title: crauxtabl.php - crée 2 tables auxilaires par catalogue
name: crauxtabl.php
doc: |
journal: |
  9/11/2021:
    - création
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/arbo.inc.php';

use Symfony\Component\Yaml\Yaml;


$arbo = 'arboCovadis'; // arborescence utilisée


// Renvoie la liste prefLabels structurée par arbo, [ {arboid} => [ {prefLabel} ]]
function prefLabels(array $keywords, array $arbos): array {
  $prefLabels = []; // liste des prefLabels des mots-clés structuré par arbo, sous forme [arboid => [prefLabel => 1]]
  foreach ($keywords as $keyword) {
    //echo "<pre>"; print_r($keyword); echo "</pre>\n";
    if ($kwValue = $keyword['value'] ?? null) {
      foreach ($arbos as $arboid => $arbo) {
        if ($prefLabel = $arbo->prefLabel($kwValue)) {
          $prefLabels[$arboid][$prefLabel] = 1;
        }
      }
    }
  }
  ksort($prefLabels);
  foreach ($prefLabels as $arboid => $labels) {
    $prefLabels[$arboid] = array_keys($labels);
  }
  //echo "<pre>prefLabels(",Yaml::dump($keywords),") -> ",Yaml::dump($prefLabels),"</pre>";
  return $prefLabels;
}

// Liste des arborescences auxquels les mots-clés peuvent appartenir
$arbos = [
  'arboCovadis'=> new Arbo('arbocovadis.yaml'),
  'annexesInspire'=> new Arbo('annexesinspire.yaml'),
];

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
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>crauxtabl.php</title></head><body><pre>\n";
}

// Choisir le serveur
PgSql::open('host=pgsqlserver dbname=gis user=docker');

PgSql::query("drop table if exists catorg$catid");
PgSql::query("create table catorg$catid(
  id varchar(256) not null, -- fileIdentifier de la fiche de données
  org text not null -- nom de l'organisation
)");
PgSql::query("create index on catorg$catid(id)");
PgSql::query("create index on catorg$catid(org)");

PgSql::query("drop table if exists cattheme$catid");
PgSql::query("create table cattheme$catid(
  id varchar(256) not null, -- fileIdentifier de la fiche de données
  theme text not null -- nom du theme
)");
PgSql::query("create index on cattheme$catid(id)");
PgSql::query("create index on cattheme$catid(theme)");

$arboOrgsPMin = new Arbo('orgpmin.yaml');

$sql = "select id, title, record from catalog$catid where type in ('dataset','series') and perimetre='Min'";
foreach (PgSql::query($sql) as $tuple) {
  //echo "$tuple[title]\n";
  $record = json_decode($tuple['record'], true);

  $prefLabels = prefLabels($record['keyword'] ?? [], [$arbo=> $arbos[$arbo]]);
  //echo "id=$tuple[id], prefLabels="; print_r($prefLabels);

  foreach ($prefLabels[$arbo] ?? [] as $theme) {
    $sql = "insert into cattheme$catid(id, theme) values ('$tuple[id]', '$theme')";
    //echo "$sql\n";
    PgSql::query($sql);
  }
  
  foreach ($record['responsibleParty'] ?? [] as $party) {
    if (!isset($party['organisationName'])) continue;
    $orgname = $party['organisationName'];
    //echo "  orgname=$orgname\n";
    $orgname = $arboOrgsPMin->prefLabel($orgname);
    if (!$orgname) continue;
    //echo "  stdOrgname=$orgname\n";
    $orgname = str_replace("'", "''", $orgname);
    $sql = "insert into catorg$catid(id, org) values ('$tuple[id]', '$orgname')";
    //echo "  $sql\n";
    PgSql::query($sql);
  }
}
echo "Ok $catid<br>\n";



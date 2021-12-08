<?php
/*PhpDoc:
title: sperim.php - réflexions sur la définition du périmètre ministériel
path: /browsecat/sperim.php
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/orgarbo.inc.php';

use Symfony\Component\Yaml\Yaml;

$catid = 'agg';

  
echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>sperim</title></head><body><pre>\n";

if (!isset($_GET['action'])) {
  echo "</pre><ul>\n";
  echo "<li><a href='?action=mdContactsErrones'>Liste des mdContacts erronés</a></li>\n";
  echo "<li><a href='?action=mdContacts'>Liste des mdContacts hors PMin</a></li>\n";
  die();
}

if (!CatInPgSql::chooseServer('local')) { // Choix du serveur 
  die("Erreur: paramètre serveur incorrect !\n");
}

$arboOrgsPMin = new OrgArbo('orgpmin.yaml');

if ($_GET['action']=='mdContactsErrones') { // Liste des mdContacts hors PMin 
  echo "</pre><ul>\n";
  $sql = "select id, title, record from catalog$catid where perimetre='Min' and type in ('dataset','series')";
  foreach (PgSql::query($sql) as $tuple) {
    $record = json_decode($tuple['record'], true);
    if (!isset($record['mdContact']) || !$record['mdContact']) {
      echo "<li><a href='a.php?action=showPg&amp;cat=$catid&amp;id=$tuple[id]'>$tuple[title]</a></li>\n";
    }
  }
  die();
}


if ($_GET['action']=='mdContacts') { // Liste des mdContacts hors PMin 
  $sql = "select id, title, record from catalog$catid where perimetre='Min' and type in ('dataset','series')";
  foreach (PgSql::query($sql) as $tuple) {
    $record = json_decode($tuple['record'], true);
    if (!isset($record['mdContact']) || !$record['mdContact']) {
      echo Yaml::dump([['error'=> "mdContact non défini", 'record'=>$record]], 4, 2);
      continue;
    }
    $mdContact = $record['mdContact'][0];
    if (!$arboOrgsPMin->orgIn($mdContact)) {
      echo Yaml::dump([$mdContact], 4, 2);
    }
  }
  die();
}

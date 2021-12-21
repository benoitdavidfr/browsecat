<?php
/*PhpDoc:
title: org.php - Test de réduction du nombre de respParties en fusionnant les parties identiques du point de vue de orgPMin
name: org.php
doc: |
  Enseignement
  Je peux réduire grandement le nbre de responsibleParty en fusionnant les parties synonymes / orgPMin
  Le % de fiches ayant un seul party passe de 50% à 96% pour geoide
  et de 58% à 94% pour agg
journal:
  15/2/2021:
    - création
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/record.inc.php';
require_once __DIR__.'/orgarbo.inc.php';
require_once __DIR__.'/orginsel.inc.php';

use Symfony\Component\Yaml\Yaml;

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>themes</title></head><body>\n";

if (!isset($_GET['cat'])) { // actions globales ou choix d'un catalogue
  if (!isset($_GET['action'])) { // choix d'une action globale ou d'un catalogue
    echo "Actions globales:<ul>\n";
    echo "<li><a href='?action=xx'>xx</a></li>\n";
    echo "</ul>\n";
    echo "Choix du catalogue:<ul>\n"; // liste les catalogues pour en choisir un
    foreach (array_merge(['agg'=> 'agg'], $cats) as $catid => $cat) {
      echo "<li><a href='?cat=$catid'>$catid</a></li>\n";
    }
  }
  die();
}

elseif (!isset($_GET['action'])) { // choix d'une action sur le catalogue choisi
  echo "Actions sur le catalogue:<ul>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=nbreRParty'>distrib. des nbres de responsibleParty</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=nbreRPartyS'>distrib. des nbres de responsibleParty simplifiés</a></li>\n";
  echo "</ul>\n";
  die();
}

function respParties(array $respParties): array { // restructuration des responsibleParties
  static $arboOrgsPMin = null;
  if (!$arboOrgsPMin)
    $arboOrgsPMin = new OrgArbo('orgpmin.yaml');
  $newStructure = []; // [{orgName}=> [{role}=> 1]]
  foreach ($respParties as $party) {
    $orgName = $arboOrgsPMin->prefLabel($party) ?? $party['organisationName'] ?? 'NO_ORG_NAME';
    $newStructure[$orgName][$party['role'] ?? 'NO_ROLE'] = 1;
  }
  //echo "<pre>"; print_r($newStructure);
  return $newStructure;
}

if (!CatInPgSql::chooseServer('local')) { // Choix du serveur
  die("Erreur de choix du serveur\n");
}

if (in_array($_GET['action'], ['nbreRParty','nbreRPartyS'])) { // nbre de responsibleParty
  $nbPartiesRep = []; // répartition du nbre de parties
  $nbTotal = 0;
  $sql = "select id,title,record from catalog$_GET[cat] where type in ('dataset','series') and perimetre='Min'";
  foreach (PgSql::query($sql) as $tuple) {
    $record = Record::create($tuple['record']);
    if ($_GET['action'] == 'nbreRParty')
      $nbParties = count($record['responsibleParty'] ?? []);
    else
      $nbParties = count(respParties($record['responsibleParty'] ?? []));
    //echo "nbParties=$nbParties<br>\n";
    $nbPartiesRep[$nbParties] = 1 + ($nbPartiesRep[$nbParties] ?? 0);
    $nbTotal++;
  }
  ksort($nbPartiesRep);
  echo "<h3>nbPartiesRep</h3><pre>\n";
  foreach ($nbPartiesRep as $nbParties => $nbOccurences) {
    $action = ($_GET['action'] == 'nbreRParty') ? 'dataPernbreRParty' : 'dataPernbreRPartyS';
    printf("<a href='?cat=$_GET[cat]&amp;action=$action&amp;nbRParties=$nbParties'>%d -> %d</a> soit %.0f %%\n",
      $nbParties, $nbOccurences, $nbOccurences/$nbTotal*100);
  }
  die();
}

elseif (in_array($_GET['action'], ['dataPernbreRParty','dataPernbreRPartyS'])) {
  $sql = "select id,title,record from catalog$_GET[cat] where type in ('dataset','series') and perimetre='Min'";
  foreach (PgSql::query($sql) as $tuple) {
    $record = Record::create($tuple['record']);
    if ($_GET['action'] == 'dataPernbreRParty')
      $nbParties = count($record['responsibleParty'] ?? []);
    else
      $nbParties = count(respParties($record['responsibleParty'] ?? []));
    if ($nbParties == $_GET['nbRParties']) {
      echo "<a href='?cat=$_GET[cat]&amp;action=showPg&amp;id=$tuple[id]'>$tuple[title]</a><br>\n";
    }
  }
  die();
}

elseif ($_GET['action']=='showPg') {
  $tuples = PgSql::getTuples("select record from catalog$_GET[cat] where id='$_GET[id]'");
  if (!$tuples) {
    die("Aucun enregistrement pour id='$_GET[id]'<br>\n");
  }
  $record = json_decode($tuples[0]['record'], true);
  $record['newRespParties'] = respParties($record['responsibleParty'] ?? []);
  $yaml =  Yaml::dump($record, 3, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
  $yaml = preg_replace('!-\n +!', '- ', $yaml);
  echo "<pre>$yaml</pre>\n";
  die();
}

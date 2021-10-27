<?php
/*PhpDoc:
title: index.php - visualise et exploite le contenu d'un catalogue moissonné et stocké dans PgSql
name: index.php
doc: |
journal: |
  27/10/2021:
    - réécriture nlle version utilisant PgSql
  18/10/2021:
    - création
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';

use Symfony\Component\Yaml\Yaml;

if (!isset($_GET['cat'])) { // liste des catalogues pour en choisir un
  echo "Catalogues:<ul>\n";
  foreach ($cats as $catid => $cat) {
    echo "<li><a href='?cat=$catid'>$catid</a></li>\n";
  }
  echo "</ul>\n";
  die();
}

if (!isset($_GET['action'])) { // menu principal 
  echo "Actions proposées:<ul>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listdata'>Toutes les MD de type dataset ou series</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listservices'>Toutes les MD de type service</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=orgsHorsSel'>Liste des organisations hors sélection</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=orgs'>Liste des organisations de la sélection</a></li>\n";
  echo "</ul>\n";
  die();
}

if ($_GET['action']=='listdata') { // Toutes les MD de type dataset ou series
  echo "<ul>\n";
  foreach (PgSql::query("select id,title from catalog$_GET[cat] where type in ('dataset','series')") as $record) {
    echo "<li><a href='?cat=$_GET[cat]&amp;action=show&amp;id=$record[id]'>$record[title]</a></li>\n";
  }
  die();
}

if ($_GET['action']=='listservices') { // Toutes les MD de type service
  echo "<ul>\n";
  foreach (PgSql::query("select id,title,record from catalog$_GET[cat] where type='service'") as $record) {
    $rec = json_decode($record['record'], true);
    echo "<li><a href='?cat=$_GET[cat]&amp;action=show&amp;id=$record[id]'>$record[title]</a> (",
      $rec['serviceType'][0],")</li>\n";
  }
  die();
}

if ($_GET['action']=='show') { // affiche une fiche
  $record = PgSql::getTuples("select record from catalog$_GET[cat] where id='$_GET[id]'")[0];
  $record = json_decode($record['record'], true);
  //echo "<pre>",json_encode($record, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  echo '<pre>',Yaml::dump($record, 2, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
  die();
}

if ($_GET['action']=='orgsHorsSel') { // liste les organisations hors sélection, permet de vérifier la sélection
  if (!is_file("$_GET[cat]Sel.yaml"))
    $orgNamesSel = [];
  else
    $orgNamesSel = Yaml::parseFile("$_GET[cat]Sel.yaml")['orgNames']; // les noms des organismes sélectionnés
  $orgNames = [];
  foreach (PgSql::query("select record from catalog$_GET[cat]") as $record) {
    $record = json_decode($record['record'], true);
    //echo "<pre>"; print_r($record); die();
    foreach ($record['responsibleParty'] ?? [] as $party) {
      if (isset($party['organisationName']) && !in_array($party['organisationName'], $orgNamesSel))
        $orgNames[$party['organisationName']] = 1;
    }
  }
  ksort($orgNames);
  echo "<pre>\n";
  foreach (array_keys($orgNames) as $orgName) {
    echo "  - $orgName\n";
  }
  die();
}

if ($_GET['action']=='orgs') { // liste des organisations sélectionnés avec lien vers leurs MD
  if (!is_file("$_GET[cat]Sel.yaml"))
    die("Pas de sélection");
  $orgNames = Yaml::parseFile("$_GET[cat]Sel.yaml")['orgNames']; // les noms des organismes sélectionnés
  sort($orgNames);
  echo "<ul>\n";
  foreach ($orgNames as $orgName) {
    echo "<li><a href='?cat=$_GET[cat]&amp;action=mdOfOrg&amp;org=",urlencode($orgName),"'>$orgName</li>\n";
  }
  die();
}

if ($_GET['action']=='mdOfOrg') { // liste les MD d'une organisation
  echo "<ul>\n";
  foreach (PgSql::query("select id,title,record from catalog$_GET[cat]") as $record) {
    echo "<li><a href='?cat=$_GET[cat]&amp;action=show&amp;id=$record[id]'>$record[title]</a></li>\n";
  }
  die();
}


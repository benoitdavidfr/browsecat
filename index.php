<?php
/*PhpDoc:
title: index.php - visualise le contenu d'un catalogue moissonné
name: index.php
doc: |
journal: |
  18/10/2021:
    - création
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/cswserver.inc.php';
require_once __DIR__.'/mdvars2.inc.php';

use Symfony\Component\Yaml\Yaml;

$cats = [
  'Sextant'=> [
    'endpointURL'=> 'https://sextant.ifremer.fr/geonetwork/srv/eng/csw',
  ],
  'GeoRisques'=> [
    'endpointURL'=> 'https://catalogue.georisques.gouv.fr/geonetwork/srv/fre/csw',
  ],
  'GpU'=> [
    'endpointURL'=> 'http://www.mongeosource.fr/geosource/1270/fre/csw',
  ],
];

if (!isset($_GET['cat'])) { // liste des catalogues
  echo "Catalogues:<ul>\n";
  foreach ($cats as $catid => $cat) {
    echo "<li><a href='?cat=$catid'>$catid</a></li>\n";
  }
  echo "</ul>\n";
  die();
}

if (!isset($_GET['org']) && !isset($_GET['id']) && !isset($_GET['list'])) { // liste des organisations du catalogue + menu id
  $catid = $_GET['cat'];
  if (is_file("${catid}Sel.yaml"))
    $sel = Yaml::parseFile("${catid}Sel.yaml")['pointOfContact']; // les organismes sélectionnés
  else
    $sel = null;

  $catid = $_GET['cat'];
  $idByOrgs = json_decode(file_get_contents("$catid/idbyorgs.json"), true);
  
  echo "<form>\n";
  echo "<input type=hidden name='cat' value='$catid'>\n";
  echo "id: <input type=text size='40' name='id'>\n";
  echo "<input type=submit value='go'>\n";
  echo "</form>\n";
  
  echo "<a href='?cat=$catid&amp;list=all'>Toutes les MD</a><br><br>\n";
  
  echo "Liste des organisations du catalogue $catid:<ul>\n";
  foreach ($idByOrgs as $org => $ids) {
    if (!$sel || in_array($org, $sel)) {
      $nb = count($ids);
      echo "<li><a href='?cat=$catid&amp;org=",urlencode($org),"'>$org ($nb)</a></li>\n";
    }
  }
  echo "</ul>\n";
  die();
}

if (isset($_GET['org'])) { // liste des JD pour l'organisation fournie
  $catid = $_GET['cat'];
  $cswServer = new CswServer($cats[$catid]['endpointURL'], $catid);
  $org = $_GET['org'];
  $idByOrgs = json_decode(file_get_contents("$catid/idbyorgs.json"), true);
  if (!isset($idByOrgs[$org]))
    die("Organisme absent<br>\n");
  foreach (array_keys($idByOrgs[$org]) as $mdid) {
    echo "<b>id: $mdid</b><br>\n";
    $isoRecord = $cswServer->getRecordById($mdid);
  
    $mdrecord = Mdvars::extract($mdid, $isoRecord);
    echo "<pre>",Yaml::dump($mdrecord),"</pre>";
  
    //echo "<pre>",str_replace('<','&lt;', $isoRecord),"</pre>\n";
    die();
  }
}

if (isset($_GET['list'])) { // affiche toutes les MD du catalogue de type dataset ou series par leur titre avec lien vers complet
  $catid = $_GET['cat'];
  $cswServer = new CswServer($cats[$catid]['endpointURL'], $catid);
  $nextRecord = 1;
  while ($nextRecord) {
    echo "nextRecord=$nextRecord<br>\n";
    try {
      $getRecords = $cswServer->getRecords($nextRecord);
    }
    catch (Exception $e) {
      die($e->getMessage());
    }
    foreach ($getRecords->csw_SearchResults->csw_BriefRecord as $briefRecord) {
      $dc_type = (string)$briefRecord->dc_type;
      //echo "$dc_type<br>\n";
      if (!in_array($dc_type, ['dataset','series']))
        continue;
      echo "<a href='?cat=$catid&amp;id=",urlencode($briefRecord->dc_identifier),"'>",$briefRecord->dc_title,"</a><br>\n";
      //print_r($briefRecord);
    }
    $nextRecord = isset($getRecords->csw_SearchResults['nextRecord']) ? (int)$getRecords->csw_SearchResults['nextRecord'] : null;
  }
}

if (isset($_GET['id'])) { // affiche le JD sur id
  $catid = $_GET['cat'];
  $mdid = $_GET['id'];
  $cswServer = new CswServer($cats[$catid]['endpointURL'], $catid);
  $isoRecord = $cswServer->getRecordById($mdid);
  if (!isset($_GET['fmt'])) { // affichage en Yaml
    $mdrecord = Mdvars::extract($mdid, $isoRecord);
    echo "<pre>",Yaml::dump($mdrecord),"</pre>\n";
    echo "<a href='?cat=$catid&amp;id=",urlencode($mdid),"&amp;fmt=xml'>Affichage en XML</a><br>\n";
  }
  else {
    header('Content-type: text/xml');
    echo $isoRecord;
  }
  die();
}

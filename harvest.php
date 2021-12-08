<?php
/*PhpDoc:
title: harvest.php - moissonne un catalogue CSW et stocke la moisson dans un des serveurs PgSql prédéfinis
name: harvest.php
doc: |
  Propose de choisir un des catalogues proposés et le serveur local ou distant.
  Moissonne le catalogue choisi.
  Crée un répertoire ayant pour nom l'id du catalogue dans le répertoire catalogs pour bufferiser les métadonnées.
  Enregistre la MD ISO pour les data et service et sinon la MD DublinCore full
  N'enregistre rien pour les FeatureCatalog.
  Stocke les MD de données et de service converties en JSON dans une base PgSql sur le serveur choisi
journal: |
  5/12/2021:
    - ajout parentIdentifier dans la table
    - ajout d'une info sur le catalogue source et la date de moissonnage
  16-17/11/2021:
    - gestion à minima du catalogue Corse dont les MD sont en DublinCore
  13/11/2021:
    - modification des paramètres pour permettre de choisir le serveur et de la gestion de ceux-ci
    - ajout des fichiers start et end 
  8/11/2021:
    - déplacement des caches des catalogues dans le répertoire catalogs
  28-29/10/2021:
    - améliorations du moissonnage de Géo-IDE
  27/10/2021:
    - stockage en PostgreSql
  18/10/2021:
    - améliorations
  11/10/2021:
    - création
includes:
  - cswserver.inc.php
  - mdvars2.inc.php
  - dublincore.inc.php
  - cats.inc.php
  - catinpgsql.inc.php
  - orginsel.inc.php
  - trestant.inc.php
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/cswserver.inc.php';
require_once __DIR__.'/mdvars2.inc.php';
require_once __DIR__.'/dublincore.inc.php';
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/orginsel.inc.php';
require_once __DIR__.'/trestant.inc.php';

use Symfony\Component\Yaml\Yaml;

if (php_sapi_name()<>'cli') { // Erreur: Uniquement en CLI
  die("Erreur: Uniquement en CLI\n");
}
else { // gestion du dialogue pour définir les paramètres 
  function usage(array $argv, array $cats) {
    echo "usage: php $argv[0] {serveur} {cat}|all [{firstRecord} [{maxRecordNum}]]\n";
    echo " où {cat} vaut:\n";
    foreach ($cats as $catid => $cat)
      echo " - $catid\n";
    echo " et où:\n";
    echo " - {serveur} vaut local pour la base locale et distant pour la base distante\n";
    echo " - {firstRecord} est le num. du premier enregistrement moissonné, par défaut 1\n";
    echo " - {maxRecordNum} est le nombre maximum d'enregistrements moissonnés\n";
    die();
  }

  if (!($catid = $argv[2] ?? null)) usage($argv, $cats);

  if ($catid == 'all') { // génère les cmdes pour remoissonner tous les catalogues CSW sauf le géocatalogue
    foreach ($cats as $catid => $cat) {
      if (($cat['conformsTo'] == 'http://www.opengis.net/def/serviceType/ogc/csw') && ($catid <> 'geocatalogue'))
        echo "php $argv[0] $argv[1] $catid\n";
    }
    die();
  }

  if (!CatInPgSql::chooseServer($server = $argv[1] ?? null)) { // Choix du serveur 
    echo "Erreur: paramètre serveur incorrect !\n";
    usage($argv, $cats);
  }

  $firstRecord = $argv[3] ?? 1;

  $maxRecordNum = $argv[4] ?? -1;
}

tempsRestant(); // initialisation 

if (!file_exists("catalogs/$catid"))
  mkdir("catalogs/$catid");

$cswServer = new CswServer($cats[$catid]['endpointURL'], "catalogs/$catid");
$cswServer->getCapabilities();

echo "Moissonnage de $catid et chargement dans PostgreSql $server\n";
$cat = new CatInPgSql($catid);
if ($firstRecord == 1) {
  $cat->create(); // recrée une nlle table
}
$nextRecord = $firstRecord;
$numberOfRecordsMatched = null;
while ($nextRecord) {
  if (($maxRecordNum <> -1) && ($nextRecord >= $maxRecordNum))
    die("Arrêt sur nextRecord=$nextRecord >= maxRecordNum=$maxRecordNum\n");
  if (!$numberOfRecordsMatched)
    fprintf(STDERR, "$catid> nextRecord=%d\n", $nextRecord);
  else
    fprintf(STDERR, "$catid> nextRecord=%d/numberOfRecordsMatched=%d, %s restant\n",
      $nextRecord, $numberOfRecordsMatched, tempsRestant(($nextRecord-1)/$numberOfRecordsMatched));
  try {
    $getRecords = $cswServer->getRecords($nextRecord);
  }
  catch (Exception $e) {
    die($e->getMessage()."\n");
  }
  if (!$getRecords->searchResults()->csw_BriefRecord) { // erreurs dans SigLoire le 28/10/2021
    //print_r($getRecords);
    echo "** Erreur de getRecords(), aucun enregistrement retourné, ligne ",__LINE__,"<br>\n";
    //$nextRecord = $getRecords->nextRecord();
    //$numberOfRecordsMatched = $getRecords->numberOfRecordsMatched();
    echo "nextRecord=$nextRecord, numberOfRecordsMatched=$numberOfRecordsMatched\n";
    if (($nextRecord == 0) || ($nextRecord >= $numberOfRecordsMatched)) break;
    $nextRecord += 20;
    continue;
  }
  foreach ($getRecords->searchResults()->csw_BriefRecord as $briefRecord) {
    $dc_type = (string)$briefRecord->dc_type;
    //echo "$dc_type\n";
    //print_r($briefRecord);
    $mdid = (string)$briefRecord->dc_identifier;
    if ($dc_type == 'FeatureCatalogue') { // on ne récupère pas les FeatureCatalogue
      continue;
    }
    elseif (in_array($dc_type, ['dataset','series','service'])) { // dans le cas data ou service, j'utilise ISO19139
      try {
        $isoRecord = $cswServer->getRecordById($mdid);
        $harvestTime = filemtime($cswServer->getRecordByIdPath($mdid));
      }
      catch (Exception $e) {
        echo "Erreur: dans CswServer::getRecordById($mdid)\n";
        continue;
      }
      try {
        $mdrecord = Iso19139::extract($mdid, $isoRecord);
      }
      catch (Exception $e) {
        echo "Erreur: dans Iso19139::extract pour $mdid\n";
        continue;
      }
      if (!$mdrecord) {
        echo "Erreur: enregistrement ISO non défini pour $mdid\n";
        print_r($briefRecord);
        $cswServer->delRecordById($mdid);
        $isoRecord = $cswServer->getRecordById($mdid);
        $mdrecord = Iso19139::extract($mdid, $isoRecord);
        if (!$mdrecord) {
          echo "2ème erreur: enregistrement ISO non défini pour $mdid\n";
          continue;
        }
      }
    }
    else { // Sinon, récupération de l'enregistrement en DublinCore
      $dcRecord = $cswServer->getRecordById($mdid, 'dc', 'full');
      $harvestTime = filemtime($cswServer->getRecordByIdPath($mdid, 'dc', 'full'));
      //print_r($dcRecord);
      try {
        $mdrecord = DublinCore::extract($mdid, $dcRecord);
      }
      catch (Exception $e) {
        echo "Erreur: dans DublinCore::extract pour $mdid\n";
        continue;
      }
      if ($ows_Exception = ($mdrecord['ows_Exception'] ?? null)) {
        echo "Exception $ows_Exception[exceptionCode] sur $mdid : $ows_Exception[ows_ExceptionText]\n";
        $mdrecord = DublinCore::cswRecord2Array($briefRecord);
      }
      if (!$mdrecord) {
        echo "Erreur: enregistrement DublinCore non défini pour $mdid\n";
        echo "briefRecord="; print_r($briefRecord);
        echo "dcRecord="; print_r($dcRecord);
        $cswServer->delRecordById($mdid);
        $isoRecord = $cswServer->getRecordById($mdid, 'dc', 'full');
        $harvestTime = filemtime($cswServer->getRecordByIdPath($mdid, 'dc', 'full'));
        $mdrecord = DublinCore::extract($mdid, $isoRecord);
        if (!$mdrecord) {
          echo "2ème erreur: enregistrement ISO non défini pour $mdid\n";
          continue;
        }
      }
    }
    // ajout d'une info sur le catalogue source et la date de moissonnage
    $mdrecord['harvest'] = [
      'srceCat' => [$catid => $cats[$catid]],
      'harvestTime' => date(DATE_ATOM, $harvestTime),
    ];
    //echo Yaml::dump($mdrecord); die();
    $cat->storeRecord($mdrecord);
  }
  $nextRecord = $getRecords->nextRecord();
  $numberOfRecordsMatched = $getRecords->numberOfRecordsMatched();
  echo "nextRecord=$nextRecord, numberOfRecordsMatched=$numberOfRecordsMatched\n";
  if ($nextRecord >= $numberOfRecordsMatched) break;
}

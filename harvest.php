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
  13/11/2021:
    - modification des paramètres pour permettre de choisir le serveur et de la gestion de ceux-ci
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
  - cats.inc.php
  - catinpgsql.inc.php
  - orginsel.inc.php
*/
require_once __DIR__.'/cswserver.inc.php';
require_once __DIR__.'/mdvars2.inc.php';
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/orginsel.inc.php';


if (php_sapi_name()<>'cli')
  die("Uniquement en CLI\n");

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

if ($catid == 'all') { // génère les cmdes pour remoissonner tous les catalogues
  foreach (array_keys($cats) as $catid) {
    echo "php $argv[0] $catid\n";
  }
  die();
}

if (!CatInPgSql::chooseServer($argv[1] ?? null)) { // Choix du serveur 
  echo "Erreur: paramètre serveur incorrect !\n";
  usage($argv, $cats);
}

$firstRecord = $argv[3] ?? 1;

$maxRecordNum = $argv[4] ?? -1;

if (!file_exists("catalogs/$catid"))
  mkdir("catalogs/$catid");

$cswServer = new CswServer($cats[$catid]['endpointURL'], "catalogs/$catid");
$cswServer->getCapabilities();

echo "Moissonnage de $catid et chargement dans PostgreSql $argv[1]\n";
$cat = new CatInPgSql($catid);
if ($firstRecord == 1)
  $cat->create(); // recrée une nlle table
$nextRecord = $firstRecord;
$numberOfRecordsMatched = null;
while ($nextRecord) {
  if (($maxRecordNum <> -1) && ($nextRecord >= $maxRecordNum))
    die("Arrêt sur nextRecord=$nextRecord >= maxRecordNum=$maxRecordNum\n");
  if (!$numberOfRecordsMatched)
    fprintf(STDERR, "$catid> nextRecord=%d\n", $nextRecord);
  else
    fprintf(STDERR, "$catid> nextRecord=%d/numberOfRecordsMatched=%d\n", $nextRecord, $numberOfRecordsMatched);
  try {
    $getRecords = $cswServer->getRecords($nextRecord);
  }
  catch (Exception $e) {
    die($e->getMessage()."\n");
  }
  if (!$getRecords->csw_SearchResults->csw_BriefRecord) { // erreurs dans SigLoire le 28/10/2021
    print_r($getRecords);
    echo "** Erreur de getRecords() ligne ",__LINE__,"<br>\n";
    $nextRecord += 20;
    continue;
  }
  foreach ($getRecords->csw_SearchResults->csw_BriefRecord as $briefRecord) {
    $dc_type = (string)$briefRecord->dc_type;
    //echo "$dc_type\n";
    //print_r($briefRecord);
    $mdid = (string)$briefRecord->dc_identifier;
    if ($dc_type == 'FeatureCatalogue') { // on ne récupère pas les FeatureCatalogue
      continue;
    }
    elseif (!in_array($dc_type, ['dataset','series','service'])) {
      // récupération de l'enregistrement en DublinCore
      $dcRecord = $cswServer->getRecordById($mdid, 'dc', 'full');
    }
    else { // dans le cas data ou service, j'utilise ISO19139
      try {
        $isoRecord = $cswServer->getRecordById($mdid);
      }
      catch (Exception $e) {
        echo "Erreur: dans CswServer::getRecordById($mdid)\n";
        continue;
      }
      try {
        $mdrecord = Mdvars::extract($mdid, $isoRecord);
      }
      catch (Exception $e) {
        echo "Erreur: dans Mdvars::extract pour $mdid\n";
        continue;
      }
      if (!$mdrecord) {
        echo "Erreur: enregistrement ISO non défini pour $mdid\n";
        print_r($briefRecord);
        $cswServer->delRecordById($mdid);
        $isoRecord = $cswServer->getRecordById($mdid);
        $mdrecord = Mdvars::extract($mdid, $isoRecord);
        if (!$mdrecord) {
          echo "2ème erreur: enregistrement ISO non défini pour $mdid\n";
          continue;
        }
      }
      $cat->storeRecord($mdrecord, orgInSel($catid, $mdrecord) ? 'Min' : null);
    }
  }
  $nextRecord = isset($getRecords->csw_SearchResults['nextRecord']) ?
    (int)$getRecords->csw_SearchResults['nextRecord']
    : null;
  $numberOfRecordsMatched = isset($getRecords->csw_SearchResults['numberOfRecordsMatched']) ?
    (int)$getRecords->csw_SearchResults['numberOfRecordsMatched']
    : null;
}

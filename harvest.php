<?php
/*PhpDoc:
title: harvest.php - moissonne un catalogue CSW et stocke 
name: harvest.php
doc: |
  Propose de choisir un des catalogues proposés.
  Moissonne le catalogue choisi.
  Crée un répertoire ayant pour nom l'id du catalogue pour bufferiser les métadonnées.
  Enregistre la MD ISO pour les data et service et sinon la MD DublinCore full
  N'enregistre rien pour les FeatureCatalog.
  Stocke les MD de données et de service converties en JSON dans une base PgSql
journal: |
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

if ($argc <= 1) {
  echo "usage: php $argv[0] {cat}|all [{firstRecord}]\n";
  echo " où {cat} vaut:\n";
  foreach ($cats as $catid => $cat)
    echo " - $catid\n";
  echo " et où {firstRecord} est le num. du premier enregistrement requêté, par défaut 1\n";
  die();
}

if ($argv[1] == 'all') { // génère les cmdes pour remoissonner tous les catalogues
  foreach (array_keys($cats) as $catid) {
    echo "php $argv[0] $catid\n";
  }
  die();
}

$catid = $argv[1];
if (!file_exists($catid))
  mkdir($catid);
$mdType = $argv[2] ?? null;

$cswServer = new CswServer($cats[$catid]['endpointURL'], $catid);
$cswServer->getCapabilities();

echo "Moissonnage de $catid et chargement dans PostgreSql\n";
$cat = new CatInPgSql($catid);
$cat->create(); // recrée une nlle table
$nextRecord = $argv[2] ?? 1;
$numberOfRecordsMatched = null;
while ($nextRecord) {
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
      $isoRecord = $cswServer->getRecordById($mdid);
      $mdrecord = Mdvars::extract($mdid, $isoRecord);
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

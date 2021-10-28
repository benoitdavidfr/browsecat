<?php
/*PhpDoc:
title: harvest.php - moissonne un catalogue CSW
name: harvest.php
doc: |
  Propose de choisir un des catalogues proposés.
  Moissonne le catalogue choisi.
  Crée un répertoire ayant pour nom l'id du catalogue pour bufferiser les métadonnées.
  Enregistre la MD ISO pour les data et service et sinon la MD DublinCore full
  N'enregistre rien pour les FeatureCatalog
journal: |
  27/10/2021:
    - stockage en PostgreSql
  18/10/2021:
    - améliorations
  11/10/2021:
    - création
*/
require_once __DIR__.'/cswserver.inc.php';
require_once __DIR__.'/mdvars2.inc.php';
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';

if ($argc <= 1) {
  echo "usage: php index.php {cat}\n";
  echo " où {cat} vaut:\n";
  foreach ($cats as $catid => $cat)
    echo " - $catid\n";
  exit;
}

$catid = $argv[1];
if (!file_exists($catid))
  mkdir($catid);
$mdType = $argv[2] ?? null;

$cswServer = new CswServer($cats[$catid]['endpointURL'], $catid);
$cswServer->getCapabilities();

/*if ($argc == 2) { // liste les organisations et génère un fichier index par organisation
  echo "Liste des organisations de $catid\n";
  $idByOrgs = []; // Liste des record ids par organisation [$orgname => [ recid => 1]]
  $nextRecord = 1;
  $numberOfRecordsMatched = null;
  while ($nextRecord) {
    if (!$numberOfRecordsMatched)
      fprintf(STDERR, "nextRecord=%d\n", $nextRecord);
    else
      fprintf(STDERR, "nextRecord=%d/numberOfRecordsMatched=%d\n", $nextRecord, $numberOfRecordsMatched);
    try {
      $getRecords = $cswServer->getRecords($nextRecord);
    }
    catch (Exception $e) {
      die($e->getMessage()."\n");
    }
    foreach ($getRecords->csw_SearchResults->csw_BriefRecord as $briefRecord) {
      $dc_type = (string)$briefRecord->dc_type;
      //echo "$dc_type\n";
      //print_r($briefRecord);
      $mdid = (string)$briefRecord->dc_identifier;
      if ($dc_type == 'FeatureCatalogue') {
        // on ne récupère pas les FeatureCatalogue
        continue;
      }
      elseif (!in_array($dc_type, ['dataset','series','service'])) {
        // récupération de l'enregistrement en DublinCore
        $dcRecord = $cswServer->getRecordById($mdid, 'dc', 'full');
      }
      else { // dans le cas data ou service, j'utilise ISO19139
        $isoRecord = $cswServer->getRecordById($mdid);
        $mdrecord = Mdvars::extract($mdid, $isoRecord);
      
        if (!isset($mdrecord['responsibleParty'])) {
          fprintf(STDERR, "Erreur: responsibleParty non défini pour %s\n", $mdid);
          continue;
        }
        foreach ($mdrecord['responsibleParty'] as $responsibleParty) {
          $orgName = $responsibleParty['organisationName'] ?? null;
          $role = $responsibleParty['role'] ?? null;
          if ($orgName && !in_array($role, ['user'])) {
            if (!isset($idByOrgs[$orgName])) {
              echo "responsibleParty: $orgName\n";
            }
            $idByOrgs[$orgName][$mdid] = 1;
          }
        }
      }
    }
    $nextRecord = isset($getRecords->csw_SearchResults['nextRecord']) ?
      (int)$getRecords->csw_SearchResults['nextRecord']
      : null;
    $numberOfRecordsMatched = isset($getRecords->csw_SearchResults['numberOfRecordsMatched']) ?
      (int)$getRecords->csw_SearchResults['numberOfRecordsMatched']
      : null;
  }

  ksort($idByOrgs);
  file_put_contents("$catid/idbyorgs.json",
    json_encode($idByOrgs, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}*/

if ($argc == 2) { // enregistre les fiches dans PostgreSql
  echo "Moissonnage de $catid et chargement dans PostgreSql\n";
  $cat = new CatInPgSql($catid);
  $cat->create(); // recrée une nlle table
  $nextRecord = 1;
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
          continue;
        }
        $cat->storeRecord($mdrecord);
      }
    }
    $nextRecord = isset($getRecords->csw_SearchResults['nextRecord']) ?
      (int)$getRecords->csw_SearchResults['nextRecord']
      : null;
    $numberOfRecordsMatched = isset($getRecords->csw_SearchResults['numberOfRecordsMatched']) ?
      (int)$getRecords->csw_SearchResults['numberOfRecordsMatched']
      : null;
  }
}

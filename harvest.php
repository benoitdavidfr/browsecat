<?php
/*PhpDoc:
title: harvest.php - moissonne un catalogue CSW
name: harvest.php
doc: |
  Crée un répertoire dont le nom est l'id
  Produit en sortie la liste des points de contact et sur STDERR des commentaires
journal: |
  18/10/2021:
    - améliorations
  11/10/2021:
    - création
*/
require_once __DIR__.'/cswserver.inc.php';
require_once __DIR__.'/mdvars2.inc.php';

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

if ($argc == 2) {
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
      die($e->getMessage());
    }
    foreach ($getRecords->csw_SearchResults->csw_BriefRecord as $briefRecord) {
      $dc_type = (string)$briefRecord->dc_type;
      //echo "$dc_type\n";
      //print_r($briefRecord);
      $mdid = (string)$briefRecord->dc_identifier;
      if (!in_array($dc_type, ['dataset','series','service'])) {
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
              $idByOrgs[$orgName][$mdid] = 1;
            }
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
  file_put_contents("$catid/idbyorgs.json", json_encode($idByOrgs, JSON_PRETTY_PRINT));
}

<?php
/*PhpDoc:
title: harvest.php - moissonne un catalogue CSW
name: harvest.php
doc: |
  Crée un répertoire dont le nom est l'id
  Produit en sortie la liste des points de contact et sur STDERR des commentaires
journal: |
  11/10/2021:
    - création
*/
require_once __DIR__.'/cswserver.inc.php';
require_once __DIR__.'/mdvars2.inc.php';

$cats = [
  'Sextant'=> [
    'endpointURL'=> 'https://sextant.ifremer.fr/geonetwork/srv/eng/csw',
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

$cswServer = new CswServer($cats[$catid]['endpointURL'], $catid);

if ($argc == 2) {
  echo "Liste des organisations de $catid\n";
  $idByOrgs = []; // Liste des record ids par organisation [$orgname => [ recid => 1]]
  $nextRecord = 1;
  while ($nextRecord) {
    fprintf(STDERR, "nextRecord=%d\n", $nextRecord);
    try {
      $getRecords = $cswServer->getRecords($nextRecord);
    }
    catch (Exception $e) {
      die($e->getMessage());
    }
    foreach ($getRecords->csw_SearchResults->csw_BriefRecord as $briefRecord) {
      $dc_type = (string)$briefRecord->dc_type;
      //echo "$dc_type\n";
      if (!in_array($dc_type, ['dataset','series']))
        continue;
      //print_r($briefRecord);
      //$record = $cswServer->getRecordById((string)$briefRecord->dc_identifier, 'dcat');
      /*$record = $cswServer->getRecordById((string)$briefRecord->dc_identifier);
      $record = preg_replace('!<(/)?([^:]+):!', '<$1$2_', $record);
      //echo $record;
      $xmlelt = new SimpleXmlElement($record);
      //print_r($xmlelt);
      //print_r($xmlelt->gmd_MD_Metadata->gmd_identificationInfo->gmd_MD_DataIdentification->gmd_pointOfContact);
      if (!isset($xmlelt->gmd_MD_Metadata->gmd_identificationInfo)) {
        fprintf(STDERR, "gmd_identificationInfo non défini pour %s\n", (string)$briefRecord->dc_identifier);
        continue;
      }
      foreach ($xmlelt->gmd_MD_Metadata->gmd_identificationInfo->gmd_MD_DataIdentification->gmd_pointOfContact as $pointOfContact) {
        if (isset($pointOfContact->gmd_CI_ResponsibleParty)) {
          $organisationName = (string)$pointOfContact->gmd_CI_ResponsibleParty->gmd_organisationName->gco_CharacterString;
          if (!isset($idByOrgs[$organisationName]))
            echo "pointOfContact: $organisationName\n";
          $idByOrgs[$organisationName][(string)$briefRecord->dc_identifier] = 1;
        }
        else {
          fprintf(STDERR, "Point de contact non gmd_CI_ResponsibleParty\n");
          //print_r($pointOfContact);
        }
      }*/
      $mdid = (string)$briefRecord->dc_identifier;
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
    $nextRecord = isset($getRecords->csw_SearchResults['nextRecord']) ? (int)$getRecords->csw_SearchResults['nextRecord'] : null;
  }
}

ksort($idByOrgs);
file_put_contents("$catid/idbyorgs.json", json_encode($idByOrgs, JSON_PRETTY_PRINT));

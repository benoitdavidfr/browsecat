<?php
/*PhpDoc:
title: index.php - moissonne un catalogue CSW
name: index.php
doc: |
journal: |
  11/10/2021:
    - création
*/
require_once __DIR__.'/cswserver.inc.php';

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
    echo "nextRecord=$nextRecord\n";
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
      $record = $cswServer->getRecordById((string)$briefRecord->dc_identifier);
      $record = preg_replace('!<(/)?([^:]+):!', '<$1$2_', $record);
      //echo $record;
      $xmlelt = new SimpleXmlElement($record);
      //print_r($xmlelt);
      //print_r($xmlelt->gmd_MD_Metadata->gmd_identificationInfo->gmd_MD_DataIdentification->gmd_pointOfContact);
      if (!isset($xmlelt->gmd_MD_Metadata->gmd_identificationInfo)) {
        echo "gmd_identificationInfo non défini pour ",(string)$briefRecord->dc_identifier,"\n";
        continue;
      }
      foreach ($xmlelt->gmd_MD_Metadata->gmd_identificationInfo->gmd_MD_DataIdentification->gmd_pointOfContact as $pointOfContact) {
        if (isset($pointOfContact->gmd_CI_ResponsibleParty)) {
          $organisationName = (string)$pointOfContact->gmd_CI_ResponsibleParty->gmd_organisationName->gco_CharacterString;
          if (!isset($idByOrgs[$organisationName]))
            echo "  $organisationName\n";
          $idByOrgs[$organisationName][(string)$briefRecord->dc_identifier] = 1;
        }
        else {
          echo "Point de contact non gmd_CI_ResponsibleParty\n";
          print_r($pointOfContact);
        }
      }
    }
    //die("Fin temporaire\n");
    $nextRecord = isset($getRecords->csw_SearchResults['nextRecord']) ? (int)$getRecords->csw_SearchResults['nextRecord'] : null;
  }
}

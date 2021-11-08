<?php
/*PhpDoc:
title: orginsel.inc.php - teste si un des responsibleParty de la fiche appartient à la sélection d'organismes
name: catinpgsql.inc.php
doc: |
journal: |
  29/10/2021:
    - création
*/
require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

function orgInSel(string $catid, array $record, string $partyType='responsibleParty'): bool {
  static $orgNamesSels = []; // liste des organismes sélectionnés par catalogue
  if (!isset($orgNamesSels[$catid])) {
    if (!is_file("${catid}Sel.yaml"))
      $orgNamesSels[$catid] = [];
    else
      $orgNamesSels[$catid] = Yaml::parseFile("${catid}Sel.yaml")['orgNames']; // les noms des organismes sélectionnés
  }
  foreach ($record[$partyType] ?? [] as $party) {
    if (isset($party['organisationName']) && in_array($party['organisationName'], $orgNamesSels[$catid])) {
      return true; // si au moins une organisation est dans la sélection alors vrai
    }
  }
  return false; // sinon faux
}

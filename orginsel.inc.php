<?php
/*PhpDoc:
title: orginsel.inc.php - teste si un des responsibleParty de la fiche appartient à la sélection d'organismes
name: orginsel.inc.php
doc: |
journal: |
  25/11/2021:
    - évolution du principe en utilisant orgpmin quand le fichier est absent
  29/10/2021:
    - création
*/
require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

function orgInSel(string $catid, array|Record $record, string $partyType='responsibleParty'): bool {
  static $orgNamesSels = []; // contient par catalogue soit la liste des org. sélectionnés, soit la valeur 'OrgArbo'
  static $arboOrgsPMin = null; // arborescence des organisations du périmètre ministériel

  if (!isset($orgNamesSels[$catid])) { // initialisation de $orgNamesSels et $arboOrgsPMin
    if (!is_file("catalogs/${catid}Sel.yaml")) {
      $orgNamesSels[$catid] = 'OrgArbo'; // utilisation de $arboOrgsPMin pour ce catalogue
      if (!$arboOrgsPMin)
        $arboOrgsPMin = new OrgArbo('orgpmin.yaml');
    }
    else
      $orgNamesSels[$catid] = Yaml::parseFile("catalogs/${catid}Sel.yaml")['orgNames']; // les noms des orgs. sélectionnés
  }
  
  foreach ($record[$partyType] ?? [] as $party) {
    if ($orgNamesSels[$catid] == 'OrgArbo') { // cas d'utilisation de l'arbo
      if ($arboOrgsPMin->orgIn($party))
        return true; // si au moins une organisation est dans la l'arbo alors vrai
    }
    else { // cas d'utilisation du fichier {cat}Sel.yaml
      if (isset($party['organisationName']) && in_array($party['organisationName'], $orgNamesSels[$catid])) {
        return true; // si au moins une organisation est dans la sélection alors vrai
      }
    }
  }
  return false; // sinon faux
}

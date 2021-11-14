<?php
/*PhpDoc:
title: orgarbo.inc.php - référentiel des organisations fondé sur une arborescence
name: orgarbo.inc.php
doc: |
  Une org est un array constitué normalement au moins des champs
    - organisationName
    - electronicMailAddress
  Gère les organisations mal définies listées dans l'entrée IMPRECIS de l'arbo des organisations.
  Pour ces organisations, on complète le nom par l'email.
journal: |
  14/11/2021:
    - création
*/
require_once __DIR__.'/arbo.inc.php';

use Symfony\Component\Yaml\Yaml;

class OrgArbo {
  private Arbo $arbo;
  
  function __construct(string $filename) { $this->arbo = new Arbo($filename); }
  
  function orgIn(array $org): array { // l'org est-il défini ? Si oui renvoie son path, sinon []
    if (!isset($org['organisationName']))
      return [];
    $path = $this->arbo->labelIn($org['organisationName']);
    if ($path <> ['IMPRECIS'])
      return $path;
    if (!isset($org['electronicMailAddress']))
      return [];
    else
      return $this->arbo->labelIn("$org[organisationName] | $org[electronicMailAddress]");
  }
  
  function prefLabel(array $org, string $lang='fr'): ?string { // retrouve le prefLabel à partir de l'org
    if ($path = $this->orgIn($org))
      return $this->arbo->node($path)->prefLabel($lang);
    else
      return null;
  }
  
  function short(array $org): ?string { // retrouve le nom court à partir d'un org
    if ($path = $this->orgIn($org))
      return $this->arbo->node($path)->short();
    else
      return null;
  }
  
  function recordIn(array $record): bool { // Un au moins des responsibleParty est une org du référentiel
    foreach ($record['responsibleParty'] ?? [] as $org) {
      if ($this->orgIn($org))
        return true;
    }
    return false;
  }

  function nodes(): array { return $this->arbo->nodes(); }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return;


$arboOrgsPMin = new OrgArbo('orgpmin.yaml');

if (0) {
  $orgs = <<<EOT
- { organisationName: 'DDT 12 (Direction Départementale des Territoires de l''Aveyron)', role: owner, electronicMailAddress: ddt-mact@aveyron.gouv.fr }
- { organisationName: ADL, role: pointOfContact, electronicMailAddress: nicolas.kusmierek@pas-de-calais.gouv.fr }
- { organisationName: ADL }
- { }
EOT;

  echo "<pre>";
  $orgs = Yaml::parse($orgs);
  // print_r($orgs);
  foreach ($orgs as $org) {
    echo Yaml::dump([[
        'org'=> $org,
        'orgIn'=> $arboOrgsPMin->orgIn($org),
        'prefLabel'=> $arboOrgsPMin->prefLabel($org),
        'short'=> $arboOrgsPMin->short($org),
      ]],
      3);
  }
}
else {
  $records = <<<EOT
- 'dct:title':
    - 'Gestion du domaine public maritime naturel (DPM)'
  responsibleParty:
    - { organisationName: 'DDTM 62 (Direction départementale des territoires et de la mer du Pas-de-Calais)', role: owner, electronicMailAddress: ddtm-mcsig@pas-de-calais.gouv.fr }
    - { organisationName: 'DDTM 62 (Direction départementale des territoires et de la mer du Pas-de-Calais)', role: owner, electronicMailAddress: ddtm-mcsig@pas-de-calais.gouv.fr }
    - { organisationName: ADL, role: pointOfContact, electronicMailAddress: nicolas.kusmierek@pas-de-calais.gouv.fr }
- 'dct:title':
    - 'Gestion du domaine public maritime naturel (DPM)'
  responsibleParty:
    - { organisationName: ADL, role: pointOfContact, electronicMailAddress: nicolas.kusmierek@pas-de-calais.gouv.fr }
- fileIdentifier:
    - fr-120066022-jdd-b4a4bc61-dd68-400b-862f-159729108a4a
  'dct:title':
    - 'Cartes de sensibilité – Cartes Régions naturelles - Milvus_milvus (Milan Royal)'
  responsibleParty:
    - { organisationName: 'ODONAT Grand Est', role: principalInvestigator, electronicMailAddress: contact@odonat-grandest.fr }
EOT;
  echo "<pre>";
  foreach (Yaml::parse($records) as $record) {
    echo Yaml::dump([[
        'record'=> $record,
        'recordIn'=> $arboOrgsPMin->recordIn($record),
      ]],
      5);
  }
}

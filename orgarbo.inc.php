<?php
/*PhpDoc:
title: orgarbo.inc.php - exploitation d'un référentiel des organisations fondé sur un thésaurus Skos
name: orgarbo.inc.php
doc: |
  Une org est un array constitué normalement au moins des champs
    - organisationName
    - electronicMailAddress
  Gère les organisations mal définies listées dans l'entrée IMPRECIS du thésaurus Skos
  Pour ces organisations, on complète leur nom par leur email.
journal: |
  19/12/2021:
    - tranfert sur skos à la place de Arbo
  14/11/2021:
    - création
*/
require_once __DIR__.'/skos.inc.php';

use Symfony\Component\Yaml\Yaml;

// Un OrgConcept est un concept Skos auquel on ajoute le champ short
class OrgConcept extends Concept {
  protected ?string $short=null; // clé courte pour affichage synthétique
  
  function __construct(array $array, array $path, array $extra) { // init. récursivement un arbre à partir d'un array
    //echo "Theme::__construct(array, path='/",implode('/',$path),"')\n";
    $this->short = $array['short'] ?? null;;
    
    // remplit les champs $path et $children + appel récursif
    parent::__construct($array, $path, $extra);
  }
  
  function short(): string { return $this->short ? $this->short : $this->path[count($this->path)-1]; }
};

// Un OrgArbo référence un thésaurus sous-jacent et propose de nouvelles méthodes fondées sur la structure org
// Certaines de ces méthodes ayant même nom et pas même signature que celle de la classe Scheme
// OrgArbo ne peut PAS hériter de Scheme
class OrgArbo {
  private Scheme $scheme; // le thésaurus sous-jacent
  
  function __construct(string $filename) { $this->scheme = new Scheme(Yaml::parseFile($filename), 'OrgConcept'); }
  
  function orgIn(array $org): array { // l'org est-il défini ? Si oui renvoie son path, sinon [] ; gère IMPRECIS
    if (!isset($org['organisationName']))
      return [];
    $path = $this->scheme->labelIn($org['organisationName']);
    if ($path <> ['IMPRECIS'])
      return $path;
    $nomPrecise = $org['organisationName'].' | '.($org['electronicMailAddress'] ?? 'noElectronicMailAddress');
    return $this->scheme->labelIn($nomPrecise);
  }
  
  // si imprecis alors renvoie le label augmenté qui doit être ajouté
  // permet d'identifier si un org a un nom imprécis,
  function imprecis(array $org): ?string {
    $path = $this->scheme->labelIn($org['organisationName'] ?? '');
    if ($path == ['IMPRECIS']) {
      $nomPrecise = $org['organisationName'].' | '.($org['electronicMailAddress'] ?? 'noElectronicMailAddress');
      return $nomPrecise;
    }
    else
      return null;
  }
  
  function prefLabel(array $org, string $lang='fr'): ?string { // retrouve le prefLabel à partir de l'org
    if ($path = $this->orgIn($org))
      return $this->scheme->node($path)->prefLabel($lang);
    else
      return null;
  }
  
  function short(array $org): ?string { // retrouve le nom court à partir d'une org
    if ($path = $this->orgIn($org))
      return $this->scheme->node($path)->short();
    else
      return null;
  }
  
  function recordIn(array $record): bool { // La fiche de MD a t'elle au moins un de ses responsibleParty dans le référentiel
    foreach ($record['responsibleParty'] ?? [] as $org) {
      if ($this->orgIn($org))
        return true;
    }
    return false;
  }

  function nodes(): array { return $this->scheme->nodes(); }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return;


$arboOrgsPMin = new OrgArbo('orgpmin.yaml');

if (!isset($_GET['action'])) {
  echo "Actions proposées:<ul>\n";
  echo "<li><a href='?action=testops'>Test orgIn/prefLabel/short</a></li>\n";
  echo "<li><a href='?action=testRecordIn'>Test recordIn</a></li>\n";
  echo "<li><a href='?action=selRef'>Test de compatibilité entre les fichiers Sel et le référentiel</a></li>\n";
  echo "<li><a href='?action=nodes'>Affiche les nodes</a></li>\n";
  die("</ul>\n");
}

elseif ($_GET['action']=='testops') { // Test orgIn/prefLabel/short 
  $orgs = <<<EOT
- { organisationName: 'DDT 12 (Direction Départementale des Territoires de l''Aveyron)', role: owner, electronicMailAddress: ddt-mact@aveyron.gouv.fr }
- { organisationName: ADL, role: pointOfContact, electronicMailAddress: nicolas.kusmierek@pas-de-calais.gouv.fr }
- { organisationName: ADL }
- { organisationName: DEAL,  electronicMailAddress: infogeo.deal-guyane@developpement-durable.gouv.fr }
- { organisationName: DEAL }
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
        'imprecis'=> $arboOrgsPMin->imprecis($org),
      ]],
      3);
  }
}

elseif ($_GET['action']=='testRecordIn') { // Test recordIn
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

elseif ($_GET['action']=='selRef') { // Test de compatibilité entre les fichiers Sel et le référentiel
  echo "<h2>Test de compatibilité entre les fichiers Sel et le référentiel</h2>\n";
  echo "<h3>Libellés de Sel hors référentiel</h3>\n";
  $dir = 'catalogs';
  if (!$dh = opendir($dir))
    die("Ouverture de $dir impossible");
  $files = [];
  while (($filename = readdir($dh)) !== false) {
    if (!preg_match('!Sel\.yaml$!', $filename)) continue;
    echo "<b>$filename</b><ul>\n";
    $orgNames = Yaml::parsefile(utf8_decode($dir).'/'.$filename)['orgNames'];
    //echo '<pre>',Yaml::dump([$filename=> $orgNames],5),"</pre>\n";
    foreach ($orgNames as $orgName) {
      if (!($short = $arboOrgsPMin->short(['organisationName'=> $orgName])))
        echo "<li>$orgName</li>\n";
    }
    echo "</ul>\n";
  }
  closedir($dh);
}

elseif ($_GET['action']=='nodes') {
  echo "<pre>\n";
  foreach ($arboOrgsPMin->nodes() as $key => $theme) {
    echo Yaml::dump([$key => [
        'prefLabel'=> $theme->prefLabel(),
        'short'=> $theme->short(),
      ]]);
  }
  die("</pre>\n");
}
<?php
/*PhpDoc:
title: dido/import.php - importe DiDo comme catalogue dans PgSql
name: import.php
doc: |
  Le schéma des fiches est défini dans importdido.schema.yaml
  L'objectif de ce script est d'importer les fiches de MDD dans PgSql en les standardisant conformément à ce schéma
  Le script écrit un fichier import.yaml dont la conformité au schéma peut être vérifiée.
journal:
  22/11/2021:
    - ajout de la définition du périmètre
    - ajout d'un publisher et d'un contactPoint par défaut (bug DiDo)
  20/11/2021:
    - correction erreur DiDo downloadUrl -> downloadURL
  19/11/2021:
    - création
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../httpcache.inc.php';
require_once __DIR__.'/../catinpgsql.inc.php';

use Symfony\Component\Yaml\Yaml;

// Les paramètres en entrée
$exportUrl = 'https://data.statistiques.developpement-durable.gouv.fr/dido/api/harvesting/dcat-ap/export/jsonld';
$catalogUri = 'https://data.statistiques.developpement-durable.gouv.fr/dido/api/harvesting/dcat-ap/id/catalog';

if (php_sapi_name()=='cli') { // gestion du dialogue pour définir les paramètres en CLI
  if (!CatInPgSql::chooseServer($argv[1] ?? null)) { // Choix du serveur 
    echo "Erreur: paramètre serveur incorrect !\n";
    echo "usage: php $argv[0] {serveur}\n";
    echo " où:\n";
    echo " - {serveur} vaut local pour la base locale et distant pour la base distante\n";
    die();
  }
}
else { // gestion du dialogue pour définir les paramètres en mode web
  if (!CatInPgSql::chooseServer($_GET['server'] ?? null)) { // Choix du serveur 
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>import</title></head><body>\n";
    echo "Choix du serveur:<ul>\n";
    echo "<li><a href='?server=local'>local</a></li>\n";
    echo "<li><a href='?server=distant'>distant</a></li>\n";
    die("</ul>\n");
  }
}

$cat = new CatInPgSql('dido');
$cat->create(); // (re)crée la table


function readDiDo(string $pageUrl): array { // lecture des ressources DiDo et les retourne
  $httpCache = new HttpCache('dido');

  $context = null;
  $resources = []; // la base des ressources structurée par type sous la forme [{id} => {resource}]]
  while ($pageUrl) {
    $page = $httpCache->request($pageUrl, 'json');
    //echo "<h2>$pageUrl</h2>\n$page\n";
    $content = json_decode($page, true);
    if (!$context) {
      $contextUrl = $content['@context'];
      $context = $httpCache->request($contextUrl, 'json');
      //echo "<h2>Context</h2>\n$context\n";
    }
    foreach ($content['@graph'] as $resource) {
      if (!($id = $resource['@id'] ?? null)) {
        echo Yaml::dump(['NO @id'=> $resource]);
      }
      else {
        $resources[$id] = $resource;
      }
    }
    //$pageUrl = $content['view']['next'] ?? null;
    $pageUrl = null;
  }
  return $resources;
}

// Passe d'une date de la forme 'Thu Jul 29 2021 06:02:07 GMT+0000 (Coordinated Universal Time)'
// à '2021-07-29T06:02:07+00:00'
function dateFromEngToIso(string $str): string { return date(DATE_ATOM, strtotime(substr($str, 0, 33))); }

function stdPublisher(string|array $record, array $resources): array {
  $default = [
    '@id'=> 'https://data.statistiques.developpement-durable.gouv.fr/dido/api/harvesting/dcat-ap/id/organizations/main',
    '@type'=> 'Agent',
    'org_title'=> 'Service des Données et des Ètudes Statistiques (SDES) du Ministère de la transition écologique (MTE)',
    'org_name'=> 'SDES',
    'comment'=> 'Le SDES est le service statistique du ministère de la transition écologique. Il fait partie du Commissariat Général au Développement Durable (CGDD)',
  ];
    
  if (!$record)
    return $default;
  elseif (is_string($record))
    return $resources[$record];
  else
    return $record;
}

function stdContactPoint(string|array $record, array $resources): array {
  $default = [
    '@id'=> 'https://data.statistiques.developpement-durable.gouv.fr/dido/api/harvesting/dcat-ap/id/contactPoint',
    '@type'=> 'Kind',
    'fn'=> 'Point de contact DiDo',
    'hasURL'=> 'https://statistiques.developpement-durable.gouv.fr/contact',
  ];
  if (!$record)
    return $default;
  elseif (is_string($record))
    return $resources[$record];
  else
    return $record;
}

function stdDistribution(array $record, array $resources): array {
  //echo Yaml::dump(['stdDistribution'=> $record]);
  $record['downloadURL'] = $record['downloadUrl']; // erreur DiDo
  unset($record['downloadUrl']);
  foreach (['license','accessURL','downloadURL','mediaType'] as $property)
    if (is_array($record[$property]))
      $record[$property] = $record[$property]['@id'];
  return $record;
}

function stdConcept(string|array $record, array $resources): array {
  if (is_string($record))
    $record = $resources[$record];
  if (isset($record['inScheme']))
    $record['inScheme'] = $record['inScheme']['@id'];
  return $record;
}

function stdDocumentation(array $record): array {
  if (isset($record['@id']))
    $record = [$record];
  foreach ($record as $i => $doc) {
    foreach (['created','issued','modified'] as $property)
      if (isset($doc[$property]) && preg_match('!Coordinated Universal Time!', $doc[$property]))
        $record[$i][$property] = dateFromEngToIso($doc[$property]);
  }
  return $record;
}

function stdDataset(array $record, int $noDtadaset, array $resources): array {
  //echo Yaml::dump(['stdDataset'=> $dataset]);
  $record['publisher'] = [stdPublisher($record['publisher'] ?? [], $resources)];
  $record['contactPoint'] = [stdContactPoint($record['contactPoint'] ?? [], $resources)];
  
  foreach (['created','issued','modified'] as $property)
    if (isset($record[$property]) && preg_match('!Coordinated Universal Time!', $record[$property]))
      $record[$property] = dateFromEngToIso($record[$property]);
    
  // remplace un objet par son @id
  foreach (['accrualPeriodicity','spatial'] as $property)
    if (isset($record[$property]) && is_array($record[$property]))
      $record[$property] = $record[$property]['@id'];
  
  // transporme spatial en array
  if (isset($record['spatial']))
    $record['spatial'] = [$record['spatial']];
  
  // standise la prop. theme
  foreach ($record['theme'] ?? [] as $i => $theme) {
    $record['theme'][$i] = stdConcept($theme, $resources);
  }
  
  // s'il y a qu'une seule doc sans aray alors création d'un array
  if (isset($record['documentation']))
    $record['documentation'] = stdDocumentation($record['documentation']);
    
  if (isset($record['hasPart'])) {
    if (isset($record['hasPart']['@id'])) // un dataset développé
      $record['hasPart'] = [$record['hasPart']['@id']];
    elseif (isset($record['hasPart'][0]['@id'])) { // une liste de datasets développés
      //echo "  une liste de datasets développés\n";
      foreach ($record['hasPart'] as $i => $hasPart)
        $record['hasPart'][$i] = $hasPart['@id'];
      //echo Yaml::dump(['stdDataset'=> $record['hasPart']]);
    }
  }
  foreach ($record['distribution'] ?? [] as $i => $distribution)
    $record['distribution'][$i] = stdDistribution($distribution, $resources);
  return array_merge(['title'=> $record['title'], 'standard'=> 'DCAT'], $record);
}

function stdCatalog(array $record, array $resources): array {
  $record['language'] = $record['language']['@id'];
  if (isset($record['publisher']))
    $record['publisher'] = [stdPublisher($record['publisher'], $resources)];
  if (isset($record['contactPoint']))
    $record['contactPoint'] = [stdContactPoint($record['contactPoint'], $resources)];
  $record['homepage'] = $record['homepage']['@id'];
  unset($record['dataset']);
  return array_merge(['title'=> $record['title']], $record);
}

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>import</title></head><body><pre>\n";
$resources = readDiDo($exportUrl);

$datasets = [];
foreach ($resources[$catalogUri]['dataset'] as $dataset) {
  if (is_string($dataset))
    $dataset = $resources[$dataset];
  echo "- $dataset[title]\n";
  $stdDataset = stdDataset($dataset, count($datasets), $resources);
  //$datasets[md5($stdDataset['title'])] = $stdDataset;
  $datasets[] = $stdDataset;
  $cat->storeRecord($stdDataset, '@id');
}

PgSql::query("update catalogdido set perimetre='Min'");

file_put_contents(
  'import.yaml',
  str_replace(["\n  -\n    ","\n      -\n        "], ["\n  - ","\n      - "],
     Yaml::dump([
      'title'=> "Liste des datasets structurés issus de DiDo",
      '$schema'=> 'import',
      'created'=> date(DATE_ATOM),
      'catalog'=> stdCatalog($resources[$catalogUri], $resources),
      'dataset'=> $datasets,
    ], 5, 2)));
die();

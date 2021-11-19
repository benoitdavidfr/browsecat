<?php
/*PhpDoc:
title: dido/viewdido.php - visualisation améliorée l'export DiDo DCAT-AP de DiDo
name: viewdido.php
doc: |
  Visualisation améliorée du fichier JSON d l'export de DiDo
journal:
  19/11/2021:
    - améliorations
  18/11/2021:
    - modif pour utiliser l'export et pas le catalogue lui-même
  17/11/2021:
    - création
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../httpcache.inc.php';

use Symfony\Component\Yaml\Yaml;

// Les paramètres
$exportUrl = 'https://data.statistiques.developpement-durable.gouv.fr/dido/api/harvesting/dcat-ap/export/jsonld';
$catalogUri = 'https://data.statistiques.developpement-durable.gouv.fr/dido/api/harvesting/dcat-ap/id/catalog';

// Passe d'une date de la forme 'Thu Jul 29 2021 06:02:07 GMT+0000 (Coordinated Universal Time)'
// à '2021-07-29T06:02:07+00:00'
//function dateFromEngToIso(string $str): string { return date(DATE_ATOM, strtotime(substr($str, 0, 33))); }

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

function show(string $uri, array $resources): void {
  switch($resources[$uri]['@type']) {
    case 'Catalog': {
      echo "<h2>Liste des données de DiDo</h2><ul>\n";
      //echo '<pre>',Yaml::dump(readDiDo($exportUrl)[$catalogUri]);
      foreach($resources[$uri]['dataset'] as $dataset) {
        //echo '<pre>',Yaml::dump($dataset),"</pre>\n";
        if (is_string($dataset))
          $dataset = $resources[$dataset];
        echo "<li><a href='?uri=",$dataset['@id'],"'>$dataset[title]</a></li>\n";
      }
      echo "</ul>\n";
      return;
    }
    
    case 'Dataset': 
    case ['Dataset','series']: {
      $record = $resources[$uri];
      $hasPart = null;
      if (isset($record['hasPart'])) {
        $hasPart = [$record['hasPart']];
        unset($record['hasPart']);
      }
      $distribution = $record['distribution'] ?? null;
      unset($record['distribution']);
      $output = Yaml::dump($record, 5, 2);
      echo '<pre>',preg_replace("!'(https?://[^']*)'!", "<a href='?uri=$1'>'$1'</a>", $output);
      foreach (['distribution','hasPart'] as $var) {
        if ($$var) {
          echo "$var:\n";
          foreach ($$var as $elt) {
            echo "  - <a href='?uri=",$elt['@id'],"'>$elt[title]</a>\n";
          }
        }
      }
      return;
    }

    default: {
      $output = Yaml::dump($resources[$uri], 5, 2);
      echo '<pre>',preg_replace("!'(https?://[^']*)'!", "<a href='?uri=$1'>'$1'</a>", $output),"</pre>\n";
      return;
    }
  }
}

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>viewDiDo</title></head><body>\n";
$resources = readDiDo($exportUrl);
$uri = $_GET['uri'] ?? $catalogUri;
if (!isset($resources[$uri])) {
  echo "redirection vers $uri\n";
  header("Location: $uri");
  die();
}
else
  show($uri, $resources);

echo "<h2>Tests</h2><ul>\n";
foreach ([
  "series"=>
'https://data.statistiques.developpement-durable.gouv.fr/dido/api/harvesting/dcat-ap/id/datasets/6102445fe436671e1cec5da8',
  ] as $label => $uri) {
    echo "<li><a href='?uri=$uri'>$label</a></li>\n";
}
die();

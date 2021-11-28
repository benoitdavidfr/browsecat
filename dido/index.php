<?php
/*PhpDoc:
title: dido/index.php - visualisation améliorée l'export DiDo DCAT-AP de DiDo
name: index.php
doc: |
  Visualisation améliorée du fichier JSON de l'export de DiDo
journal:
  27/11/2021:
    - affichage hiérarchique des JdD avec mouse-over avec les mots-clés et les thèmes
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

// lit les ressources DiDo et les retourne sous la forme [{uri}=> {resource}]
function readDiDo(string $pageUrl): array {
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

// le par. est-il une liste ? cad un array dont les clés sont la liste des n-1 premiers entiers positifs, [] est une liste
function is_list($list): bool { return is_array($list) && (array_keys($list) == array_keys(array_keys($list))); }

if (0) { // Test de is_list()  
  echo "Test is_list<br>\n";
  foreach ([[], [1, 2, 3], ['a'=>'a','b'=>'b'], ['a','b'=>'c'], [1=>'a',0=>'b'],[1=>'a','b']] as $list) {
    echo json_encode($list), (is_list($list) ? ' is_list' : ' is NOT list') , "<br>\n";
  }
  die("FIN test is_list<br><br>\n");
}

function themes(array $themes): array { // construit la liste de thèmes 
  //echo Yaml::dump($themes);
  $result = [];
  foreach ($themes as $k => $theme) {
    if (is_string($theme))
      $result[] = $theme;
    else
      $result[] = $theme['prefLabel'];
  }
  //echo Yaml::dump(['$result'=> $result]);
  return $result; 
}

function buildTree(string $catalogUri, array $resources): array {
  // intialisation de $datasetTree à partir de $resources et $catalogUri
  // [{uri}=> {dataset}] & {dataset} ::= ['title'=>{title}, 'hasPart'=> [{uri}=> {dataset}]]
  $datasetTree = ['Référentiels'=> ['title'=> "Référentiels", 'hasPart'=> []]];
  foreach($resources[$catalogUri]['dataset'] as $dataset) {
    if (!is_array($dataset))
      $dataset = $resources[$dataset];
    if (preg_match('!^(Référentiel|Nomenclature) - !', $dataset['title'])) {
      $datasetTree['Référentiels']['hasPart'][$dataset['@id']] = [
        'title'=> $dataset['title'],
        'keyword'=> $dataset['keyword'] ?? [],
        'theme'=> themes($dataset['theme'] ?? []),
      ];
    }
    else {
      $datasetTree[$dataset['@id']] = [
        'title' => $dataset['title'],
        'keyword'=> $dataset['keyword'] ?? [],
        'theme'=> themes($dataset['theme'] ?? []),
      ];
      if (isset($dataset['hasPart'])) {
        $hasPart = $dataset['hasPart'];
        foreach (is_list($hasPart) ? $hasPart : [$hasPart] as $part)
          $datasetTree[$dataset['@id']]['hasPart'][$part['@id']] = [
            'title'=> $part['title'],
            'keyword'=> $part['keyword'] ?? [],
            'theme'=> themes($part['theme'] ?? []),
          ];
      }
    }
  }
  // puis suppression à la racine des JdD enfants 
  foreach ($datasetTree as $dsid => $dataset) {
    foreach ($dataset['hasPart'] ?? [] as $partUri => $hasPart) {
      unset($datasetTree[$partUri]);
    }
  }
  return $datasetTree;
}

function showTree(array $tree): void { // affichage récursif de l'arbre construit par buildTree
  echo "<ul>\n";
  foreach ($tree as $uri => $node) {
    $hasPart = $node['hasPart'] ?? [];
    unset($node['hasPart']);
    $text = Yaml::dump($node);
    $text = str_replace(['"'],['\"'], $text);
    echo "<li title=\"$text\"><a href='?uri=$uri'>$node[title]</a>",
          //"<br>keyword: ",implode(', ', $node['keyword'] ?? []),
          //"<br>theme: ",implode(', ', $node['theme'] ?? []),
          "</li>\n";
    if ($hasPart)
      showTree($hasPart);
  }
  echo "</ul>\n";
}

function show(string $uri, array $resources): void {
  switch($resources[$uri]['@type']) {
    case 'Catalog': {
      echo "<h2>Liste des données de DiDo</h2>\n";
      //echo '<pre>',Yaml::dump(readDiDo($exportUrl)[$catalogUri]);
      /*echo "<ul>\n";
      foreach($resources[$uri]['dataset'] as $dataset) {
        //echo '<pre>',Yaml::dump($dataset),"</pre>\n";
        if (is_string($dataset))
          $dataset = $resources[$dataset];
        echo "<li><a href='?uri=",$dataset['@id'],"'>$dataset[title]</a></li>\n";
      }
      echo "</ul>\n";*/
      
      //echo "<h2>asTree</h2>\n";
      //echo '<pre>',Yaml::dump(['$datasetTree'=> buildTree($uri, $resources)], 6, 2),"</pre>\n";
      showTree(buildTree($uri, $resources));
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

if (!isset($_GET['uri'])) {
  echo "<h2>Tests</h2><ul>\n";
  foreach ([
    "series"=>
  'https://data.statistiques.developpement-durable.gouv.fr/dido/api/harvesting/dcat-ap/id/datasets/6102445fe436671e1cec5da8',
    ] as $label => $uri) {
      echo "<li><a href='?uri=$uri'>$label</a></li>\n";
  }
}
die();

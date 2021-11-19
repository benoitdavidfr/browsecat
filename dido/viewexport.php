<?php
/*PhpDoc:
title: dido/viewexport.php - première version brute de visualisation de l'export DCAT-AP de DiDo
name: viewexport.php
doc: |
  Visualisation assez brute du fichier JSON d l'export de DiDo
journal:
  18/11/2021:
    - modif pour utiliser l'export et pas le catalogue lui-même
  17/11/2021:
    - création
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../httpcache.inc.php';

use Symfony\Component\Yaml\Yaml;

// Les paramètres
$pageUrl = 'https://data.statistiques.developpement-durable.gouv.fr/dido/api/harvesting/dcat-ap/export/jsonld';
$catalogUri = 'https://data.statistiques.developpement-durable.gouv.fr/dido/api/harvesting/dcat-ap/id/catalog';

// Passe d'une date de la forme 'Thu Jul 29 2021 06:02:07 GMT+0000 (Coordinated Universal Time)'
// à '2021-07-29T06:02:07+00:00'
//function dateFromEngToIso(string $str): string { return date(DATE_ATOM, strtotime(substr($str, 0, 33))); }

/*function getRecord(HttpCache $httpCache, string $type, string $pageUrl): array {
  $json = $httpCache->request($pageUrl, 'json');
  //echo "<h3>$type</h3>\n";//"$json\n";
  $content = json_decode($json, true);
  switch ($content['@type']) {
    case 'Dataset':
    case ['Dataset','series']: {
      $record = array_merge(
        ['id'=> $content['@id']],
        ['title'=> $content['title']],
        ['description'=> $content['description']],
        isset($content['accrualPeriodicity']) ? ['accrualPeriodicity'=> $content['accrualPeriodicity']['@id']] : [],
        isset($content['temporal']) ? ['temporal'=> $content['temporal']] : [],
        isset($content['issued']) ? ['issued'=> dateFromEngToIso($content['issued'])] : [],
        isset($content['created']) ? ['created'=> dateFromEngToIso($content['created'])] : [],
        isset($content['modified']) ? ['modified'=> dateFromEngToIso($content['modified'])] : [],
        isset($content['spatial']) ? ['spatial'=> $content['spatial']] : [],
        isset($content['hasPart']) ?
          ['hasPart'=> is_string($content['hasPart']) ? [$content['hasPart']] : $content['hasPart']]
          : [],
        isset($content['isPartOf']) ?
          ['isPartOf'=> is_string($content['isPartOf']) ? [$content['isPartOf']] : $content['isPartOf']]
          : [],
        isset($content['publisher']) ? ['publisher'=> getRecord($httpCache, 'Agent', $content['publisher'])] : [],
        isset($content['contactPoint']) ? ['contactPoint'=> getRecord($httpCache, 'Kind', $content['contactPoint'])] : [],
        isset($content['keyword']) ? ['keyword'=> $content['keyword']] : [],
        isset($content['theme']) ? ['theme'=> $content['theme']] : [],
        isset($content['documentation']) ? ['documentation'=> $content['documentation']] : [],
        isset($content['rights']) ? ['rights'=> $content['rights']] : [],
        isset($content['distribution']) ? ['distribution'=> []] : [],
      );
      
      foreach ($content['distribution'] ?? [] as $distribution) {
        try {
          $record['distribution'][] = getRecord($httpCache, 'Distribution', $distribution);
        }
        catch (Exception $e) {
          echo "Exception sur $distribution -> ",$e->getMessage(),"\n";
        }
      }
      
      break;
    }
    case 'Agent': {
      $record = [
        'id'=> $content['@id'],
        'org_name'=> $content['org_name'],
        'org_title'=> $content['org_title'],
        'comment'=> $content['comment'],
      ];
      break;
    }
    case 'Kind': {
      $record = [
        'id'=> $content['@id'],
        'fn'=> $content['fn'],
        'hasURL'=> $content['hasURL'],
      ];
      break;
    }
    case 'Distribution': {
      if (!($id = $content['@id'] ?? null))
        throw new Exception("@id non défini sur $pageUrl");
      if (!preg_match('!^https://data.statistiques.developpement-durable.gouv.fr/dido/api/harvesting/dcat-ap/!', $pageUrl))
        throw new Exception("@id incorrect sur $pageUrl");
      if (!isset($content['mediaType']['@id']))
        throw new Exception("@id de mediaType non défini sur $pageUrl");
      $record = array_merge(
        ['id'=> $content['@id']],
        ['title'=> $content['title']],
        isset($content['issued']) ? ['issued'=> dateFromEngToIso($content['issued'])] : [],
        ['license'=> $content['license']['@id']],
        ['mediaType'=> $content['mediaType']['@id']],
        ['accessURL'=> $content['accessURL']['@id']],
        ['downloadUrl'=> $content['downloadUrl']['@id']],
      );
      break;
    }
    default: {
      $record = [
        '@id'=> $content['@id'],
        '@type'=> $content['@type'],
      ];
      break;
    }
  }
  foreach (array_keys($content) as $prop) {
    if (!isset($record[$prop]) && !in_array($prop, ['@context','@id','@type','identifier']))
      echo "<b>prop $prop on type $type non tranférée</b>\n";
  }
  $record['source'] = $content;
  return $record;
}*/
  
$httpCache = new HttpCache('dido');

echo "<pre>\n";
$context = null;
$nbdatasets = 0;
$resources = []; // la base des ressources structurée par type sous la forme [{id} => {resource}]
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
//echo count($resources[$catalogUri]['dataset'])," JD détectés\n";

$uri = $_GET['uri'] ?? $catalogUri;
if (!isset($resources[$uri])) {
  echo "redirection vers $uri\n";
  header("Location: $uri");
  die();
}
elseif (isset($_SERVER['HTTP_REFERER'])
    && preg_match('!\?uri='.str_replace('?','\?', $uri).'!', $_SERVER['HTTP_REFERER'])) {
  echo "Boucle détectée\n";
  header("Location: $uri");
  die();
}
else {  
  //echo Yaml::dump(['HTTP_REFERER'=> $_SERVER['HTTP_REFERER'] ?? "non défini"]);
  $output = Yaml::dump($resources[$uri], 5, 2);
  echo preg_replace("!'(https?://[^']*)'!", "<a href='?uri=$1'>'$1'</a>", $output);
}


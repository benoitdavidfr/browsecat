<?php
/*PhpDoc:
name: view.php
title: dgouv/view.php - Test de l'accès à l'API en vue de télécharger les JD du périmètre
classes:
doc: |
  Export conforme au schéma ../dido/importdido.schema.yaml
journal: |
  20/11/2021:
    - création
includes:
  - ../../phplib/accents.inc.php
  - ../httpcache.inc.php
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../../phplib/accents.inc.php';
require_once __DIR__.'/../httpcache.inc.php';

use Symfony\Component\Yaml\Yaml;

$root = 'https://www.data.gouv.fr/api/1'; // URL racine de l'API

if (php_sapi_name()=='cli') { // gestion du dialogue pour définir les paramètres en CLI
  die("script non prévu en CLI\n");
}
else {
  if (!isset($_GET['action'])) { // gestion du dialogue pour définir les paramètres en mode web
    echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>dgouv/view</title></head><body>\n";
    echo "Actions<ul>\n";
    echo "<li><a href='?action=orgs'>Les organisations</a></li>\n";
    echo "<li><a href='?action=org'>Les JDD d'une organisation</a></li>\n";
    echo "<li><a href='?action=import'>Importe les JDD pertinents dans PgSql</a></li>\n";
    echo "</ul>\n";
    die();
  }
}

// standardise un nom en supprimant les accents et en passant en minuscules
// permet notamment d'effectuer un tri plus pertinent
function stdname(string $name): string { return strtolower(supprimeAccents($name)); }

if ($_GET['action']=='orgs') { // Liste les organisations
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>dgouv/view</title></head><body><pre>\n";
  $orgs = []; // [{org_name_standardisé} => {org_name}1]
  $dgouv = new HttpCache('dgouv');
  $url = "$root/organizations/";
  while($url) {
    //echo "<b>$url</b>\n";
    $page = json_decode($dgouv->request($url), true);
    //echo Yaml::dump($page, 3);
    foreach ($page['data'] as $org) {
      if ((count($org['members']) == 1)
        && ($org['members'][0]['user']['uri'] == 'https://www.data.gouv.fr/api/1/users/passerelle-inspire/'))
          echo "- <s>$org[name]</s>\n";
      else
        //echo "- $org[name]\n";
        $orgs[stdname($org['name'])] = $org['name'];
    }
    $url = $page['next_page'] ?? null;
  }
  echo "<b>Liste des organisations</b>\n";
  ksort($orgs);
  //print_r($orgs);
  foreach ($orgs as $orgname)
    echo "- $orgname\n";
  die();
}

if ($_GET['action']=='org') { // Affiche la liste des JDD d'une organisation 
  function orgsByName(string $root, string $orgname): array {
    $orgs = [];
    $dgouv = new HttpCache('dgouv');
    $url = "$root/organizations/";
    while ($url) {
      $page = json_decode($dgouv->request($url), true);
      foreach ($page['data'] as $org) {
        if (stdname($org['name']) == $orgname) {
          $orgs[] = $org;
        }
      }
      $url = $page['next_page'] ?? null;
    }
    return $orgs;
  }
  
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>dgouv/view</title></head><body>\n";
  if (!isset($_GET['orgname']) && !isset($_GET['orguri'])) { // sélectionne une organisations parmi celles du périmètre 
    echo "<ul>\n";
    foreach(Yaml::parsefile('orgsel.yaml')['orgs'] as $orgname) {
      echo "<li><a href='?action=org&amp;orgname=",urlencode($orgname),"'>$orgname</a></li>\n";
    }
    echo "</ul>\n";
    die();
  }
  elseif (!isset($_GET['a2'])) { // recherche de l'organisation dans la liste
    $dgouv = new HttpCache('dgouv');
    $url = "$root/organizations/";
    foreach (orgsByName($root, stdname($_GET['orgname'])) as $org) {
      echo '<pre>',Yaml::dump(['$org'=> $org], 5, 2),"</pre>\n";
      echo "Actions:<ul>\n";
      echo "<li><a href='?action=org&amp;orguri=$org[uri]&amp;a2=catalog'>Catalogue</a></li>\n";
      echo "<li><a href='?action=org&amp;orguri=$org[uri]&amp;a2=datasets'>Datasets</a></li>\n";
      echo "</ul>\n";
    }
    die();
  }
  elseif ($_GET['a2']=='catalog') { // catalogue DCAT en JSON-LD 
    $dgouv = new HttpCache('dgouv');
    $catalog = json_decode($dgouv->request("$_GET[orguri]catalog"), true);
    echo '<pre>',Yaml::dump(['$catalog'=> $catalog], 4, 2),"</pre>\n";
    die();
  }
  elseif ($_GET['a2']=='datasets') { // liste des JDD 
    $dgouv = new HttpCache('dgouv');
    $datasets = json_decode($dgouv->request("$_GET[orguri]datasets"), true);
    //echo '<pre>',Yaml::dump(['$datasets'=> $datasets], 4, 2),"</pre>\n";
    echo "<ul>\n";
    foreach ($datasets['data'] as $i => $dataset) {
      $strike = isset($dataset['extras']['inspire:identifier']);
      $sb = $strike ? '<s>':'';
      $se = $strike ? '</s>':'';
      echo "<li><a href='?action=dataset&amp;uri=$dataset[uri]'>$sb$dataset[title]$se</a></li>\n";
    }
    echo "</ul>\n";
    die();
  }
}

if ($_GET['action']=='dataset') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>dgouv/view</title></head><body>\n";
  $dgouv = new HttpCache('dgouv');
  $dataset = json_decode($dgouv->request($_GET['uri']), true);
  echo '<pre>',Yaml::dump(['$dataset'=> $dataset], 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),"</pre>\n";
  die();
}

if ($_GET['action']=='import') {
  // standardise une distribution
  function stdDistributions(array $resources, array $dataset): array {
    static $transcodages = [
      'license'=> [
        'notspecified'=> 'notspecified',
        'other-open'=> 'other-open',
        'fr-lo'=> 'https://www.etalab.gouv.fr/licence-ouverte-open-licence',
        'lov2'=> 'https://www.etalab.gouv.fr/licence-ouverte-open-licence',
        'odc-by'=> 'https://opendatacommons.org/licenses/by/1-0/',
        'odc-odbl'=> 'https://opendatacommons.org/licenses/odbl/',
      ],
    ];
    $distributions = [];
    foreach ($resources as $resource) {
      $distributions[] = [
        '@id'=> $resource['url'],
        '@type'=> 'Distribution',
        'title'=> $resource['title'],
        'license'=> $transcodages['license'][$dataset['license']] ?? "'$dataset[license]' non définie dans import",
        'created'=> $resource['created_at'].'Z',
        'modified'=> $resource['last_modified'].'Z',
        'issued'=> $resource['published'].'Z',
        'mediaType'=> 'https://www.iana.org/assignments/media-types/'.$resource['mime'],
        //'accessURL'=> $resource['preview_url'], // preview_url pose pbs car chemin relatif ! et pas URL
        'downloadURL'=> $resource['url'],
      ];
    }
    return $distributions;
  }
  
  // standardise une fiche de MDD
  function stdDataset(array $dataset): array {
    static $transcodages = [
      'accrualPeriodicity'=> [
        'annual'=> 'http://publications.europa.eu/resource/authority/frequency/ANNUAL',
        'quarterly'=> 'http://publications.europa.eu/resource/authority/frequency/QUARTERLY',
        'monthly'=> 'http://publications.europa.eu/resource/authority/frequency/MONTHLY',
        'fourTimesAWeek'=>  'http://publications.europa.eu/resource/authority/frequency/WEEKLY_4',
        'daily'=> 'http://publications.europa.eu/resource/authority/frequency/DAILY',
        'hourly'=> 'http://publications.europa.eu/resource/authority/frequency/HOURLY',
        'punctual'=> 'http://publications.europa.eu/resource/authority/frequency/NEVER',
        'continuous'=> 'http://publications.europa.eu/resource/authority/frequency/UPDATE_CONT',
        'irregular'=> 'http://publications.europa.eu/resource/authority/frequency/IRREG',
        'unknown'=> 'http://publications.europa.eu/resource/authority/frequency/UNKNOWN',
      ],
    ];
    $stdDataset = [
      '@id'=> $dataset['uri'],
      '@type'=> 'Dataset',
      'standard'=> 'DCAT',
      'title'=> $dataset['title'],
      'description'=> $dataset['description'],
      'identifier'=> $dataset['uri'],
      'publisher'=> [
        array_merge(
          ['@id'=> $dataset['organization']['uri']],
          ['@type'=> 'Agent'],
          ['org_title'=> $dataset['organization']['name']],
          isset($dataset['organization']['acronym']) ? ['org_name'=> $dataset['organization']['acronym']] : [],
        )
      ],
      'contactPoint'=> [[
        '@id'=> $dataset['organization']['uri'],
        '@type'=> 'Kind',
        'fn'=> $dataset['organization']['name'],
        'hasURL'=> $dataset['uri'], // le contact peut être effectué au travers de data.gouv
      ]],
      'accrualPeriodicity'=> $transcodages['accrualPeriodicity'][$dataset['frequency']] ?? "ERREUR sur $dataset[frequency]",
      'created'=> $dataset['created_at'].'Z',
      'modified'=> $dataset['last_modified'].'Z',
      'keyword'=> $dataset['tags'],
      'distribution'=> stdDistributions($dataset['resources'], $dataset),
    ];
    return $stdDataset;
  }
  
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>dgouv/view</title></head><body><pre>\n";
  if (!is_file('orgsel.yaml')) {
    die("Erreur le fichier des organisations orgsel.yaml doit être créé\n");
  }
  $orgnames = Yaml::parsefile('orgsel.yaml')['orgs'];
  foreach ($orgnames as $i => $orgname)
    $orgnames[$i] = stdname($orgname);
  
  // recherche de la liste des URIs des orgs
  $dgouv = new HttpCache('dgouv');
  $url = "$root/organizations/";
  $orguris = []; // lite des URIs des orgs
  while ($url) {
    $page = json_decode($dgouv->request($url), true);
    foreach ($page['data'] as $org) {
      if (in_array(stdname($org['name']), $orgnames)) {
        $orguris[] = $org['uri'];
      }
    }
    $url = $page['next_page'] ?? null;
  }
  //echo Yaml::dump($orguris);
  
  $stdDatasets = [];
  foreach ($orguris as $orguri) {
    $datasets = json_decode($dgouv->request("${orguri}datasets"), true);
    foreach ($datasets['data'] as $i => $dataset) {
      if (isset($dataset['extras']['inspire:identifier'])) // s'il a été moissonné alors je l'élimine 
        continue;
      $stdDataset = stdDataset($dataset);
      echo Yaml::dump([$orguri => [$i => $stdDataset['title']]], 6, 2);
      $stdDatasets[] = $stdDataset;
    }
  }
  file_put_contents(
    'import.yaml',
    str_replace(["\n  -\n    ","\n      -\n        "], ["\n  - ","\n      - "],
       Yaml::dump([
        'title'=> "Liste des datasets structurés issus de DataGouv",
        '$schema'=> '../dido/importdido',
        'created'=> date(DATE_ATOM),
        'dataset'=> $stdDatasets,
      ], 5, 2)));
  die();
}

<?php
/*PhpDoc:
name: view.php
title: dgouv/view.php - Découverte de l'API en vue de télécharger les JD du périmètre
classes:
doc: |
  Export conforme au schéma ../dido/importdido.schema.yaml
journal: |
  22/11/2021:
    - finalisation de l'import, il manque cependant la conversion de spatial en bbox
  21/11/2021:
    - Découverte des fonctionnalités de localisation géographique de DataGouv
  20/11/2021:
    - création
includes:
  - ../../phplib/accents.inc.php
  - ../httpcache.inc.php
  - ../catinpgsql.inc.php
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../../phplib/accents.inc.php';
require_once __DIR__.'/../httpcache.inc.php';
require_once __DIR__.'/../catinpgsql.inc.php';

use Symfony\Component\Yaml\Yaml;

$API = 'https://www.data.gouv.fr/api/1'; // URL racine de l'API

if (php_sapi_name()=='cli') { // en CLI -> import
  if (!CatInPgSql::chooseServer($argv[1] ?? null)) { // Choix du serveur 
    echo "Erreur: paramètre serveur incorrect !\n";
    echo "usage: php $argv[0] {serveur}\n";
    echo " où:\n";
    echo " - {serveur} vaut local pour la base locale ou distant pour la base distante\n";
    die();
  }
  $_GET['action'] = 'import'; // en CLI on réalise l'import
}
else { // PAS CLI
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>dgouv/view</title></head><body>\n";
  if (!isset($_GET['action'])) { // gestion du dialogue pour définir les paramètres en mode web
    echo "Actions:<ul>\n";
    echo "<li><a href='?action=orgs'>Affiche toutes les organisations</a></li>\n";
    echo "<li><a href='?action=datasets'>Affiche les JDD par organisation sélectionnée</a></li>\n";
    echo "<li><a href='?action=import'>Importe les JDD pertinents dans PgSql</a></li>\n";
    echo "<li><a href='?action=spatial'>Explore l'aspect d'indexation géographique de l'API</a></li>\n";
    echo "</ul>\n";
    echo "Exemples:<ul>\n";
    echo "<li><a href='?action=showYaml",
      "&amp;uri=https://www.data.gouv.fr/api/1/datasets/grands-projets-de-centrales-photovoltaiques-dans-le-rhone/'>",
      "fiche de MDD avec propriété spatial</a></li>\n";
    echo "</ul>\n";
    die();
  }
  echo "<pre>";
}

// standardise un nom en supprimant les accents et en le passant en minuscules
// permet notamment d'effectuer un tri plus pertinent
function stdname(string $name): string { return strtolower(supprimeAccents($name)); }

if ($_GET['action']=='orgs') { // Liste toutes les organisations
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>dgouv/view</title></head><body><pre>\n";
  $orgs = []; // [{org_name_standardisé} => {org_name}1]
  $dgouv = new HttpCache('dgouv');
  $url = "$API/organizations/";
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

if ($_GET['action']=='datasets') { // Affiche la liste des JDD d'une organisation 
  function orgsByName(string $API, string $orgname): array { // retrouve une organisation identifiée par son nom
    $orgs = [];
    $dgouv = new HttpCache('dgouv');
    $url = "$API/organizations/";
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
  
  echo "</pre>\n";
  if (!isset($_GET['a2'])) { // sélectionne une organisations par son nom parmi celles du périmètre 
    echo "<ul>\n";
    foreach(Yaml::parsefile('orgsel.yaml')['orgs'] as $orgname) {
      echo "<li><a href='?action=datasets&amp;orgname=",urlencode($orgname),"&amp;a2=showOrg'>$orgname</a>, ",
            "<a href='?action=datasets&amp;orgname=",urlencode($orgname),"&amp;a2=catalog'>son catalogue</a>, ",
            "<a href='?action=datasets&amp;orgname=",urlencode($orgname),"&amp;a2=datasets'>ses datasets</a>",
            "</li>\n";
    }
    echo "</ul>\n";
    die();
  }
  elseif ($_GET['a2']=='showOrg') { // recherche de l'organisation dans la liste
    $dgouv = new HttpCache('dgouv');
    $url = "$API/organizations/";
    foreach (orgsByName($API, stdname($_GET['orgname'])) as $org) {
      echo '<pre>',Yaml::dump(['$org'=> $org], 5, 2),"</pre>\n";
      //echo "Actions:<ul>\n";
      //echo "<li><a href='?action=datasets&amp;orguri=$org[uri]&amp;a2=catalog'>Catalogue</a></li>\n";
      //echo "<li><a href='?action=datasets&amp;orguri=$org[uri]&amp;a2=datasets'>Datasets</a></li>\n";
      //echo "</ul>\n";
    }
    die();
  }
  elseif ($_GET['a2']=='catalog') { // catalogue DCAT en JSON-LD 
    $dgouv = new HttpCache('dgouv');
    foreach (orgsByName($API, stdname($_GET['orgname'])) as $org) {
      $catalog = json_decode($dgouv->request("$org[uri]catalog"), true);
      echo '<pre>',Yaml::dump(["$org[uri]catalog"=> $catalog], 4, 2),"</pre>\n";
    }
    die();
  }
  elseif ($_GET['a2']=='datasets') { // liste des JDD 
    $dgouv = new HttpCache('dgouv');
    foreach (orgsByName($API, stdname($_GET['orgname'])) as $org) {
      $datasets = json_decode($dgouv->request("$org[uri]datasets"), true);
      //echo '<pre>',Yaml::dump(["$org[uri]datasets" => $datasets], 4, 2),"</pre>\n";
      echo "<ul>\n";
      foreach ($datasets['data'] as $i => $dataset) {
        $strike = isset($dataset['extras']['inspire:identifier']);
        $sb = $strike ? '<s>':'';
        $se = $strike ? '</s>':'';
        echo "<li><a href='?action=showYaml&amp;uri=$dataset[uri]'>$sb$dataset[title]$se</a></li>\n";
      }
      echo "</ul>\n";
    }
    die();
  }
}

if ($_GET['action']=='showYaml') { // Affiche en Yaml un enregistrement défini par l'uri 
  $dgouv = new HttpCache('dgouv');
  try {
    $record = json_decode($dgouv->request($_GET['uri']), true);
  }
  catch(Exception $e) {
    die("Erreur '".$e->getMessage()."'\n");
  }
  echo Yaml::dump([$_GET['uri'] => $record], 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),"\n";
  die();
}

if ($_GET['action']=='import') { // Génération de l'import 
  {/* algorithme:
    - lit les organisations listées par leur nom dans orgsel.yaml
    - pour chaque organisation
      - affiche le nom de l'organisation
      - récupère dans DataGouv ses datasets
      - élimine les datasets moissonnés au travers de la passerelle Inspire
      - affiche le titre du dataset
      - Standardise le dataset pour être conforme au schéma ../dido/import.schema.yaml
    - écrit dans le fichier import.yaml la liste des datasets standardisés,
      la conformité du fichier au schéma peut être testée
  */}
  function stdLicense(string $license): string { // standardise une licence 
    static $transcodages = [
      'notspecified'=> 'notspecified',
      'other-open'=> 'other-open',
      'fr-lo'=> 'https://www.etalab.gouv.fr/wp-content/uploads/2014/05/Licence_Ouverte.pdf', // version 1
      'lov2'=> 'https://www.etalab.gouv.fr/licence-ouverte-open-licence', // version 2
      'odc-by'=> 'https://opendatacommons.org/licenses/by/1-0/',
      'odc-odbl'=> 'https://opendatacommons.org/licenses/odbl/',
    ];
    return $transcodages[$license] ?? "'$license' non définie dans import ligne ".__LINE__;
  }

  function stdSpatial(array $spatial): array { // standardise la propriété spatial
    //if (!isset($spatial['zones']) || $spatial['zones'])
      //return [];
    $stdSpatial = [];
    foreach ($spatial['zones'] ?? [] as $zoneid) {
      $stdSpatial[] = "https://data.gouv.fr/api/1/spatial/zone/$zoneid/";
    }
    return $stdSpatial;
  }
  
  function stdDistributions(array $resources, array $dataset): array { // standardise une distribution 
    $distributions = [];
    foreach ($resources as $resource) {
      $distributions[] = [
        '@id'=> $resource['url'],
        '@type'=> 'Distribution',
        'title'=> $resource['title'],
        'license'=> stdLicense($dataset['license']),
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
  
  function stdDataset(array $dataset): array { // standardise une fiche de MDD
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
        'hasURL'=> $dataset['uri'], // contact à effectuer au travers de data.gouv
      ]],
      'accrualPeriodicity'=> $transcodages['accrualPeriodicity'][$dataset['frequency']] ?? "ERREUR sur $dataset[frequency]",
      'created'=> $dataset['created_at'].'Z',
      'modified'=> $dataset['last_modified'].'Z',
      'keyword'=> $dataset['tags'],
      'spatial'=> stdSpatial($dataset['spatial'] ?? []),
      'rights'=> $dataset['rights']  ?? null,
      'documentation'=> $dataset['documentation']  ?? [],
      'distribution'=> stdDistributions($dataset['resources'], $dataset),
    ];
    if (!$stdDataset['spatial'])
      unset($stdDataset['spatial']);
    if (!$stdDataset['rights'])
      unset($stdDataset['rights']);
    if (!$stdDataset['documentation'])
      unset($stdDataset['documentation']);
    return $stdDataset;
  }
  
  if ((php_sapi_name()<>'cli') && !CatInPgSql::chooseServer($_GET['server'] ?? null)) { // Choix du serveur en non CLI 
    echo "</pre>\n";
    echo "Choix du serveur:<ul>\n";
    echo "<li><a href='?action=$_GET[action]&amp;server=local'>local</a></li>\n";
    echo "<li><a href='?action=$_GET[action]&amp;server=distant'>distant</a></li>\n";
    die("</ul>\n");
  }

  $cat = new CatInPgSql('dgouv');
  $cat->create(); // (re)crée la table
  
  if (!is_file('orgsel.yaml')) {
    die("Erreur le fichier des organisations orgsel.yaml doit être créé\n");
  }
  $orgnames = Yaml::parsefile('orgsel.yaml')['orgs'];
  foreach ($orgnames as $i => $orgname)
    $orgnames[$i] = stdname($orgname);
  
  // recherche de la liste des URIs des orgs
  $dgouv = new HttpCache('dgouv');
  $url = "$API/organizations/";
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
    echo "$orguri:\n";
    foreach ($datasets['data'] as $i => $dataset) {
      if (isset($dataset['extras']['inspire:identifier'])) // s'il a été moissonné alors je l'élimine 
        continue;
      $stdDataset = stdDataset($dataset);
      echo "  $i: $stdDataset[title]\n";
      $stdDatasets[] = $stdDataset;
      $cat->storeRecord($stdDataset, '@id');
    }
  }

  PgSql::query("update catalogdgouv set perimetre='Min'");

  file_put_contents(
    'import.yaml',
    str_replace(["\n  -\n    ","\n      -\n        "], ["\n  - ","\n      - "],
       Yaml::dump([
        'title'=> "Liste des datasets structurés issus de DataGouv",
        '$schema'=> '../dido/import',
        'created'=> date(DATE_ATOM),
        'dataset'=> $stdDatasets,
      ], 5, 2)));
  die();
}

// Découverte des fonctionnalités de localisation géographique de DataGouv
if ($_GET['action']=='spatial') { // liste des couches (levels)
  $dgouv = new HttpCache('dgouv');
  $levels = json_decode($dgouv->request("$API/spatial/levels/"), true);
  echo Yaml::dump(['/spatial/levels/'=> $levels]);
  echo "</pre>Liste des couches:<ul>\n";
  foreach ($levels as $level) {
    echo "<li>$level[name]: ",
          "<a href='?action=features&amp;level=$level[id]'>features</a>, ",
          "<a href='?action=children&amp;level=$level[id]'>children</a></li>\n";
  }
  die("</ul>\n");
}

if ($_GET['action']=='features') { // liste des objets de la couche 
  ini_set('max_execution_time', 60);
  $dgouv = new HttpCache('dgouv', ['timeout'=> 30.0]);
  $coverage = json_decode($dgouv->request("$API/spatial/coverage/$_GET[level]/"), true);
  foreach ($coverage['features'] as $no => $feature) {
    if ($no < 20)
      $coverage['features'][$no]['geometry'] = '...';
    elseif ($no == 20)
      $coverage['features'][$no] = '...';
    else
      unset($coverage['features'][$no]);
  }
  echo Yaml::dump(["/spatial/coverage/$_GET[level]/" => $coverage], 8, 2);
  die();
}

if ($_GET['action']=='children') { // liste des objets de la couche avec liens vers children 
  $dgouv = new HttpCache('dgouv');
  $coverage = json_decode($dgouv->request("$API/spatial/coverage/$_GET[level]/"), true);
  echo "</pre><ul>\n";
  foreach ($coverage['features'] as $no => $feature) {
    echo "<li><a href='$API/spatial/zone/$feature[id]/children/'>",$feature['properties']['name']," ($feature[id])</li>\n";
  }
  die();
}

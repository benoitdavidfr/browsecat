<?php
/*PhpDoc:
title: ajouttheme.php - ajout de thèmes
name: ajouttheme.php
doc: |
  Test de l'ajout de thèmes Covadis par analyse du titre.
  J'associe dans l'arbo Covadis à chaque thème une liste d'expressions régulières que je teste sur les titres des fiches
  de MDD qui n'ont pas de thème Covadis.
  En première passe (7/11), j'ai ajouté au moins un thème Covadis sur 832 fiches de DtAra sur 1005 soit 83%.
  Sans modifs spécifiques, cela rajoute au moins un thème Covadis sur 228 fiches de DtAra sur  540 soit 42%.
journal: |
  6-7/11/2021:
    - création
includes: [cats.inc.php, catinpgsql.inc.php, arbo.inc.php]
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/arbo.inc.php';

use Symfony\Component\Yaml\Yaml;


// Renvoie la liste prefLabels structurée par arbo, [ {arboid} => [ {prefLabel} ]]
function prefLabels(array $keywords, array $arbos): array {
  $prefLabels = []; // liste des prefLabels des mots-clés structuré par arbo, sous forme [arboid => [prefLabel => 1]]
  foreach ($keywords as $keyword) {
    //echo "<pre>"; print_r($keyword); echo "</pre>\n";
    if ($kwValue = $keyword['value'] ?? null) {
      foreach ($arbos as $arboid => $arbo) {
        if ($prefLabel = $arbo->prefLabel($kwValue)) {
          $prefLabels[$arboid][$prefLabel] = 1;
        }
      }
    }
  }
  ksort($prefLabels);
  foreach ($prefLabels as $arboid => $labels) {
    $prefLabels[$arboid] = array_keys($labels);
  }
  //echo "<pre>prefLabels(",Yaml::dump($keywords),") -> ",Yaml::dump($prefLabels),"</pre>";
  return $prefLabels;
}

if (php_sapi_name()=='cli') {
  if ($argc <= 1) {
    echo "usage: php $argv[0] {cat}|all\n";
    echo " où {cat} vaut:\n";
    foreach ($cats as $catid => $cat)
      echo " - $catid\n";
    echo " et où {firstRecord} est le num. du premier enregistrement requêté, par défaut 1\n";
    die();
  }

  $catid = $argv[1];

  if ($catid == 'all') { // génère les cmdes pour traiter tous les catalogues
    foreach (array_keys($cats) as $catid) {
      echo "php $argv[0] $catid\n";
    }
    die();
  }
}
else { // php_sapi_name()<>'cli'
  if (!isset($_GET['cat'])) { // choix du catalogue ou actions globales
    echo "Catalogues:<ul>\n";
    foreach ($cats as $catid => $cat) {
      echo "<li><a href='?cat=$catid'>$catid</a></li>\n";
    }
    echo "</ul>\n";
    die();
  }
  $catid = $_GET['cat'];
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>ajouttheme.php</title></head><body><pre>\n";
}

if (is_file('localhost.inc.php')) { // Choix du serveur
  echo "Serveur local<br>\n";
  PgSql::open('host=pgsqlserver dbname=gis user=docker');
}
else {
  echo "Serveur OVH<br>\n";
  PgSql::open('pgsql://benoit@db207552-001.dbaas.ovh.net:35250/catalog/public');
}

$arboCovadis = new Arbo('arbocovadis.yaml');

foreach($arboCovadis->nodes() as $theme) {
  foreach ($theme->regexps() as $regexp)
    $matches[Arbo::simplif($regexp)] = ['theme'=> (string)$theme, 'nbre'=> 0];
}

$nbMdd = 0;
$nbAjouts = 0;
$sql = "select id,title, record from catalog$catid
        where type in ('dataset','series') and perimetre='Min'";
foreach (PgSql::query($sql) as $tuple) {
  $record = json_decode($tuple['record'], true);
  if ($prefLabels = prefLabels($record['keyword'] ?? [], ['a'=>$arboCovadis]))
    continue;
  //echo " - $tuple[title]\n";
  $keywords = [];
  foreach ($matches as $label => $match) {
    if (preg_match("!$label!i", Arbo::simplif($tuple['title']))) {
      $keywords[$match['theme']] = 1;
      $matches[$label]['nbre']++;
    }
  }
  if ($keywords) {
    //echo "   + ",implode(', ', array_keys($keywords)),"\n";
    foreach (array_keys($keywords) as $kw)
      $record['keyword'][] = [
        'value'=> $kw,
        'thesaurusTitle'=>"ajouttheme.php/arbocovadis",
        'thesaurusDate'=> date('Y-m-d'),
        'thesaurusDateType'=> 'publication',
        'thesaurusId'=> 'http://localhost/browsecat/ajouttheme.php/arbocovadis',
      ];
    //print_r($record);
    $cat = new CatInPgSql($catid);
    $cat->updateRecord($tuple['id'], $record);
    $nbAjouts++;
  }
  else {
    $noMatches[] = $tuple['title'];
  }
  $nbMdd++;
}

printf("%d / %d soit %.0f %%\n", $nbAjouts, $nbMdd, $nbAjouts/$nbMdd*100);

echo Yaml::dump($matches);

echo Yaml::dump(['$noMatches' => $noMatches]);


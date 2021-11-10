<?php
/*PhpDoc:
title: geojson.php - sortie GéoJSON du catalogue
name: geojson.php
doc: |
journal: |
  10/11/2021:
    - adaptation du code EN COURS pour utiliser les tables auxiliaires créées par crauxtabl
  7-8/11/2021
    - création
    - ajout d'un champ area dans la base pour ne pas avoir à charger tous les éléments pour les trier
    - encore trop long, la requête sur les PPRN de agg excède 3'
    - faire des tables index sur les organisations et les thèmes ?
*/
//ini_set('max_execution_time', 60);

require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/arbo.inc.php';
require_once __DIR__.'/annexes.inc.php';
require_once __DIR__.'/orginsel.inc.php';

// Choisir le serveur
PgSql::open('host=pgsqlserver dbname=gis user=docker');
//PgSql::open('pgsql://browsecat:Browsecat9@db207552-001.dbaas.ovh.net:35250/catalog/public');

function isOrg(string $otype, string $orgname, array $record): bool { // Teste si $_GET['org'] fait partie des $_GET['type']
  //echo "isOrg(otype=$otype, organme=$orgname)\n";
  static $arboOrgsPMin = null;
  if (!$arboOrgsPMin)
    $arboOrgsPMin = new Arbo('orgpmin.yaml');
  if ($orgname <> 'NO ORG') {
    foreach ($record[$otype] as $org) {
      //print_r($org);
      if (!isset($org['organisationName'])) continue;
      $plabel = $arboOrgsPMin->prefLabel($org['organisationName']);
      if ($plabel == $orgname) {
        //echo "isOrg()->true\n";
        return true;
      }
    }
    // $isOrg <=> au moins une des organisations est celle demandée
    //echo "isOrg()->false\n";
    return false;
  }
  else { // ($_GET['org'] == 'NO ORG') // cas où aucune organisation n'a d'organisationName
    foreach ($record[$otype] as $org) {
      if (isset($org['organisationName']))
        echo $arboOrgsPMin->prefLabel($org['organisationName']),"<br>\n";
      else
        echo "organisationName non défini<br>\n";
      if (isset($org['organisationName']) && $arboOrgsPMin->prefLabel($org['organisationName']))
        return false;
    }
    return true;
  }
}

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

// Teste si $theme fait partie des mots-clés de $record
function isTheme(string $arbo, string $theme, array $record): bool {
  $arbos = [
    'arboCovadis'=> new Arbo('arbocovadis.yaml'),
    'annexesInspire'=> new Arbo('annexesinspire.yaml'),
  ];
  $prefLabels = prefLabels($record['keyword'] ?? [], ['a'=>$arbos[$arbo]]);
  $isTheme = false;
  foreach ($prefLabels['a'] ?? ['NON CLASSE'] as $plabel) {
    if ($plabel == $theme)
      return true;
  }
  return false;
}

header('Content-type: application/json');
echo '{"type": "FeatureCollection",',"\n";

$i = 0;
$sql = "select cat.id, title, record
        from catalog$_GET[cat] cat, catorg$_GET[cat] org, cattheme$_GET[cat] theme
        where
          type in ('dataset','series') and perimetre='Min' and area <> 0
          and cat.id=org.id and org.org='".str_replace("'","''", $_GET['org'])."'
          and cat.id=theme.id and theme.theme='".str_replace("'","''", $_GET['theme'])."'
        order by area desc";
//echo "\"query\": \"",str_replace("\n",' ',$sql),"\",\n";
echo '"features": [',"\n";
foreach (PgSql::query($sql) as $tuple) {
  $record = json_decode($tuple['record'], true);
  
  /*// Teste si $_GET['org'] fait partie des $_GET['type'] dans $record
  if (isset($_GET['org']) && !isOrg($_GET['otype'], $_GET['org'], $record))
    continue;
  
  // Teste si $_GET['theme'] fait partie des mots-clés de $record
  if (isset($_GET['theme']) && !isTheme($_GET['arbo'], $_GET['theme'], $record))
    continue;*/
  
  //echo "<li><a href='gere.php?cat=$_GET[cat]&amp;action=showPg&amp;id=$tuple[id]'>$tuple[title]</a></li>\n";
  
  if (!($bbox = $record['dcat:bbox'][0] ?? null)) continue;
  
  $feature = [
    'type'=> 'Feature',
    'area'=> ($bbox['eastLon']-$bbox['westLon']) * ($bbox['northLat']-$bbox['southLat']),
    'link'=> "http://localhost/browsecat/gere.php?cat=$_GET[cat]&action=showPg&id=$tuple[id]",
    'properties'=> [
      'title'=> $tuple['title'],
      'lon'=> sprintf('%.4f -> %.4f', $bbox['westLon'], $bbox['eastLon']),
      'lat'=> sprintf('%.4f -> %.4f', $bbox['southLat'], $bbox['northLat']),
      'area'=> ($bbox['eastLon']-$bbox['westLon']) * ($bbox['northLat']-$bbox['southLat']),
      'id'=> $tuple['id'],
    ]
  ];
  if ($bbox['westLon'] > $bbox['eastLon']) { // si erreur alors échange westLon <-> eastLon
    $westLon = $bbox['westLon'];
    $bbox['westLon'] = $bbox['eastLon'];
    $bbox['eastLon'] = $westLon;
  }
  if ($bbox['southLat'] > $bbox['northLat']) { // si erreur alors échange southLat <-> northLat
    $southLat = $bbox['southLat'];
    $bbox['southLat'] = $bbox['northLat'];
    $bbox['northLat'] = $southLat;
    continue;
  }
  switch ($_GET['gtype']) {
    case 'Polygon': {
      $feature['geometry'] = [
        'type'=> 'Polygon',
        'coordinates'=> [
          [
            [$bbox['westLon'], $bbox['southLat']],
            [$bbox['westLon'], $bbox['northLat']],
            [$bbox['eastLon'], $bbox['northLat']],
            [$bbox['eastLon'], $bbox['southLat']],
            [$bbox['westLon'], $bbox['southLat']],
          ]
        ],
      ];
      break;
    }

    case 'LineString': {
      $feature['geometry'] = [
        'type'=> 'LineString',
        'coordinates'=> [
            [$bbox['westLon'], $bbox['southLat']],
            [$bbox['eastLon'], $bbox['northLat']],
        ],
      ];
      break;
    }
    
    case 'Point': {
      $feature['geometry'] = [
        'type'=> 'Point',
        'coordinates'=> [
          ($bbox['westLon']+$bbox['eastLon'])/2,
          ($bbox['southLat']+$bbox['northLat'])/2,
        ],
      ];
      break;
    }
  }
  if ($i) echo ",\n"; // séparateur entre features sauf au début et à la fin
  $i++;
  echo json_encode($feature,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}

die("\n]}\n");

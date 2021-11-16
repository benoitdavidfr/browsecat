<?php
/*PhpDoc:
title: geojson.php - sortie GéoJSON du catalogue ou d'une partie
name: geojson.php
doc: |
  Génère un Feature GeoJSON par bbox associé à une fiche de métadonnées
  grâce à l'utilisation des table catbbox généré dans addbbox
  Si plusieurs fiches s'appuient sur le même bbox, ces fiches sonr regroupées pour en afficher un seul.
journal: |
  16/11/2021:
    - utilisation de catbbox
  15/11/2021:
    - modif du lien pour référence a.php
    - ajout paramètre id pour fabriquer une carte de situation
  11/11/2021:
    - regroupement des bbox identiques en un seul
  10/11/2021:
    - adaptation du code EN COURS pour utiliser les tables auxiliaires créées par crauxtabl
  7-8/11/2021
    - création
    - ajout d'un champ area dans la base pour ne pas avoir à charger tous les éléments pour les trier
    - encore trop long, la requête sur les PPRN de agg excède 3'
    - faire des tables index sur les organisations et les thèmes ?
includes: [cats.inc.php, catinpgsql.inc.php, arbo.inc.php, orginsel.inc.php]
*/
//ini_set('max_execution_time', 60);

header('Access-Control-Allow-Origin: *');

require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/arbo.inc.php';
require_once __DIR__.'/orginsel.inc.php';

if (!CatInPgSql::chooseServer($_SERVER['HTTP_HOST']=='localhost' ? 'local' : 'distant')) { // Choix du serveur 
  die("Erreur dans  CatInPgSql::chooseServer()!\n");
}

/*function isOrg(string $otype, string $orgname, array $record): bool { // Teste si $_GET['org'] fait partie des $_GET['type']
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
}*/

// Renvoie la liste prefLabels structurée par arbo, [ {arboid} => [ {prefLabel} ]]
/*function prefLabels(array $keywords, array $arbos): array {
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
}*/

// Teste si $theme fait partie des mots-clés de $record
/*function isTheme(string $arbo, string $theme, array $record): bool {
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
}*/

class Feature {
  private array $feature;
  
  static function geometry(string $gtype, array $bbox): array { // construit la géométrie pour un bbox
    if ($bbox['westLon'] > $bbox['eastLon']) { // si erreur alors échange westLon <-> eastLon
      $westLon = $bbox['westLon'];
      $bbox['westLon'] = $bbox['eastLon'];
      $bbox['eastLon'] = $westLon;
    }
    if ($bbox['southLat'] > $bbox['northLat']) { // si erreur alors échange southLat <-> northLat
      $southLat = $bbox['southLat'];
      $bbox['southLat'] = $bbox['northLat'];
      $bbox['northLat'] = $southLat;
    }
    switch ($gtype) {
      case 'xPolygon': {
        return [
          'type'=> 'Polygon',
          'coordinates'=> [ // le bbox
            [
              [round($bbox['westLon'],6), round($bbox['southLat'],6)],
              [round($bbox['westLon'],6), round($bbox['northLat'],6)],
              [round($bbox['eastLon'],6), round($bbox['northLat'],6)],
              [round($bbox['eastLon'],6), round($bbox['southLat'],6)],
              [round($bbox['westLon'],6), round($bbox['southLat'],6)],
            ]
          ],
        ];
      }
      
      case 'Polygon': {
        $e = $bbox['eastLon'];
        $w = $bbox['westLon'];
        $dlon = $e - $w;
        $s = $bbox['southLat'];
        $n = $bbox['northLat'];
        $dlat = $n - $s;
        return [
          'type'=> 'Polygon',
          'coordinates'=> [ // coins cassés
            [
              [round($w,6), round($s+$dlat/10,6)],
              [round($w,6), round($n-$dlat/10,6)],
              [round($w+$dlon/10,6), round($n,6)],
              [round($e-$dlon/10,6), round($n,6)],
              [round($e,6), round($n-$dlat/10,6)],
              [round($e,6), round($s+$dlat/10,6)],
              [round($e-$dlon/10,6), round($s,6)],
              [round($w+$dlon/10,6), round($s,6)],
              [round($w,6), round($s+$dlat/10,6)],
            ]
          ],
        ];
      }

      case 'LineString': {
        return [
          'type'=> 'LineString',
          'coordinates'=> [
              [$bbox['westLon'], $bbox['southLat']],
              [$bbox['eastLon'], $bbox['northLat']],
          ],
        ];
      }
    
      case 'Point': {
        return [
          'type'=> 'Point',
          'coordinates'=> [
            ($bbox['westLon']+$bbox['eastLon'])/2,
            ($bbox['southLat']+$bbox['northLat'])/2,
          ],
        ];
      }
    }
  }
  
  /*static function multiGeometry(string $gtype, array $bboxes): array { // construit la géométrie pour plusieurs bbox
    $geometry = [
      'type'=> "Multi$gtype",
      'coordinates'=> [],
    ];
    foreach ($bboxes as $bbox) {
      $gbbox = self::geometry($gtype, $bbox);
      $geometry['coordinates'][] = $gbbox['coordinates'];
    }
    return $geometry;
  }*/
  
  function __construct(string $gtype, array $tuple, array $bbox) {
    $this->feature = [
      'type'=> 'Feature',
      'area'=> ($bbox['eastLon']-$bbox['westLon']) * ($bbox['northLat']-$bbox['southLat']),
      'link'=> "http://$_SERVER[HTTP_HOST]/browsecat/a.php?cat=$_GET[cat]&action=showPg&id=$tuple[id]",
      'style'=> ['color'=> 'blue', 'fillOpacity'=> 0],
      'properties'=> [
        'title'=> $tuple['title'],
        'lon'=> sprintf('%.4f -> %.4f', $bbox['westLon'], $bbox['eastLon']),
        'lat'=> sprintf('%.4f -> %.4f', $bbox['southLat'], $bbox['northLat']),
        'area'=> ($bbox['eastLon']-$bbox['westLon']) * ($bbox['northLat']-$bbox['southLat']),
        'id'=> $tuple['id'],
      ],
      'geometry'=> self::geometry($gtype, $bbox),
    ];
  }
  
  function aggFeatures(array $tuple, array $bbox): void {
    $westLon = min($bbox['westLon'], $bbox['eastLon']);
    $southLat = min($bbox['southLat'], $bbox['northLat']);
    $eastLon = max($bbox['westLon'], $bbox['eastLon']);
    $northLat = max($bbox['southLat'], $bbox['northLat']);
    if (isset($this->feature['properties']['id'])) {
      if ($tuple['id'] == $this->feature['properties']['id'])
        return;
      $this->feature['ids'] = [$this->feature['properties']['id'], $tuple['id']];
      unset($this->feature['properties']['id']);
      $this->feature['properties']['title'] = [ $this->feature['properties']['title'], $tuple['title'] ];
    }
    else {
      if (in_array($tuple['id'], $this->feature['ids']))
        return;
      $this->feature['ids'][] = $tuple['id'];
      $this->feature['properties']['title'][] = $tuple['title'];
    }
    $this->feature['link'] = "http://$_SERVER[HTTP_HOST]/browsecat/a.php?cat=$_GET[cat]&action=showUsingIds"
      ."&ids=".implode(',',$this->feature['ids']);
  }
  
  function __toString(): string {
    return json_encode($this->feature,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  }
}

function sep(): string {
  static $i=0;
  return $i++ ? ",\n" : ''; // séparateur entre features sauf au début et à la fin
}

//header('Content-type: application/json');
header('Content-type: text/plain');
echo '{"type": "FeatureCollection",',"\n";

$sql = "select cat.id, title, record, nobbox, area
        from catalog$_GET[cat] cat, catbbox$_GET[cat] bbox"
       .(isset($_GET['org']) ? ", catorg$_GET[cat] org" : '')
       .(isset($_GET['theme']) ? ", cattheme$_GET[cat] theme" : '')."
        where
          type in ('dataset','series') and perimetre='Min' and cat.id=bbox.id\n"
  .(isset($_GET['id']) ? "and cat.id='$_GET[id]'\n" : '');
if (isset($_GET['org']))
  $sql .= "and cat.id=org.id and org.org='".str_replace("'","''", $_GET['org'])."'\n";
if (isset($_GET['theme']))
  $sql .= "and cat.id=theme.id and theme.theme='".str_replace("'","''", $_GET['theme'])."'\n";
$sql .= "order by area desc,westLon,southLat,eastLon";
echo "\"query\": \"",str_replace("\n",' ',$sql),"\",\n";
//echo "\"query\": \"",str_replace("\n",' ',$sql),"\"\n"; die("\n]}\n");
echo '"features": [',"\n";
$i = 0;
$feature = null;
$prevTuple = [];
foreach (PgSql::query($sql) as $tuple) {
  $record = json_decode($tuple['record'], true);
  
  //echo "<li><a href='gere.php?cat=$_GET[cat]&amp;action=showPg&amp;id=$tuple[id]'>$tuple[title]</a></li>\n";
  
  if (!($bboxes = $record['dcat:bbox'] ?? null)) continue;
  if (!($bbox = $bboxes[$tuple['nobbox']] ?? null)) continue;
  
  //print_r($tuple);
  if ($prevTuple && ($tuple['area'] == $prevTuple['area'])) {
    //echo sep(),"\"$tuple[id] a même area que $prevTuple[id] -> $tuple[area]\"";
    $feature->aggFeatures($tuple, $bbox);
  }
  else {
    if ($feature)
      echo sep(),$feature;
    $feature = new Feature($_GET['gtype'], $tuple, $bbox);
  }
  $prevTuple = $tuple;
}
if ($feature) {
  echo sep(),$feature;
}

die("\n]}\n");

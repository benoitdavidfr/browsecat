<?php
/*PhpDoc:
title: geojson.php - sortie GéoJSON du catalogue ou d'une partie
name: geojson.php
doc: |
  Génère un flux GeoJSON de BBox des objets du catalogue ou d'une partie.
  Deux types de fonctionnement:
    - si le paramètre $_GET['id'] est défini alors génération d'un Feature correspondant au BBox de la fiche
      ou aux BBox de la fiche. Seule la table contenant le catalog est utilisée.
      Permet de faire des cartes de situation avec des données minimum.
    - SINON le fichier contient un Feature par BBox et avec l'utilisation des table catbbox généré dans addbbox,
      si plusieurs fiches s'appuient sur le même bbox, ces fiches sonr regroupées pour en afficher un seul.
      De plus, les BBox sont triés du plus grand au plus petit pour faciliter l'interaction avec eux.
journal: |
  17/11/2021:
    - cas particulier lorsque id est défini pour afficher alors éventuellement une multiGeometry
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
includes: [cats.inc.php, catinpgsql.inc.php]
*/
//ini_set('max_execution_time', 60);

header('Access-Control-Allow-Origin: *');

require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';

if (!CatInPgSql::chooseServer($_SERVER['HTTP_HOST']=='localhost' ? 'local' : 'distant')) { // Choix du serveur 
  die("Erreur dans  CatInPgSql::chooseServer()!\n");
}

// classe utilisée pour construire et agréger des bbox dans un Feature GeoJSON et l'afficher en JSON.
class Feature {
  private array $feature; // stockage d'un Feature GeoJSON
  
  // Génère la géométrie GeoJSON pour un bbox en fonction du gtype
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
  
  // Génère la multi-géométrie GeoJSON pour un ensemble de bbox en fonction du gtype
  static function multiGeometry(string $gtype, array $bboxes): array { // construit la géométrie pour plusieurs bbox
    $geometry = [
      'type'=> "Multi$gtype",
      'coordinates'=> [],
    ];
    foreach ($bboxes as $bbox) {
      $gbbox = self::geometry($gtype, $bbox);
      $geometry['coordinates'][] = $gbbox['coordinates'];
    }
    return $geometry;
  }
  
  function __construct(string $gtype, array $tuple, array $bboxes) { // crée un Feature pour un ensemble de bbox
    $this->feature = [
      'type'=> 'Feature',
      'area'=> ($bboxes[0]['eastLon']-$bboxes[0]['westLon']) * ($bboxes[0]['northLat']-$bboxes[0]['southLat']),
      'link'=> "http://$_SERVER[HTTP_HOST]/browsecat/a.php?cat=$_GET[cat]&action=showPg&id=$tuple[id]",
      'style'=> ['color'=> 'blue', 'fillOpacity'=> 0],
      'properties'=> [
        'title'=> $tuple['title'],
        'lon'=> sprintf('%.4f -> %.4f', $bboxes[0]['westLon'], $bboxes[0]['eastLon']),
        'lat'=> sprintf('%.4f -> %.4f', $bboxes[0]['southLat'], $bboxes[0]['northLat']),
        'area'=> ($bboxes[0]['eastLon']-$bboxes[0]['westLon']) * ($bboxes[0]['northLat']-$bboxes[0]['southLat']),
        'id'=> $tuple['id'],
      ],
      'geometry'=> (count($bboxes) > 1) ? self::multiGeometry($gtype, $bboxes) : self::geometry($gtype, $bboxes[0]),
    ];
  }
  
  function aggFeatures(array $tuple, array $bbox): void { // agrège un nouvel objet dans le même bbox
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
  
  function __toString(): string { // affiche le Feature en JSON
    return json_encode($this->feature,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  }
}

function sep(): string { // affiche rien lors du premier appel puis ",\n", permet d'afficher simplement un séparateur JSON
  static $i=0;
  return $i++ ? ",\n" : ''; // séparateur entre features sauf au début et à la fin
}

function usage(string $errorMessage) { // aide à l'utilisation du script + exemples utilisables pour tester le script
  header('HTTP/1.1 400 Bad Request');
  header('Content-type: text/html');
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>geojson.php</title></head><body><pre>\n";
  echo "<b>$errorMessage</b>\n";
  echo "Les paramètres de ce script sont:\n";
  echo " - gtype : le type de géométrie (Point, LineString, Polygon) (obligatoire)\n";
  echo " - cat : le catalogue interrogé (obligatoire)\n";
  echo " - id : l'identifiant de la fiche pour laquelle le bbox est demandé (facultatif)\n";
  echo " - org : le nom de l'organisme responsable des fiches pour lesquelles le bbox est demandé (facultatif)\n";
  echo " - theme : le libellé du thème des fiches pour lesquelles le bbox est demandé (facultatif)\n";
  echo "Exemples:\n";
  foreach ([
      "gtype=Polygon&cat=GeoRisques&org=Direction+G%C3%A9n%C3%A9rale+de+la+Pr%C3%A9vention+des+Risques+%28DGPR%29"
        ."&theme=NON%20CLASSE",
      "gtype=Polygon&cat=GeoRisques&theme=NON%20CLASSE",
      "gtype=Polygon&cat=GeoRisques",
      "gtype=Polygon&cat=GeoRisques&id=1c128799-b6cd-4c8a-8859-8addec012dec",
    ] as $url) {
      echo " - <a href='?$url'>$url</a>\n";
  }
  die();
}

if (!isset($_GET['gtype'])) { usage("Erreur: paramètre GET 'gtype' obligatoire"); }

if (!isset($_GET['cat'])) { usage("Erreur: paramètre GET 'cat' obligatoire"); }

if (!isset($cats[$_GET['cat']])) { // Erreur: catalogue incorrect 
  header('HTTP/1.1 400 Bad Request');
  header('Content-type: text/html');
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>geojson.php</title></head><body><pre>\n";
  echo "<b>Erreur: le catalogue '$_GET[cat]' n'existe pas</b>\n";
  echo "La liste des catalogues est:\n";
  foreach (array_keys($cats) as $catid)
    echo " - $catid\n";
  die();
}

//header('Content-type: application/json');
header('Content-type: text/plain');
echo '{"type": "FeatureCollection",',"\n";


if (isset($_GET['id'])) { // cas simple d'affichage du bbox ou multi-bbox correspondant à une fiche 
  $sql = "select id, title, record from catalog$_GET[cat] where id='$_GET[id]'";
  echo "\"query\": \"$sql\",\n";
  echo '"features": [',"\n";
  $tuple = PgSql::getTuples($sql)[0];
  $record = json_decode($tuple['record'], true);
  if ($bboxes = $record['dcat:bbox'] ?? null) {
    $feature = new Feature($_GET['gtype'], $tuple, $bboxes);
    echo $feature;
  }
}
else { // cas d'affichage d'un ensemble de bbox avec agrégation des bbox identiques 
  $sql = "select cat.id, title, record, nobbox, area
          from catalog$_GET[cat] cat, catbbox$_GET[cat] bbox"
         .(isset($_GET['org']) ? ", catorg$_GET[cat] org" : '')
         .(isset($_GET['theme']) ? ", cattheme$_GET[cat] theme" : '')."
          where
           type in ('dataset','series') and perimetre='Min' and cat.id=bbox.id\n";
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
      $feature = new Feature($_GET['gtype'], $tuple, [$bbox]);
    }
    $prevTuple = $tuple;
  }
  if ($feature) {
    echo sep(),$feature;
  }
}

die("\n]}\n");

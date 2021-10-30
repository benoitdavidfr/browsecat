<?php
/*PhpDoc:
title: geojson.php - sortie GéoJSON du catalogue
name: geojson.php
doc: |
journal: |
*/
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/arbo.inc.php';
require_once __DIR__.'/annexes.inc.php';
require_once __DIR__.'/orginsel.inc.php';

$features = [];

foreach (PgSql::query("select id,title,record from catalogdatAra where perimetre='Min'") as $tuple) {
  $record = json_decode($tuple['record'], true);
  $bbox = $record['dcat:bbox'][0] ?? null;
  if (!$bbox) continue;
  if ($bbox['westLon'] > $bbox['eastLon']) continue;
  if ($bbox['southLat'] > $bbox['northLat']) continue;
  
  $feature = [
    'type'=> 'Feature',
    'area'=> ($bbox['eastLon']-$bbox['westLon']) * ($bbox['northLat']-$bbox['southLat']),
    'link'=> "http://localhost/browsecat/index.php?cat=datAra&action=showPg&id=$tuple[id]",
    'properties'=> [
      'title'=> $tuple['title'],
      'lon'=> sprintf('%.4f -> %.4f', $bbox['westLon'], $bbox['eastLon']),
      'lat'=> sprintf('%.4f -> %.4f', $bbox['southLat'], $bbox['northLat']),
      'id'=> $tuple['id'],
    ]
  ];
  switch ($_GET['type']) {
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

  $features[] = $feature;
}

function cmp($a, $b): int { return $a['area'] > $b['area'] ? -1 : 1; }

usort($features, "cmp");

header('Content-type: application/json');
die(json_encode(['type'=> 'FeatureCollection', 'features'=> $features]));


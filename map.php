<?php
/*PhpDoc:
name: map.php
title: map.php - carte Leaflet
doc: |
journal: |
  29/10/2021:
    - création
includes: [ lib/accesscntrl.inc.php ]
*/
$browsecaturl = "http://localhost/browsecat";
$center = (isset($_GET['center']) ? explode(',',$_GET['center']) : [46.5, 3]);
$center[0] = $center[0]+0;
$center[1] = $center[1]+0;
$zoom = (isset($_GET['zoom']) ? $_GET['zoom'] : 6);
?>
<!DOCTYPE HTML><html><head>
  <title>carte des MDD</title>
  <meta charset="UTF-8">
  <!-- meta nécessaire pour le mobile -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <!-- styles nécessaires pour le mobile -->
  <link rel='stylesheet' href='leaflet/llmap.css'>
  <!-- styles et src de Leaflet -->
  <link rel="stylesheet" href='leaflet/leaflet.css'/>
  <script src='leaflet/leaflet.js'></script>
  <!-- Include the edgebuffer plugin -->
  <script src="leaflet/leaflet.edgebuffer.js"></script>
  <!-- Include the Control.Coordinates plugin -->
  <link rel='stylesheet' href='leaflet/Control.Coordinates.css'>
  <script src='leaflet/Control.Coordinates.js'></script>
  <!-- Include the uGeoJSON plugin -->
  <script src="leaflet/leaflet.uGeoJSON.js"></script>
  <!-- plug-in d'appel des GeoJSON en AJAX -->
  <script src='leaflet/leaflet-ajax.js'></script>
</head>
<body>
  <div id="map" style="height: 100%; width: 100%"></div>
  <script>
var browsecaturl = <?php echo "'$browsecaturl';\n"; ?>

// affichage des caractéristiques de chaque MD
var onEachFeature = function (feature, layer) {
  layer.bindPopup(
    '<pre>'
    + JSON.stringify(feature.properties,null,' ')
    + '</pre>' + "\n"
    + "<a href='" + feature.link + "' target='blank'>lien</a>"
  );
  layer.bindTooltip(feature.properties.title);
}

var map = L.map('map').setView(<?php echo json_encode($center),",$zoom";?>);  // view pour la zone
L.control.scale({position:'bottomleft', metric:true, imperial:false}).addTo(map);

// activation du plug-in Control.Coordinates
var c = new L.Control.Coordinates();
c.addTo(map);
map.on('click', function(e) { c.setCoordinates(e); });

var baseLayers = {
  // IGN
  "IGN" : new L.TileLayer(
    'https://igngp.geoapi.fr/tile.php/plan-ignv2/{z}/{x}/{y}.png',
    { "format":"image/jpeg","minZoom":0,"maxZoom":18,"detectRetina":false,
      "attribution":"&copy; <a href='http://www.ign.fr' target='_blank'>IGN</a>"
    }
  ),
  // OSM
  "OSM" : new L.TileLayer(
    'http://{s}.tile.osm.org/{z}/{x}/{y}.png',
    {"attribution":"&copy; <a href='https://www.openstreetmap.org/copyright' target='_blank'>les contributeurs d’OpenStreetMap</a>"}
  ),
  // Fond blanc
  "Fond blanc" : new L.TileLayer(
    'https://visu.gexplor.fr/utilityserver.php/whiteimg/{z}/{x}/{y}.jpg',
    { format: 'image/jpeg', minZoom: 0, maxZoom: 21, detectRetina: false}
  )
};
map.addLayer(baseLayers["IGN"]);

var overlays = {
  "BBox" : new L.GeoJSON.AJAX(browsecaturl+'/geojson.php?type=Polygon', {
    style: { color: 'blue', fillOpacity: 0}, minZoom: 0, maxZoom: 18, onEachFeature: onEachFeature
  }),
  "Line" : new L.GeoJSON.AJAX(browsecaturl+'/geojson.php?type=LineString', {
    style: { color: 'blue', fillOpacity: 0}, minZoom: 0, maxZoom: 18, onEachFeature: onEachFeature
  }),
  "Point" : new L.GeoJSON.AJAX(browsecaturl+'/geojson.php?type=Point', {
    style: { color: 'blue', fillOpacity: 0}, minZoom: 0, maxZoom: 18, onEachFeature: onEachFeature
  }),
};

L.control.layers(baseLayers, overlays).addTo(map);
    </script>
  </body>
</html>

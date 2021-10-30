<?php
/*PhpDoc:
title: cats.inc.php - liste des catalogues
name: cats.inc.php
doc: |
journal: |
  21/10/2021:
    - création
*/

$cats = [
  // catalogues nationaux
  'geoide'=> [ // uniquement les MDD
    'endpointURL'=> 'http://ogc.geo-ide.developpement-durable.gouv.fr/csw/dataset-harvestable',
  ],
  'eauFrance' => [
    'endpointURL'=> 'http://www.data.eaufrance.fr/geosource/srv/fre/csw',
  ],
  'Sextant'=> [
    'endpointURL'=> 'https://sextant.ifremer.fr/geonetwork/srv/eng/csw',
  ],
  'GeoRisques'=> [
    'endpointURL'=> 'https://catalogue.georisques.gouv.fr/geonetwork/srv/fre/csw',
  ],
  'cerema'=> [
    'endpointURL'=> 'https://www.cdata.cerema.fr/geonetwork/srv/fre/csw-catalogue-cdata',
  ],
  
  // catalogues MonGeosource
  'GpU'=> [
    'endpointURL'=> 'http://www.mongeosource.fr/geosource/1270/fre/csw',
  ],
  'geolittoral'=> [
    'endpointURL'=> 'https://www.mongeosource.fr/geosource/1111/fre/csw',
  ],
  'drealNormandie'=> [
    'endpointURL'=> 'http://metadata.carmencarto.fr/geonetwork/8/fre/csw',
  ],
  'drealCentreVdL'=> [
    'endpointURL'=> 'http://metadata.carmencarto.fr/geonetwork/11/fre/csw',
  ],
  'dealReunion'=> [
    'endpointURL'=> 'http://metadata.carmencarto.fr/geonetwork/29/fre/csw',
  ],
  
  // autres catalogues régionaux
  'DatAra'=> [ // chgt d'URL le 22/10/2021
    'endpointURL'=> 'http://datara.gouv.fr/geonetwork/srv/eng/csw-RAIN',
  ],
  'geoBretagne'=> [
    'endpointURL'=> 'https://geobretagne.fr/geonetwork/srv/fre/csw',
  ],
  'pictoOccitanie'=> [
    'endpointURL'=> 'https://www.picto-occitanie.fr/geonetwork/srv/fre/csw',
  ],
  'sigLoire'=> [
    'endpointURL'=> 'http://catalogue.sigloire.fr/geonetwork/srv/fr/csw-sigloire',
  ],
  'geo2France'=> [
    'endpointURL'=> 'https://www.geo2france.fr/geonetwork/srv/fre/csw',
  ],
  'sigena'=> [
    'endpointURL'=> 'https://www.sigena.fr/geonetwork/srv/fre/csw',
  ],
  'ideoBFC'=> [
    'endpointURL'=> 'https://inspire.ternum-bfc.fr/geonetwork/srv/fre/csw',
  ],
  'karuGeo'=> [
    'endpointURL'=> 'https://www.karugeo.fr/geonetwork/srv/fre/csw',
  ],
  'geoMartinique'=> [
    'endpointURL'=> 'http://www.geomartinique.fr/geonetwork/srv/fre/csw',
  ],
  'geoGuyane'=> [
    'endpointURL'=> 'http://www.geoguyane.fr/geonetwork/srv/fre/csw',
  ],
];

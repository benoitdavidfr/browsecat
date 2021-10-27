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
  'Sextant'=> [
    'endpointURL'=> 'https://sextant.ifremer.fr/geonetwork/srv/eng/csw',
  ],
  'GeoRisques'=> [
    'endpointURL'=> 'https://catalogue.georisques.gouv.fr/geonetwork/srv/fre/csw',
  ],
  'GpU'=> [
    'endpointURL'=> 'http://www.mongeosource.fr/geosource/1270/fre/csw',
  ],
  'DatAra'=> [ // chgt d'URL le 22/10/2021
    'endpointURL'=> 'http://datara.gouv.fr/geonetwork/srv/eng/csw-RAIN',
  ],
];

<?php
/*PhpDoc:
title: cats.inc.php - liste des catalogues
name: cats.inc.php
doc: |
  Le champ conformsTo prend pour valeurs:
    - http://www.opengis.net/def/serviceType/ogc/csw
    - https://swagger.io/specification/v2/
    - http://www.w3.org/ns/hydra/core#Collection
journal: |
  22/11/2021:
    - ajout du chmp conformsTo et des catalogues DiDo et DataGouv
  16-17/11/2021:
    - ajout du catalogue Corse dont les MD sont en DublinCore et de très mauvaise qualité
  22-30/10/2021:
    - ajouts
  21/10/2021:
    - création
*/

$cats = [
  // catalogues nationaux
  'geocatalogue'=> [ // Le Géocatalogue est utilisé uniquement commé référence
    'dontAgg'=> true, // ne pas prendre en compte dans l'agrégation
    'endpointURL'=> 'http://www.geocatalogue.fr/api-public/servicesRest', // Totalité du Géocatalogue
    //'endpointURL'=> 'http://www.geocatalogue.fr/api-public/inspire/servicesRest', // Catalogue Inspire
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'geoide'=> [ // uniquement les MDD
    'endpointURL'=> 'http://ogc.geo-ide.developpement-durable.gouv.fr/csw/dataset-harvestable',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'dido'=> [ // Export DCAT/Hydra
    'endpointURL'=> 'https://data.statistiques.developpement-durable.gouv.fr/dido/api/harvesting/dcat-ap/export/jsonld',
    'conformsTo'=> 'http://www.w3.org/ns/hydra/core#Collection',
  ],
  'dgouv'=> [ // Utilisation de l'API et non de l'export DCAT 
    'endpointURL'=> 'https://www.data.gouv.fr/api/1',
    'conformsTo'=> 'https://swagger.io/specification/v2/',
  ],
  'eauFrance' => [
    'shortName'=> 'eauF',
    'endpointURL'=> 'http://www.data.eaufrance.fr/geosource/srv/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'sandreAtlas'=> [
    'shortName'=> 'sndr',
    'endpointURL'=> 'http://www.sandre.eaufrance.fr/atlas/srv/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'Sextant'=> [
    'shortName'=> 'Sxt',
    'endpointURL'=> 'https://sextant.ifremer.fr/geonetwork/srv/eng/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'GeoRisques'=> [
    'shortName'=> 'GRsk',
    'endpointURL'=> 'https://catalogue.georisques.gouv.fr/geonetwork/srv/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'cerema'=> [
    'shortName'=> 'crm',
    'endpointURL'=> 'https://www.cdata.cerema.fr/geonetwork/srv/fre/csw-catalogue-cdata',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'ign'=> [
    'endpointURL'=> 'https://wxs.ign.fr/catalogue/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'ignInspire'=> [
    'shortName'=> 'iIns',
    'endpointURL'=> 'https://wxs.ign.fr/catalogue/csw-inspire',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'shom'=> [
    'shortName'=> 'sh',
    'endpointURL'=> 'https://services.data.shom.fr/geonetwork/srv/fre/csw-produits',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  
  // catalogues MonGeosource
  'GpU'=> [
    'endpointURL'=> 'http://www.mongeosource.fr/geosource/1270/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'geolittoral'=> [
    'shortName'=> 'GLit',
    'endpointURL'=> 'https://www.mongeosource.fr/geosource/1111/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'onf'=> [
    'endpointURL'=> 'http://metadata.carmencarto.fr/geonetwork/105/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'drealNormandie'=> [
    'shortName'=> 'drNor',
    'endpointURL'=> 'http://metadata.carmencarto.fr/geonetwork/8/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'drealCentreVdL'=> [
    'shortName'=> 'drCvl',
    'endpointURL'=> 'http://metadata.carmencarto.fr/geonetwork/11/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'dealReunion'=> [
    'shortName'=> 'deRe',
    'endpointURL'=> 'http://metadata.carmencarto.fr/geonetwork/29/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  
  // autres catalogues régionaux
  'DatAra'=> [ // chgt d'URL le 22/10/2021
    'shortName'=> 'DAra',
    'endpointURL'=> 'http://datara.gouv.fr/geonetwork/srv/eng/csw-RAIN',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'geoBretagne'=> [
    'shortName'=> 'Bzh',
    'endpointURL'=> 'https://geobretagne.fr/geonetwork/srv/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'pictoOccitanie'=> [
    'shortName'=> 'pctOc',
    'endpointURL'=> 'https://www.picto-occitanie.fr/geonetwork/srv/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'sigLoire'=> [
    'shortName'=> 'sLoir',
    'endpointURL'=> 'http://catalogue.sigloire.fr/geonetwork/srv/fr/csw-sigloire',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'geopal'=> [
    'shortName'=> 'GPal',
    'endpointURL'=> 'https://www.geopal.org/geonetwork/srv/eng/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'geo2France'=> [
    'shortName'=> 'G2F',
    'endpointURL'=> 'https://www.geo2france.fr/geonetwork/srv/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'sigena'=> [
    'shortName'=> 'sNa',
    'endpointURL'=> 'https://www.sigena.fr/geonetwork/srv/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'ideoBFC'=> [
    'shortName'=> 'iBFC',
    'endpointURL'=> 'https://inspire.ternum-bfc.fr/geonetwork/srv/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'karuGeo'=> [
    'shortName'=> 'krG',
    'endpointURL'=> 'https://www.karugeo.fr/geonetwork/srv/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'geoMartinique'=> [
    'shortName'=> 'Mtq',
    'endpointURL'=> 'http://www.geomartinique.fr/geonetwork/srv/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  'geoGuyane'=> [
    'shortName'=> 'Guf',
    'endpointURL'=> 'http://www.geoguyane.fr/geonetwork/srv/fre/csw',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],
  /*'acOddCorse'=> [
    'shortName'=> 'Cor',
    'dontAgg'=> true, // ne pas prendre en compte dans l'agrégation car de trop mauvaise qualité
    'endpointURL'=> 'https://georchestra.ac-corse.fr/geoserver/ows',
    'conformsTo'=> 'http://www.opengis.net/def/serviceType/ogc/csw',
  ],*/
];

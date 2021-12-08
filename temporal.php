<?php
/*PhpDoc:
title: temporal.php - estimation des première et derniere dates de moissonnage
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/cswserver.inc.php';

use Symfony\Component\Yaml\Yaml;

foreach ($cats as $catid => $cat) {
  if ($catid == 'geocatalogue') continue;
  if ($cat['conformsTo'] <> 'http://www.opengis.net/def/serviceType/ogc/csw') continue;
  $cswServer = new CswServer($cat['endpointURL'], "catalogs/$catid");
  $temporal = $cswServer->temporal();
  echo Yaml::dump([$catid => $temporal]);
}

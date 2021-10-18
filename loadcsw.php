<?php
/*PhpDoc:
title: loadcsw.php - moissonne un catalogue CSW et charge ses fiches dans le triplestore
name: loadcsw.php
doc: |
  La liste des serveurs CSW provient du catalogue geovect.
  La transformation ISO -> DCAT est effectuée avec la feuille XSL de GeoDCAT-AP.
  Le triplestore destinataire est défini dans la classe CswLoader
journal: |
  17/2/2021:
    - ajout possibilité de moissonner un groupe de catalogues
  16/2/2021:
    - ajout de la définition du catalogue et de son lien avec ses fiches
  15/2/2021:
    21:24 chargement de Sextant Fin normale 8060 data+service
*/
require_once __DIR__.'/../geovect/vendor/autoload.php';
$dcat = require_once __DIR__.'/../geovect/dcat/dcat.php';
require_once __DIR__.'/cswserver.inc.php';
require_once __DIR__.'/../jena/jena.inc.php';
require_once __DIR__.'/iso2dcat.inc.php';

use Symfony\Component\Yaml\Yaml;

// Gestion du log à 2 niveaux
class Log {
  static string $mainPath=''; // chemin du fichier de logs principal
  static string $secDirPath=''; // chemin du répertoire de logs secondaires
  
  // réinitialise le fichier de log
  static function init(string $dirPath, array $argv) {
    self::$mainPath = $dirPath.'/loadcsw.log.yml';
    self::$secDirPath = $dirPath.'/logs';
    
    file_put_contents(self::$mainPath, Yaml::dump([
      'start' => [
        'timeStamp'=> date(DATE_ATOM),
        'argv'=> $argv,
        '_GET'=> $_GET ?? [],
      ]
    ]));
    // supprime les fichiers secondaires de log
    echo `rm -r $dirPath/logs`;
    echo `mkdir $dirPath/logs`;
  }
  
  static function one(int $no, array $brief, array $long): void {
    file_put_contents(
      self::$mainPath,
      Yaml::dump([$no => $brief], 99, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
      FILE_APPEND);
    file_put_contents(self::$secDirPath."/loadcsw$no.yml",
      Yaml::dump($long, 99, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
    );
  }
};

// Gestion du serveur RDF et des méthodes de chargement de données dans ce serveur
class CswLoader {
  const DCAT_HARVEST = false; // moissonnage en Dcat
  const USE_GEODCAT = false; // utilisation de feuille XSLT de GeoDCAT-AP
  //const LOAD_ISO = 'CswLoader::loadIsoUsingGeoDCat'; // utilisation de la feuile XSL GeoDCAT-AP
  //const NB_RECORD_MAX = 0; // si différent de 0, nbre max d'enregistrements lus en CSW
  const NB_RECORD_MAX = 200; // si différent de 0, nbre max d'enregistrements lus en CSW
  const LOAD_ISO = 'loadIsoUsingHomeTransfo'; // utilisation d'une transformation maison
  //const SERVER_URI = 'sparql://admin@172.17.0.6:3030'; // URI du serveur RDF stockant les données
  //const SERVER_URI = 'sparql://admin@172.18.0.5:3030'; // URI du serveur RDF stockant les données réseau mynet 12/3/2021
  const SERVER_URI = 'sparql://admin@fuseki:3030'; // URI du serveur RDF stockant les données réseau mynet 12/3/2021
  const DATASET = 'catalog'; // nom du dataset du serveur RDF stockant les données
  
  static $dirPath=''; // répertoire de la moisson
  static ?Fuseki $server = null; // serveur RDF stockant les données
  static int $no = 0; // no d'enregistrement
  
  static function init(string $dirPath) {
    self::$dirPath = $dirPath;
    self::$server = new Fuseki(self::SERVER_URI);
  } 
  
  // charge dans l'entrepôt RDF la définition du catalogue correspondant au serveur CSW
  static function loadServer(string $catUri, array $cat) {
    try {
      //$result = self::$server->postData(self::DATASET, $cat['catalog']->turtle(), 'application/x-turtle');
      $result = self::$server->postData(self::DATASET, json_encode($cat['catalog']->jsonld()), 'application/ld+json');
    }
    catch (Exception $e) {
      echo "Erreur ",$e->getMessage()," sur le catalogue\n";
      die("Fin ligne ".__LINE__."\n");
    }
  }
  
  // chargement d'une fiche de MD ISO dans le triplestore en utilisant la feuille XSLT de GeoDCAT-AP
  static function loadIsoUsingGeoDCat(string $catUri, string $id, SimpleXMLElement $dcRecord, string $isoRecord, string $path) {
    //return;
    
    // Si l'enregistrement ISO correspond à une erreur CSW
    if (strpos($isoRecord, '<ows:ExceptionReport ') !== false) {
      Log::one(
        self::$no++,
        [
          'errorMessage'=> "erreur CSW",
        ],
        [
          'timeStamp'=> date(DATE_ATOM),
          'errorMessage'=> "erreur CSW",
          'path'=> $path,
          'id'=> $id,
          'isoxml'=> $record,
          'dcRecord'=> $dcRecord->asXml(),
        ]
      );
      if (php_sapi_name() == 'cli')
        echo "Erreur CSW sur path=$path\n";
      //die("Fin ligne ".__LINE__."\n");
      return;
    }
  
    // transformation de la fiche en DCAT
    //echo "DEBUT transformation de la fiche en DCAT\n";
    $errorFile = self::$dirPath.'/xsltproc.errors.txt';
    $dcatxml = `xsltproc iso-19139-to-dcat-ap/iso-19139-to-dcat-ap.xsl $path 2>$errorFile`;
    if ($errors = file_get_contents($errorFile)) {
      Log::one(
        self::$no++,
        [
          'timeStamp'=> date(DATE_ATOM),
          'path'=> $path,
          'errorMessage'=> "Erreur de transformation XSLT en DCAT",
        ],
        [
          'timeStamp'=> date(DATE_ATOM),
          'path'=> $path,
          'errorMessage'=> "Erreur de transformation XSLT en DCAT",
          'dcRecord'=> $dcRecord->asXml(),
          'isoRecord'=> $isoRecord,
          'dcatxml'=> $dcatxml,
          'xsltErrors'=> $errors,
        ]
      );
      echo "Erreur de transformation XSLT en DCAT sur $path\n";
      //return; // même lorsqu'il y a des erreurs, le DCAT peut être intéressant !
    }
    //echo "FIN transformation de la fiche en DCAT\n";
    
    $uri = $catUri."/items/".urlencode($id);
    echo "store ",self::$no," $uri\n";
    // ajout de l'identifiant de la fiche à la fiche
    $pos = strpos($dcatxml, '<rdf:Description>');
    $dcatxml = substr_replace($dcatxml, "<rdf:Description rdf:about=\"$uri\">", $pos, 17);
    try {
      $result = self::$server->postData(self::DATASET, $dcatxml, 'application/rdf+xml');
    }
    catch (Exception $e) {
      Log::one(
        self::$no++,
        [
          'timeStamp'=> date(DATE_ATOM),
          'path'=> $path,
          'errorMessage'=> $e->getMessage(),
        ],
        [
          'timeStamp'=> date(DATE_ATOM),
          'path'=> $path,
          'errorMessage'=> $e->getMessage(),
          'dcatxml'=> $dcatxml,
          'dcRecord'=> $dcRecord->asXml(),
        ]
      );
      if (php_sapi_name() == 'cli')
        echo "Erreur ",$e->getMessage()," sur path=$path\n";
      return;
      //die("Fin ligne ".__LINE__."\n");
    }
    
    $resourceProperty = match ((string)$dcRecord->dc_type) {
      'service' => 'dcat:service',
      default => 'dcat:dataset',
    };
    $ttl = [
      "@prefix dcat: <http://www.w3.org/ns/dcat#>",
      '',
      "<$catUri> $resourceProperty <$uri> .",
    ];
    try {
      $result = self::$server->postData(self::DATASET, implode("\n", $ttl), 'application/x-turtle');
    }
    catch (Exception $e) {
      echo "Erreur ",$e->getMessage()," sur le catalogue\n";
      die("Fin ligne ".__LINE__."\n");
    }
    
    self::$no++;
  }

  /*// chargement d'une fiche de MD DCAT dans le triplestore
  static function loadDcat(string $catUri, string $id, SimpleXMLElement $dcRecord, string $dcatRecord) {
    //return;
    
    self::init();
    
    // Si l'enregistrement ISO correspond à une erreur CSW
    if (strpos($dcatRecord, '<ows:ExceptionReport ') !== false) {
      Log::one(
        self::$no++,
        [
          'errorMessage'=> "erreur CSW",
        ],
        [
          'timeStamp'=> date(DATE_ATOM),
          'errorMessage'=> "erreur CSW",
          'id'=> $id,
          'dcatRecord'=> $dcatRecord,
          'dcRecord'=> $dcRecord->asXml(),
        ]
      );
      if (php_sapi_name() == 'cli')
        echo "Erreur CSW sur id=$id\n";
      //die("Fin ligne ".__LINE__."\n");
      return;
    }
  
    $uri = $catUri."/items/".urlencode($id);
    echo "store ",self::$no," $uri\n";
    // ajout de l'identifiant de la fiche à la fiche
    $pos = strpos($dcatRecord, '<rdf:Description>');
    $dcatRecord = substr_replace($dcatRecord, "<rdf:Description rdf:about=\"$uri\">", $pos, 17);
    try {
      $result = self::$server->postData(self::DATASET, $dcatRecord, 'application/rdf+xml');
    }
    catch (Exception $e) {
      Log::one(
        self::$no++,
        [
          'timeStamp'=> date(DATE_ATOM),
          'path'=> $path,
          'errorMessage'=> $e->getMessage(),
        ],
        [
          'timeStamp'=> date(DATE_ATOM),
          'path'=> $path,
          'errorMessage'=> $e->getMessage(),
          'dcatRecord'=> $dcatRecord,
          'dcRecord'=> $dcRecord->asXml(),
        ]
      );
      if (php_sapi_name() == 'cli')
        echo "Erreur ",$e->getMessage()," sur path=$path\n";
      return;
      //die("Fin ligne ".__LINE__."\n");
    }
    
    $resourceProperty = match ((string)$dcRecord->dc_type) {
      'service' => 'dcat:service',
      default => 'dcat:dataset',
    };
    $ttl = [
      "@prefix dcat: <http://www.w3.org/ns/dcat#>",
      '',
      "<$catUri> $resourceProperty <$uri> .",
    ];
    try {
      $result = self::$server->postData(self::DATASET, implode("\n", $ttl), 'application/x-turtle');
    }
    catch (Exception $e) {
      echo "Erreur ",$e->getMessage()," sur le catalogue\n";
      die("Fin ligne ".__LINE__."\n");
    }
    
    self::$no++;
  }*/

  // chargement d'une fiche de MD DC dans le triplestore
  static function loadDc(string $catUri, string $id, string $biefRecord, string $fullRecord, string $path) {
  }
};

if (php_sapi_name() == 'cli') {
  if ($argc < 2) {
    echo "usage: $argv[0] {catalogue}\n";
    echo "Les catalogues disponibles sont:\n";
    foreach ($dcat->cswCatTree() as $pcatId => $cats) {
      foreach ($cats as $catId => $cat) {
        echo "  - $pcatId/$catId -> ",$cat['cswAccessService']->{'title@fr'},"\n";
      }
    }
    die();
  }
  elseif (!strpos($argv[1], '/')) { // appel sur tous les catalogues du pere
    foreach ($dcat->cswCatTree()[$argv[1]] as $catId => $cat) {
      echo "php $argv[0] $argv[1]/$catId\n";
    }
    die();
  }
  else {
    list($args['pcat'], $args['cat']) = explode('/', $argv[1]);
  }
}
else {
  echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>load.php</title></head><body><pre>\n";
  if (!isset($_GET['cat'])) {
    foreach ($dcat->cswCatTree() as $pcatId => $cats) {
      foreach ($cats as $catId => $cat) {
        echo "  - <a href='?pcat=$pcatId&amp;cat=$catId'>",$cat['cswAccessService']->{'title@fr'},"</a>\n";
      }
    }
    die();
  }
  else {
    $args['pcat'] = $_GET['pcat'];
    $args['cat'] = $_GET['cat'];
  }
}

if (!($cat = $dcat->cswCatTree()[$args['pcat']][$args['cat']] ?? null))
  die("Erreur geocat $args[pcat]/$args[cat] non trouvé\n");

$dirPath = __DIR__."/$args[pcat]-$args[cat]";
if (!file_exists($dirPath))
  mkdir($dirPath);

Log::init($dirPath, $argv ?? []);
CswLoader::init($dirPath);

CswLoader::loadServer("https://geocat.fr/$args[pcat]/catalog/$args[cat]", $cat);

//die("Fin ligne ".__LINE__."\n");

$cswServer = new CswServer($cat['cswAccessService']->endpointURL, $dirPath);

$dc_types = [];
$nextRecord = 1;
while ($nextRecord && (!CswLoader::NB_RECORD_MAX || ($nextRecord < CswLoader::NB_RECORD_MAX))) {
  try {
    $getRecords = $cswServer->getRecords($nextRecord);
  }
  catch (Exception $e) {
    Log::one(
      $nextRecord,
      [
        'error'=> "Erreur fatale - ".$e->getMessage(),
      ],
      [
        'error'=> "Erreur fatale - ".$e->getMessage(),
      ]
    );
    die($e->getMessage());
  }
  foreach ($getRecords->csw_SearchResults->csw_BriefRecord as $record) {
    //echo "record=",$record->asXML(),"\n";
    $dc_type = (string)$record->dc_type;
    if (!isset($dc_types[$dc_type]))  {
      $dc_types[$dc_type] = 1;
      echo "dc_type=$dc_type\n";
    }
    if (!in_array($dc_type, ['dataset','series','nonGeographicDataset','service'])) {
      CswLoader::loadDc(
        "https://geocat.fr/catalog/$args[cat]", // catUri
        (string)$record->dc_identifier,
        $record,
        $cswServer->getRecordById((string)$record->dc_identifier, 'dc'),
        $cswServer->getRecordByIdPath((string)$record->dc_identifier, 'dc')
      );
    }
    /*elseif (CswLoader::DCAT_HARVEST) { // Test NON concluant de moissonnage CSW en DCAT
      CswLoader::loadDcat(
        "https://geocat.fr/$args[pcat]/catalog/$args[cat]", // catUri
        (string)$record->dc_identifier,
        $record,
        $cswServer->getRecordById((string)$record->dc_identifier, 'dcat')
      );
    }*/
    else { // utilise une tranfo maison
      $loadingFunction = CswLoader::LOAD_ISO;
      (CswLoader::LOAD_ISO)(
        "https://geocat.fr/catalog/$args[cat]", // catUri
        (string)$record->dc_identifier,
        $record,
        $cswServer->getRecordById((string)$record->dc_identifier),
        $cswServer->getRecordByIdPath((string)$record->dc_identifier)
      );
    }
  }
  $nextRecord = isset($getRecords->csw_SearchResults['nextRecord']) ? (int)$getRecords->csw_SearchResults['nextRecord'] : null;
}
die("Fin normale"
  .($nextRecord ? " pour nextRecord=$nextRecord" : " pour nextRecord=null")
  .(CswLoader::NB_RECORD_MAX ? " et pour ".CswLoader::NB_RECORD_MAX." enregistrements max" : '')
  ."\n");


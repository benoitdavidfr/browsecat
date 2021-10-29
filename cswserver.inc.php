<?php
/*PhpDoc:
name: cswserver.inc.php
title: cswserver.inc.php - gère les appels à un serveur CSW
classes:
doc: |
journal: |
  16/1/2021:
    - extension des options de getRecords() et getRecordById()
    - réécriture du code par défaut pour consulter interactivement le catalogue
  8/2/2021:
    - création
includes:
  - httpcache.inc.php
*/
require_once __DIR__.'/httpcache.inc.php';

/*PhpDoc: classes
name: CswServer
title: class CswServer -- gère les appels à un serveur CSW
methods:
*/
class CswServer {
  protected string $baseUrl; // Url de base sans séparateur à la fin ; peut éventuellement contenir des paramètres
  protected HttpCache $cacheGetCap; // cache du GetCapabilities
  protected HttpCache $cacheGetRecs; // // cache des GetRecords
  protected HttpCache $cacheGetRecById; // cache des GetRecordById
  
  function __construct(string $baseUrl, string $cacheDir) {
    $this->baseUrl = $baseUrl;
    $this->cacheGetCap = new HttpCache("$cacheDir/cap");
    $this->cacheGetRecs = new HttpCache("$cacheDir/recs");
    $this->cacheGetRecById = new HttpCache("$cacheDir/byid");
  }
  
  // effectue un GetCapabilities et retourne le résultat XML
  function getCapabilities(): string {
    $url = $this->baseUrl
      .(strpos($this->baseUrl, '?') ? '&' : '?')
      .'SERVICE=CSW&REQUEST=GetCapabilities';
    //echo "url=$url\n";
    return $this->cacheGetCap->request($url, 'xml');
  }
  
  // construit l'URL d'un GetRecords en DublinCore
  function getRecordsUrl(int $start, $elementSetName='brief'): string {
    $namespace = '';
    return $this->baseUrl
      .(strpos($this->baseUrl, '?') ? '&' : '?')
      .'SERVICE=CSW&version=2.0.2&REQUEST=GetRecords'
      .'&ResultType=results'
      .'&ElementSetName='.$elementSetName
      .'&maxRecords=20'
      .'&OutputFormat='.rawurlencode('application/xml')
      .'&OutputSchema='.rawurlencode('http://www.opengis.net/cat/csw/2.0.2')
      .$namespace
      .'&TypeNames='.rawurlencode('csw:Record')
      .'&startPosition='.$start;
  }
  
  // effectue un GetRecords en DublinCore et retourne un SimpleXMLElement
  function getRecords(int $start, $elementSetName='brief'): SimpleXMLElement {
    $namespace = '';
    $url = $this->getRecordsUrl($start, $elementSetName);
    try {
      $records = $this->cacheGetRecs->request($url, 'xml');
    } catch (Exception $e) {
      throw new Exception("Erreur de GetRecords sur ".$this->baseUrl." avec message ".$e->getMessage());
      //die($e->getMessage." ligne ".__LINE__." du fichier ".__FILE__);
    }
    $records = preg_replace('!<(/)?([^:]+):!', '<$1$2_', $records);
    //$records = str_replace(['</csw:','<csw:','</dc:','<dc:','</ows:'],['</csw_','<csw_','</dc_','<dc_'], $records);
    return new SimpleXMLElement($records);
  }
  
  // Retourne le chemin du fichier du cache stockant le GetRecords
  function getRecordsPath(string $id, $elementSetName='brief'): string {
    return $this->cacheGetRecs->path(md5($this->getRecordsUrl($id, $elementSetName)), 'xml');
  }
  
  // Construit l'URL d'un GetRecordById,
  public function getRecordByIdUrl(string $id, string $output, string $elementSetName): string {
    $outputSchema = match($output) {
      'dc' => 'http://www.opengis.net/cat/csw/2.0.2',
      'dcat'=> 'http://www.w3.org/ns/dcat#',
      'iso19139' => 'http://www.isotc211.org/2005/gmd',
      default => 'http://www.isotc211.org/2005/gmd',
    };
    $typeNames = match($output) {
      'dc'=> 'csw:Record',
      'dcat'=> 'dcat',
      'iso19139' => 'gmd:MD_Metadata',
      default => 'gmd:MD_Metadata',
    };
    return $this->baseUrl
       .(strpos($this->baseUrl,'?') ? '&' : '?')
       .'SERVICE=CSW&VERSION=2.0.2&REQUEST=GetRecordById'
       .'&ResultType=results'
       .'&ELEMENTSETNAME='.$elementSetName
       .'&OUTPUTFORMAT='.rawurlencode('application/xml')
       .'&OUTPUTSCHEMA='.rawurlencode($outputSchema)
       .'&TypeNames='.rawurlencode($typeNames)
       .'&ID='.rawurlencode($id);
  }
  
  // Effectue un GetRecordById par défaut en récupérant les MD en ISO 19139 (défaut), en DC ou en DCAT
  // $output prend pour valeur:
  //  - 'dc' pour Dublin Core
  //  - 'dcat' pour DCAT
  //  - 'iso19139' pour ISO 19115/19139 (défaut)
  function getRecordById(string $id, string $output='iso19139', string $elementSetName='full'): string {
    try {
      return $this->cacheGetRecById->request($this->getRecordByIdUrl($id, $output, $elementSetName), 'xml');
    } catch (Exception $e) {
       die($e->getMessage()." ligne ".__LINE__." du fichier ".__FILE__."\n");
    }
  }
  
  // Retourne le chemin du fichier du cache stockant l'enregistrement id
  function getRecordByIdPath(string $id, string $output='iso19139', string $elementSetName='full'): string {
    return $this->cacheGetRecById->path(md5($this->getRecordByIdUrl($id, $output, $elementSetName)), 'xml');
  }
  
  // Efface le fichier de cache stockant l'enregistrement id
  function delRecordById(string $id, string $output='iso19139', string $elementSetName='full'): void {
    $path = $this->getRecordByIdPath($id, $output, $elementSetName);
    unlink($path);
  }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return;

$geocats = [
  'geocatalogue0'=> 'http://www.geocatalogue.fr/api-public/servicesRest',
  'nsextant'=> 'https://sextant.ifremer.fr/geonetwork/srv/eng/csw',
  'neaufrance'=> 'http://www.data.eaufrance.fr/geosource/srv/fre/csw',
  'nign'=> 'https://wxs.ign.fr/catalogue/csw',
  'nonf'=> 'http://metadata.carmencarto.fr/geonetwork/105/fre/csw',
  'ngeoide'=> 'http://ogc.geo-ide.developpement-durable.gouv.fr/csw/all-harvestable',
  'rideobfc'=> 'https://www.ideobfc.fr/geonetwork/srv/fre/csw',
  'roddcorse'=> 'https://georchestra.ac-corse.fr/geoserver/ows',
  'rgeoguyane'=> 'http://www.geoguyane.fr/geonetwork/srv/fre/csw',
  'rdreunion'=> 'http://metadata.carmencarto.fr/geonetwork/29/fre/csw',
];
if (!isset($_GET['s'])) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>cswserver.inc.php</title></head><body><pre>\n";
  foreach ($geocats as $id => $url)
    echo "<a href='?s=$id'>$id</a>\n";
  die();
}
//$dirPath = __DIR__.'/'; $cswUrl = 

if (!isset($_GET['a'])) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>cswserver.inc.php</title></head><body><pre>\n";
  echo "<a href='?a=gc&amp;s=$_GET[s]'>getCapabilities</a>\n";
  echo "<a href='?a=rs&amp;s=$_GET[s]'>getRecords</a>\n";
  die();
}

$dirPath = __DIR__."/$_GET[s]";
if (!file_exists($dirPath))
  mkdir($dirPath);

$cswServer = new CswServer($geocats[$_GET['s']], $dirPath);
if ($_GET['a']=='gc') { // getCapabilities
  header('Content-type: text/xml; charset="utf-8"');
  die($cswServer->getCapabilities());
}
elseif ($_GET['a']=='rs') { // getRecords 
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>cswserver.inc.php</title></head><body><pre>\n";
  $nextRecord = $_GET['n'] ?? 1;
  $getRecords = $cswServer->getRecords($nextRecord);
  $numberOfRecordsMatched = (int)$getRecords->csw_SearchResults['numberOfRecordsMatched'];
  echo "<a href='",substr($cswServer->getRecordsPath($nextRecord), strlen($_SERVER['DOCUMENT_ROOT'])),
    "'>getRecords $nextRecord/$numberOfRecordsMatched en cache</a>\n";
  $i = 0;
  foreach ($getRecords->csw_SearchResults->csw_BriefRecord as $record) {
    $id = (string)$record->dc_identifier;
    //echo "- no: ",$nextRecord+$i++," / $numberOfRecordsMatched\n";
    //echo "  id=$id\n";
    echo "- title=",$record->dc_title,"\n";
    echo "  type=",$record->dc_type;
    foreach (['iso19139'=>'ISO','dc'=>'DC','dcat'=>'DCAT'] as $key=> $label)
      echo " <a href='?a=id&amp;id=",urlencode($id),"&amp;output=$key&amp;s=$_GET[s]'>$label</a>";
    echo "\n";
  }
  if (!isset($getRecords->csw_SearchResults['nextRecord']) || ((int)$getRecords->csw_SearchResults['nextRecord']) == 0) {
    echo "nextRecord non défini ou nul\n";
  }
  else {
    $nextRecord = (int)$getRecords->csw_SearchResults['nextRecord'];
    echo "<a href='?a=rs&amp;n=$nextRecord&amp;s=$_GET[s]'>getRecords $nextRecord / $numberOfRecordsMatched</a>\n";
  }
}
elseif ($_GET['a']=='id') { // getRecordById 
  $getRecordById = $cswServer->getRecordById($_GET['id'] ?? null, $_GET['output'] ?? null);
  header('Content-type: text/xml; charset="utf-8"');
  die($getRecordById);
}
die("Ok\n");


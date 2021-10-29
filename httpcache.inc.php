<?php
/*PhpDoc:
name: httpcache.inc.php
title: httpcache.inc.php - gère un cache de requêtes Http
classes:
doc: |
  Permet d'éviter de lancer plusieurs fois une requête Http.
  La requête est identifiée par le MD5 de l'url de requête
  Le cache est stocké dans un répertoire avec un sous-répertoire défini par les 3 premiers caractères du MD5
journal: |
  8/1/2021:
    - création
includes:
  - ../phplib/http.inc.php
*/
require_once __DIR__.'/../phplib/http.inc.php';

/*PhpDoc: classes
name: HttpCache
title: class HttpCache -- gestion d'un cache Http
methods:
doc: |
  Si le paramètre $dirPath vaut null alors aucun cache n'est mis en oeuvre
  Les options http sont celles définies pour la classe Http
*/
class HttpCache {
  protected ?string $dirPath; // chemin du répertoire de stockage ou null si pas de stockage
  protected array $httpOptions;
  
  function __construct(?string $dirPath=null, array $httpOptions=[]) {
    $this->dirPath = $dirPath;
    if ($dirPath && !file_exists($dirPath))
      mkdir($dirPath);
    $this->httpOptions = $httpOptions;
  }
  
  // construit le chemin du fichier du cache, retourne null ssi aucun cache n'est défini
  function path(string $md5, string $ext): ?string {
    if (!$this->dirPath)
      return null;
    else
      return $this->dirPath.'/'.substr($md5, 0, 3).'/'.substr($md5, 3).'.'.$ext;
  }
  
  /*PhpDoc: methods
  name: request
  title: "function request(string $url, string $ext): string - effectue une erquête au travers du cache"
  doc: |
    Effectue une requête sur l'URL $url, $ext est utilisé pour définir l'extension du fichier du cache
  */
  function request(string $url, string $ext=''): string {
    if (!$this->dirPath)
      return Http::request($url);
    $id = md5($url);
    $path = $this->path($id, $ext);
    //echo "path=$path\n";
    if (file_exists($path)) {
      //echo "en cache\n";
      return file_get_contents($path);
    }
    $return = Http::request($url, $this->httpOptions);
    if (!file_exists(dirname($path)))
      mkdir(dirname($path));
    file_put_contents($path, $return['body']);
    return $return['body'];
  }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__))) return;
echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>httpcache.inc.php</title></head><body><pre>\n";


$httpCache = new HttpCache(__DIR__.'/testcache');
$url = 'https://sextant.ifremer.fr/geonetwork/srv/eng/csw?SERVICE=CSW&REQUEST=GetCapabilities';
echo str_replace('<', '{', $httpCache->request($url, 'xml'));
die("Ok\n");

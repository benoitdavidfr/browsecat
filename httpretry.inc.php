<?php
/*PhpDoc:
name: httpretry.inc.php
title: httpretry.inc.php - gère des requêtes Http avec un mécanisme de relance en cas d'erreur
classes:
doc: |
  Utilise le même nom de méthode request() que la classe Http dans ../phplib/http.inc.php
journal: |
  30/10/2021:
    - création
includes:
  - ../phplib/http.inc.php
*/
require_once __DIR__.'/../phplib/http.inc.php';

/*PhpDoc: classes
name: HttpRetry
title: class HttpRetry -- gestion des requêtes Http avec un mécanisme de relance en cas d'erreur
methods:
doc: |
  Ajouter un mécanisme de réessai en cas d'erreur avec un délai par ex de 2**n sec.
*/
class HttpRetry {
  /*PhpDoc: methods
  name: request
  title: "static function request(string $url, array $options=[]): array"
  doc: |
    Renvoie un array constitué d'un champ 'headers' et d'un champ 'body'
    Les options possibles sont:
      'referer'=> referer à utiliser
      'proxy'=> proxy à utiliser
      'method'=> méthode HTTP à utiliser, par défaut 'GET'
      'Accept'=> type MIME demandé, ex 'application/json,application/geo+json'
      'Accept-Language'=> langage demandé, ex 'en'
      'Cookie' => cookie défini
      'Authorization' => en-tête HTTP Authorization contenant permettant l'authentification d'un utilisateur
      'ignore_errors' => true // pour éviter la génération d'une exception
      'maxRetry' => nbre de relances avec un délai de 2**n sec., par défaut aucune relance
      'Content-Type'=> Content-Type à utiliser pour les méthodes POST et PUT
      'content'=> texte à envoyer en POST ou PUT
  */
  static function request(string $url, array $options=[]): array {
    echo "HttpRetry::request($url, ",json_encode($options),")<br>\n";
    if (!isset($options['maxRetry']) || ($options['maxRetry']==0))
      return Http::request($url, $options);
    $maxRetry = $options['maxRetry'];
    unset($options['maxRetry']);
    $ignore_errors = $options['ignore_errors'] ?? false;
    $options['ignore_errors'] = true;
    $nbRetry = 0;
    while (true) {
      try {
        $return = Http::request($url, $options);
        //echo "<pre>"; print_r($return); echo "</pre>\n";
        $errorCode = Http::errorCode($return['headers']);
        if ($errorCode == 200)
          return $return;
      }
      catch (Exception $e) {
        $return = null;
        $errorCode = -1;
      }
      echo "nbRetry=$nbRetry, errorCode=$errorCode<br>\n";
      if ($nbRetry >= $maxRetry)
        break;
      //sleep(2 ** $nbRetry); echo "sleep(",2 ** $nbRetry,")<br>\n";
      $nbRetry++;
    }
    // je sors de la boucle forcément sur une erreur
    if (!$return) // si retour par exception alors je lève une exception avec le message réçue de Http::request
      throw new Exception($e->getMessage());
    elseif (!$ignore_errors) // si l'option ignore_errors était fausse alors je lève une exception
      throw new Exception("Erreur '".$return['headers'][0]."' dans HttpRetry::query() : sur url=$url");
    else { // sinon je retourne l'erreur
      //print_r($return);
      return $return;
    }
  }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return;


/* Test sans redirect
$return = HttpRetry::request('http://localhost/browsecat/server.php', ['maxRetry'=> 2, 'ignore_errors'=> true]);
echo '<pre>'; print_r($return);
*/

$host = 'localhost';
$host = 'bdavid.alwaysdata.net';
  
// Test avec redirect en localhost ou sur Alwaysdata
$return = HttpRetry::request("http://$host/browsecat/server.php?redirect=true", ['maxRetry'=> 5, 'ignore_errors'=> true]);
echo '<pre>'; print_r($return);

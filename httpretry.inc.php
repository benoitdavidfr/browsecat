<?php
/*PhpDoc:
name: httpretry.inc.php
title: httpretry.inc.php - gère des requêtes Http avec un mécanisme de relance en cas d'erreur
classes:
doc: |
  Utilise le même nom de méthode request() et les mêmes paramètres que dans la classe Http dans ../phplib/http.inc.php
  Ajoute simplement une option maxRetry avec le nbre de réessais.
  Si 0 alors Http::request() est appelé
journal: |
  30/10-2/11/2021:
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
  Ajoute un mécanisme de réessai en cas d'erreur avec un délai de 2**n sec.
*/
class HttpRetry {
  /*PhpDoc: methods
  name: request
  title: "static function request(string $url, array $options=[]): array"
  doc: |
    Retourne un array constitué d'un champ 'headers' et d'un champ 'body'.
    En cas d'erreur:
      - si la requête est interrompue avant que le serveur ne réponde, par ex. à cause d'un timeout
        - alors levée d'une exception
      - sinon si l'option ignore_errors est définie à true alors retourne l'erreur dans le headers
      - sinon levée d'une exception
  
    Les options possibles sont:
      'referer'=> referer utilisé
      'proxy'=> proxy à utiliser
      'method'=> méthode HTTP utilisée, par défaut 'GET'
      'Accept'=> type MIME demandé, ex 'application/json,application/geo+json'
      'Accept-Language'=> langage demandé, ex 'en'
      'Cookie' => cookie défini
      'Authorization' => en-tête HTTP Authorization permettant l'authentification d'un utilisateur
      'ignore_errors' => true // évite la génération d'une exception
      'maxRetry' => nbre de relances avec un délai de 2**n sec., par défaut aucune relance
      'Content-Type'=> Content-Type utilisé pour les méthodes POST et PUT
      'content'=> texte envoyé en POST ou PUT
  */
  static function request(string $url, array $options=[]): array {
    //echo "HttpRetry::request($url, ",json_encode($options),")<br>\n";
    if (!isset($options['maxRetry']) || ($options['maxRetry']==0))
      return Http::request($url, $options);
    $maxRetry = $options['maxRetry'];
    unset($options['maxRetry']);
    $ignore_errors = $options['ignore_errors'] ?? false;
    $options['ignore_errors'] = true;
    $nbRetry = 0;
    while (true) {
      $result = null;
      try {
        $result = Http::request($url, $options);
        //echo "<pre>"; print_r($result); echo "</pre>\n";
      }
      catch (Exception $e) {
        $errorCode = -1;
      }
      if ($result) {
        $errorCode = Http::errorCode($result['headers']);
        if ($errorCode == 200)
          return $result;
      }
      echo "nbRetry=$nbRetry, ",($errorCode == -1) ? $e->getMessage() : "errorCode=$errorCode","<br>\n";
      if ($nbRetry >= $maxRetry)
        break;
      sleep(2 ** $nbRetry); //echo "sleep(",2 ** $nbRetry,")<br>\n";
      $nbRetry++;
    }
    // je sors de la boucle forcément sur une erreur
    if (!$result) // si retour d'une exception alors je la transmet
      throw new Exception($e->getMessage());
    elseif (!$ignore_errors) { // sinon si l'option ignore_errors était fausse alors je lève une exception
      $errorCode = Http::errorCode($result['headers']);
      throw new Exception("Erreur '$errorCode' dans HttpRetry::query() : sur url=$url");
    }
    else { // sinon je retourne le retour de Http::request()
      //print_r($result);
      return $result;
    }
  }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return;


$host = 'localhost';
$host = 'bdavid.alwaysdata.net';
  
// Test avec redirect en localhost ou sur Alwaysdata
//$result = HttpRetry::request("http://$host/browsecat/testserver.php?redirect=true", ['maxRetry'=> 5, 'ignore_errors'=> true]);
$result = HttpRetry::request("http://$host/browsecat/testserver.php?redirect=true", ['maxRetry'=> 5]);
echo '<pre>'; print_r($result);

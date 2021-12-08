<?php
/*PhpDoc:
title: quali.php - contrôle qualité des métadonnées
name: quali.php
doc: |
  Chaque fiche doit:
    - avoir un et un seul titre
    - avoir au moins un identifiant unique
    - avoir au moins un mdContact et au moins un responsibleParty
    - le mdContact doit pouvoir être contacté

  Résultat:
    agg:
      synthese: 181 fiches erronées sur 28952 soit 0.6 %
      noMdContactEMail: 53
      noMdContact: 10
      noId: 81
      noMdContactOrgName: 9
      badMdContactEMail: 24
      noTitle: 4
    geocatalogue: Aucune Mdd
    geoide: ok
    dido: Aucune Mdd
    dgouv: Aucune Mdd
    eauFrance:
      synthese: 9 fiches erronées sur 79 soit 11.4 %
      noMdContactEMail: 9
    sandreAtlas: ok
    Sextant:
      synthese: 6 fiches erronées sur 501 soit 1.2 %
      noMdContact: 2
      noId: 4
    GeoRisques: ok
    cerema: ok
    ign:
      synthese: 17 fiches erronées sur 19 soit 89.5 %
      noMdContactEMail: 17
    ignInspire: Aucune Mdd
    shom: ok
    GpU:
      synthese: 3 fiches erronées sur 227 soit 1.3 %
      noMdContactOrgName: 1
      noMdContact: 2
    geolittoral: ok
    onf: Aucune Mdd
    drealNormandie:
      synthese: 12 fiches erronées sur 563 soit 2.1 %
      noMdContact: 1
      noMdContactOrgName: 2
      noId: 8
      badMdContactEMail: 1
    drealCentreVdL:
      synthese: 13 fiches erronées sur 169 soit 7.7 %
      noId: 3
      noMdContactOrgName: 4
      badMdContactEMail: 5
      noTitle: 1
    dealReunion:
      synthese: 38 fiches erronées sur 60 soit 63.3 %
      noId: 33
      noTitle: 3
      noMdContactEMail: 1
      noMdContactOrgName: 1
    DatAra:
      synthese: 18 fiches erronées sur 1248 soit 1.4 %
      noMdContactOrgName: 1
      noMdContactEMail: 10
      noId: 3
      badMdContactEMail: 3
      noMdContact: 1
    geoBretagne:
      synthese: 14 fiches erronées sur 628 soit 2.2 %
      noMdContactEMail: 3
      badMdContactEMail: 11
    pictoOccitanie: ok
    sigLoire: Aucune Mdd
    geopal: ok
    geo2France:
      synthese: 6 fiches erronées sur 149 soit 4.0 %
      noMdContactEMail: 6
    sigena:
      synthese: 21 fiches erronées sur 3651 soit 0.6 %
      noMdContactEMail: 5
      noId: 10
      noMdContact: 5
      badMdContactEMail: 1
    ideoBFC:
      synthese: 21 fiches erronées sur 805 soit 2.6 %
      noId: 18
      noMdContactEMail: 2
      badMdContactEMail: 1
    karuGeo:
      synthese: 3 fiches erronées sur 122 soit 2.5 %
      noId: 2
      badMdContactEMail: 1
    geoMartinique: ok
    geoGuyane:
      synthese: 2 fiches erronées sur 369 soit 0.5 %
      badMdContactEMail: 1
      noId: 1
*/
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/orgarbo.inc.php';
require_once __DIR__.'/cats.inc.php';

use Symfony\Component\Yaml\Yaml;

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>quali</title></head><body><pre>\n";

if (!CatInPgSql::chooseServer('local')) { // Choix du serveur 
  die("Erreur: paramètre serveur incorrect !\n");
}

if (!isset($_GET['action'])) {
  echo "</pre><ul>\n";
  echo "<li><a href='?action=global'>global</a></li>\n";
  die();
}

class Quali { // implémente une méthode de contrôle qualité sur les fiches, un objet = un rapport
  const MdContactEMailOk = [
    'http://www.statistiques.developpement-durable.gouv.fr/nouscontacter.html',
    'http://www.brgm.fr/content/contact',
  ];
  private $catid; // 
  private $nbMdd = 0; // nbre de Mdd testées
  private $nbErrors = 0; // nbre d'erreurs
  private $errors = []; // nbre d'erreurs par type d'erreur

  // Test qualité sur une fiche - Modifie le rapport et retourne vrai si pas d'erreur, false si une erreur
  function cntrlRecord(array $record): bool {
    $this->nbMdd++;
    if (!isset($record['dct:title']) || !$record['dct:title']) {
      $this->errors['noTitle'] = 1 + ($this->errors['noTitle'] ?? 0);
      $this->nbErrors++;
      return false;
    }
    elseif (count($record['dct:title']) > 1) {
      $this->errors['multipleTitle'] = 1 + ($this->errors['multipleTitle'] ?? 0);
      $this->nbErrors++;
      return false;
    }
    elseif (!isset($record['mdContact']) || !$record['mdContact']) {
      $this->errors['noMdContact'] = 1 + ($this->errors['noMdContact'] ?? 0);
      $this->nbErrors++;
      return false;
    }
    elseif (!isset($record['mdContact'][0]['organisationName']) ||
      ($record['mdContact'][0]['organisationName'] == '-- Nom du point de contact des métadonnées --')) {
        $this->errors['noMdContactOrgName'] = 1 + ($this->errors['noMdContactOrgName'] ?? 0);
        $this->nbErrors++;
        return false;
    }
    elseif (!isset($record['mdContact'][0]['electronicMailAddress'])) {
      $this->errors['noMdContactEMail'] = 1 + ($this->errors['noMdContactEMail'] ?? 0);
      $this->nbErrors++;
      return false;
    }
    else {
      $mdContact = $record['mdContact'][0];
      $mdContactEMail = $mdContact['electronicMailAddress'];
      $emailAddPattern = '[-\._a-zA-Z0-9]+@[-\._a-zA-Z0-9]+';
      $emailSep = '( / |; )';
      if (!preg_match("!^$emailAddPattern($emailSep$emailAddPattern)*\$!", $mdContactEMail) &&
        !in_array($mdContactEMail, self::MdContactEMailOk)) {
          //echo "mdContactEMail=$mdContactEMail\n";
          $this->errors['badMdContactEMail'] = 1 + ($this->errors['badMdContactEMail'] ?? 0);
          $this->nbErrors++;
          return false;
      }
      elseif (!isset($record['responsibleParty']) || !$record['responsibleParty']) {
        $this->errors['noResponsibleParty'] = 1 + ($this->errors['noResponsibleParty'] ?? 0);
        $this->nbErrors++;
        return false;
      }
      elseif (!isset($record['dct:identifier']) || !$record['dct:identifier']) {
        $this->errors['noId'] = 1 + ($this->errors['noId'] ?? 0);
        $this->nbErrors++;
        return false;
      }
      else {
        return true;
      }
    }
  }
  
  function __construct(string $catid) { // initialise le rapport qualité 
    $this->catid = $catid;
  }
  
  function cntrlCat($detail=false) {
    $sql = "select id, title, record from catalog$this->catid where perimetre='Min' and type in ('dataset','series')";
    foreach (PgSql::query($sql) as $tuple) {
      $record = json_decode($tuple['record'], true);
      if (!$this->cntrlRecord($record, $tuple, $detail) && $detail)
        echo "- <a href='?action=showPg&amp;cat=$this->catid&amp;id=$tuple[id]'>$tuple[title]</a>\n";
    }
  }
  
  function report(): array {
    return [
      'nbMdd'=> $this->nbMdd,
      'nbErrors'=> $this->nbErrors,
      'errors'=> $this->errors,
    ];
  }
  
  function out() { // affiche le rapport
    if (!$this->nbMdd)
      echo "$this->catid: Aucune Mdd\n";
    elseif (!$this->nbErrors)
      echo "$this->catid: ok\n";
    else {
      echo "<a href='?action=cat&amp;cat=$this->catid'>$this->catid</a>:\n";
      printf("  synthese: %d fiches erronées sur %d soit %.1f %%\n",
          $this->nbErrors, $this->nbMdd, $this->nbErrors/$this->nbMdd*100);
      foreach ($this->errors as $key => $nb)
        echo "  $key: $nb\n";
    }
  }
};

if ($_GET['action']=='global') {
  $quali = new Quali('agg');
  $quali->cntrlCat();
  $quali->out();
  foreach ($cats as $catid => $cat) {
    $quali = new Quali($catid);
    $quali->cntrlCat();
    $quali->out();
  }
  die();
}

if ($_GET['action']=='cat') {
  $quali = new Quali($_GET['cat']);
  $quali->cntrlCat(true);
  $quali->out();
  die();
}

if ($_GET['action'] == 'showPg') {
  $quali = new Quali($_GET['cat']);

  $sql = "select id, title, record from catalog$_GET[cat] where id='$_GET[id]'";
  $tuple = PgSql::getTuples($sql)[0];
  $record = json_decode($tuple['record'], true);
  $quali->cntrlRecord($record);
  //$quali->out();
  echo Yaml::dump(array_merge(['errors'=> $quali->report()['errors']], $record), 3, 2);
}
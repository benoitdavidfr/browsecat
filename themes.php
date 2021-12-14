<?php
/*PhpDoc:
title: theme.php - gestion des thèmes
name: theme.php
doc: |
journal: |
  13-14/12/2021:
    - création
includes:
  - cats.inc.php
  - catinpgsql.inc.php
  - record.inc.php
  - orgarbo.inc.php
  - orginsel.inc.php
*/
require_once __DIR__.'/cats.inc.php';
require_once __DIR__.'/catinpgsql.inc.php';
require_once __DIR__.'/record.inc.php';
require_once __DIR__.'/orgarbo.inc.php';
require_once __DIR__.'/orginsel.inc.php';

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>themes</title></head><body>\n";

if (!isset($_GET['cat'])) { // choix du catalogue ou actions globales
  if (!isset($_GET['action'])) {
    echo "Actions globales:<ul>\n";
    echo "<li><a href='?action=showThemes'>Affiche les thèmes</a></li>\n";
    echo "</ul>\n";
    echo "Choix du catalogue:<ul>\n"; // liste les catalogues pour en choisir un
    foreach (array_merge(['agg'=> 'agg'], $cats) as $catid => $cat) {
      echo "<li><a href='?cat=$catid'>$catid</a></li>\n";
    }
  }
  elseif ($_GET['action']=='showThemes') { // affiche l'arbo. ou un thème ou un sous-thème 
    $themes = new Arbo('themes.yaml');
    $arbocovadis = new Arbo('arbocovadis.yaml');
    $themes->addCovadis();
    if (isset($_GET['th'])) {
      echo "<pre>"; print_r($themes->node(explode(',',$_GET['th'])));
    }
    else {
      //echo "<pre>\n";
      //echo "themes="; print_r($themes);
      echo "<ul>\n";
      foreach ($themes->children() as $thid => $theme) {
        //echo "$thid: \n"; //print_r($theme);
        echo "<li><a href='?action=showThemes&th=$thid'>",$theme->prefLabel(),"</a>",
             //' (/',$theme->covadis(),')',
             "</li><ul>\n";
        foreach ($theme->children() as $sthid => $stheme) {
          if ($stcovadis = $stheme->covadis()) {
            $tstcovadis = '/'.$theme->covadis().'/'.$stheme->covadis();
            $stlabel = $stheme->altLabels()[0];
          }
          echo "<li><a href='?action=showThemes&th=$thid,$sthid'>",$stheme->prefLabel(),"</a>",
               //$stcovadis ? " ($tstcovadis - $stlabel)" : '',
               "</li>\n";
        }
        echo "</ul>\n";
      }
    }
  }
  die();
}
else {
  if (!isset($_GET['action'])) { // actions sur un catalogue 
    echo "Actions sur le catalogue:<ul>\n";
    echo "<li><a href='?cat=$_GET[cat]&amp;action=listkws'>Liste les mots-clés des fiches non indexées</a></li>\n";
    echo "<li><a href='?cat=$_GET[cat]&amp;action=ajoutheme'>Ajout themes</a></li>\n";
    echo "</ul>\n";
  }
  elseif ($_GET['action'] == 'listkws') {
    function oneOfTheKwsInThemes(array $keywords, Arbo $themes): bool {
      foreach ($keywords as $keyword) {
        if ($themes->labelIn($keyword['value']))
          return true;
      }
      return false;
    }
    
    $themes = new Arbo('themes.yaml');
    if (!CatInPgSql::chooseServer($_SERVER['HTTP_HOST']=='localhost' ? 'local' : 'distant')) { // Choix du serveur
      die("Erreur de choix du serveur\n");
    }
    $kwValues = []; // [value => ['label'=> label, 'nbre'=> nbre]]
    $nbreMdd = 0;
    $nbreMatch = 0;
    $sql = "select id,title,record from catalog$_GET[cat] where type in ('dataset','series','Dataset','Dataset,series')";
    foreach (PgSql::query($sql) as $record) {
      $id = $record['id'];
      $title = $record['title'];
      //$record = json_decode($record['record'], true);
      $record = Record::create($record['record']);
      //echo '<pre>$record='; print_r($record); echo "</pre>\n";
      if (!orgInSel($_GET['cat'], $record)) // si aucune organisation appartient à la sélection alors on saute
        continue;
      
      if (!oneOfTheKwsInThemes($record['keyword'] ?? [], $themes)) {
        foreach ($record['keyword'] ?? [] as $keyword) {
          //echo "$keyword[value]<br>\n";
          if (!isset($kwValues[strtolower($keyword['value'])])) {
            $kwValues[strtolower($keyword['value'])] = ['nbre' => 1, 'label'=> $keyword['value']];
          }
          else
            $kwValues[strtolower($keyword['value'])]['nbre']++;
        }
      }
      else {
        $nbreMatch++;
      }
      //if (count($kwValues) > 2) break;
      $nbreMdd++;
    }
    printf("<b>%d / %d match soit %.0f %%</b><br>\n", $nbreMatch, $nbreMdd, $nbreMatch/$nbreMdd*100);
    ksort($kwValues);
    foreach ($kwValues as $kwValue) {
      echo "$kwValue[label] ($kwValue[nbre])<br>\n";
    }
    die();
  }
  elseif ($_GET['action'] == 'ajoutheme') { // pour ajouter des thèmes
  }
}

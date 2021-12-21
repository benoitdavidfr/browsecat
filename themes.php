<?php
/*PhpDoc:
title: themes.php - gestion des thèmes après création de themes.yaml
name: themes.php
doc: |
  Sans regexp sur agg 86% des données indexées par un thème
  Avec Regexp 27117 / 29134 match soit 93 %
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
require_once __DIR__.'/theme.inc.php';

use Symfony\Component\Yaml\Yaml;

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>themes</title></head><body>\n";

if (!CatInPgSql::chooseServer('local')) { // Choix du serveur
  die("Erreur de choix du serveur\n");
}

if (!isset($_GET['cat'])) { // actions globales ou choix d'un catalogue
  if (!isset($_GET['action'])) { // choix d'une action globale ou d'un catalogue
    echo "Actions globales:<ul>\n";
    echo "<li><a href='?action=showThemes'>Affiche les thèmes</a></li>\n";
    echo "<li><a href='?action=deleteAddedThemes'>Suppression des thèmes ajoutés dans tous les catalogues</a></li>\n";
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
  
  elseif ($_GET['action']=='deleteAddedThemes') { // Suppression des thèmes ajoutés dans tous les catalogues
    foreach ($cats as $catid => $cat) {
      deleteAddedThemesInCat($catid);
    }
  }
  die();
}

elseif (!isset($_GET['action'])) { // choix d'une action sur le catalogue choisi
  echo "Actions sur le catalogue:<ul>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listTitles'>Liste titres des fiches du périmètre avec lien</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listkws'>Liste les mots-clés des fiches non indexées</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=listAddedThemes'>Liste les fiches ayant un thème ajouté</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=deleteAddedThemes'>Suppression des thèmes ajoutés</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=addthemes'>Ajout themes</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=chargethemes'>Charge cattheme$_GET[cat]</a></li>\n";
  echo "<li><a href='?cat=$_GET[cat]&amp;action=alternative'>Visualise alternative</a></li>\n";
  echo "</ul>\n";
  die();
}

if ($_GET['action'] == 'listTitles') { // Liste titres des fiches du périmètre avec lien
  $sql = "select id,title from catalog$_GET[cat]
          where type in ('dataset','series','Dataset','Dataset,series')
            and perimetre='Min'";
  foreach (PgSql::query($sql) as $tuple) {
    echo "<a href='a.php?cat=$_GET[cat]&amp;action=showPg&amp;id=$tuple[id]'>$tuple[title]<br>\n";
  }
  die();
}

elseif ($_GET['action'] == 'listkws') { // Liste les mots-clés des fiches non indexées
  $themes = new Taxonomy('themes.yaml');
  $kwValues = []; // [value => ['label'=> label, 'nbre'=> nbre]]
  $nbreMdd = 0;
  $nbreMatch = 0;
  $sql = "select id,title,record from catalog$_GET[cat]
          where type in ('dataset','series','Dataset','Dataset,series')
            and perimetre='Min'";
  foreach (PgSql::query($sql) as $tuple) {
    $id = $tuple['id'];
    $title = $tuple['title'];
    //$record = json_decode($record['record'], true);
    $record = Record::create($tuple['record']);
    //echo '<pre>$record='; print_r($record); echo "</pre>\n";
    if (!orgInSel($_GET['cat'], $record)) // si aucune organisation appartient à la sélection alors on saute
      continue;
    
    if ($themes->noKwIn($record['keyword'] ?? [])) {
      foreach ($record['keyword'] ?? [] as $keyword) {
        //echo "$keyword[value]<br>\n";
        if (!isset($keyword['value'])) {}
        elseif (!isset($kwValues[strtolower($keyword['value'])])) {
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

elseif ($_GET['action'] == 'listAddedThemes') { // Liste les fiches ayant un thème ajouté
  function existsAddedTheme(array $keywords): bool {
    foreach ($keywords as $keyword) {
      if (($thesaurusId = $keyword['thesaurusId'] ?? null)
        && in_array($thesaurusId, [
          'http://bdavid.alwaysdata.net/browsecat/arbocovadis.yaml',
          'https://raw.githubusercontent.com/benoitdavidfr/browsecat/main/themes.yaml'])) {
          return true;
      }
    }
    return false;
  }
  
  $nbWithAddedTheme = 0;
  $sql = "select id,title,record from catalog$_GET[cat] where type in ('dataset','series','Dataset','Dataset,series')";
  foreach (PgSql::query($sql) as $tuple) {
    $record = Record::create($tuple['record']);
    if (existsAddedTheme($record['keyword'] ?? [])) {
      echo "<a href='a.php?cat=$_GET[cat]&amp;action=showPg&amp;id=$tuple[id]'>$tuple[title]</a><br>\n";
      $nbWithAddedTheme++;
    }
  }
  echo "$nbWithAddedTheme fiches avec un thème ajouté<br>\n";
}

// Supprime les thèmes ajoutés dans la liste des mots-clés, retourne vrai ssi les keywords ont été modifiés
function deleteAddedThemesInKeywords(array &$keywords): bool {
  $modified = false;
  foreach ($keywords as $i => $keyword) {
    if (($thesaurusId = $keyword['thesaurusId'] ?? null)
      && in_array($thesaurusId, [
          'http://bdavid.alwaysdata.net/browsecat/arbocovadis.yaml',
          'https://raw.githubusercontent.com/benoitdavidfr/browsecat/main/themes.yaml'])) {
        unset($keywords[$i]);
        $modified = true;
    }
  }
  if ($modified)
    $keywords = array_values($keywords);
  return $modified;
}

function deleteAddedThemesInCat(string $catid): void { // Supprime les thèmes ajoutés dans un catalogue 
  $cat = new CatInPgSql($catid);
  $nbModified = 0;
  $sql = "select id,title,record from catalog$catid where type in ('dataset','series','Dataset','Dataset,series')";
  foreach (PgSql::query($sql) as $tuple) {
    $record = Record::create($tuple['record']);
    $keywords = $record['keyword'] ?? [];
    if (deleteAddedThemesInKeywords($keywords)) {
      if ($keywords)
        $record['keyword'] = $keywords;
      else
        unset($record['keyword']);
      $cat->updateRecord($tuple['id'], $record);
      echo "modifié: $tuple[title]<br>\n";
      $nbModified++;
    }
  }
  echo "$nbModified fiches modifiées dans $catid<br>\n";
}

if ($_GET['action'] == 'deleteAddedThemes') { // Suppression des thèmes ajoutés
  deleteAddedThemesInCat($_GET['cat']);
  die();
}

// essaie d'ajouter un thème en utilisant les regexp aux fiches de MD du catalogue $catid
function addThemesInCat(Taxonomy $themes, string $catid): void {
  $cat = new CatInPgSql($catid);
  $nbNoKw = 0; // nb fiches n'ayant aucun mot-clé correspondant à un thème
  $nbAdded = 0; // nb fiches sur lesquelles un thème a été ajouté
  $sql = "select id,title,record from catalog$catid
          where type in ('dataset','series','Dataset','Dataset,series') and perimetre='Min'";
  foreach (PgSql::query($sql) as $tuple) {
    $record = Record::create($tuple['record']);
    $keywords = $record['keyword'] ?? [];
    if (!$themes->noKwIn($keywords))
      continue;
    // Tente d'ajouter un/des thèmes
    $strings = array_merge(
      [$record['dct:title'][0]],
      isset($record['dct:alternative'][0]) ? [$record['dct:alternative'][0]] : []);
    if ($themes->addThemeInKeywords($keywords, $strings)) {
      $nbAdded++;
      $record['keyword'] = $keywords;
      $cat->updateRecord($tuple['id'], $record);
      //echo "<i>modifié: $tuple[title]</i><br>\n";
    }
    $nbNoKw++;
  }
  if ($nbNoKw)
    printf("Ajout de thèmes pour %d / %d soit %.0f %%<br>\n", $nbAdded, $nbNoKw, $nbAdded/$nbNoKw*100);
  else
    echo "Toutes les fiches correspondent à un thème.<br>\n";
}

if ($_GET['action'] == 'addthemes') { // Ajout de thèmes
  $themes = new Taxonomy('themes.yaml');
  addThemesInCat($themes, $_GET['cat']);
}

function chargethemes(Taxonomy $themes, string $catid) {
  PgSql::query("drop table if exists cattheme$catid");
  PgSql::query("create table cattheme$catid(
    id varchar(256) not null, -- fileIdentifier de la fiche de données
    theme text not null -- nom du theme
  )");
  
  $sql = "select id,title,record from catalog$catid
          where type in ('dataset','series','Dataset','Dataset,series') and perimetre='Min'";
  foreach (PgSql::query($sql) as $tuple) {
    $record = Record::create($tuple['record']);
    $prefLabels = $themes->prefLabelsFromKeywords($record['keyword'] ?? []);
    foreach ($prefLabels as $prefLabel) {
      $prefLabel = str_replace("'", "''", $prefLabel);
      PgSql::query("insert into cattheme$catid(id, theme) values('$tuple[id]', '$prefLabel')");
    }
  }
  
  PgSql::query("create unique index on cattheme$catid(id, theme)");
  PgSql::query("create index on cattheme$catid(theme)");
}

if ($_GET['action'] == 'chargethemes') { // Charge cattheme$_GET[cat]
  $themes = new Taxonomy('themes.yaml');
  chargethemes($themes, $_GET['cat']);
  die();
}

if ($_GET['action'] == 'alternative') { // Visualise alternative
  $alts = [];
  $sql = "select id,title,record from catalog$_GET[cat]
          where type in ('dataset','series','Dataset','Dataset,series') and perimetre='Min'";
  foreach (PgSql::query($sql) as $tuple) {
    $record = Record::create($tuple['record']);
    if ($alt = $record['dct:alternative'][0] ?? null)
      $alts[$alt] = 1;
  }
  ksort($alts);
  foreach ($alts as $alt => $un)
    echo "$alt<br>\n";
  die();
}
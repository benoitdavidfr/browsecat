<?php
/*PhpDoc:
title: index.php - page d'accueil
name: index.php
doc: |
journal: |
  13/11/2021:
    - transfert de l'exécution des actions dans a.php en ne laissant que les 2 menus
  12/11/2021:
    - transformation en page d'accueil
  8/11/2021:
    - renommage index.php -> manage.php pour utiliser index.php comme accès plus gd public
  27/10-4/11/2021:
    - réécriture nlle version utilisant PgSql
  18/10/2021:
    - création
includes:
  - cats.inc.php
*/
define('VERSION', "index.php 13/11/2021 10:11");
require_once __DIR__.'/cats.inc.php';

if (isset($_GET['action']))
  die("Erreur: action $_GET[action] non réalisable<br>\n");

if (!isset($_GET['cat'])) { // choix d'une action globale ou d'un catalogue
  echo "<h2>POC d'accès aux données</h2>\n";
  echo "<h3>Actions globales ou utilisation du catalogue agrégé</h3><ul>\n";
  echo "<li><a href='a.php?cat=agg&action=nbMdParOrgTheme&type=responsibleParty&arbo=arboCovadis'>",
    "Dénombrement des MDD par responsibleParty et arboCovadis</a></li>\n";
  //echo "<li><a href='a.php?action=listkws'>",
    //"Synthèse des taux d'appartenance des mots-clés aux 2 arborescences de thèmes</a></li>\n";
    // NE FONCTIONNE PAS !!
  echo "<li><a href='a.php?action=croise'>Nbre de MD communes entre catalogues</a></li>\n";
  echo "<li><a href='a.php?cat=agg&amp;action=listkws'>Liste des mots-clés du catalogue avec leur appartenance aux arborescences</a></li>\n";
  echo "<li><a href='a.php?action=version'>Affichage de la version des actions</a></li>\n";
  echo "</ul>\n";
  
  echo "<h3>Utilisation par catalogue</h3><ul>\n";
  foreach ($cats as $catid => $cat) {
    echo "<li><a href='a.php?cat=$catid'>$catid</a></li>\n";
  }
  echo "</ul>\n";
  echo "--<br>\nVersion ",VERSION,"<br>\n";
}
  
else { // (isset($_GET['cat'])) // choix d'une action sur un catalogue particulier
  echo "<h2>Actions sur le catalogue $_GET[cat]</h2><ul>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=nbMdParOrgTheme&amp;type=responsibleParty&amp;arbo=arboCovadis'>
    Dénombrement des MD par responsibleParty et arboCovadis</a></li>\n";  
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=nbMdParOrg&amp;type=responsibleParty'>Dénombrement des MD par responsibleParty</a></li>\n";
  //echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=nbMdParTheme'>Dénombrement des MD par thème des 2 arborescences</a></li>\n";
  // NE FONCTIONNE PAS !!
  echo "<br>\n";

  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=orgsHorsSel'>Liste des organisations hors périmètre</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=listkws'>Liste les mots-clés des MDD du périmètre</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=mdContacts'>Liste les mdContacts des MDD du périmètre</a></li>\n";
  echo "<br>\n";

  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=listdata'>Liste des titres de toutes les MDD (type dataset ou series)</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=listdataperimetre'>Liste des titres des MDD du périmètre</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=listdataYaml'>Liste de toutes les MDD en Yaml (type dataset ou series)</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=listservices'>Liste des titres des MD de service</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=orgs'>Liste des organisations du périmètre</a></li>\n";
  echo "</ul>\n";
  // Menu
  echo "Affiche une MD à partir de son id<br>\n";
  echo "<form action='a.php'>\n";
  echo "<input type=hidden name='cat' value='$_GET[cat]'>\n";
  echo "id: <input type=text size='80' name='id'>\n";
  echo "<select name='action'>\n";
  echo "  <option value='showPg'>showPg</option>\n";
  echo "  <option value='showIsoXml'>showIsoXml</option>\n";
  echo "  <option value='showDcXml'>showDcXml</option>\n";
  echo "  <option value='showPath'>showPath</option>\n";
  echo "  <option value='extract'>extract</option>\n";
  echo "</select>\n";
  echo "<input type=submit value='go'>\n";
  echo "</form>\n";
}
die();

<?php
/*PhpDoc:
title: gere.php - visualise et exploite le contenu des catalogues moissonnés et stockés dans PgSql
name: gere.php
doc: |
  Je m'intéresse principalement aux MDD du périmètre ministériel, cad les MDD dont un au moins des responsibleParty
  est une DG du pôle ministériel (MTE/MCTRCT/MM) ou un service déconcentré du pôle ou une DTT(M).
  Les mdContacts de ces MDD ne sont parfois pas des organisations du pôle.
journal: |
  13/11/2021:
    - transfert de l'exécution des actions dans a.php en ne laissant que les 2 menus
  11/11/2021:
    - carte de thème indépendamment de l'org
  10/11/2021:
    - renommage manage.php -> gere.php
  8/11/2021:
    - renommage index.php -> manage.php pour utiliser index.php comme accès plus gd public
  27/10-4/11/2021:
    - réécriture nlle version utilisant PgSql
  18/10/2021:
    - création
includes:
  - cats.inc.php
*/
define('VERSION', "gere.php 13/11/2021 10:30");
require_once __DIR__.'/cats.inc.php';

if (isset($_GET['action']))
  die("Erreur: action $_GET[action] non réalisable<br>\n");

if (!isset($_GET['cat'])) { // choix d'une action globale ou d'un catalogue
  echo "Catalogues:<ul>\n";
  foreach ($cats as $catid => $cat) {
    echo "<li><a href='?cat=$catid'>$catid</a></li>\n";
  }
  echo "</ul>\n";
  echo "Actions globales:<ul>\n";
  echo "<li><a href='a.php?action=createAggTable'>(Re)Créer une table agrégeant les catalogues</a></li>\n";
  echo "<li><a href='a.php?action=createAggSel'>(Re)Créer un fichier agrégeant les organisations du périmètre</a></li>\n";
  echo "<li><a href='a.php?cat=agg&amp;action=listkws'>Lister les mots-clés du catalogue agrégé</a></li>\n";
  echo "<li><a href='a.php?action=listkws'>Synthèse des taux d'appartenance des mots-clés aux arborescences</a></li>\n";
  echo "<li><a href='a.php?action=croise'>Calcul du nbre de MD communes entre catalogues</a></li>\n";
  echo "<li><a href='a.php?action=diffgeocat'>Affichage des MDD du Géocatalogue du périmètre n'appartenant pas à agg</a></li>\n";
  echo "<li><a href='a.php?action=searchById'>Cherche dans les différents catalogues dans lesquels cette fiche est présente</a></li>\n";
  echo "<li><a href='a.php?action=version'>Affichage de la version des actions</a></li>\n";
  echo "</ul>\n";
  echo "--<br>\nVersion ",VERSION,"<br>\n";
}
  
else { // (isset($_GET['cat'])) // choix d'une action sur un catalogue particulier
  echo "Actions proposées:<ul>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=listdata'>Toutes les MDD (type dataset ou series)</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=listdataYaml'>Toutes les MDD (type dataset ou series) en Yaml</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=listservices'>Toutes les MD de service</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=orgsHorsSel'>Liste des organisations hors périmètre</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=orgsHorsArbo&amp;type=responsibleParty'>
    Liste des responsibleParty hors arbo. des orgs.</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=orgsHorsArbo&amp;type=mdContact'>
    Liste des mdContact hors arbo. des orgs. des MDD du périmètre</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=orgs'>Liste des organisations du périmètre</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=listdataperimetre'>Liste les MDD du périmètre</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=listkws'>Liste les mots-clés des MDD du périmètre</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=ldwkw'>
    MDD dont au moins un mot-clé correspond à une des arborescences</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=ldnkw'>MDD dont aucun mot-clé correspond à une des arborescences</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=setPerimetre'>Enregistre le périmetre sur les MD</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=mdContacts'>Liste les mdContacts des MDD du périmètre</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=qualibbox'>Analyse de la qualité des BBox</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=nbMdParTheme'>Dénombrement des MD par thème</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=nbMdParOrg&amp;type=responsibleParty'>Dénombrement des MD par responsibleParty</a></li>\n";
  echo "<li><a href='a.php?cat=$_GET[cat]&amp;action=nbMdParOrgTheme&amp;type=responsibleParty&amp;arbo=arboCovadis'>
    Dénombrement des MD par responsibleParty et arboCovadis</a></li>\n";  
  echo "</ul>\n";
  // Menu
  echo "Affichage d'une fiche à partir de son id<br>\n";
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

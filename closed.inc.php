<?php
/*PhpDoc:
title: closed.inc.php - actions de visualisation et gestion du contenu des catalogues moissonnés et stockés dans PgSql
name: closed.inc.php
doc: |
journal: |
  22/11/2021:
    - création
*/
if ($_SERVER['HTTP_HOST']<>'localhost') {
  die("<b>Ce site est actuellement fermé pour maintenance, merci de revenir plus tard.</b>\n");
}

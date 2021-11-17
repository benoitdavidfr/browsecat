<?php
/*PhpDoc:
name:  dublincore.inc.php
title: dublincore.inc.php - classe statique DublinCore pour effectuer la transformation du XML en array
classes:
functions:
doc: |
journal: |
  16-17/11/2021:
    - création
*/

/*PhpDoc: classes
name:  DublinCore
title: DublinCore - classe statique DublinCore pour effectuer la transformation du XML en array
pubproperties:
methods:
doc: |
*/

class DublinCore {
  // transforme un XML sous forme SimpleXMLElement en enregistrement array
  static function cswRecord2Array(SimpleXMLElement $cswRecord): array {
    $mdrecord = [];
    $mdrecord['fileIdentifier'] = [(string)$cswRecord->dc_identifier];
    if (!$mdrecord['fileIdentifier']) {
      echo "Erreur dans DublinCore::cswRecord2Array sur cswRecord="; print_r($cswRecord);
      return [];
    }
    $mdrecord['dct:title'] = [ (string)$cswRecord->dc_title ];
    $mdrecord['dct:type'] = [ (string)$cswRecord->dc_type ];
    if ($cswRecord->dct_abstract)
      $mdrecord['dct:abstract'] = [(string)$cswRecord->dct_abstract];
    if ($cswRecord->dc_description)
      $mdrecord['dc:description'] = [(string)$cswRecord->dc_description];
    $mdrecord['dct:references'] = [[
      'scheme'=> $cswRecord->dct_references['scheme'],
      'href'=> (string)$cswRecord->dct_references,
    ]];
    if ($cswRecord->dc_subject) {
      $mdrecord['dc:subject'] = [];
      foreach ($cswRecord->dc_subject as $subject)
        $mdrecord['dc:subject'][] = (string)$subject;
    }
    if ($cswRecord->ows_BoundingBox) {
      $ows_LowerCorner = explode(' ', (string)$cswRecord->ows_BoundingBox->ows_LowerCorner);
      $ows_UpperCorner = explode(' ', (string)$cswRecord->ows_BoundingBox->ows_UpperCorner);
      $mdrecord['dcat:bbox'] = [[
        'southLat'=> $ows_LowerCorner[0],
        'westLon'=>  $ows_LowerCorner[1],
        'northLat'=> $ows_UpperCorner[0],
        'eastLon'=>  $ows_UpperCorner[1],
      ]];
    }
    $mdrecord['dublinCore'] = $cswRecord;
    return $mdrecord;
  }
  
  // transforme un XML sous forme de chaine de caractères en enregistrement array
  static function extract(string $id, string $xmlstring): array {
    //echo "DublinCore::extract(id=$id)\n";
    $xmlstring = preg_replace('!<(/)?([^:]+):!', '<$1$2_', $xmlstring);
    try {
      $xmlelt = new SimpleXMLElement($xmlstring);
    }
    catch (Exception $e) { 
      throw new Exception("Erreur sur new SimpleXMLElement");
    }
    if ($xmlelt->ows_Exception) {
      $result['ows_Exception']['exceptionCode'] = (string)$xmlelt->ows_Exception['exceptionCode'];
      $result['ows_Exception']['ows_ExceptionText'] = (string)$xmlelt->ows_Exception->ows_ExceptionText;
      return $result;
    }
    if ($xmlelt->csw_Record) {
      return self::cswRecord2Array($xmlelt->csw_Record);
    }
  }
};

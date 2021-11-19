<?php
/*PhpDoc:
title: catinsql.inc.php - gestion d'un catalogue dans PgSql
name: catinpgsql.inc.php
doc: |
journal: |
  27/10/2021:
    - création
includes:
  - ../phplib/pgsql.inc.php
*/
require_once __DIR__.'/../phplib/pgsql.inc.php';

$mdvars = [
  // fileIdentifier (hors règlement INSPIRE)
  'fileIdentifier' => [
    'title-fr' => "Identificateur du fichier",
    'title-en' => "File identifier",
    'xpath' => '//gmd:MD_Metadata/gmd:fileIdentifier/*',
    'multiplicity' => [ 'data' => 1, 'service' => 1 ],
  ],
  // parentIdentifier (hors règlement INSPIRE)
  'parentIdentifier' => [
    'title-fr' => "Identificateur d'un parent",
    'title-en' => "Parent identifier",
    'xpath' => '//gmd:MD_Metadata/gmd:parentIdentifier/*',
    'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
  ],
  // aggregationInfo (hors règlement INSPIRE)
  'aggregationInfo' =>  [
    'title-fr' => "métadonnées agrégées",
    'title-en' => "aggregated metadata",
    'xpath' => '//gmd:identificationInfo/*/gmd:aggregationInfo',
    'svars' => [
      'aggregateDataSetIdentifier' => [
        'xpath' => '//gmd:aggregationInfo/*/gmd:aggregateDataSetIdentifier/*/gmd:code/gco:CharacterString',
      ],
      'associationType' => [
        'xpath' => '//gmd:associationType/*',
      ],
      'initiativeType' => [
        'xpath' => '//gmd:initiativeType/*',
      ],
    ],
    'multiplicity' => [ 'data' => '0..*' ],
  ],
  // 1.1. Intitulé de la ressource - 1.1. Resource title
  'dct:title' => [
    'title-fr' => "Intitulé de la ressource",
    'title-en' => "Resource title",
    'xpath' => '//gmd:identificationInfo/*/gmd:citation/*/gmd:title/gco:CharacterString',
    'multiplicity' => [ 'data' => 1, 'service' => 1 ],
  ],
  // alternateTitle (hors règlement INSPIRE)
  'dct:alternative' => [
    'title-fr' => "Intitulé alternatif de la ressource",
    'title-en' => "Alternate resource title",
    'xpath' => '//gmd:identificationInfo/*/gmd:citation/*/gmd:alternateTitle/gco:CharacterString',
    'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
  ],
  // 1.2. Résumé de la ressource - 1.2. Resource abstract
  'dct:description' => [
    'title-fr' => "Résumé de la ressource",
    'title-en' => "Resource abstract",
    'xpath' => '//gmd:identificationInfo/*/gmd:abstract/gco:CharacterString',
    'text' => 1,
    'multiplicity' => [ 'data' => 1, 'service' => 1 ],
  ],
  // 1.3. Type de la ressource - 1.3. Resource type
  'dct:type' => [
    'title-fr' => "Type de la ressource",
    'title-en' => "Resource type",
    'xpath' => '//gmd:MD_Metadata/gmd:hierarchyLevel/gmd:MD_ScopeCode/@codeListValue',
    'valueDomain' => ['series','dataset','services'],
    'multiplicity' => [ 'data' => 1, 'service' => 1 ],
  ],
  // 1.4. Localisateur de la ressource - 1.4. Resource locator
  'locator'=> [
    'title-fr' => "Localisateur de la ressource",
    'title-en' => "Resource locator",
    'xpath' =>  '//gmd:distributionInfo/*/gmd:transferOptions/*/gmd:onLine',
    'svars' => [
      'url' => [
        'xpath' => '//gmd:onLine/*/gmd:linkage/gmd:URL',
      ],
      'protocol' => [
        'xpath' => '//gmd:onLine/*/gmd:protocol/gco:CharacterString',
      ],
      'name' => [
        'xpath' => '//gmd:onLine/*/gmd:name/gco:CharacterString',
      ],
    ],
    'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
  ],
  // 1.5. Identificateur de ressource unique - 1.5. Unique resource identifier
  'dct:identifier' => [
    'title-fr' => "Identificateur de ressource unique",
    'title-en' => "Unique resource identifier",
    'xpath' => '//gmd:identificationInfo/*/gmd:citation/*/gmd:identifier',
    'svars' => [
      'codeSpace' => [
        'xpath' => '//gmd:identifier/*/gmd:codeSpace/gco:CharacterString',
      ],
      'code' => [
        'xpath' => '//gmd:identifier/*/gmd:code/gco:CharacterString',
      ],
    ],
    'multiplicity' => [ 'data' => '1..*' ],
  ],
  // 1.6.  Ressource Couplée (service) - 1.6. Coupled resource
  'operatesOn' => [
    'title-fr' => "Ressource Couplée",
    'title-en' => "Coupled resource",
    'xpath' => '//gmd:identificationInfo/*/srv:operatesOn',
    'svars'=> [
      'uuidref'=> [
        'xpath' => '//srv:operatesOn/@uuidref',
      ],
      'href'=> [
        'xpath' => '//srv:operatesOn/@xlink:href',
      ],
    ],
    'multiplicity' => [ 'service' => '0..*' ],
  ],
  // 1.7. Langue de la ressource - 1.7. Resource language
  'dct:language' => [
    'title-fr' => "Langue de la ressource",
    'title-en' => "Resource language",
    'xpath' => '//gmd:identificationInfo/*/gmd:language/gmd:LanguageCode',
    'svars'=> [
      'codeList'=> [
        'xpath'=> '//gmd:LanguageCode/@codeList',
      ],
      'codeListValue'=> [
        'xpath'=> '//gmd:LanguageCode/@codeListValue',
      ],
    ],
    'multiplicity' => [ 'data' => '0..*' ],
  ],
  // Encodage (hors règlement INSPIRE)
  'distributionFormat' => [
    'title-fr' => "Encodage",
    'title-en' => "Distribution format",
    'xpath' => '//gmd:distributionInfo/*/gmd:distributionFormat',
    'svars'=> [
      'name'=> [
        'xpath' => '//gmd:distributionFormat/*/gmd:name/gco:CharacterString',
      ],
      'version'=> [
        'xpath' => '//gmd:distributionFormat/*/gmd:version/gco:CharacterString',
      ],
    ],
    'multiplicity' => [ 'data' => '1..*' ],
  ],
  // Encodage des caractères (hors règlement INSPIRE)
  'characterSet' => [
    'title-fr' => "Encodage des caractères",
    'title-en' => "Character set",
    'xpath' => '//gmd:identificationInfo/*/gmd:characterSet/gmd:MD_CharacterSetCode/@codeListValue',
    'multiplicity' => [ 'data' => '0..1' ],
  ],
  // Type de représentation géographique (hors règlement INSPIRE)
  'spatialRepresentationType' => [
    'title-fr' => "Type de représentation géographique",
    'title-en' => "Spatial representation type",
    'xpath' => '//gmd:identificationInfo/*/gmd:spatialRepresentationType/gmd:MD_SpatialRepresentationTypeCode/@codeListValue',
    'multiplicity' => [ 'data' => '1..*' ],
  ],

  // 2. CLASSIFICATION DES DONNÉES ET SERVICES GÉOGRAPHIQUES
  // 2.1. Catégorie thématique - 2.1. Topic category
  'topicCategory' => [
    'title-fr' => "Catégorie thématique",
    'title-en' => "Topic category",
    'xpath' => '//gmd:identificationInfo/*/gmd:topicCategory/gmd:MD_TopicCategoryCode',
    'multiplicity' => [ 'data' => '1..*' ],
    'valueDomain' => [
        'farming','biota','boundaries','climatologyMeteorologyAtmosphere','economy','elevation','environment',
        'geoscientificInformation','health','imageryBaseMapsEarthCover','intelligenceMilitary','inlandWaters',
        'location','oceans','planningCadastre','society','structure','transportation','utilitiesCommunication'
    ],
  ],
  // 2.2.  Type de service de données géographiques (service) - 2.2. Spatial data service type
  'serviceType' => [
    'title-fr' => "Type de service de données géographiques",
    'title-en' => "Spatial data service type",
    'xpath' => '//gmd:identificationInfo/*/srv:serviceType/gco:LocalName',
    'multiplicity' => [ 'service' => 1 ],
    'valueDomain' => ['discovery','view','download','transformation','invoke','other']
  ],

  // 3. MOT CLÉ - KEYWORD
  // 3.1. Valeur du mot clé - Keyword value
  // 3.2. Vocabulaire contrôlé d’origine - Originating controlled vocabulary
  'keyword' => [
    'title-fr' => "Mot-clé",
    'title-en' => "Keyword",
    'xpath' => '//gmd:identificationInfo/*/gmd:descriptiveKeywords',
    'svars'=> [
      'value'=> [
        'xpath' => '//gmd:descriptiveKeywords/*/gmd:keyword/gco:CharacterString',
      ],
      'thesaurusTitle'=> [
        'xpath' => '//gmd:descriptiveKeywords/*/gmd:thesaurusName/*/gmd:title/gco:CharacterString',
      ],
      'thesaurusDate'=> [
        'xpath' => '//gmd:descriptiveKeywords/*/gmd:thesaurusName/*/gmd:date/*/gmd:date/gco:Date',
      ],
      'thesaurusDateType'=> [
        'xpath' => '//gmd:descriptiveKeywords/*/gmd:thesaurusName/*/gmd:date/*/gmd:dateType'
            .'/gmd:CI_DateTypeCode/@codeListValue',
      ],
      'thesaurusId'=> [
        'xpath' => '//gmd:descriptiveKeywords/*/gmd:thesaurusName/*/gmd:identifier/*/gmd:code/gmx:Anchor/@xlink:href',
      ],
    ],
    'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
  ],

  // 4. SITUATION GÉOGRAPHIQUE - 4. GEOGRAPHIC LOCATION
  // 4.1. Rectangle de délimitation géographique - 4.1. Geographic bounding box
  // revoir le path pour les services
  'dcat:bbox' => [
    'title-fr' => "Rectangle de délimitation géographique",
    'title-en' => "Geographic bounding box",
    'xpath' => '//gmd:identificationInfo/*/gmd:extent/*/gmd:geographicElement/gmd:EX_GeographicBoundingBox',
    'svars'=> [
      'westLon'=> [
        'xpath' => '//gmd:EX_GeographicBoundingBox/gmd:westBoundLongitude/gco:Decimal',
      ],
      'eastLon'=> [
        'xpath' => '//gmd:EX_GeographicBoundingBox/gmd:eastBoundLongitude/gco:Decimal',
      ],
      'southLat'=> [
        'xpath' => '//gmd:EX_GeographicBoundingBox/gmd:southBoundLatitude/gco:Decimal',
      ],
      'northLat'=> [
        'xpath' => '//gmd:EX_GeographicBoundingBox/gmd:northBoundLatitude/gco:Decimal',
      ],
    ],
    'multiplicity' => [ 'data' => '1..*', 'service' => '0..*' ],
  ],

  // 5. RÉFÉRENCE TEMPORELLE
  // 5.1. Étendue temporelle - 5.1. Temporal extent
  'dct:temporal' => [
    'title-fr' => "Étendue temporelle",
    'title-en' => "Temporal extent",
    'xpath' => '//gmd:identificationInfo/*/gmd:extent/*/gmd:temporalElement/*/gmd:extent/gml:TimePeriod',
    'svars'=> [
      'begin' => [
        'xpath' => '//gml:TimePeriod/gml:beginPosition',
      ],
      'end' => [
        'xpath' => '//gml:TimePeriod/gml:endPosition',
      ],
    ],
    'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
  ],
  // 5.2. Date de publication - 5.2. Date of publication
  'dct:issued' => [
    'title-fr' => "Date de publication",
    'title-en' => "Date of publication",
    // identificationInfo[1]/*/citation/*/date[./*/dateType/*/text()='publication']/*/date
    'xpath' => "//gmd:identificationInfo/*/gmd:citation/*/gmd:date[./gmd:CI_Date/gmd:dateType/*"
        ."/@codeListValue='publication']/gmd:CI_Date/gmd:date/gco:Date",
    'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
  ],
  // 5.3. Date de dernière révision - 5.3. Date of last revision
  'dct:modified' => [
    'title-fr' => "Date de dernière révision",
    'title-en' => "Date of last revision",
    // identificationInfo[1]/*/citation/*/date[./*/dateType/*/text()='revision']/*/date
    'xpath' => "//gmd:identificationInfo/*/gmd:citation/*/gmd:date[./gmd:CI_Date/gmd:dateType/*"
        ."/@codeListValue='revision']/gmd:CI_Date/gmd:date/gco:Date",
    'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
  ],
  // 5.4. Date de création - 5.4. Date of creation
  'dct:created' => [
    'title-fr' => "Date de création",
    'title-en' => "Date of creation",
    // identificationInfo[1]/*/citation/*/date[./*/dateType/*/text()='creation']/*/date
    'xpath' => "//gmd:identificationInfo/*/gmd:citation/*/gmd:date[./gmd:CI_Date/gmd:dateType/*"
        ."/@codeListValue='creation']/gmd:CI_Date/gmd:date/gco:Date",
    'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
  ],

  // 6. QUALITÉ ET VALIDITÉ - 6. QUALITY AND VALIDITY
  // 6.1. Généalogie - 6.1. Lineage
  'dct:provenance' => [
    'title-fr' => "Généalogie",
    'title-en' => "Lineage",
    'xpath' => '//gmd:dataQualityInfo/*/gmd:lineage/*/gmd:statement/gco:CharacterString',
    'text' => 1,
    'multiplicity' => [ 'data' => 1 ],
  ],
  // 6.2. Résolution spatiale - 6.2. Spatial resolution
  'spatialResolutionScaleDenominator' => [
    'title-fr' => "Résolution spatiale : dénominateur de l'échelle",
    'title-en' => "Spatial resolution: scale denominator",
    'xpath' => '//gmd:identificationInfo/*/gmd:spatialResolution/*/gmd:equivalentScale/*/gmd:denominator/gco:Integer',
    'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
  ],
  'spatialResolutionDistance' => [
    'title-fr' => "Résolution spatiale : distance",
    'title-en' => "Spatial resolution: distance",
    'xpath' => '//gmd:identificationInfo/*/gmd:spatialResolution/*/gmd:distance',
    'svars'=> [
      'unit'=> [
        'xpath' => '//gmd:distance/gco:Distance/@uom',
      ],
      'value'=> [
        'xpath' => '//gmd:distance/gco:Distance',
      ],
    ],
    'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
  ],

  // 7. CONFORMITÉ - 7. CONFORMITY
  // 7.1. Spécification - 7.1. Specification
  // dataQualityInfo/*/report/*/result/*/specification
  'dct:conformsTo' => [
    'title-fr' => "Spécification",
    'title-en' => "Specification",
    'xpath' => '//gmd:dataQualityInfo/*/gmd:report/*/gmd:result',
    'svars'=> [
      'specificationDate'=> [
        'xpath' => '//gmd:result/*/gmd:specification/*/gmd:date/*/gmd:date/gco:Date',
      ],
      'specificationTitle'=> [
        'xpath' => '//gmd:result/*/gmd:specification/*/gmd:title/gco:CharacterString', // Anchor !!
      ],
      // 7.2. Degré - 7.2. Degree
      // dataQualityInfo/*/report/*/result/*/pass
      'pass'=> [
        'xpath' => '//gmd:result/*/gmd:pass/gco:Boolean',
      ],
    ],
    'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
  ],

  // 8. CONTRAINTES EN MATIÈRE D’ACCÈS ET D’UTILISATION - 8. CONSTRAINT RELATED TO ACCESS AND USE
  // 8.1. Conditions applicables à l’accès et à l’utilisation - 8.1. Conditions applying to access and use
  'useLimitation' => [
    'title-fr' => "Conditions d'utilisation",
    'title-en' => "Use conditions",
    'xpath' => '//gmd:identificationInfo/*/gmd:resourceConstraints/*/gmd:useLimitation/gco:CharacterString',
    'text' => 1,
    'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
  ],

  // 8.2. Restrictions concernant l’accès public - 8.2. Limitations on public access
  'accessConstraints' => [
    'title-fr' => "Restrictions concernant l’accès public",
    'title-en' => "Limitations on public access",
    'xpath' => '//gmd:identificationInfo/*/gmd:resourceConstraints/gmd:MD_LegalConstraints',
    // identificationInfo[1]/*/resourceConstraints/*/accessConstraints
    'svars'=> [
      'code'=> [
        'xpath' => '//gmd:MD_LegalConstraints/gmd:accessConstraints/gmd:MD_RestrictionCode/@codeListValue',
      ],
      // identificationInfo[1]/*/resourceConstraints/*/otherConstraints
      'others'=> [
        'xpath' => '//gmd:MD_LegalConstraints/gmd:otherConstraints/gco:CharacterString',
      ],
    ],
    'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
  ],
  // identificationInfo[1]/*/resourceConstraints/*/classification
  'classification' => [
    'title-fr' => "Contrainte de sécurité intéressant la Défense nationale",
    'title-en' => "Classification",
    'xpath' => '//gmd:identificationInfo/*/gmd:resourceConstraints/*/gmd:classification'
        .'/gmd:MD_ClassificationCode/@codeListValue',
    'multiplicity' => [ 'data' => '0..*', 'service' => '0..*' ],
  ],

  // 9. ORGANISATIONS RESPONSABLES DE L’ÉTABLISSEMENT, DE LA GESTION, DE LA MAINTENANCE ET DE LA DIFFUSION DES SÉRIES
  //    ET DES SERVICES DE DONNÉES GÉOGRAPHIQUES
  // 9. ORGANISATIONS RESPONSIBLE FOR THE ESTABLISHMENT, MANAGEMENT, MAINTENANCE AND DISTRIBUTION OF SPATIAL DATA SETS
  //    AND SERVICE
  // 9.1. Partie responsable - 9.1. Responsible party
  'responsibleParty' => [
    'title-fr' => "Partie responsable",
    'title-en' => "Responsible party",
    'xpath' => '//gmd:identificationInfo/*/gmd:pointOfContact',
    'svars' => [
      'organisationName' => [
        'xpath' => '//gmd:pointOfContact/*/gmd:organisationName/gco:CharacterString', // Free Text Element !!!
        //'stdFunc' => 'stdOrganisationName',
      ],
      // 9.2. Rôle de la partie responsable - 9.2. Responsible party role
      'role' => [
        'xpath' => '//gmd:pointOfContact/*/gmd:role/gmd:CI_RoleCode/@codeListValue',
        //'stdFunc' => true,
      ],
      // Ajout 20/2/2021
      'individualName' => [
        'xpath' => '//gmd:pointOfContact/*/gmd:individualName/gco:CharacterString',
      ],
      'electronicMailAddress' => [
        'xpath' => '//gmd:pointOfContact/*/gmd:contactInfo/*/gmd:address/*/gmd:electronicMailAddress/gco:CharacterString',
      ],
    ],
    'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
  ],

  // 10. Métadonnées concernant les métadonnées - METADATA ON METADATA
  // 10.1. Point de contact des métadonnées - 10.1. Metadata point of contact
  'mdContact' => [
    'title-fr' => "Point de contact des métadonnées",
    'title-en' => "Metadata point of contact",
    'xpath' => '//gmd:contact',
    'svars'=> [
      'organisationName'=> [
        'xpath'=> '//gmd:contact/*/gmd:organisationName/gco:CharacterString',
      ],
      // Ajout 20/2/2021
      'individualName' => [
        'xpath' => '//gmd:contact/*/gmd:individualName/gco:CharacterString',
      ],
      'electronicMailAddress' => [
        'xpath' => '//gmd:contact/*/gmd:contactInfo/*/gmd:address/*/gmd:electronicMailAddress/gco:CharacterString',
      ],
    ],
    'multiplicity' => [ 'data' => '1..*', 'service' => '1..*' ],
  ],
  // 10.2. Date des métadonnées - 10.2. Metadata date
  'mdDate' => [
    'title-fr' => "Date des métadonnées",
    'title-en' => "Metadata date",
    'xpath' => '//gmd:dateStamp/gco:DateTime',
    'multiplicity' => [ 'data' => 1, 'service' => 1 ],
  ],
  // 10.3. Langue des métadonnées - 10.3. Metadata language
  'mdLanguage' => [
    'title-fr' => "Langue des métadonnées",
    'title-en' => "Metadata language",
    'xpath' => '//gmd:MD_Metadata/gmd:language/gmd:LanguageCode',
    'svars'=> [
      'codeList'=> [
        'xpath'=> '//gmd:LanguageCode/@codeList',
      ],
      'codeListValue'=> [
        'xpath'=> '//gmd:LanguageCode/@codeListValue',
      ],
    ],
    'multiplicity' => [ 'data' => 1, 'service' => 1 ],
  ],
  'mdLanguageAsString' => [
    'title-fr' => "Langue des métadonnées (codé comme gco:CharacterString)",
    'title-en' => "Metadata language",
    'xpath' => '//gmd:MD_Metadata/gmd:language/gco:CharacterString',
    'multiplicity' => [ 'data' => 1, 'service' => 1 ],
  ],
];

class CatInPgSql {
  const SERVERS = [
    'local' => 'host=pgsqlserver dbname=gis user=docker',
    'distant' => 'pgsql://benoit@db207552-001.dbaas.ovh.net:35250/catalog/public',
  ];
  
  private string $catid;
  
  static function chooseServer(?string $server): bool { // retourne vrai si le paramètre est ok, sinon faux
    if ($params = self::SERVERS[$server] ?? null) {
      PgSql::open($params);
      return true;
    }
    else
      return false;
  }
  
  function __construct(string $catid) { $this->catid = $catid; }
  
  function create(): void { // crée une table pour stocker les métadonnées du catalogue
    $catid = $this->catid;
    PgSql::query("drop table if exists catalog$catid");
    PgSql::query("create table catalog$catid(
      id varchar(256) not null primary key, -- fileIdentifier
      record json, -- enregistrement de la fiche en JSON
      title text, -- 1.1. Intitulé de la ressource
      type varchar(256), -- 1.3. Type de la ressource
      perimetre varchar(256) -- 'Min','Op','Autres' ; null si non défini
    )");
  }
  
  function storeRecord(array $record, string $idProperty='fileIdentifier'): void { // enregistre une fiche de métadonnées
    //print_r($record);
    $catid = $this->catid;
    if (isset($record[$idProperty]) && is_array($record[$idProperty])) {
      $id = $record[$idProperty][0];
    }
    elseif (isset($record[$idProperty])) {
      $id = $record[$idProperty];
    }
    else {
      echo "$idProperty non défini dans storeRecord<br>\n";
      print_r($record);
      die();
    }
    $recjson = str_replace("'", "''", json_encode($record, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    
    $title = $record['dct:title'][0] ?? $record['title'] ?? "NON DEFINI";
    $title = str_replace("'", "''", $title);
    $type = $record['dct:type'][0] ?? $record['@type'] ?? "NON DEFINI";
    if (is_array($type))
      $type = implode(',', $type);
    $type = str_replace("'", "''", $type);

    try {
      PgSql::query("insert into catalog$catid(id,record,title,type)"
        ." values('$id','$recjson','$title','$type')");
    }
    catch (Exception $e) {
      echo "Erreur dans storeRecord: ", $e->getMessage(),"<br>\n";
      echo "title: $title<br>\n";
    }
  }
  
  function updateRecord(string $id, array $record) {
    $catid = $this->catid;
    $recjson = str_replace("'", "''", json_encode($record, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    $sql = "update catalog$catid set record='$recjson' where id='$id'";
    //echo "$sql\n";
    PgSql::query($sql);
  }
};

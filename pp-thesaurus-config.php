<?php

// system definitions
define('PP_THESAURUS_PLUGIN_NAME', 'Climate change glossary');
define('PP_THESAURUS_PLUGIN_URL', 'https://wordpress.org/plugins/climate-change-glossary');
define('PP_THESAURUS_SHORTCODE_PREFIX', 'ccg');
define('PP_THESAURUS_ARC_URL', 'https://github.com/semsol/arc2/tarball/master');

// settings definitions
define('PP_THESAURUS_DBPEDIA_ENDPOINT_SHOW', FALSE);
define('PP_THESAURUS_DBPEDIA_ENDPOINT', 'http://sparql.reeep.org');
define('PP_THESAURUS_ENDPOINT_SHOW', FALSE);
define('PP_THESAURUS_ENDPOINT', 'http://poolparty.reegle.info/PoolParty/sparql/glossary');
define('PP_THESAURUS_IMPORT_FILE_SHOW', FALSE);

// parse exceptions
define('PP_THESAURUS_SPARQL_FILTER', 'OPTIONAL { ?concept skos:notation ?notation. } FILTER (!bound(?notation)).');
define('PP_THESAURUS_DESCRIPTION_EXCEPTION', '/No reegle definition available/i');

// sidebar widget definitions
define('PP_THESAURUS_SIDEBAR_TITLE', 'Glossary Search');
define('PP_THESAURUS_SIDEBAR_DESCRIPTION', 'Search field for the climate change glossary');
define('PP_THESAURUS_SIDEBAR_INFO', 'Type a term ...');

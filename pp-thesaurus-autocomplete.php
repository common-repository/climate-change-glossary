<?php
$sPath = dirname( dirname( dirname( dirname( __FILE__ ) ) ) );

require_once( $sPath . '/wp-load.php' );
require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/PPThesaurusManager.class.php' );
require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/PPThesaurusItem.class.php' );
require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/PPThesaurusPage.class.php' );

$oPPTManager = PPThesaurusManager::getInstance();
$aConcepts   = $oPPTManager->searchConcepts( $_GET['q'], $_GET['lang'], 100 );

echo implode( "\n", $aConcepts );

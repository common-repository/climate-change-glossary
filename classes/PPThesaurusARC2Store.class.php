<?php

if ( file_exists( PP_THESAURUS_PLUGIN_DIR . 'arc/ARC2.php' ) ) {
	require_once( PP_THESAURUS_PLUGIN_DIR . 'arc/ARC2.php' );
}


function getWpPrefix() {
	global $wpdb;

	return $wpdb->prefix;
}


class PPThesaurusARC2Store {

	const SKOS_CORE = 'http://www.w3.org/2004/02/skos/core#';
	const SLUG = 'pp-thesaurus';

	protected static $oInstance;
	protected $oStore;
	protected $bExistsData;


	protected function __construct() {
		$this->oStore      = ARC2::getStore( self::getStoreConfig() );
		$this->bExistsData = NULL;
	}

	/**
	 * Return an instance of a class.
	 *
	 * @return object
	 *   A single instance of this class.
	 */
	public static function getInstance() {
		if ( ! isset( self::$oInstance ) ) {
			$sClass          = __CLASS__;
			self::$oInstance = new $sClass();
		}

		return self::$oInstance;
	}

	/**
	 * Returns the configuration array for the triple store.
	 */
	protected function getStoreConfig() {
		$aConfig = array(
			'db_host'    => DB_HOST,
			'db_name'    => DB_NAME,
			'db_user'    => DB_USER,
			'db_pwd'     => DB_PASSWORD,
			'store_name' => getWpPrefix() . 'pp_thesaurus',
		);

		return $aConfig;
	}

	/**
	 * Returns the triple store object.
	 */
	public function getStore() {
		return $this->oStore;
	}

	/**
	 * Creates the tables for the triple store.
	 */
	public function setUp() {
		if ( ! $this->oStore->isSetUp() ) {
			$this->oStore->setUp();
		}
	}

	/**
	 * Removes the tables for the triple store.
	 */
	public function drop() {
		if ( $this->oStore->isSetUp() ) {
			$this->oStore->drop();
		}
	}

	/**
	 * Checks if data is stored in the triple store.
	 */
	public function existsData() {
		if ( is_null( $this->bExistsData ) ) {
			$sQuery            = "
				PREFIX skos: <" . self::SKOS_CORE . ">

				SELECT ?concept
				WHERE {
					?concept a skos:Concept .
				}
				LIMIT 1";
			$aRow              = $this->oStore->query( $sQuery, 'row' );
			$this->bExistsData = count( $aRow ) ? TRUE : FALSE;
		}

		return $this->bExistsData;
	}

	/**
	 * Imports the triples from a RDF/XML file into the triple store.
	 */
	public static function importFromFile() {
		$aUploadFile = $_FILES['importFile'];

		// Check if no file is given
		if ( $aUploadFile['error'] == 4 ) {
			return TRUE;
		}

		// Check the downloaded file if it is OK
		if ( $aUploadFile['error'] >= 1 ) {
			throw new Exception ( __( 'An error has occurred while downloading the file.', self::SLUG ) );
		}
		if ( $aUploadFile['type'] != 'application/rdf+xml' ) {
			throw new Exception ( __( 'The specified file is not an RDF file.', self::SLUG ) );
		}
		if ( ! is_uploaded_file( $aUploadFile['tmp_name'] ) ) {
			throw new Exception ( __( 'An error has occurred while downloading the file.', self::SLUG ) );
		}

		// Create the tables for the triple store
		$oStore = ARC2::getStore( self::getStoreConfig() );
		$oStore->setUp();

		// All tables are emptied
		$oStore->reset();

		// Load RDF data into triple store
		if ( ! ( $oStore->query( 'LOAD <file://' . $aUploadFile['tmp_name'] . '>' ) ) ) {
			throw new Exception ( __( 'An error has occurred while storing the RDF data to the database.', self::SLUG ) );
		}

		return TRUE;
	}

	/**
	 * Imports the triples form a sparql endpoint into the triple store.
	 */
	public static function importFromEndpoint() {
		// Get data from spaql endpoint
		$sThesaurusEndpoint = empty( $_POST['thesaurusEndpoint'] ) ? PP_THESAURUS_ENDPOINT : $_POST['thesaurusEndpoint'];
		if ( empty( $sThesaurusEndpoint ) ) {
			throw new Exception ( __( 'No SPARQL endpoint has been indicated.', self::SLUG ) );
		}

		// Load the remote sparql endpiont
		$aConfig  = array(
			'remote_store_endpoint' => $sThesaurusEndpoint,
			'remote_store_timeout'  => 2
		);
		$oEPStore = ARC2::getRemoteStore( $aConfig );

		// Create the tables for the triple store
		$oARCStore = ARC2::getStore( self::getStoreConfig() );
		$oARCStore->setUp();

		// All tables are emptied
		$oARCStore->reset();

		// Save data into ARC store
		self::importFromEndpointLoop( $oEPStore, $oARCStore );
	}

	/**
	 * Saves recursively data from sparql endpoint into the triple store.
	 */
	protected static function importFromEndpointLoop( &$oEPStore, &$oARCStore, $iCounter = 0 ) {
		$iLimit  = 1000;
		$iOffset = $iCounter * $iLimit;
		$sQuery  = "
			CONSTRUCT {	?s ?p ?o }
			WHERE {?s ?p ?o }
			LIMIT $iLimit
			OFFSET $iOffset";

		$aData = $oEPStore->query( $sQuery, 'raw' );
		if ( $aError = $oEPStore->getErrors() ) {
			throw new Exception ( __( 'The transfer of data from the SPARQL endpoint is not possible.', self::SLUG ) );
		}

		// Insert data
		if ( ! empty( $aData ) ) {
			// Go throw the data and replace defective characters.
			foreach ( $aData as &$aDataBlock ) {
				foreach ( $aDataBlock as $sKey => &$aTriples ) {
					if ( $sKey == 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' ) {
						foreach ( $aTriples as &$aType ) {
							$aType['value'] = str_replace( array( '(', ')', ',', ';' ), array(
								'%28',
								'%29',
								'%2C',
								'%3B'
							), $aType['value'] );
							$iPos           = strrpos( $aType['value'], '/' );
							$sName          = str_replace( array( '.', ':' ), array(
								'%2E',
								'%3A'
							), substr( $aType['value'], $iPos ) );
							$aType['value'] = substr( $aType['value'], 0, $iPos ) . $sName;
						}
					} else {
						foreach ( $aTriples as &$aValue ) {
							if ( $aValue['type'] == 'literal' && ! isset( $aValue['datatype'] ) ) {
								$aValue['value'] = str_replace( '\\', '&#92;', $aValue['value'] );
							}
						}
					}
				}
			}
			$oARCStore->insert( $aData, '' );
			if ( $aError = $oARCStore->getErrors() ) {
				throw new Exception ( __( 'An error has occurred while storing the data from the SPARQL endpoint to the database.', self::SLUG ) );
			}
			self::importFromEndpointLoop( $oEPStore, $oARCStore, ++ $iCounter );
		}
	}
}

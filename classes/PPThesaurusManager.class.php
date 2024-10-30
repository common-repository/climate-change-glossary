<?php


class PPThesaurusManager {

	const SKOS_CORE = 'http://www.w3.org/2004/02/skos/core#';
	const PLACEHOLDER_CONTENT = 'pp-contentplaceholder';
	const PLACEHOLDER_TERM = 'pp-termplaceholder';
	const PLACEHOLDER_TAG = 'pp-tagplaceholder';
	const SLUG = 'pp-thesaurus';

	protected static $oInstance;

	protected $oStore;
	protected $WPOptions;
	protected $aConceptList;
	protected $sLanguage;
	protected $sDefaultLanguage;
	protected $aAvailableLanguages;
	protected $aNoParseContent;
	protected $aBlacklist;
	protected $sBlackTags = 'a, label, map, select, sub, sup, code';


	protected function __construct() {
		// Den internen ARC-Triplestore konfigurieren
		$oPPStore                  = PPThesaurusARC2Store::getInstance();
		$this->oStore              = $oPPStore->getStore();
		$this->WPOptions           = get_option( 'PPThesaurus' );
		$this->aConceptList        = array();
		$this->sLanguage           = '';
		$this->sDefaultLanguage    = '';
		$this->aAvailableLanguages = array();
		$this->aNoParseContent     = array();
		$this->aBlacklist          = NULL;
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
	 * Gets all the available languages from the triple store.
	 */
	public function getLanguages() {
		if ( empty( $this->aAvailableLanguages ) ) {
			$sQuery = "
				PREFIX skos: <" . self::SKOS_CORE . ">

				SELECT ?prefLabel
				WHERE {
					?concept a skos:Concept .
					?concept skos:prefLabel ?prefLabel .
					" . PP_THESAURUS_SPARQL_FILTER . "
				}";
			$aRows  = $this->oStore->query( $sQuery, 'rows' );

			if ( $this->oStore->getErrors() ) {
				throw new Exception ( sprintf( __( 'Could not execute query: %s', self::SLUG ), $sQuery ) );
			}

			$aLanguages = array();
			foreach ( $aRows as $aRow ) {
				if ( ! in_array( $aRow['prefLabel lang'], $aLanguages ) ) {
					$aLanguages[] = $aRow['prefLabel lang'];
				}
			}
			sort( $aLanguages, SORT_STRING );
			$this->aAvailableLanguages = $aLanguages;
		}

		return $this->aAvailableLanguages;
	}

	/**
	 * Returns the language.
	 */
	public function getLanguage() {
		$this->setLanguage();

		return $this->sLanguage;
	}

	/**
	 * Sets the language and the default language.
	 */
	protected function setLanguage() {
		if ( empty( $this->sLanguage ) ) {
			$aLang = explode( '#', $this->WPOptions['languages'] );
			if ( function_exists( 'qtrans_getLanguage' ) ) {
				$sLang                  = qtrans_getLanguage();
				$sDefaultLang           = get_option( 'qtranslate_default_language' );
				$sDefaultLang           = in_array( $sDefaultLang, $aLang ) ? $sDefaultLang : $aLang[0];
				$this->sLanguage        = in_array( $sLang, $aLang ) ? $sLang : $sDefaultLang;
				$this->sDefaultLanguage = $sDefaultLang;
			} else {
				$this->sLanguage        = $aLang[0];
				$this->sDefaultLanguage = $aLang[0];
			}
		}
	}

	/**
	 * Replaces the content with a placeholder.
	 * Is called by the shortcode "noparse".
	 */
	public function cutContent( $aAttr, $sContent = NULL ) {
		if ( is_null( $sContent ) ) {
			return '';
		}
		if ( ! $this->parseAllowed() ) {
			return do_shortcode( $sContent );
		}

		$iCount                  = count( $this->aNoParseContent );
		$this->aNoParseContent[] = do_shortcode( $sContent );

		return '<' . self::PLACEHOLDER_CONTENT . '>' . $iCount . '</' . self::PLACEHOLDER_CONTENT . '>';
	}

	/**
	 * Parses the hole page content and replaces the concepts found to a link.
	 */
	public function parse( $sContent ) {
		global $post;

		if ( ! $this->parseAllowed() ) {
			return $sContent;
		}

		// Get the concepts found in the whole post content
		if ( ( $aConcepts = PPThesaurusCache::get( $post->ID ) ) === FALSE ) {
			$aConcepts = $this->searchConceptsInContent( $post->post_content );

			// Cache the concepts found
			PPThesaurusCache::put( $post->ID, $aConcepts );
		}
		if ( empty( $aConcepts ) ) {
			return $sContent;
		}

		// Get the concepts in the blacklist.
		$aBlacklist = $this->getBlacklist();

		// Replace all the problematic HTML tags with a placeholder.
		$oDom = new simple_html_dom();
		$oDom->load( $sContent );
		$oTags       = $oDom->find( $this->sBlackTags );
		$aTagMatches = array();
		$i           = 0;
		foreach ( $oTags as $oTag ) {
			$aTagMatches[]   = $oTag->outertext;
			$oTag->outertext = '<' . self::PLACEHOLDER_TAG . '>' . $i ++ . '</' . self::PLACEHOLDER_TAG . '>';
		}
		$sContent = $oDom->save();

		// Replace all HTML tags with attributes with a placeholder.
		$sSearch = "<\w+(\s+\w+(\s*=\s*(?:\".*?\"|'.*?'|[^'\">\s]+))?)+\s*/?>"; // match all start tags with attributes
		preg_match_all( '#' . $sSearch . '#i', $sContent, $aMatches );
		$aTagMatches    = array_merge( $aTagMatches, $aMatches[0] );
		$iTagMatchCount = count( $aTagMatches );
		for ( ; $i < $iTagMatchCount; $i ++ ) {
			$sReplace = '<' . self::PLACEHOLDER_TAG . '>' . $i . '</' . self::PLACEHOLDER_TAG . '>';
			$sContent = str_replace( $aTagMatches[ $i ], $sReplace, $sContent );
		}

		// Replace all concepts found with a placeholder (but only for the first hit).
		$aTermMatches = array();
		foreach ( $aConcepts as $iId => $oConcept ) {
			$sLabel   = strtolower( $oConcept->label );
			$sPattern = addcslashes( $oConcept->label, '/.*+()' );
			// Check if the label has only uppercase characters
			$sPattern = '/(\W)(' . $sPattern . ')(\W)/';
			if ( strcmp( $sPattern, strtoupper( $sPattern ) ) ) {
				$sPattern .= 'i';
			}
			if ( ! in_array( $sLabel, $aBlacklist ) && preg_match( $sPattern, $sContent, $aMatches ) ) {
				$aTermMatches[ $iId ] = $aMatches[2];
				$sPlaceholder         = '$1<' . self::PLACEHOLDER_TERM . '>' . $iId . '</' . self::PLACEHOLDER_TERM . '>$3';
				$sContent             = preg_replace( $sPattern, $sPlaceholder, $sContent, 1 );
			}
		}

		// Restore all placeholders
		$oDom->clear();
		$oDom->load( $sContent );
		$oTags = $oDom->find( self::PLACEHOLDER_CONTENT . ', ' . self::PLACEHOLDER_TERM . ', ' . self::PLACEHOLDER_TAG );
		$oPPTT = PPThesaurusTemplate::getInstance();
		foreach ( $oTags as $oTag ) {
			$iNumber = (int) $oTag->innertext;
			switch ( $oTag->tag ) {
				case self::PLACEHOLDER_CONTENT:
					$oTag->outertext = $this->aNoParseContent[ $iNumber ];
					break;
				case self::PLACEHOLDER_TERM:
					$oConcept        = $aConcepts[ $iNumber ];
					$sDefinition     = $this->getDefinition( $oConcept->uri, $oConcept->definition, TRUE );
					$sLink           = $oPPTT->getLink( $aTermMatches[ $iNumber ], $oConcept->uri, $oConcept->prefLabel, $sDefinition );
					$oTag->outertext = $sLink;
					break;
				case self::PLACEHOLDER_TAG:
					$oTag->outertext = $aTagMatches[ $iNumber ];
					break;
			}
		}
		$sContent = $oDom->save();
		if ( ! empty( $this->aNoParseContent ) ) {
			$sContent = do_shortcode( $sContent );
		}

		return $sContent;
	}

	/**
	 * Checks if parsing is allowed.
	 */
	protected function parseAllowed() {
		global $post;

		$oPage = PPThesaurusPage::getInstance();

		// Is the plugin configured?
		if ( $oPage->thesaurusId <= 0 ) {
			return FALSE;
		}

		// is automated linking disabled?
		if ( $this->WPOptions['linking'] == 'disabled' ) {
			return FALSE;
		}

		// is the content for a feed, an archive or a search call?
		if ( is_feed() || is_archive() || is_search() ) {
			return FALSE;
		}

		// is the page a thesaurus page?
		$aPages = array( $oPage->thesaurusId, $oPage->itemPage->ID );
		if ( in_array( $post->ID, $aPages ) ) {
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Returns the dbpedia definition to a concept if the description is empty.
	 */
	public function getDefinition( $sConceptUri, $sDefinition, $bTruncate = FALSE, $bBlock = FALSE ) {
		$sLinking = $this->WPOptions['linking'];
		if ( $bTruncate && ( $sLinking == 'disabled' || ( $bBlock && $sLinking == 'only_link' ) ) ) {
			return '';
		}

		$sPattern = PP_THESAURUS_DESCRIPTION_EXCEPTION;
		if ( ! empty( $sPattern ) && preg_match( $sPattern, $sDefinition ) ) {
			$sDefinition = '';
		}

		if ( empty( $sDefinition ) && ! empty( $this->WPOptions['dbpediaEndpoint'] ) ) {
			$sQuery = "
				PREFIX skos: <" . self::SKOS_CORE . ">

				SELECT ?dbpediaUri
				WHERE {
					<$sConceptUri> a skos:Concept .

					OPTIONAL {
						<$sConceptUri> skos:exactMatch ?dbpediaUri .
					}
					OPTIONAL {
						<$sConceptUri> skos:closeMatch ?dbpediaUri .
					}

					FILTER(regex(str(?dbpediaUri), '^http://dbpedia.org', 'i'))
				}";
			$aRow   = $this->oStore->query( $sQuery, 'row' );

			if ( $this->oStore->getErrors() ) {
				throw new Exception ( sprintf( __( 'Could not execute query: %s', self::SLUG ), $sQuery ) );
			}

			if ( ! empty( $aRow ) ) {
				$sDefinition = $this->getDbPediaDefinition( $aRow['dbpediaUri'] );
			}
		}

		if ( ! empty( $sDefinition ) && $bTruncate ) {
			$sDefinition = strip_tags( $sDefinition );

			return $this->truncate( $sDefinition );
		}

		return $sDefinition;
	}

	/**
	 * Returns the abstract of a dbpedia URI if it exists.
	 */
	protected function getDbPediaDefinition( $sDBPediaUri ) {
		$this->setLanguage();
		if ( empty( $this->sLanguage ) ) {
			return '';
		}

		$aConfig  = array(
			'remote_store_endpoint' => $this->WPOptions['dbpediaEndpoint'],
			'remote_store_timeout'  => 2
		);
		$oEPStore = ARC2::getRemoteStore( $aConfig );

		$sQuery = "
			PREFIX rdfs:<http://www.w3.org/2000/01/rdf-schema#>
			PREFIX dbpedia-owl:<http://dbpedia.org/ontology/>

			SELECT *
			WHERE {
				<$sDBPediaUri> rdfs:label ?label.
				<$sDBPediaUri> dbpedia-owl:abstract ?description.
				FILTER (lang(?label) = '" . $this->sLanguage . "' && lang(?description) = '" . $this->sLanguage . "').
			}";

		$aRow = $oEPStore->query( $sQuery, 'row' );
		if ( $oEPStore->getErrors() || empty( $aRow ) ) {
			return '';
		}

		return trim( $aRow['description'] );
	}

	/**
	 * Trancats a text.
	 */
	protected function truncate( $sText, $iLength = 300, $sEtc = ' ...', $bBreakWords = FALSE ) {
		if ( $iLength == 0 ) {
			return '';
		}

		if ( strlen( $sText ) > $iLength ) {
			$iLength -= min( $iLength, strlen( $sEtc ) );
			if ( ! $bBreakWords ) {
				$sText = preg_replace( '/\s+?(\S+)?$/', '', substr( $sText, 0, $iLength + 1 ) );
			}

			return substr( $sText, 0, $iLength ) . $sEtc;
		} else {
			return $sText;
		}
	}

	/**
	 * Returns a list with the ABC and if concepts exists for each character.
	 * TODO: Generate this list after the thesaurus import
	 */
	public function getAbcIndex( $sFilter = 'ALL' ) {
		$aList = $this->getConceptList();

		$aIndex['ALL'] = 'enabled';
		for ( $i = 65; $i <= 90; $i ++ ) {
			$aIndex[ $i ] = 'disabled';
		}
		$aIndex[35] = 'disabled'; // is the "#" sign

		foreach ( $aList as $oConcept ) {
			$sChar = ord( strtoupper( $oConcept->prefLabel ) );
			if ( ! ( $sChar >= 65 && $sChar <= 90 ) ) {
				$sChar = '35';
			}
			$aIndex[ $sChar ] = ( $sChar == $sFilter ) ? 'selected' : 'enabled';
		}

		if ( strtoupper( $sFilter ) == 'ALL' ) {
			$aIndex['ALL'] = 'selected';
		}

		return $aIndex;
	}

	/**
	 * Returns a list of concepts for the selected character.
	 */
	public function getList( $sFilter = 'ALL' ) {
		$aList = $this->getConceptList();
		if ( strtoupper( $sFilter ) == 'ALL' ) {
			return $aList;
		}

		$aReturn = array();
		foreach ( $aList as $iId => $oConcept ) {
			$sChar = ord( strtoupper( $oConcept->prefLabel ) );
			if ( ! ( $sChar >= 65 && $sChar <= 90 ) ) {
				$sChar = '35';
			}
			if ( $sChar == $sFilter ) {
				$aReturn[ $iId ] = $oConcept;
			}
		}

		return $aReturn;
	}

	/**
	 * Returns a concept object from a given URI.
	 */
	public function getItem( $sUri ) {
		$sUri = trim( $sUri );
		$this->setLanguage();
		if ( empty( $this->sLanguage ) || empty( $sUri ) ) {
			return NULL;
		}

		$sUri    = urldecode( $sUri );
		$sFilter = PP_THESAURUS_SPARQL_FILTER;
		$sFilter = empty( $sFilter ) ? '' : str_replace( '?concept', "<$sUri>", $sFilter );
		$sQuery  = "
			PREFIX skos: <" . self::SKOS_CORE . ">

			SELECT *
			WHERE {
				<$sUri> a skos:Concept .
				<$sUri> skos:prefLabel ?prefLabel FILTER (lang(?prefLabel) = '" . $this->sLanguage . "') .
				OPTIONAL {<$sUri> skos:definition ?definition . }
				OPTIONAL {<$sUri> skos:altLabel ?altLabel FILTER (lang(?altLabel) = '" . $this->sLanguage . "') . }
				OPTIONAL {<$sUri> skos:hiddenLabel ?hiddenLabel FILTER (lang(?hiddenLabel) = '" . $this->sLanguage . "') . }
				OPTIONAL {<$sUri> skos:scopeNote ?scopeNote FILTER (lang(?scopeNote) = '" . $this->sLanguage . "') . }
				" . $sFilter . "
			}
		";
		$aRows   = $this->oStore->query( $sQuery, 'rows' );

		if ( $this->oStore->getErrors() ) {
			throw new Exception ( sprintf( __( 'Could not execute query: %s', self::SLUG ), $sQuery ) );
		}
		if ( empty( $aRows ) ) {
			return NULL;
		}

		$oItem          = new PPThesaurusItem();
		$aAltLabels     = array();
		$aHiddenLabels  = array();
		$aDefinitions   = array();
		$bWithRelations = TRUE;
		$sSearch        = '';

		$oItem->uri = $sUri;
		foreach ( $aRows as $aRow ) {
			$oItem->prefLabel = trim( $aRow['prefLabel'] );
			if ( isset( $aRow['altLabel'] ) ) {
				$aAltLabels[] = trim( $aRow['altLabel'] );
			}
			if ( isset( $aRow['hiddenLabel'] ) ) {
				$aHiddenLabels[] = trim( $aRow['hiddenLabel'] );
			}
			if ( isset( $aRow['definition'] ) ) {
				$sDefinition = trim( $aRow['definition'] );
				if ( ! empty( $sDefinition ) ) {
					switch ( $aRow['definition lang'] ) {
						case $this->sLanguage:
							$aDefinitions[ $this->sLanguage ][] = $sDefinition;
							break;
						case $this->sDefaultLanguage:
							$aDefinitions[ $this->sDefaultLanguage ][] = $sDefinition;
							break;
						default:
							$aDefinitions['other'][] = $sDefinition;
							break;
					}
				}
			}
			if ( isset( $aRow['scopeNote'] ) ) {
				$oItem->scopeNote = $aRow['scopeNote'];
			}
			if ( $bWithRelations ) {
				$oItem->broaderList  = $this->getItemRelations( $sUri, 'broader' );
				$oItem->narrowerList = $this->getItemRelations( $sUri, 'narrower' );
				$oItem->relatedList  = $this->getItemRelations( $sUri, 'related' );
				$bWithRelations      = FALSE;
			}
		}

		$sSearch = $this->addQuotes( $oItem->prefLabel );
		if ( ! empty( $aAltLabels ) ) {
			$oItem->altLabels = array_unique( $aAltLabels );
			$aSearch          = array_map( array( $this, 'addQuotes' ), $oItem->altLabels );
			$sSearch .= ' ' . implode( ' ', $aSearch );
		}
		if ( ! empty( $aHiddenLabels ) ) {
			$oItem->hiddenLabels = array_unique( $aHiddenLabels );
		}
		if ( ! empty( $aDefinitions[ $this->sLanguage ] ) ) {
			$oItem->definition = implode( '<br />', array_unique( $aDefinitions[ $this->sLanguage ] ) );
		} elseif ( ! empty( $aDefinitions[ $this->sDefaultLanguage ] ) ) {
			$oItem->definition = $this->getDefinitionInfo() . implode( '<br />', array_unique( $aDefinitions[ $this->sDefaultLanguage ] ) );
		} elseif ( ! empty( $aDefinitions['other'] ) ) {
			$oItem->definition = implode( '<br />', array_unique( $aDefinitions['other'] ) );
		}
		$oItem->searchLink = get_bloginfo( 'url', 'display' ) . '/?ppt&s=' . urlencode( $sSearch );

		return $oItem;
	}

	/**
	 * Returns all the concepts from a given relation type and concept
	 * as a list of concept objects.
	 */
	protected function getItemRelations( $sUri, $sRelType ) {
		$sFilter = PP_THESAURUS_SPARQL_FILTER;
		$sFilter = empty( $sFilter ) ? '' : str_replace( '?concept', "?relation", $sFilter );
		$sQuery  = "
			PREFIX skos: <" . self::SKOS_CORE . ">

			SELECT DISTINCT ?relation ?prefLabel ?definition
			WHERE {
				<$sUri> a skos:Concept .
				<$sUri> skos:$sRelType ?relation .
				?relation skos:prefLabel ?prefLabel FILTER (lang(?prefLabel) = '" . $this->sLanguage . "') .
				OPTIONAL { ?relation skos:definition ?definition . }
				" . $sFilter . "
			}
		";

		$aRows = $this->oStore->query( $sQuery, 'rows' );
		if ( count( $aRows ) <= 0 ) {
			return array();
		}

		if ( $this->oStore->getErrors() ) {
			throw new Exception ( sprintf( __( 'Could not execute query: %s', self::SLUG ), $sQuery ) );
		}

		$aResult      = array();
		$aDefinitions = array();
		$sLastConcept = '';
		$oItem        = new PPThesaurusItem();
		$bFirst       = TRUE;
		foreach ( $aRows as $aRow ) {
			if ( $aRow['relation'] != $sLastConcept ) {
				if ( ! $bFirst ) {
					if ( ! empty( $aDefinitions[ $this->sLanguage ] ) ) {
						$oItem->definition = implode( '<br />', array_unique( $aDefinitions[ $this->sLanguage ] ) );
					} elseif ( ! empty( $aDefinitions[ $this->sDefaultLanguage ] ) ) {
						$oItem->definition = $this->getDefinitionInfo() . implode( '<br />', array_unique( $aDefinitions[ $this->sDefaultLanguage ] ) );
					} elseif ( ! empty( $aDefinitions['other'] ) ) {
						$oItem->definition = implode( '<br />', array_unique( $aDefinitions['other'] ) );
					}
					$aResult[]    = $oItem;
					$oItem        = new PPThesaurusItem();
					$aDefinitions = array();
				}
				$sLastConcept     = $aRow['relation'];
				$oItem->uri       = $aRow['relation'];
				$oItem->prefLabel = trim( $aRow['prefLabel'] );
			}
			if ( isset( $aRow['definition'] ) ) {
				$sDefinition = trim( $aRow['definition'] );
				if ( ! empty( $sDefinition ) ) {
					switch ( $aRow['definition lang'] ) {
						case $this->sLanguage:
							$aDefinitions[ $this->sLanguage ][] = $sDefinition;
							break;
						case $this->sDefaultLanguage:
							$aDefinitions[ $this->sDefaultLanguage ][] = $sDefinition;
							break;
						default:
							$aDefinitions['other'][] = $sDefinition;
							break;
					}
				}
			}
			$bFirst = FALSE;
		}
		if ( ! empty( $aDefinitions[ $this->sLanguage ] ) ) {
			$oItem->definition = implode( '<br />', array_unique( $aDefinitions[ $this->sLanguage ] ) );
		} elseif ( ! empty( $aDefinitions[ $this->sDefaultLanguage ] ) ) {
			$oItem->definition = $this->getDefinitionInfo() . implode( '<br />', array_unique( $aDefinitions[ $this->sDefaultLanguage ] ) );
		} elseif ( ! empty( $aDefinitions['other'] ) ) {
			$oItem->definition = implode( '<br />', array_unique( $aDefinitions['other'] ) );
		}
		$aResult[] = $oItem;

		return $aResult;
	}

	/**
	 * Gets all the concepts with all data from the thesaurus.
	 */
	protected function getConcepts() {
		$this->setLanguage();
		if ( empty( $this->sLanguage ) ) {
			return $this->aConceptList;
		}

		if ( empty( $this->aConceptList ) ) {
			$sQuery = "
				PREFIX skos: <" . self::SKOS_CORE . ">

				SELECT DISTINCT ?concept ?label ?definition ?rel
				WHERE {
					?concept a skos:Concept .
					?concept ?rel ?label .
		  		OPTIONAL { ?concept skos:definition ?definition . }
					" . PP_THESAURUS_SPARQL_FILTER . "

					{ ?concept skos:prefLabel ?label . }
					UNION
					{ ?concept skos:altLabel ?label . }
					UNION
					{ ?concept skos:hiddenLabel ?label . }

					FILTER (str(?label) != '' && lang(?label) = '" . $this->sLanguage . "').
				}
			";

			$aRows = $this->oStore->query( $sQuery, 'rows' );

			if ( $this->oStore->getErrors() ) {
				throw new Exception ( sprintf( __( 'Could not execute query: %s', self::SLUG ), $sQuery ) );
			}
			if ( count( $aRows ) <= 0 ) {
				return $this->aConceptList;
			}

			$sLastConcept = '';
			$oConcept     = new PPThesaurusItem();
			$aDefinitions = array();
			$bFirst       = TRUE;
			$aPrefLabels  = array();
			$aOtherLabels = array();
			$i            = 0;
			foreach ( $aRows as $aRow ) {
				if ( $aRow['label'] != $sLastConcept ) {
					if ( ! $bFirst ) {
						if ( ! empty( $aDefinitions[ $this->sLanguage ] ) ) {
							$oConcept->definition = implode( '<br />', array_unique( $aDefinitions[ $this->sLanguage ] ) );
						} elseif ( ! empty( $aDefinitions[ $this->sDefaultLanguage ] ) ) {
							$oConcept->definition = $this->getDefinitionInfo() . implode( '<br />', array_unique( $aDefinitions[ $this->sDefaultLanguage ] ) );
						} elseif ( ! empty( $aDefinitions['other'] ) ) {
							$oConcept->definition = implode( '<br />', array_unique( $aDefinitions['other'] ) );
						}
						if ( $oConcept->rel == self::SKOS_CORE . 'prefLabel' ) {
							$aPrefLabels[ $oConcept->uri ] = $oConcept->label;
							$oConcept->prefLabel           = $oConcept->label;
							if ( isset( $aOtherLabels[ $oConcept->uri ] ) && ! empty( $aOtherLabels[ $oConcept->uri ] ) ) {
								foreach ( $aOtherLabels[ $oConcept->uri ] as $iId ) {
									$this->aConceptList[ $iId ]->prefLabel = $oConcept->prefLabel;
								}
							}
						} else {
							$aOtherLabels[ $oConcept->uri ][] = $i;
							if ( isset( $aPrefLabels[ $oConcept->uri ] ) ) {
								$oConcept->prefLabel = $aPrefLabels[ $oConcept->uri ];
							}
						}
						$this->aConceptList[ $i ++ ] = $oConcept;
						$oConcept                    = new PPThesaurusItem();
						$aDefinitions                = array();
					}
					$sLastConcept    = $aRow['label'];
					$sLabel          = preg_replace( '/ {2,}/', ' ', $aRow['label'] );
					$oConcept->uri   = $aRow['concept'];
					$oConcept->label = trim( $sLabel );
					$oConcept->rel   = $aRow['rel'];
					$oConcept->count = count( explode( ' ', $oConcept->label ) );
				}
				if ( isset( $aRow['definition'] ) ) {
					$sDefinition = trim( $aRow['definition'] );
					if ( ! empty( $sDefinition ) ) {
						switch ( $aRow['definition lang'] ) {
							case $this->sLanguage:
								$aDefinitions[ $this->sLanguage ][] = $sDefinition;
								break;
							case $this->sDefaultLanguage:
								$aDefinitions[ $this->sDefaultLanguage ][] = $sDefinition;
								break;
							default:
								$aDefinitions['other'][] = $sDefinition;
								break;
						}
					}
				}
				$bFirst = FALSE;
			}
			if ( ! empty( $aDefinitions[ $this->sLanguage ] ) ) {
				$oConcept->definition = implode( '<br />', array_unique( $aDefinitions[ $this->sLanguage ] ) );
			} elseif ( ! empty( $aDefinitions[ $this->sDefaultLanguage ] ) ) {
				$oConcept->definition = $this->getDefinitionInfo() . implode( '<br />', array_unique( $aDefinitions[ $this->sDefaultLanguage ] ) );
			} elseif ( ! empty( $aDefinitions['other'] ) ) {
				$oConcept->definition = implode( '<br />', array_unique( $aDefinitions['other'] ) );
			}
			$this->aConceptList[ $i ] = $oConcept;

			unset( $aPrefLabels );
			unset( $aOtherLabels );
		}

		usort( $this->aConceptList, array( $this, 'sortByCount' ) );

		return $this->aConceptList;
	}

	/**
	 * Gets all the concepts with prefLabel and definition from the thesaurus.
	 */
	protected function getConceptList() {
		$this->setLanguage();
		if ( empty( $this->sLanguage ) ) {
			return $this->aConceptList;
		}

		if ( empty( $this->aConceptList ) ) {
			$sQuery = "
				PREFIX skos: <" . self::SKOS_CORE . ">

				SELECT DISTINCT ?concept ?label ?definition
				WHERE {
					?concept a skos:Concept .
					?concept skos:prefLabel ?label FILTER (lang(?label) = '" . $this->sLanguage . "').
					OPTIONAL { ?concept skos:definition ?definition . }
					" . PP_THESAURUS_SPARQL_FILTER . "
				}
			";

			$aRows = $this->oStore->query( $sQuery, 'rows' );

			if ( $this->oStore->getErrors() ) {
				throw new Exception ( sprintf( __( 'Could not execute query: %s', self::SLUG ), $sQuery ) );
			}
			if ( count( $aRows ) <= 0 ) {
				return $this->aConceptList;
			}

			$sLastConcept = '';
			$oConcept     = new PPThesaurusItem();
			$aDefinitions = array();
			$bFirst       = TRUE;
			$i            = 0;
			foreach ( $aRows as $aRow ) {
				if ( $aRow['label'] != $sLastConcept ) {
					if ( ! $bFirst ) {
						if ( ! empty( $aDefinitions[ $this->sLanguage ] ) ) {
							$oConcept->definition = implode( '<br />', array_unique( $aDefinitions[ $this->sLanguage ] ) );
						} elseif ( ! empty( $aDefinitions[ $this->sDefaultLanguage ] ) ) {
							$oConcept->definition = $this->getDefinitionInfo() . implode( '<br />', array_unique( $aDefinitions[ $this->sDefaultLanguage ] ) );
						} elseif ( ! empty( $aDefinitions['other'] ) ) {
							$oConcept->definition = implode( '<br />', array_unique( $aDefinitions['other'] ) );
						}
						$this->aConceptList[ $i ++ ] = $oConcept;
						$oConcept                    = new PPThesaurusItem();
						$aDefinitions                = array();
					}
					$sLastConcept        = $aRow['label'];
					$sLabel              = preg_replace( '/ {2,}/', ' ', $aRow['label'] );
					$oConcept->uri       = $aRow['concept'];
					$oConcept->prefLabel = trim( $sLabel );
				}
				if ( isset( $aRow['definition'] ) ) {
					$sDefinition = trim( $aRow['definition'] );
					if ( ! empty( $sDefinition ) ) {
						switch ( $aRow['definition lang'] ) {
							case $this->sLanguage:
								$aDefinitions[ $this->sLanguage ][] = $sDefinition;
								break;
							case $this->sDefaultLanguage:
								$aDefinitions[ $this->sDefaultLanguage ][] = $sDefinition;
								break;
							default:
								$aDefinitions['other'][] = $sDefinition;
								break;
						}
					}
				}
				$bFirst = FALSE;
			}
			if ( ! empty( $aDefinitions[ $this->sLanguage ] ) ) {
				$oConcept->definition = implode( '<br />', array_unique( $aDefinitions[ $this->sLanguage ] ) );
			} elseif ( ! empty( $aDefinitions[ $this->sDefaultLanguage ] ) ) {
				$oConcept->definition = $this->getDefinitionInfo() . implode( '<br />', array_unique( $aDefinitions[ $this->sDefaultLanguage ] ) );
			} elseif ( ! empty( $aDefinitions['other'] ) ) {
				$oConcept->definition = implode( '<br />', array_unique( $aDefinitions['other'] ) );
			}
			$this->aConceptList[ $i ] = $oConcept;
		}

		usort( $this->aConceptList, array( $this, 'sortByLabel' ) );

		return $this->aConceptList;
	}

	/**
	 * Searches concepts for a given search string.
	 */
	public function searchConcepts( $sString, $sLanguage, $iLimit = 100 ) {
		$sString    = trim( $sString );
		$sLanguage  = trim( $sLanguage );
		$aLanguages = $this->getLanguages();

		if ( empty( $sString ) ) {
			return array();
		}
		if ( empty( $sLanguage ) || ! in_array( $sLanguage, $aLanguages ) ) {
			$this->setLanguage();
			$sLanguage = $this->sDefaultLanguage;
		}

		$sString = esc_sql( $sString );

		$sQuery = "
			PREFIX skos:<http://www.w3.org/2004/02/skos/core#>

			SELECT DISTINCT ?concept ?label 
			WHERE {
				?concept a skos:Concept.
				{ 
					?concept skos:prefLabel ?label FILTER(regex(str(?label),'$sString','i') && lang(?label) = '$sLanguage').
				} UNION {
					?concept skos:altLabel ?label FILTER(regex(str(?label),'$sString','i') && lang(?label) = '$sLanguage').
				}
				" . PP_THESAURUS_SPARQL_FILTER . "
			}
			ORDER BY ASC(?label)
			LIMIT $iLimit";

		$aRows = $this->oStore->query( $sQuery, 'rows' );
		if ( $this->oStore->getErrors() || count( $aRows ) <= 0 ) {
			return array();
		}

		$aListStart  = array();
		$aListBegin  = array();
		$aListMiddle = array();
		$aListEnd    = array();
		$oPPTT       = PPThesaurusTemplate::getInstance();
		foreach ( $aRows as $aData ) {
			$sUrl  = $oPPTT->getItemLink( $aData['label'] );
			$sData = $aData['label'] . '|' . $sUrl . '?uri=' . $aData['concept'];
			if ( preg_match( "/^$sString/i", $aData['label'] ) ) {
				$aListStart[] = $sData;
			} elseif ( preg_match( "/ $sString/i", $aData['label'] ) ) {
				$aListBegin[] = $sData;
			} elseif ( preg_match( "/$sString$/i", $aData['label'] ) ) {
				$aListEnd[] = $sData;
			} else {
				$aListMiddle[] = $sData;
			}
		}
		sort( $aListStart );
		sort( $aListBegin );
		sort( $aListMiddle );
		sort( $aListEnd );

		$aList = array_merge( $aListStart, $aListBegin, $aListMiddle, $aListEnd );

		return $aList;
	}

	/**
	 * Searches in a content for concepts from thesaurus.
	 */
	protected function searchConceptsInContent( $sContent ) {
		$aAllConcepts = $this->getConcepts();
		$sContent     = do_shortcode( $sContent );
		$aConcepts    = array();

		// Remove all the problematic HTML tags
		$oDom = new simple_html_dom();
		$oDom->load( $sContent );
		$oTags = $oDom->find( $this->sBlackTags );
		foreach ( $oTags as $oTag ) {
			$oTag->outertext = '';
		}
		$sContent = $oDom->save();
		unset( $oDom );

		// Remove all HTML tags with attributes.
		$sSearch = "<\w+(\s+\w+(\s*=\s*(?:\".*?\"|'.*?'|[^'\">\s]+))?)+\s*/?>"; // match all start tags with attributes
		preg_replace( '#' . $sSearch . '#i', '', $sContent );

		// Search the concepts in the content
		foreach ( $aAllConcepts as $oConcept ) {
			$sPattern = addcslashes( $oConcept->label, '/.*+()' );
			// Check if the label has only uppercase characters
			$sPattern = '/(\W)(' . $sPattern . ')(\W)?/';
			if ( strcmp( $sPattern, strtoupper( $sPattern ) ) ) {
				$sPattern .= 'i';
			}
			if ( preg_match( $sPattern, $sContent, $aMatches ) ) {
				$aConcepts[] = $oConcept;
			}
		}

		return $aConcepts;
	}

	/**
	 * Returns an info text for the definition.
	 */
	protected function getDefinitionInfo() {
		if ( function_exists( 'qtrans_getLanguageName' ) ) {
			$sSelLang = strtolower( qtrans_getLanguageName( $this->sLanguage ) );
			$sDefLang = strtolower( qtrans_getLanguageName( $this->sDefaultLanguage ) );
		} else {
			$sSelLang = strtoupper( $this->sLanguage );
			$sDefLang = strtoupper( $this->sDefaultLanguage );
		}
		$sDefinition = '<span class="PPThesaurusDefInfo">' . sprintf( __( 'Definition not available in %s', self::SLUG ), $sSelLang ) . '.</span>';
		$sDefinition .= '<strong>' . sprintf( __( 'Definition in %s', self::SLUG ), $sDefLang ) . '</strong>:<br />';

		return $sDefinition;
	}

	/**
	 * Callback function for sorting by count.
	 */
	protected function sortByCount( $a, $b ) {
		if ( $a->count == $b->count ) {
			return 0;
		}

		return ( $a->count < $b->count ) ? 1 : - 1;
	}

	/**
	 * Callback function for sorting by label.
	 */
	protected function sortByLabel( $a, $b ) {
		return strcasecmp( $a->prefLabel, $b->prefLabel );
	}

	/**
	 * Callback function to add quotes to an item if has more then one word.
	 *
	 * @return string
	 */
	protected function addQuotes( $item ) {
		if ( strpos( $item, ' ' ) !== FALSE ) {
			$item = '"' . $item . '"';
		}

		return $item;
	}

	/**
	 * Returns a list of blacklisted concepts.
	 */
	protected function getBlacklist() {
		if ( ! is_array( $this->aBlacklist ) ) {
			$this->aBlacklist = array();
			if ( ( $sBlacklist = stripslashes( $this->WPOptions['termsBlacklist'] ) ) != '' ) {
				$this->aBlacklist = array_map( array( $this, 'cleanBlacklistTerm' ), explode( ',', $sBlacklist ) );
			}
		}

		return $this->aBlacklist;
	}

	/**
	 * Cleans a label.
	 */
	protected function cleanBlacklistTerm( $sLabel ) {
		return trim( strtolower( strip_tags( $sLabel ) ) );
	}
}

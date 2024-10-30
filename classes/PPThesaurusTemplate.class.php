<?php

class PPThesaurusTemplate {

	const SLUG = 'pp-thesaurus';

	protected static $oInstance;

	protected $oPPTM;
	protected $oItem;


	public function __construct() {
		$this->oPPTM = PPThesaurusManager::getInstance();
		$this->oItem = NULL;
	}


	public static function getInstance() {
		if ( ! isset( self::$oInstance ) ) {
			$sClass          = __CLASS__;
			self::$oInstance = new $sClass();
		}

		return self::$oInstance;
	}

	/**
	 * Shows the ABC filter
	 */
	public function showABCIndex( $aAtts ) {
		if ( isset( $_GET['filter'] ) ) {
			$iChar = $_GET['filter'];
		} elseif ( isset( $_GET['uri'] ) ) {
			try {
				if ( is_null( $this->oItem ) ) {
					$this->oItem = $this->oPPTM->getItem( $_GET['uri'] );
				}
			}
			catch ( Exception $e ) {
				return '<p>' . __( 'An error has occurred while reading concept data.', self::SLUG ) . '</p>';
			}
			$iChar = ord( strtoupper( $this->oItem->prefLabel ) );
		} else {
			$iChar = 65;
		}

		$oPage    = PPThesaurusPage::getInstance();
		$aIndex   = $this->oPPTM->getAbcIndex( $iChar );
		$iCount   = count( $aIndex );
		$sContent = '<ul class="PPThesaurusAbcIndex">';
		$i        = 1;
		foreach ( $aIndex as $sChar => $sKind ) {
			$sClass  = ( $i == 1 ) ? 'first' : ( $i == $iCount ? 'last' : '' );
			$sLetter = ( $sChar == 'ALL' ) ? 'ALL' : chr( $sChar );
			$sLink   = '<a href="' . get_bloginfo( 'url', 'display' ) . '/' . $oPage->thesaurusPage->post_name . '?filter=' . $sChar . '">' . $sLetter . '</a>';
			switch ( $sKind ) {
				case 'disabled':
					if ( ! empty( $sClass ) ) {
						$sClass = ' class="' . $sClass . '"';
					}
					$sContent .= '<li' . $sClass . '>' . $sLetter . '</li>';
					break;

				case 'enabled':
					if ( ! empty( $sClass ) ) {
						$sClass = ' class="' . $sClass . '"';
					}
					$sContent .= '<li' . $sClass . '>' . $sLink . '</li>';
					break;

				case 'selected':
					$sContent .= '<li class="selected ' . $sClass . '">' . $sLink . '</li>';
					break;
			}
			$i ++;
		}
		$sContent .= '</ul>';

		return $sContent;
	}

	/**
	 * Shows a list of concepts.
	 */
	public function showItemList( $aAtts ) {
		$sFilter  = isset( $_GET['filter'] ) ? $_GET['filter'] : 65;
		$aList    = $this->oPPTM->getList( $sFilter );
		$iCount   = count( $aList );
		$sContent = '';
		if ( $iCount > 0 ) {
			$sContent .= '<ul class="PPThesaurusList">';
			$i = 1;
			foreach ( $aList as $oConcept ) {
				$sClass      = ( $i == 1 ) ? ' class="first"' : ( $i == $iCount ? ' class="last"' : '' );
				$sDefinition = $this->oPPTM->getDefinition( $oConcept->uri, $oConcept->definition, TRUE, TRUE );
				$sContent .= '<li' . $sClass . '>' . $this->getLink( $oConcept->prefLabel, $oConcept->uri, $oConcept->prefLabel, $sDefinition, TRUE ) . '</li>';
				$i ++;
			}
			$sContent .= '</ul>';
		}

		return $sContent;
	}

	/**
	 * Shows the datails of a concept.
	 */
	public function showItemDetails( $aAtts ) {
		if ( ! isset( $_GET['uri'] ) ) {
			return '';
		}

		try {
			if ( is_null( $this->oItem ) ) {
				$this->oItem = $this->oPPTM->getItem( $_GET['uri'] );
			}
		}
		catch ( Exception $e ) {
			return '<p>' . __( 'An error has occurred while reading concept data.', self::SLUG ) . '</p>';
		}

		$sContent = '<div class="PPThesaurusDetails">';
		if ( $this->oItem->searchLink ) {
			$sContent .= '<p>' . __( 'Search for documents related with', self::SLUG ) . ' <a href="' . $this->oItem->searchLink . '">' . $this->oItem->prefLabel . '</a></p>';
		}
		if ( $this->oItem->altLabels ) {
			$sContent .= '<div class="synonyms"><strong>' . __( 'Synonyms', self::SLUG ) . ':</strong> ' . implode( ', ', $this->oItem->altLabels ) . '</div>';
		}
		$sContent .= '<p class="definition">' . $this->oPPTM->getDefinition( $this->oItem->uri, $this->oItem->definition ) . '</p>';

		if ( $this->oItem->scopeNote ) {
			$sContent .= '<blockquote>' . $this->oItem->scopeNote . '</blockquote>';
		}

		if ( $this->oItem->relatedList ) {
			$sContent .= '<p class="relation"><strong>' . __( 'Related terms', self::SLUG ) . ':</strong><br />' . implode( ', ', $this->getLinkList( $this->oItem->relatedList ) ) . '</p>';
		}

		if ( $this->oItem->broaderList ) {
			$sContent .= '<p class="relation"><strong>' . __( 'Broader terms', self::SLUG ) . ':</strong><br />' . implode( ', ', $this->getLinkList( $this->oItem->broaderList ) ) . '</p>';
		}

		if ( $this->oItem->narrowerList ) {
			$sContent .= '<p class="relation"><strong>' . __( 'Narrower terms', self::SLUG ) . ':</strong><br />' . implode( ', ', $this->getLinkList( $this->oItem->narrowerList ) ) . '</p>';
		}

		if ( $this->oItem->uri ) {
			$sLabel = '<strong>' . $this->oItem->prefLabel . '</strong>';
			$sLink  = '<a href="' . $this->oItem->uri . '" target="_blank">' . $sLabel . '</a>';
			$sContent .= '<p>' . sprintf( __( 'Linked data frontend for %s', self::SLUG ), $sLink ) . '.</p>';
		}

		$sContent .= '</div>';

		return $sContent;
	}

	/**
	 * Shows the search form inside the content.
	 */
	public function showSearchForm( $aAtts ) {
		$sTitle = ! isset( $aAtts['title'] ) ? PP_THESAURUS_SIDEBAR_TITLE : apply_filters( 'widget_title', $aAtts['title'] );
		$sInfo  = ! isset( $aAtts['placeholder'] ) ? PP_THESAURUS_SIDEBAR_INFO : apply_filters( 'widget_info', $aAtts['placeholder'] );
		$sWidth = empty( $aAtts['width'] ) ? '100%' : apply_filters( 'widget_width', $aAtts['width'] );

		if (!empty($sTitle)) {
			$sTitle = '<div class="PPThesaurus_searchtitle">' . $sTitle . '</div>';
		}
		if (!empty($sInfo)) {
			$sInfo = 'placeholder="' . $sInfo . '"';
		}

		$form = '[' . PP_THESAURUS_SHORTCODE_PREFIX . '-noparse]
			<script type="text/javascript">
			//<![CDATA[
				var pp_thesaurus_suggest_url = "' . plugins_url( '/pp-thesaurus-autocomplete.php', dirname( __FILE__ ) ) . '?lang=' . $this->oPPTM->getLanguage() . '";
			//]]>
			</script>
			' . $sTitle . '
			<div class="PPThesaurus_searchform">
				<input class="pp_thesaurus_input_term" type="text" name="term" value="" ' . $sInfo . '" style="width:' . $sWidth . '" />
			</div>[/' . PP_THESAURUS_SHORTCODE_PREFIX . '-noparse]';

		return do_shortcode( $form );
	}

	/**
	 * Creates a list of links.
	 */
	private function getLinkList( $aItemList ) {
		if ( empty( $aItemList ) ) {
			return array();
		}

		$aLinks = array();
		foreach ( $aItemList as $oItem ) {
			$sDefinition = $this->oPPTM->getDefinition( $oItem->uri, $oItem->definition, TRUE, TRUE );
			$aLinks[]    = $this->getLink( $oItem->prefLabel, $oItem->uri, $oItem->prefLabel, $sDefinition, TRUE );
		}

		return $aLinks;
	}

	/**
	 * Creates a link.
	 */
	public function getLink( $sText, $sUri, $sPrefLabel, $sDefinition, $bShowLink = FALSE ) {
		if ( empty( $sDefinition ) ) {
			if ( $bShowLink ) {
				$sPage = $this->getItemLink( $sPrefLabel );
				$sLink = '<a class="PPThesaurus" href="' . $sPage . '?uri=' . $sUri . '" title="Term: ' . $sPrefLabel . '">' . $sText . '</a>';
			} else {
				$sLink = $sText;
			}
		} else {
			$sPage = $this->getItemLink( $sPrefLabel );
			$sLink = '<a class="PPThesaurus" href="' . $sPage . '?uri=' . $sUri . '" title="Term: ' . $sPrefLabel . '">' . $sText . '</a>';
			$sLink .= '<span style="display:none;">' . $sDefinition . '</span>';
		}

		return $sLink;
	}

	/**
	 * Creates a link for an item.
	 */
	public function getItemLink( $sPrefLabel = '' ) {
		$oPage = PPThesaurusPage::getInstance();
		$name  = strtolower( $sPrefLabel );
		$name  = preg_replace( array( '/\s+/', '/[^a-z0-9_-]+/' ), array( '-', '_' ), $name );

		return get_bloginfo( 'url', 'display' ) . '/' . $oPage->thesaurusPage->post_name . '/' . $name;
	}

	/**
	 * Returns the title for the browser window.
	 */
	public function setWPTitle( $sTitle, $sSep, $sSepLocation ) {
		$sTitle    = trim( $sTitle );
		$sNewTitle = $this->setTitle( $sTitle );
		if ( $sNewTitle == $sTitle ) {
			return $sTitle;
		}

		return sprintf( __( 'Definition of %s', self::SLUG ), $sNewTitle );
	}

	/**
	 * Returns the title for the page.
	 */
	public function setTitle( $sTitle ) {
		$oPage  = PPThesaurusPage::getInstance();
		$oChild = $oPage->itemPage;
		$sTitle = trim( $sTitle );

		if ( ! is_page( $oChild->ID ) ) {
			return $sTitle;
		}

		if ( function_exists( 'qtrans_split' ) ) {
			$aTitles    = qtrans_split( $oChild->post_title );
			$sPageTitle = $aTitles[ qtrans_getLanguage() ];
		} else {
			$sPageTitle = $oChild->post_title;
		}
		if ( strcasecmp( $sTitle, $sPageTitle ) || empty( $_GET['uri'] ) ) {
			return $sTitle;
		}

		try {
			if ( is_null( $this->oItem ) ) {
				$this->oItem = $this->oPPTM->getItem( $_GET['uri'] );
			}
		}
		catch ( Exception $e ) {
			return $sTitle;
		}

		return $this->oItem->prefLabel;
	}
}


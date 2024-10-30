<?php

class PPThesaurusPage {
	protected static $oInstance;

	protected $iThesaurusId;
	protected $oThesaurusPage;
	protected $oItemPage;

	protected function __construct() {
		$this->iThesaurusId   = 0;
		$this->oThesaurusPage = NULL;
		$this->oItemPage      = NULL;
	}

	public static function getInstance() {
		if ( ! isset( self::$oInstance ) ) {
			$sClass          = __CLASS__;
			self::$oInstance = new $sClass();
		}

		return self::$oInstance;
	}

	public function __get( $sName ) {
		switch ( $sName ) {
			case 'thesaurusId':
				return $this->getThesaurusId();
				break;

			case 'thesaurusPage':
				return $this->getThesaurusPage();
				break;

			case 'itemPage':
				return $this->getItemPage();
				break;
		}
	}

	protected function getThesaurusId() {
		global $oPPThesaurus;

		if ( empty( $this->iThesaurusId ) ) {
			$this->iThesaurusId = $oPPThesaurus->WPOptions['pageId'];
		}

		return $this->iThesaurusId;
	}

	/**
	 * TODO: Create page if not is available.
	 */
	protected function getThesaurusPage() {
		if ( empty( $this->oThesaurusPage ) ) {
			$this->oThesaurusPage = get_post( $this->getThesaurusId() );
		}

		return $this->oThesaurusPage;
	}

	/**
	 * TODO: Create page if not is available.
	 */
	protected function getItemPage() {
		if ( empty( $this->oItemPage ) ) {
			$aChildren = get_children(
				array(
					'numberposts' => 1,
					'post_parent' => $this->getThesaurusId(),
					'post_type'   => 'page'
				)
			);
			if ( ! $this->oItemPage = array_shift( $aChildren ) ) {
				$itemPage = new stdClass();
				$itemPage->itemPage = new stdClass();
				$itemPage->ID = 0;
				$this->oItemPage = $itemPage;
			}
		}

		return $this->oItemPage;
	}
}

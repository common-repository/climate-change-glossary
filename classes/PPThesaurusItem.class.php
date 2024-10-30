<?php

class PPThesaurusItem {

	public $aData = array();

	public function __construct() {
	}

	public function __set( $sVar, $sValue ) {
		$this->aData[ $sVar ] = $sValue;
	}

	public function __get( $sVar ) {
		if ( isset( $this->aData[ $sVar ] ) ) {
			return $this->aData[ $sVar ];
		}

		return '';
	}
}


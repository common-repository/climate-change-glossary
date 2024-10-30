<?php

class PPThesaurusWidget extends WP_Widget {

	const SLUG = 'pp-thesaurus';


	public function __construct() {
		$sTitle       = __( PP_THESAURUS_SIDEBAR_TITLE, self::SLUG );
		$sDescription = __( PP_THESAURUS_SIDEBAR_DESCRIPTION, self::SLUG );
		parent::__construct( 'pp_thesaurus_sidebar_search', $sTitle, array( 'description' => $sDescription ) );
	}

	public function widget( $aArgs, $aInstance ) {
		$oPPTM = PPThesaurusManager::getInstance();

		$sTitle = empty( $aInstance['title'] ) ? PP_THESAURUS_SIDEBAR_TITLE : apply_filters( 'widget_title', $aInstance['title'] );
		$sInfo  = empty( $aInstance['info'] ) ? PP_THESAURUS_SIDEBAR_INFO : apply_filters( 'widget_info', $aInstance['info'] );
		$sWidth = empty( $aInstance['width'] ) ? '100%' : apply_filters( 'widget_width', $aInstance['width'] );

		echo $aArgs['before_widget'];
		if ( ! empty( $sTitle ) ) {
			echo $aArgs['before_title'] . $sTitle . $aArgs['after_title'];
		}
		echo '
			<script type="text/javascript">
			//<![CDATA[
				var pp_thesaurus_suggest_url = "' . plugins_url( '/pp-thesaurus-autocomplete.php', dirname( __FILE__ ) ) . '?lang=' . $oPPTM->getLanguage() . '";
			//]]>
			</script>
			<div class="PPThesaurus_sidebar">
				<input id="pp_thesaurus_input_term" class="pp_thesaurus_input_term" type="text" name="term" value="" placeholder="' . $sInfo . '" style="width:' . $sWidth . '" />
			</div>
		';
		echo $aArgs['after_widget'];
	}

	public function form( $aInstance ) {
		$aOptions  = array(
			'title' => PP_THESAURUS_SIDEBAR_TITLE,
			'info'  => PP_THESAURUS_SIDEBAR_INFO,
			'width' => '100%',
		);
		$aInstance = wp_parse_args( (array) $aInstance, $aOptions );
		$sTitle    = esc_attr( $aInstance['title'] );
		$sInfo     = esc_attr( $aInstance['info'] );
		$sWidth    = esc_attr( $aInstance['width'] );

		echo '
		<p>
			<label for="' . $this->get_field_id( 'title' ) . '">' . __( 'Title', self::SLUG ) . ': <br />
				<input id="' . $this->get_field_id( 'title' ) . '" class="widefat" name="' . $this->get_field_name( 'title' ) . '" type="text" value="' . $sTitle . '" />
			</label>
		</p>
		<p>
			<label for="' . $this->get_field_id( 'info' ) . '">' . __( 'Info text', self::SLUG ) . ': <br />
				<input id="' . $this->get_field_id( 'info' ) . '" class="widefat" name="' . $this->get_field_name( 'info' ) . '" type="text" value="' . $sInfo . '" />
			</label>
		</p>
		<p>
			<label for="' . $this->get_field_id( 'width' ) . '">' . __( 'Width of the search field', self::SLUG ) . ': <br />
				<input id="' . $this->get_field_id( 'width' ) . '" class="widefat" name="' . $this->get_field_name( 'width' ) . '" type="text" value="' . $sWidth . '" />
			</label> ("%" ' . __( 'or', self::SLUG ) . ' "px")
		</p>
		';
	}

	public function update( $aNewInstance, $aOldInstance ) {
		$aInstance          = $aOldInstance;
		$aInstance['title'] = trim( strip_tags( stripslashes( $aNewInstance['title'] ) ) );
		$aInstance['info']  = trim( strip_tags( stripslashes( $aNewInstance['info'] ) ) );
		$aInstance['width'] = trim( strip_tags( stripslashes( $aNewInstance['width'] ) ) );

		return $aInstance;
	}
}

<?php

class PPThesaurusCache {

	public static function get( $iPostId ) {
		$aConceptList = get_post_meta( $iPostId, 'pp-thesaurus-cache', TRUE );

		return is_array( $aConceptList ) ? $aConceptList : FALSE;
	}

	public static function put( $iPostId, $aConceptList ) {
		update_post_meta( $iPostId, 'pp-thesaurus-cache', $aConceptList );
	}

	public static function delete( $iPostId ) {
		delete_post_meta( $iPostId, 'pp-thesaurus-cache' );
	}

	public static function clear() {
		global $wpdb;

		$query = "
			DELETE FROM " . $wpdb->postmeta . "
			WHERE meta_key = %s";
		$wpdb->query( $wpdb->prepare( $query, 'pp-thesaurus-cache' ) );
	}
}

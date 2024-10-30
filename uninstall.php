<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die( __( 'You are not allowed to call this page directly.' ) );
}


define( 'PP_THESAURUS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/PPThesaurus.class.php' );
require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/PPThesaurusCache.class.php' );
require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/PPThesaurusARC2Store.class.php' );


/**
 * Remove options and glossary pages on plugin uninstall.
 */
if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	$blogIds = PPThesaurus::getBlogIds();
	foreach ( $blogIds as $blogId ) {
		switch_to_blog( $blogId );

		delete_glossary_pages();
		delete_triple_store();
		PPThesaurusCache::clear();
		delete_option( 'PPThesaurus' );
	}
} else {
	delete_glossary_pages();
	delete_triple_store();
	PPThesaurusCache::clear();
	delete_option( 'PPThesaurus' );
}


/**
 * Deletes the main glossary page and its sub pages.
 */
function delete_glossary_pages() {
	$options = get_option( 'PPThesaurus' );
	$pageId  = $options['pageId'];

	// Delete glossary pages if it is set
	if ( $pageId > 0 ) {
		// Get and delete all sub pages
		$subPages = get_pages( array( 'child_of' => $pageId ) );
		foreach ( $subPages as $subPage ) {
			wp_delete_post( $subPage->ID, TRUE );
		}

		// Delete the main glossary page
		wp_delete_post( $pageId, TRUE );
	}
}

/**
 * Drops the triple store tables with its data and removes the ARC2 library.
 */
function delete_triple_store() {
	// Drop the triple store tables
	$oPPStore = PPThesaurusARC2Store::getInstance();
	$oPPStore->drop();
	unset( $oPPStore );

	if ( ! is_writable( PP_THESAURUS_PLUGIN_DIR ) ) {
		return FALSE;
	}

	// Go into the plugin directory
	$sDir = getcwd();
	chdir( PP_THESAURUS_PLUGIN_DIR );

	// Remove the ARC2 directory
	@rmdir( 'arc' );

	chdir( $sDir );

	return TRUE;
}


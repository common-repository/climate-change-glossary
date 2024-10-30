<?php
/**
 * The WordPress Plugin Climate change glossary
 *
 * Climate Change Glossary plugin makes websites more understandable. Blogs benefit from linking posts with key terms automatically. The plugin uses SKOS vocabularies.
 *
 * Plugin Name: Climate change glossary
 * Plugin URI: http://poolparty.biz
 * Description: This plugin imports a SKOS thesaurus via <a href="https://github.com/semsol/arc2">ARC2</a>. It highlighs terms and generates links automatically in any page which contains terms from the thesaurus.
 * Version: 2.3
 * Author: reegle.info
 * Author URI: http://www.reegle.info
 * Text Domain: pp-thesaurus
 * Domain Path: /languages
 */

/* 	Copyright 2011-2015  Kurt Moser  (email: k.moser@semantic-web.at)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


/**
 * Defines.
 */
define( 'PP_THESAURUS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Include configurations und classes.
 */
require_once( PP_THESAURUS_PLUGIN_DIR . 'pp-thesaurus-config.php' );
require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/PPThesaurus.class.php' );
require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/PPThesaurusManager.class.php' );
require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/PPThesaurusCache.class.php' );
require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/PPThesaurusPage.class.php' );
require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/PPThesaurusItem.class.php' );
require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/PPThesaurusTemplate.class.php' );
require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/PPThesaurusWidget.class.php' );
require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/PPThesaurusARC2Store.class.php' );
require_once( PP_THESAURUS_PLUGIN_DIR . 'classes/simple_html_dom.php' );

/**
 * Enable error reporting.
 */
/*
error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors', '1');
*/

/**
 * Register hooks that are fired when the plugin is activated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 */
$oPPThesaurus = PPThesaurus::getInstance();
register_activation_hook( __FILE__, array( $oPPThesaurus, 'activate' ) );
register_deactivation_hook( __FILE__, array( $oPPThesaurus, 'deactivate' ) );

/**
 * Load the plugin and widget.
 */
add_action( 'plugins_loaded', array( $oPPThesaurus, 'getInstance' ) );
add_action( 'widgets_init', array( $oPPThesaurus, 'registerWidget' ) );

function PPThesaurusGetWpPrefix() {
	global $wpdb;

	return $wpdb->prefix;
}


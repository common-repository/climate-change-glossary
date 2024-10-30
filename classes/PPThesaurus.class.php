<?php

class PPThesaurus {

	const VERSION = 1.0;
	const SLUG = 'pp-thesaurus';

	/**
	 * @var object
	 */
	protected static $oInstance;

	/**
	 * @var boolean
	 */
	protected $thesaurusUpdated = FALSE;

	/**
	 * @var array
	 */
	public $WPOptions;


	private function __construct() {
		$this->WPOptions = get_option( 'PPThesaurus' );
		$this->upgradeOptions();
		$this->initHooks();
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
	 * Upgrades the old options to the new options.
	 */
	protected function upgradeOptions() {
		if ( get_option( 'PPThesaurusId' ) === FALSE ) {
			return TRUE;
		}

		// Tranfer the values from the old options into the new options
		$iPopup   = get_option( 'PPThesaurusPopup' );
		$sLinking = ( $iPopup == 0 ) ? 'only_link' : ( ( $iPopup == 1 ) ? 'tooltip' : 'disabled' );
		$aOptions = array(
			'version'           => self::VERSION,
			// Options version
			'pageId'            => get_option( 'PPThesaurusId' ),
			// The ID of the main glossary page
			'linking'           => $sLinking,
			// Options for automated linking [tooltip|only_link|disabled]
			'termsBlacklist'    => '',
			// Terms excluded from automated linking
			'languages'         => get_option( 'PPThesaurusLanguage' ),
			// Enabled languages for the thesaurus
			'dbpediaEndpoint'   => get_option( 'PPThesaurusDBPediaEndpoint' ),
			// The DBPedia SPARQL endpoint
			'thesaurusEndpoint' => get_option( 'PPThesaurusSparqlEndpoint' ),
			// The thesaurus SPARQL endpoint for import/update it
			'importFile'        => get_option( 'PPThesaurusImportFile' ),
			// The file name from the with the thesaurus data
			'updated'           => get_option( 'PPThesaurusUpdated' ),
			// The last import/update date
		);
		update_option( 'PPThesaurus', $aOptions );

		// Delete old options
		delete_option( 'PPThesaurusId' );
		delete_option( 'PPThesaurusPopup' );
		delete_option( 'PPThesaurusLanguage' );
		delete_option( 'PPThesaurusDBPediaEndpoint' );
		delete_option( 'PPThesaurusSparqlEndpoint' );
		delete_option( 'PPThesaurusImportFile' );
		delete_option( 'PPThesaurusUpdated' );
		delete_option( 'PPThesaurusSidebarTitle' );
		delete_option( 'PPThesaurusSidebarInfo' );
		delete_option( 'PPThesaurusSidebarWidth' );

		return TRUE;
	}

	/**
	 * Initialises the hooks and shortcodes.
	 */
	public function initHooks() {
		add_action( 'init', array( $this, 'loadTextdomain' ) );
		add_action( 'admin_menu', array( $this, 'requestSettingsPage' ) );
		if ( self::existARC2() ) {
			$oPPTM = PPThesaurusManager::getInstance();
			$oPPTT = PPThesaurusTemplate::getInstance();

			// Register actions
			add_action( 'init', array( $this, 'init' ) );
			add_action( 'wpmu_new_blog', array( $this, 'activateNewBlog' ) );
			add_action( 'save_post', array( 'PPThesaurusCache', 'delete' ) );
			add_action( 'delete_post', array( 'PPThesaurusCache', 'delete' ) );
			add_action( 'posts_where', array( $this, 'changeWhereSearchQuery' ), 10, 2 );

			// Register filters
			add_filter( 'the_content', array( $oPPTM, 'parse' ), 100 );
			add_filter( 'the_title', array( $oPPTT, 'setTitle' ) );
			add_filter( 'wp_title', array( $oPPTT, 'setWPTitle' ), 10, 3 );
			add_filter( 'query_vars', array( $this, 'addCustomSearchQueryParam' ) );

			// Register shortcodes
			add_shortcode( PP_THESAURUS_SHORTCODE_PREFIX . '-abcindex', array( $oPPTT, 'showABCIndex' ) );
			add_shortcode( PP_THESAURUS_SHORTCODE_PREFIX . '-itemlist', array( $oPPTT, 'showItemList' ) );
			add_shortcode( PP_THESAURUS_SHORTCODE_PREFIX . '-itemdetails', array( $oPPTT, 'showItemDetails' ) );
			add_shortcode( PP_THESAURUS_SHORTCODE_PREFIX . '-searchform', array( $oPPTT, 'showSearchForm' ) );
			add_shortcode( PP_THESAURUS_SHORTCODE_PREFIX . '-noparse', array( $oPPTM, 'cutContent' ) );
		}

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( realpath( dirname( __FILE__ ) ) ) . self::SLUG . '.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'addActionLinks' ) );
	}


	/*********************************************
	 * Plugin activate methods
	 *********************************************/

	/**
	 * Fired when the plugin is activated.
	 */
	public static function activate( $bNetworkWide ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( $bNetworkWide ) {
				// Get all blog ids
				$aBlogIds = self::getBlogIds();
				foreach ( $aBlogIds as $iBlogId ) {
					switch_to_blog( $iBlogId );
					self::singleActivate();
					restore_current_blog();
				}
			} else {
				self::singleActivate();
			}
		} else {
			self::singleActivate();
		}
	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @param int $iBlogId ID of the new blog.
	 */
	public function activateNewBlog( $iBlogId ) {
		if ( 1 !== add_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $iBlogId );
		self::singleActivate();
		restore_current_blog();
	}

	/**
	 * Fired for each blog when the plugin is activated.
	 */
	protected static function singleActivate() {
		// Install ARC2
		self::installARC2();

		// Create glossary pages and save default option values
		$aWPOptions = get_option( 'PPThesaurus' );
		if ( $aWPOptions === FALSE ) {
			$iPageId  = self::addGlossaryPages();
			$aOptions = array(
				'version'           => self::VERSION, // Options version
				'pageId'            => $iPageId, // The ID of the main glossary page
				'linking'           => 'tooltip', // Options for automated linking [tooltip|only_link|disabled]
				'termsBlacklist'    => '', // Terms excluded from automated linking
				'languages'         => 'en', // Enabled languages for the thesaurus
				'dbpediaEndpoint'   => PP_THESAURUS_DBPEDIA_ENDPOINT, // The DBPedia SPARQL endpoint
				'thesaurusEndpoint' => PP_THESAURUS_ENDPOINT, // The thesaurus SPARQL endpoint for import/update it
				'importFile'        => '', // The file name from the with the thesaurus data
				'updated'           => '', // The last import/update date
			);
			update_option( 'PPThesaurus', $aOptions );
		} else {
			// Publish the glossary and glossary item page.
			if ( $thesaurusPage = $aWPOptions['pageId'] ) {
				wp_publish_post( $thesaurusPage );
				$aChildren = get_children(
					array(
						'numberposts' => 1,
						'post_parent' => $aWPOptions['pageId'],
						'post_type'   => 'page'
					)
				);
				if ( $itemPage = array_shift( $aChildren ) ) {
					wp_publish_post( $itemPage );
				}
			}
		}
	}


	/**
	 * Fired when the plugin is deactivated.
	 */
	public static function deactivate( $bNetworkWide ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( $bNetworkWide ) {
				// Get all blog ids
				$aBlogIds = self::getBlogIds();
				foreach ( $aBlogIds as $iBlogId ) {
					switch_to_blog( $iBlogId );
					self::singleDeactivate();
					restore_current_blog();
				}
			} else {
				self::singleDeactivate();
			}
		} else {
			self::singleDeactivate();
		}
	}

	/**
	 * Sets the status to private for the glossary and glossary item page.
	 */
	protected static function singleDeactivate() {
		$oPage = PPThesaurusPage::getInstance();

		if ( $post = $oPage->thesaurusPage ) {
			$post->post_status = 'private';
			wp_update_post( $post );
		}
		if ( $post = $oPage->itemPage ) {
			$post->post_status = 'private';
			wp_update_post( $post );
		}
	}

	/**
	 * Register and enqueue JavaScript files, style sheets and sidebar widget.
	 */
	public function init() {
		// Register and enqueue JavaScript files and style sheets only on the public area
		if ( ! is_admin() ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( self::SLUG . '-autocomplete-script', plugins_url( '/js/jquery.autocomplete.min.js', dirname( __FILE__ ) ), array( 'jquery' ) );
			wp_enqueue_script( self::SLUG . '-common-script', plugins_url( '/js/script.js', dirname( __FILE__ ) ), array( self::SLUG . '-autocomplete-script' ) );
			wp_enqueue_style( self::SLUG . '-autocomplete-style', plugins_url( '/css/jquery.autocomplete.css', dirname( __FILE__ ) ) );
			wp_enqueue_style( self::SLUG . '-common-style', plugins_url( '/css/style.css', dirname( __FILE__ ) ) );

			// Load tooltip Javascript and style sheet if it is enabled
			if ( $this->WPOptions['linking'] == 'tooltip' ) {
				wp_enqueue_script( self::SLUG . '-unitip-script', plugins_url( '/js/unitip.js', dirname( __FILE__ ) ), array( 'jquery' ) );
				wp_enqueue_style( self::SLUG . '-unitip-style', plugins_url( '/js/unitip/unitip.css', dirname( __FILE__ ) ) );
			}
		}

		// Add new rewrite rule for the concept detail page.
		// First get the glossary page and its child for setting the rewrite rule data dynamically.
		$pageId = $this->WPOptions['pageId'];
		if ( $pageId > 0 ) {
			global $wp_rewrite;

			$thesaurusPage = get_post( $pageId );
			$itemPage      = get_pages( array( 'child_of' => $pageId ) );
			if ( ! empty( $itemPage ) ) {
				add_rewrite_rule( '^' . $thesaurusPage->post_name . '/([^/]*)', 'index.php?page_id=' . $itemPage[0]->ID, 'top' );
				$wp_rewrite->flush_rules();
			}
		}
	}

	/**
	 * Adds the custom query variable 'ppt';
	 */
	public function addCustomSearchQueryParam( $qvars ) {
		$qvars[] = 'ppt';

		return $qvars;
	}

	/**
	 * Changes the AND joints between the search sentences to OR joints,
	 * but only if the 'ppt' query variable is given.
	 */
	public function changeWhereSearchQuery( $where, $query ) {
		if ( isset( $query->query_vars['ppt'] ) ) {
			$where = str_replace( ')) AND ((', ')) OR ((', $where );
		}

		return $where;
	}

	/**
	 * Register the sidebar widget
	 */
	public function registerWidget() {
		register_widget( 'PPThesaurusWidget' );
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function loadTextdomain() {
		load_plugin_textdomain( self::SLUG, FALSE, dirname( plugin_basename( dirname( __FILE__ ) ) ) . '/languages/' );
	}

	/**
	 * Add settings action link to the plugins page.
	 */
	public function addActionLinks( $aLinks ) {
		return array_merge( array( '<a href="' . admin_url( 'options-general.php?page=' . self::SLUG ) . '">' . __( 'Settings', self::SLUG ) . '</a>' ), $aLinks );
	}

	/**
	 * Adds the glossary and item page.
	 */
	protected function addGlossaryPages() {
		// page with the list of concepts
		$aPageConf = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Glossary',
			'post_content' => "[" . PP_THESAURUS_SHORTCODE_PREFIX . "-abcindex]\n[" . PP_THESAURUS_SHORTCODE_PREFIX . "-itemlist]",
			'post_parent'  => 0
		);
		if ( ! ( $iPageId = wp_insert_post( $aPageConf ) ) ) {
			die( __( 'The glossary pages cannot be created.', self::SLUG ) );
		}
		// page with the details of a concept
		$aChildPageConf = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Item',
			'post_content' => "[" . PP_THESAURUS_SHORTCODE_PREFIX . "-abcindex]\n[" . PP_THESAURUS_SHORTCODE_PREFIX . "-itemdetails]",
			'post_parent'  => $iPageId
		);
		if ( ! ( $iChildPageId = wp_insert_post( $aChildPageConf ) ) ) {
			die( __( 'The glossary pages cannot be created.', self::SLUG ) );
		}

		return $iPageId;
	}

	/**
	 * Downlods and installs the ARC2 library if not exists.
	 * It caches the thesaurus in a triple store.
	 */
	protected function installARC2() {
		if ( self::existARC2() ) {
			return TRUE;
		}

		$error_pre  = __( 'The ARC2 library cannot be installed automatically.', self::SLUG ) . ' ';
		$error_post = sprintf( __( 'Please install ARC2 manually (see the <a href="%s" target="_blank">installation guide</a> point 3 and 4).', self::SLUG ), PP_THESAURUS_PLUGIN_URL . '/installation/' );
		if ( ! is_writable( PP_THESAURUS_PLUGIN_DIR ) ) {
			$error = $error_pre . __( 'The plugin directory is not writable.', self::SLUG ) . '<br />' . $error_post;
			die( $error );
		}

		$sDir = getcwd();
		chdir( PP_THESAURUS_PLUGIN_DIR );

		// download ARC2
		$sTarFileName = 'arc.tar.gz';
		$sCmd         = 'wget --no-check-certificate -T 2 -t 1 -O ' . $sTarFileName . ' ' . PP_THESAURUS_ARC_URL . ' 2>&1';
		$aOutput      = array();
		exec( $sCmd, $aOutput, $iResult );
		if ( $iResult != 0 ) {
			chdir( $sDir );
			$error = $error_pre . __( 'The library cannot be downloaded from <a href="https://github.com/semsol/arc2" target="_blank">https://github.com/semsol/arc2</a>.', self::SLUG ) . '<br />' . $error_post;
			die( $error );
		}

		// untar the file
		$sCmd    = 'tar -xvzf ' . $sTarFileName . ' 2>&1';
		$aOutput = array();
		exec( $sCmd, $aOutput, $iResult );
		if ( $iResult != 0 ) {
			chdir( $sDir );
			$error = $error_pre . __( 'The library cannot be unzipped.', self::SLUG ) . '<br />' . $error_post;
			die( $error );
		}

		// delete old arc direcotry and tar file
		@rmdir( 'arc' );
		@unlink( $sTarFileName );

		// rename the ARC2 folder to arc
		$sCmd    = 'mv semsol-arc2-* arc 2>&1';
		$aOutput = array();
		exec( $sCmd, $aOutput, $iResult );
		if ( $iResult != 0 ) {
			chdir( $sDir );
			$error = $error_pre . __( 'The entire content of the library cannot be moved into the "arc" sub directory.', self::SLUG ) . '<br />' . $error_post;
			die( $error );
		}

		chdir( $sDir );

		return TRUE;
	}


	/*********************************************
	 * Plugin load methods
	 *********************************************/

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 */
	public function requestSettingsPage() {
		add_options_page( PP_THESAURUS_PLUGIN_NAME, PP_THESAURUS_PLUGIN_NAME, 'manage_options', self::SLUG, array(
			$this,
			'loadSettingsPage'
		) );
	}

	/**
	 * Loads and shows the settings page.
	 */
	public function loadSettingsPage() {
		if ( ! self::existARC2() ) {
			echo '
			<div class="wrap">
				<div class="icon32" id="icon-options-general"></div>
				<h2>' . PP_THESAURUS_PLUGIN_NAME . ' ' . __( 'Settings', self::SLUG ) . '</h2>
				' . $this->showMessage( __( 'Please install ARC2 first before you can change the settings!', self::SLUG ), 'error' ) . '
				<p>' . __( 'Download ARC2 from https://github.com/semsol/arc2 and unzip it. Open the unziped folder and upload the entire contents into the \'/wp-content/plugins/poolparty-thesaurus/arc/\' directory.', self::SLUG ) . '</p>
			</div>
			';
			exit();
		}
		// Check if mbstring extension for php is installed.
		if ( ! extension_loaded( 'mbstring' ) ) {
			echo '
      <div class="wrap">
        <div class="icon32" id="icon-options-general"></div>
        <h2>' . PP_THESAURUS_PLUGIN_NAME . ' ' . __( 'Settings', self::SLUG ) . '</h2>
        ' . $this->showMessage( __( 'Please install and enable the mbstring extension for PHP!', self::SLUG ), 'error' ) . '
        <p>' . __( 'For more information please visit <a href="http://php.net/manual/en/mbstring.installation.php" target="_blank">mbstring installation</a> on php.net', self::SLUG ) . '</p>
      </div>
      ';
			exit();
		}

		// Save the settings
		$this->saveSettings();

		$oPPStore           = PPThesaurusARC2Store::getInstance();
		$sUpdated           = $this->WPOptions['updated'];
		$sImportFile        = $this->WPOptions['importFile'];
		$sThesaurusEndpoint = isset( $this->WPOptions['thesaurusEndpoint'] ) ? $this->WPOptions['thesaurusEndpoint'] : PP_THESAURUS_ENDPOINT;
		$sDate              = empty( $sUpdated ) ? 'undefined' : date( 'd.m.Y', $sUpdated );
		$sFrom              = empty( $sImportFile ) ? empty( $sThesaurusEndpoint ) ? 'undefined' : 'Thesaurus endpoint' : $sImportFile;
		echo '
			<div class="wrap">
				<div class="icon32" id="icon-options-general"></div>
				<h2>' . PP_THESAURUS_PLUGIN_NAME . ' ' . __( 'Settings', self::SLUG ) . '</h2>
				<h3>' . __( 'Data settings', self::SLUG ) . '</h3>
				<form method="post" action="" enctype="multipart/form-data">
		';
		wp_nonce_field( self::SLUG, self::SLUG . '-nonce' );
		echo '
					<table class="form-table">
						<tr valign="baseline">
							<th scope="row" colspan="2">
		';
		if ( PP_THESAURUS_ENDPOINT_SHOW && PP_THESAURUS_IMPORT_FILE_SHOW ) {
			if ( $oPPStore->existsData() ) {
				printf( __( 'Last data update on %1$s from %2$s', self::SLUG ), "<strong>$sDate</strong>", "<strong>$sFrom</strong>" );
			} else {
				echo '<span style="color:red;">' . __( 'Please import a SKOS Thesaurus', self::SLUG ) . '.</span>';
			}
		} else {
			if ( $oPPStore->existsData() ) {
				printf( __( 'Last data update on %1$s', self::SLUG ), "<strong>$sDate</strong>" );
			} else {
				echo '<span style="color:red;">' . __( 'Please click on "Import/Update Thesaurus"', self::SLUG ) . '.</span>';
			}
		}
		echo '
							</th>
						</tr>
		';
		if ( PP_THESAURUS_ENDPOINT_SHOW || PP_THESAURUS_IMPORT_FILE_SHOW ) {
			echo '
						<tr valign="baseline">
							<th scope="row" colspan="2"><strong>' . __( 'Import/Update SKOS Thesaurus from', self::SLUG ) . '</strong>:</th>
						</tr>
			';
		}
		if ( PP_THESAURUS_ENDPOINT_SHOW ) {
			echo '
						<tr valign="baseline">
							<th scope="row">' . __( 'Thesaurus endpoint', self::SLUG ) . '</th>
							<td>
								URL: <input type="text" size="50" name="thesaurusEndpoint" value="' . $sThesaurusEndpoint . '" />
							</td>
						</tr>
			';
		}
		if ( PP_THESAURUS_IMPORT_FILE_SHOW ) {
			echo '
						<tr valign="baseline">
							<th scope="row">' . __( 'RDF/XML file', self::SLUG ) . ' (max. ' . ini_get( 'post_max_size' ) . 'B)</th>
							<td><input type="file" size="50" name="importFile" value="" /></td>
						</tr>
			';
		}
		echo '
						<tr valign="baseline">
							<th scope="row" colspan="2">' . __( "Uploading the thesaurus can take a few minutes (4-5 minutes).<br />Please remain patient and don't interrupt the procedure.", self::SLUG ) . '</th>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" class="button-primary" value="' . __( 'Import/Update Thesaurus', self::SLUG ) . '" />
						<input type="hidden" name="from" value="data_settings" />
					</p>
				</form>
		';
		if ( $oPPStore->existsData() ) {
			echo '
				<p>&nbsp;</p>
				<h3>' . __( 'Common settings', self::SLUG ) . '</h3>
				<form method="post" action="">
			';
			wp_nonce_field( self::SLUG, self::SLUG . '-nonce' );

			$sLinking_tooltip   = '';
			$sLinking_only_link = '';
			$sLinking_disabled  = '';
			$sLinking           = isset( $this->WPOptions['linking'] ) ? $this->WPOptions['linking'] : 'tooltip';
			$sVariable          = 'sLinking_' . $sLinking;
			$$sVariable         = 'checked="checked" ';
			$sBlacklist         = isset( $this->WPOptions['termsBlacklist'] ) ? $this->WPOptions['termsBlacklist'] : '';
			$sDBPediaEndpoint   = isset( $this->WPOptions['dbpediaEndpoint'] ) ? $this->WPOptions['dbpediaEndpoint'] : PP_THESAURUS_DBPEDIA_ENDPOINT;

			echo '
				<table class="form-table">
					<tr valign="baseline">
						<th scope="row">' . __( 'Options for automated linking of recognized terms', self::SLUG ) . '</th>
						<td>
							<input id="linking_tooltip" type="radio" name="linking" value="tooltip" ' . $sLinking_tooltip . '/>
							<label for="linking_tooltip">' . __( 'link and show description in tooltip', self::SLUG ) . '</label><br />
							<input id="linking_only_link" type="radio" name="linking" value="only_link" ' . $sLinking_only_link . '/>
							<label for="linking_only_link">' . __( 'link without tooltip', self::SLUG ) . '</label><br />
							<input id="linking_disabled" type="radio" name="linking" value="disabled" ' . $sLinking_disabled . '/>
							<label for="linking_disabled">' . __( 'automated linking disabled', self::SLUG ) . '</label>
						</td>
					</tr>
					<tr valign="baseline">
						<th scope="row">' . __( 'Terms excluded from automated linking', self::SLUG ) . '</th>
						<td>
							<input type="text" class="regular-text" name="termsBlacklist" value="' . $sBlacklist . '"  />
							<span class="description">(' . __( 'comma separated values', self::SLUG ) . ')</span>
						<td>
					</tr>
					<tr valign="baseline">
						<th scope="row">' . __( 'Thesaurus languages', self::SLUG ) . '</th>
						<td>' . $this->loadLanguageSettings() . '</td>
					</tr>
			';
			if ( PP_THESAURUS_DBPEDIA_ENDPOINT_SHOW ) {
				echo '
					<tr valign="baseline">
						<th scope="row">' . __( 'DBPedia SPARQL endpoint', self::SLUG ) . '</th>
						<td>
							URL: <input type="text" size="50" name="dbpediaEndpoint" value="' . $sDBPediaEndpoint . '" />
						</td>
					</tr>
				';
			}
			echo '
				</table>
				<p class="submit">
					<input type="submit" class="button-primary" value="' . __( 'Save settings', self::SLUG ) . '" />
					<input type="hidden" name="from" value="common_settings" />
				</p>
			</form>
			';
		}
		echo '
			<p>
				This plugin is provided by the <a href="http://poolparty.biz/" target="_blank">PoolParty</a> Team.<br />
				PoolParty is an easy-to-use SKOS editor for the Semantic Web
			</p>
		</div>
		';
	}

	/**
	 * Generates the form for the language settings.
	 */
	protected function loadLanguageSettings() {
		$aStoredLanguages = array();
		if ( $sLang = $this->WPOptions['languages'] ) {
			$aStoredLanguages = explode( '#', $sLang );
		}

		$oPPTM          = PPThesaurusManager::getInstance();
		$aThesLanguages = $oPPTM->getLanguages();
		$aSysLanguages  = array();
		if ( function_exists( 'qtranxf_getSortedLanguages' ) ) {
			$aLang = qtranxf_getSortedLanguages();
			foreach ( $aLang as $sLang ) {
				$aSysLanguages[ $sLang ] = qtranxf_getLanguageNameNative( $sLang );
			}
		}

		$sContent = '';
		if ( empty( $aSysLanguages ) ) {
			$sFirstLang = $aStoredLanguages[0];
			foreach ( $aThesLanguages as $sLang ) {
				$sChecked = $sLang == $sFirstLang ? 'checked="checked" ' : '';
				$sContent .= '<input id="lang_' . $sLang . '" type="radio" name="languages[]" value="' . $sLang . '" ' . $sChecked . '/> ';
				$sContent .= '<label for="lang_' . $sLang . '">' . $sLang . '</label><br />';
			}
		} else {
			global $q_config;
			$aFlags    = $q_config['flag'];
			$sFlagPath = $q_config['flag_location'];
			$sFlagPath = plugins_url() . substr( $sFlagPath, strpos( $sFlagPath, '/' ) );
			foreach ( $aSysLanguages as $sLang => $sLangName ) {
				if ( in_array( $sLang, $aThesLanguages ) ) {
					$sChecked = in_array( $sLang, $aStoredLanguages ) ? 'checked="checked" ' : '';
					$sContent .= '<input id="lang_' . $sLang . '" type="checkbox" name="languages[]" value="' . $sLang . '" ' . $sChecked . '/> ';
					$sContent .= '<label for="lang_' . $sLang . '">';
					$sContent .= '<img src="' . $sFlagPath . $aFlags[ $sLang ] . '" alt="Language: ' . $sLangName . '" /> ' . $sLangName . '</label><br />';
				} else {
					$sContent .= '<input id="lang_' . $sLang . '" type="checkbox" name="languages[]" value="' . $sLang . '" disabled="disabled" /> ';
					$sContent .= '<img src="' . $sFlagPath . $aFlags[ $sLang ] . '" alt="Language: ' . $sLangName . '" /> ';
					$sContent .= $sLangName . ' (' . __( 'not available', self::SLUG ) . ')<br />';
				}
			}
		}

		return $sContent;
	}

	/**
	 * Saves all the settings and imports the thesaurus.
	 */
	protected function saveSettings() {
		if ( isset( $_POST['from'] ) && isset( $_POST[ self::SLUG . '-nonce' ] ) && wp_verify_nonce( $_POST[ self::SLUG . '-nonce' ], self::SLUG ) ) {
			$bError = FALSE;
			switch ( $_POST['from'] ) {
				case 'data_settings':
					if ( PP_THESAURUS_ENDPOINT_SHOW && PP_THESAURUS_IMPORT_FILE_SHOW &&
					     empty( $_POST['thesaurusEndpoint'] ) && empty( $_FILES['importFile']['name'] )
					) {
						echo $this->showMessage( __( 'Please indicate the SPARQL endpoint or the SKOS file to be imported.', self::SLUG ), 'error' );
						$bError = TRUE;
					} elseif ( PP_THESAURUS_ENDPOINT_SHOW && ! PP_THESAURUS_IMPORT_FILE_SHOW && empty( $_POST['thesaurusEndpoint'] ) ) {
						echo $this->showMessage( __( 'Please indicate the SPARQL endpoint.', self::SLUG ), 'error' );
						$bError = TRUE;
					} elseif ( ! PP_THESAURUS_ENDPOINT_SHOW && PP_THESAURUS_IMPORT_FILE_SHOW && empty( $_FILES['importFile']['name'] ) ) {
						echo $this->showMessage( __( 'Please indicate the SKOS file to be imported.', self::SLUG ), 'error' );
						$bError = TRUE;
					}
					if ( ! $bError ) {
						try {
							set_time_limit( 1800 ); // half an our
							if ( ! empty( $_FILES['importFile']['name'] ) ) {
								PPThesaurusARC2Store::importFromFile();
								$this->WPOptions['importFile'] = $_FILES['importFile']['name'];
							} else {
								PPThesaurusARC2Store::importFromEndpoint();
								$this->WPOptions['languages']         = $this->setDefaultLanguages();
								$this->WPOptions['thesaurusEndpoint'] = $_POST['thesaurusEndpoint'];
								$this->WPOptions['importFile']        = '';
							}
							PPThesaurusCache::clear();
						}
						catch ( Exception $e ) {
							echo $this->showMessage( $e->getMessage(), 'error' );
							$bError = TRUE;
						}
					}
					$this->WPOptions['updated'] = time();
					break;

				case 'common_settings':
					$this->WPOptions['linking'] = $_POST['linking'];
					if ( ! preg_match( '/^[-\w,_ ]*$/', $_POST['termsBlacklist'] ) ) {
						echo $this->showMessage( __( 'Invalid characters in the comma separated list.', self::SLUG ), 'error' );
						$bError = TRUE;
					}
					$this->WPOptions['termsBlacklist'] = esc_html( trim( $_POST['termsBlacklist'] ) );
					$languages                         = ( ! isset( $_POST['languages'] ) || empty( $_POST['languages'] ) ) ? '' : implode( '#', $_POST['languages'] );
					if ( $this->WPOptions['languages'] != $languages ) {
						$this->WPOptions['languages'] = $languages;
						PPThesaurusCache::clear();
					}
					if ( isset( $_POST['dbpediaEndpoint'] ) ) {
						$this->WPOptions['dbpediaEndpoint'] = trim( $_POST['dbpediaEndpoint'] );
					}
					break;
			}
			if ( ! $bError ) {
				update_option( 'PPThesaurus', $this->WPOptions );
				echo $this->showMessage( __( 'Settings saved.', self::SLUG ), 'updated fade' );
			}
		}
	}

	/**
	 * Gets and returns the selected languages.
	 */
	protected function setDefaultLanguages() {
		// Set the default languages only if a new SPAQL endpoint is given
		if ( $this->WPOptions['thesaurusEndpoint'] == $_POST['thesaurusEndpoint'] ) {
			return $this->WPOptions['languages'];
		}

		$oPPStore = PPThesaurusARC2Store::getInstance();
		$oPPTM    = PPThesaurusManager::getInstance();
		if ( ! $oPPStore->existsData() ) {
			// No thesaurus data is given
			return $this->WPOptions['languages'];
		}

		$aThesLanguages = $oPPTM->getLanguages();
		if ( ! function_exists( 'qtrans_getSortedLanguages' ) ) {
			return $aThesLanguages[0];
		}

		$aLanguages    = array();
		$aSysLanguages = qtrans_getSortedLanguages();
		foreach ( $aSysLanguages as $sLang ) {
			if ( in_array( $sLang, $aThesLanguages ) ) {
				$aLanguages[] = $sLang;
			}
		}
		sort( $aLanguages, SORT_STRING );

		return implode( '#', $aLanguages );
	}


	/*********************************************
	 * Helper methods
	 *********************************************/

	/**
	 * Checks if ARC2 is installed.
	 */
	protected function existARC2() {
		if ( class_exists( 'ARC2' ) || file_exists( PP_THESAURUS_PLUGIN_DIR . 'arc/ARC2.php' ) ) {
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * not archived, not spam, not deleted
	 *
	 * @return array|false    The blog ids, false if no matches.
	 */
	public static function getBlogIds() {
		global $wpdb;

		// get an array of blog ids
		$sSql = "SELECT blog_id
            FROM $wpdb->blogs
            WHERE archived = '0' AND spam = '0'
            AND deleted = '0'";

		return $wpdb->get_col( $sSql );
	}

	/**
	 * Returns a message in a container.
	 */
	protected function showMessage( $sMessage, $sClass = 'info' ) {
		return '
			<div id="message" class="' . $sClass . '">
				<p><strong>' . $sMessage . '</strong></p>
			</div>
		';
	}

}


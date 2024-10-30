=== climate change glossary ===
Author URI: http://www.reegle.info
Plugin URI: http://poolparty.biz
Contributors: reegle
Tags: renewable energy, energy efficiency, climate development, renewables, efficiency, glossary, thesaurus, poolparty, skos, rdf
Requires at least: 3.1
Tested up to: 5.1
Stable tag: 2.3
License: GPLv2


This plugin imports a SKOS thesaurus, highlights terms and generates links automatically for any terms available in the thesaurus.


== Description ==

With this plugin any SKOS thesaurus can be imported into your Wordpress blog (via ARC2 into an RDF triple store). A renewable energy and climate change thesaurus is pre-installed.
On two pages (automatically generated) the whole thesaurus can be displayed and used as a glossary on your blog. One page is the main page of the glossary which displays all concepts with their preferred labels and their alternative labels (synonyms). The list of concepts is displayed in an alphabetical order and can be filtered by their first letters. The second page represents the detail view of each concept. All kinds of labels and relations (prefLabel, altLabel, hiddenLabel, definition, scopNote, broader, narrower und related) of a given term (concept) can be loaded and displayed.
Each post is analysed automatically to find words and phrases maching labels of a concept (prefLabel, altLabel or hiddenLabel) in the thesaurus. The first hit will automatically be highlighted. A mousover tooltip shows the short description of the term/phrase and the link points to the more detailed description on the glossary page.

= Shortcodes =
* [ccg-abcindex]: displays an abc filter for filtering the concepts alphabetically.
* [ccg-itemlist]: displays a list of concepts (filterd by its first letter).
* [ccg-itemdetails]: displays the detail page of a selected concept.
* [ccg-noparse] ... [/ccg-noparse]: the content between of the shortcode is excluded from autmatic linking.
* [ccg-searchform title="..." width="...[ px | % ]"]: displays a search form with a autocomplete field to find a concept.

= What's new? =
* The Plugin works now with the user-friendly multilingual dynamic content management [qTranslate X](https://wordpress.org/plugins/qtranslate-x/) for a multilingual WordPress
* The URL to the detail page of a concept is now SEO optimized
* The search algorithm behind the search link on top of the concept detail page is extended (searches now for prefered and alternative labels)
* Implemented new shortcode with which you can add a simple glossary search form between content. The shortcode is called [ccg-searchform] and has two optional parameters: title (The title over the textfield. Leave blank if you don't want a title) and width (The width of the textfield). An example: [ccg-searchform title="Glossary search" width="50%"]

= Features = 
* It is possible to include a list of glossary terms in the settings which will then be excluded from automated linking.
* There is a sidebar-widget which incorporates a search field including autocomplete. This autocomplete service suggests terms from the glossary. Once such a term is chosen, one is automatically connected to the webpage describing the term. The widget can be pulled into any sidebar (depending on the theme) from the sub-section of *Appearance/Widgets*.
* There is a shortcode with which specific parts of the content can be excluded from automatically being linked. The shortcode is called ccg-noparse, and it is opened with *[ccg-noparse]* and closed with *[/ccg-noparse]*. Automated linking is disabled for any text between the code.

Thanks to Benjamin Nowack: The thesaurus is imported into the system and is queried via ARC2 (https://github.com/semsol/arc2).
Thanks to rduffy (http://wordpress.org/extend/plugins/profile/rduffy). His *Glossary* Plugin (http://wordpress.org/extend/plugins/automatic-glossary) inspired me, and I was able to develop this plugin on top of his ideas.

Works with PHP 5, MySQL 5 und ARC2


== Installation ==

= Install using WordPress =
1. Log in and go to *Plugins* and click on *Add New*.
2. Search for *climate change glossary* and hit the *Install Now* link in the results. WordPress will install it.
3. From the Plugin Management page in Wordpress, activate the *Climate change glossary* plugin.
4. Go to *Settings* -> *Climate change glossary* in Wordpress and click on *Import/Update Thesaurus*. Uploading the thesaurus can take a few minutes (4-5 minutes). Please remain patient and don*t interrupt the procedure.

= Install manually =
1. Download the plugin zip file and unzip it.
2. Upload the plugin contents into your WordPress installation*s plugin directory on the server. The plugin*s .php files, readme.txt and subfolders should be installed in the *wp-content/plugins/climate-change-glossary/* directory.
3. Download ARC2 from https://github.com/semsol/arc2 and unzip it.
4. Open the unziped folder and upload the entire contents into the */wp-content/plugins/climate-change-glossary/arc/* directory.
5. From the Plugin Management page in Wordpress, activate the *Climate change glossary* plugin.
6. Go to *Settings* -> *Climate change glossary* in Wordpress and click on *Import/Update Thesaurus*. Uploading the thesaurus can take a few minutes (4-5 minutes). Please remain patient and don*t interrupt the procedure.

= Install ARC2 manually (if automatic install has failed) =
1. Download ARC2 from https://github.com/semsol/arc2 and unzip it.
2. Open the unziped folder and upload the entire contents into the */wp-content/plugins/poolparty-thesaurus/arc/* directory.


== Frequently Asked Questions ==

= Does my main automatically generated glossary page need to be titled **Glossary**? =
No. It can be called whatever you like. You can enter a content if you like, but be careful with the shortcuts.

= Does my automatically generated subpage need to be titled **Item**? =
No. It can be called whatever you like. You can enter a content if you like, but be careful with the shortcuts.

= How do I add a thesaurus item?  =
You will need a SKOS thesaurus management tool like PoolParty (http://poolparty.biz) to add/modify terms. The glossary is generated automatically from the imported thesaurus.

= How can I exclude certain text sections from automated linking? =
Enclose such text sections with preceding [ccg-noparse] and a final [/ccg-noparse]

= How can I exclude certain glossary terms from automated linking? =
In the settings (admin area: *Settings* -> *Climate change glossary*) under "Terms excluded from automated linking" you can now add a list of glossary terms to be excluded from automated linking. 

= How can I update the glossary? =
Simply load the updated thesaurus again (admin area: *Settings* -> *Climate change glossary*). The old thesaurus will be overwritten. New or updated concepts will be recognized immediately by the link generator.

= How to style the tooltip? =
This tooltip consists of a CSS file and three PNG pictures which can be found in the plugin directory (*js/unitip/*). Two pictures consist of the top and bottom edge with and without the pointer and the third picture consists of the middle part.
To style this tooltip, the three pictures can be interchanged and the CSS file adjusted accordingly.


== Screenshots ==
1. Tooltip with the description of a concept
2. The detail page of a concept
3. Admin settings page


== Changelog ==
= 2.3 - 18.03.2019 =
* Apdated simple html dom.

= 2.2.1 - 17.02.2016 =
* The Plugin can be used in multilingual WordPress blogs. Pre-condition is to use another plugin called qTranslate X (https://wordpress.org/plugins/qtranslate-x/) by the qTranslate Team

= 2.2 - 22.12.2015 =
* Small bug fixes and deprecated functions exchanged
* The URL to the detail page of a concept is now SEO optimized 
* The search algorithm behind the search link on top of the concept detail page is extended (searches now for prefered and alternative labels)
* Implemented new shortcode with which you can add a simple glossary search form between content. The shortcode is called [ccg-searchform] and has two optional parameters: title (The title over the textfield. Leave blank if you don't want a title) and width (The width of the textfield). An example: [ccg-searchform title="Glossary search" width="50%"]

= 2.1.1 - 24.03.2015 =
* Bug fixes on the ARC2 installation function

= 2.1 - 19.02.2015 =
* Improved speed of the linking posts with key terms
* Resolved Problem with the activation and the deinstallation if the ARC2 library is not installed
* Resolved Problem with the HTML tag <code>
* Added Multi site support
* Adapted the plugin settings page
* Improved the uninstall mechanism
* Improved sidebar widget *Glossary Search*

= 2.0.1 - 14.10.2015 =
* Small bug fix on autocomplete function

= 2.0 =
* By caching of relevant concepts of a post / article the performance has been optimised when calling a post / article
* The Plugin has been reworked to ensure the use in future WordPress versions

= 1.4 =
* It is now possible to include a list of glossary terms in the settings which will then be excluded from automated linking.
* Bug fixes (thanks rtweedie (http://wordpress.org/support/profile/rtweedie) for the detailed error report)

= 1.3.2 =
* Widget description added

= 1.3.1 =
* Fixed small error in the header title

= 1.3 =
* Updating the plugin via the wordpress admin interface has been simplified. The plugin now gets the ARC2-tripelstore and installs it automatically without need to intervene  manually.
* There is a new sidebar-widget which incorporates a search field including autocomplete. This autocomplete service suggests terms from the glossary. Once such a term is chosen, one is automatically connected to the webpage describing the term. The widget can be pulled into any sidebar (depending on the theme) from the sub-section of *Appearance/Widgets*.
* There is a new shortcode with which specific parts of the content can be excluded from automatically being linked. The shortcode is called ccg-noparse, and it is opened with *[ccg-noparse]* and closed with *[/ccg-noparse]*. Automated linking is disabled for any text between the code.
* Automated finding and linking of concepts in running content can be totally disabled under settings. The glossary area is still present and can be reached via the glossary link and the sidebar widget.
* The procedure for the automated linking has been stabilized and improved
* Bugfixes

= 1.2 =
* New, simplified configuration setting page
* Performance problems resolved
* Few Bugs fixed

= 1.1 =
* Bug with the import from SPARQL endpoint is resolved

= 1.0 =
* Initial release


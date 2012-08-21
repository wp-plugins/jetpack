<?php

/**
 * Module Name: Custom CSS
 * Module Description: Customize the appearance of your site using CSS but without modifying your theme.
 * Sort Order: 11
 * First Introduced: 1.7
 */

function jetpack_load_custom_css() {
	include dirname( __FILE__ ) . "/custom-css/custom-css.php";
}

jetpack_load_custom_css();

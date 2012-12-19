<?php

/**
 * Module Name: Tiled Galleries
 * Module Description: Create elegant magazine-style mosaic layouts for your photos without using an external graphic editor.
 * First Introduced: 2.1
 */

function jetpack_load_tiled_gallery() {
	include dirname( __FILE__ ) . "/tiled-gallery/tiled-gallery.php";
}

jetpack_load_tiled_gallery();
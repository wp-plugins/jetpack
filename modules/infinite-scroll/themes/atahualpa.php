<?php
/**
 * Enable support for Infinite Scroll for Atahualpa
 *
 * @uses add_theme_support
 * @action after_setup_theme
 * @return null
 */
function atahualpa_infinite_scroll_init() {
	add_theme_support( 'infinite-scroll', array(
		'container' => 'middle',
		'render'    => 'atahualpa_infinite_scroll_render'
	) );
}
add_action( 'after_setup_theme', 'atahualpa_infinite_scroll_init' );

/**
 * Rendering function for IS
 *
 * @global $bfa_ata_postcount
 * @uses have_posts, the_post, post_class, the_ID, is_page, bfa_post_kicker, bfa_post_headline, bfa_post_byline, bfa_post_bodycopy, bfa_post_pagination, bfa_archives_page, bfa_post_footer
 * @return string
 */
function atahualpa_infinite_scroll_render() {
	global $bfa_ata_postcount;

	while (have_posts()) : the_post(); $bfa_ata_postcount++; ?>
		<?php /* Post Container starts here */
		if ( function_exists('post_class') ) { ?>
		<div <?php if ( is_page() ) { post_class('post'); } else { post_class(); } ?> id="post-<?php the_ID(); ?>">
		<?php } else { ?>
		<div class="<?php echo ( is_page() ? 'page ' : '' ) . 'post" id="post-'; the_ID(); ?>">
		<?php } ?>
		<?php bfa_post_kicker('<div class="post-kicker">','</div>'); ?>
		<?php bfa_post_headline('<div class="post-headline">','</div>'); ?>
		<?php bfa_post_byline('<div class="post-byline">','</div>'); ?>
		<?php bfa_post_bodycopy('<div class="post-bodycopy clearfix">','</div>'); ?>
		<?php bfa_post_pagination('<p class="post-pagination"><strong>'.__('Pages:','atahualpa').'</strong>','</p>'); ?>
		<?php bfa_archives_page('<div class="archives-page">','</div>'); // Archives Pages. Displayed on a specific static page, if configured at ATO -> Archives Pages: ?>
		<?php bfa_post_footer('<div class="post-footer">','</div>'); ?>
		</div><!-- / Post -->
	<?php endwhile;
}

/**
 * Enqueue theme-specific CSS
 *
 * @uses wp_enqueue_style, plugin_dir
 * @action wp_enqueue_scripts
 * @return null
 */
function atahualpa_infinite_scroll_css() {
	wp_enqueue_style( 'infinite-atahualpa', plugin_dir( 'atahualpa.css', __FILE__ ), array( 'the-neverending-homepage' ), '20121003' );
}
add_action( 'wp_enqueue_scripts', 'atahualpa_infinite_scroll_css' );
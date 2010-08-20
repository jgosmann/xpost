<?php
/*
Plugin Name: Xpost Infobox Widget
Plugin URI: http://www.hyper-world.de/en/computer-2/xpost/
Description: Widget in addition to Xpost to show information of crossposted posts in the target blog.
Version: 1.2.1
Author: Jan Gosmann <jan@hyper-world.de>
Author URI: http://www.hyper-world.de
Text Domain: xpost
*/

/*  Copyright 2009-2010 Jan Gosmann  (email: jan@hyper-world.de)

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

/* I was told this include is necessary to be compatible with WP3 multiuser. */
require_once ( ABSPATH . WPINC . '/pluggable.php' );

require_once( 'xpost_config.php' );

add_action( 'admin_menu', 'xpost_add_target_widget' );

function xpost_add_target_widget() {
    add_meta_box( 'xpost-target-widget', 'Xpost Infobox', 'xpost_target_widget', 'post', 'advanced' );
}

/**
 * Echoes the Xpost widget. Categories of the blogs will be loaded with AJAX.
 */
function xpost_target_widget() {
	$url = get_post_meta( $_GET['post'], 'xpost_origin_url', true );
	$name = get_post_meta( $_GET['post'], 'xpost_origin_name', true );
	$posturl = get_post_meta( $_GET['post'], 'xpost_original_posturl', true );
	
	echo '<p>';
	if( empty( $url ) ) {
		_e('This post is the original.', 'xpost');
	} else {
		$bloglink = sprintf( '<a href="%s" title="%s">%s</a>', $url, $url, $name );
		printf( __('This is a crosspost. The <a href="%s" title="%s">original post</a> resides in %s.', 'xpost'), $posturl, $posturl, $bloglink );
	}
	echo '</p>';
}

?>

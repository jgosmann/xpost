<?php
/*
Plugin Name: Xpost CommunityServer edition
Plugin URI: http://github.com/codeinvain/XpostCS
Description: Allows to crosspost a post to Community Server blogs.
Version: 1.0
Author: Daniel Cohen <daniel@codeinvain.com>
Author URI: http://www.codeinvain.com

Original Author: Jan Gosmann <jan@hyper-world.de>
Original Author URI: http://www.hyper-world.de
*/

/*  Copyright 2009 Jan Gosmann  (email: jan@hyper-world.de)

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
include_once( 'xpost_options.php' );
include_once( 'xpost_widget.php' );
include_once( 'xpost_post.php' );
include_once( 'xpost_comments.php' );
include_once( 'xpost_xmlrpc.php' );

require_once( ABSPATH.'wp-admin/includes/upgrade.php' );

register_activation_hook( __FILE__, 'install_xpost' );


function install_xpost() {
	global $wpdb;
   
   
	$sql = 'CREATE TABLE '.XPOSTCS_TABLE_NAME.' (
			id       INT          NOT NULL AUTO_INCREMENT,
			blogid   INT          NOT NULL,
			selected BOOLEAN      NOT NULL,
			name     VARCHAR(128),
			url      VARCHAR(128),
			xmlrpc   VARCHAR(128) NOT NULL,
			user     VARCHAR(64),
		xpost_comments BOOLEAN NOT NULL DEFAULT false,
		xpost_community_server BOOLEAN NOT NULL DEFAULT false,
		xpost_summary_only BOOLEAN NOT NULL DEFAULT false,
			password VARCHAR(64),
			comment  VARCHAR(256),
			PRIMARY KEY  ( id ) )';      
		dbDelta( $sql );
      
	$sql = 'CREATE TABLE '.XPOSTCS_TABLE_NAME.' (
			id            INT NOT NULL,
			local_postid  INT NOT NULL,
			remote_postid INT NOT NULL,
			PRIMARY KEY  ( id, local_postid ) )';
		dbDelta( $sql );
 
      add_option( "xpost_db_version", XPOSTCS_DB_VERSION );
   }
}

?>

<?php
/*
 * Options page of the plugin.
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

require_once( 'xpost_config.php' );
require_once( ABSPATH . WPINC .'/class-IXR.php' );

add_action( 'admin_menu', 'xpost_plugin_menu' );

function xpost_plugin_menu() {
	add_options_page( 'Xpost Options', 'Xpost', 'manage_options', 'xpost.php', 'xpost_options_page');
}

function xpost_options_page() {
	global $wpdb;
	$errors = array();
	$messages = array();
	
	/* Initialize required variables */
	$blogId = 0;
	$user = $password = $comment = "";
	$xmlrpc = "http://";
	$xpost_comments = false;
	$xpost_community_server = false;
	$xpost_summary_only=false;
	$selected = false;
	
	if( $_POST['updateBlog'] ) {
		$showBlogTable = false;
		$showNewBlogForm = false;
		$showUpdateBlogForm = true;	
	} else {
		$showBlogTable = true;
		$showNewBlogForm = true;
		$showUpdateBlogForm = false;
	}
	
	/* Process user transmitted data */
	if( $_POST['newBlog'] || $_POST['updateBlog'] ) {
		$id = intval( $_POST['id'] );
		$blogId = intval( $_POST['blogid'] );	
		$xmlrpc = stripslashes( $_POST['xmlrpc'] );
		$user = stripslashes( $_POST['user'] );
		$password = stripslashes( $_POST['password'] );
		$comment = stripslashes( $_POST['comment'] );
		$xpost_comments = $_POST['xpost_comments'] ? true : false;
		$xpost_community_server = $_POST['xpost_community_server'] ? true : false;
		$xpost_summary_only = $_POST['xpost_summary_only'] ? true : false;
		
		$selected = $_POST['selected'] ? true : false;
		
		if( strlen( $xmlrpc ) > 128 ) {
			$errors[] = length_error( __('XML-RPC URL', 'xpost'), 128 );
		}
		if( strlen( $user ) > 64 ) {
			$errors[] = length_error( __('username', 'xpost'), 64 );
		}
		if( strlen( $password ) > 64 ) {
			$errors[] = length_error( __('password', 'xpost'), 64 );
		}
		if( strlen( $comment ) > 256 ) {
			$errors[] = length_error( __('comment', 'xpost'), 256 );
		}
		
		if( empty( $xmlrpc ) ) {
			$errors[] = empty_error( __('XML-RPC URL', 'xpost') );
		}
		
		/* Check whether XML-RPC connection works */
		$client = @new IXR_Client( $xmlrpc );
		if ($xpost_community_server){
		$success = $client->query( 'blogger.getUsersBlogs',$user, $user, $password );
			
		}else{
			$success = $client->query( 'wp.getUsersBlogs', $user, $password );
		}
		$response = $client->getResponse();	
		
		if ( !success || isset( $response['faultString'] ) ) {		
			$error = __('XML-RPC connection to the blog failed', 'xpost');
			if( isset( $response['faultString'] ) ){
				$error .= ': ' . $response['faultString'];
			} else {
				$error .= '.';
			}
			$errors[] = $error;
		}		
		
		if( count( $response ) == 0 ) {
			$errors[] = __('XML-RPC request returned no blogs.', 'xpost');
		}
		
		if( count( $errors ) == 0 ) {
			$inserted = 0;
			foreach( $response as $blog ) {
				$insBlogId = $blog['blogId'] ? $blog['blogId'] : $blog['blogid'];
				$name = substr(  html_entity_decode ($blog['blogName']), 0, 128 );
				echo html_entity_decode ($blog['blogName']);
				$url = substr( $blog['url'], 0, 128 );

				if( $_POST['newBlog'] ) {
					$rowsAffected = $wpdb->insert( XPOSTCS_TABLE_NAME,
						array(
							'blogid' => $insBlogId,
							'selected' => $selected,
							'name' => $name,
							'url' => $url,
							'xmlrpc' => $xmlrpc,
							'user' => $user,
							'password' => $password,
							'xpost_comments' => $xpost_comments,
							'xpost_community_server' => $xpost_community_server,
							'xpost_summary_only' => $xpost_summary_only,
							'comment' => $comment ),
						array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d','%s' ) );
					if( $rowsAffected <= 0 ) {
						
						$errors[] = sprintf( __('Error while inserting the blog &quot;<a href="%1$s">%2$s</a>&quot; (blog id: %3d) into the database.', 'xpost'), $url, $name, $blogId );					
					} else {
						++$inserted;
					}
				} else if( $_POST['updateBlog'] ) {
					if( $blogId == $insBlogId ) {
						$rowsAffected = $wpdb->update( XPOSTCS_TABLE_NAME,
							array(
								'selected' => $selected,
								'name' => $name,
								'url' => $url,
								'xmlrpc' => $xmlrpc,
								'user' => $user,
								'password' => $password,
								'xpost_comments' => $xpost_comments,
								'xpost_community_server' => $xpost_community_server,
								'xpost_summary_only' => $xpost_summary_only,
								'comment' => $comment ),
							array(
								'id' => $id,
								'blogid' => $blogId ),
							array( '%d', '%s', '%s', '%s', '%s', '%s','%d', '%d', '%d', '%s' ),
							array( '%d', '%s' ) );
						if( $rowsAffected <= 0 ) {
							$errors[] = __('Error while updating the blog.', 'xpost');
						} else {
							++$inserted;
							break;
						}
					}
				}
			}
			
			if( $_POST['newBlog'] ) {
				$messages[] = sprintf( __ngettext("New blog has successfully been added.", "%d new blogs have successfully been added.", $inserted, 'xpost'), inserted );
			} else if( $_POST['updateBlog'] ) {
				if( $inserted > 0 ) {
					$messages[] = __("Updated the blog.", 'xpost');
					$showBlogTable = true;
					$showNewBlogForm = true;
					$showUpdateBlogForm = false;
				} else if( count( $errors ) == 0 ) {
					$errors[] = __('The XML-RPC request did not return a blog with a matching ID. Maybe the blog has been deleted?', 'xpost');
				}
			}
			$user = $password = $comment = "";
			$xmlrpc = "http://";
			$selected = false;
		}					
	} else if( $_POST['changeBlog'] ) {
		$id = intval( $_POST['id'] );
		$sql = "SELECT blogid, xmlrpc, user, password, xpost_comments,xpost_community_server,xpost_summary_only, comment, selected FROM ".XPOSTCS_TABLE_NAME." WHERE id = $id";
		$blog = $wpdb->get_row( $sql );
		if( empty( $blog ) ) {
			$errors[] = __('Could not load blog data to edit.', 'xpost');
		} else {
			$showBlogTable = false;
			$showNewBlogForm = false;
			$showUpdateBlogForm = true;
			if ($blog->xpost_community_server){
			$blogid = $blog->user;
			}else{
				$blogid = $blog->blogid;
			}
			$xmlrpc = $blog->xmlrpc;
			$user = $blog->user;
			$password = $blog->password;
			$xpost_comments = $blog->xpost_comments;
			$xpost_community_server = $blog->xpost_community_server;
			$xpost_summary_only = $blog->xpost_summary_only;
			$comment = $blog->comment;
			$selected = $blog->selected;
		}
	} else if( $_POST['deleteBlog'] ) {
		$id = intval( $_POST['id'] );
		$sql = "DELETE FROM ".XPOSTCS_TABLE_NAME." WHERE id = $id";
		if( $wpdb->query( $sql ) <= 0 ) {
			$errors[] = __('Error while deleting the blog from the database.', 'xpost');
		}
		$sql = "DELETE FROM ".XPOSTCS_POSTS_TABLE_NAME." WHERE id = $id";
		if( $wpdb->query( $sql ) <= 0 ) {
			$errors[] = __('Error while deleting the blog from the database.', 'xpost');
		}
	}
	
	?><div class="wrap">
		<h2>Xpost</h2>
		
		<p><?php _e('This plugin allows you to crosspost to other Wordpress blogs.', 'xpost'); ?></p>
		<?php
		
		/* Output errors */
		if( count( $errors ) > 0 ) {
			if( $addNewBlog ) {
				$showBlogTable = false;
			}
			
			?><div class="error"><ul><?php
				foreach( $errors as $error ) {
					?><li><strong><?php _e('Error', 'xpost'); ?></strong>: <?php echo $error; ?></li><?php
				}
			?></ul></div><?php
		}
		
		/* Output messages */
		if( count( $messages ) > 0 ) {
			?><div class="updated"><?php
				foreach( $messages as $message ) {
					?><p><?php echo $message; ?></p><?php
				}
			?></div><?php
		}
	
		/* Output the page */
		if( $showBlogTable ) { ?>
			<h3><?php _e('Your Blogs', 'xpost'); ?></h3>
			<?php echo_blogs_tbl(); ?>
		<?php } ?>
		
		<?php if( $showNewBlogForm ) { ?>
			<h3><?php _e('Add New Blog', 'xpost'); ?></h3>
			<form method="post" action="">
				<?php echo_edit_blog_tbl( $xmlrpc, $user, $password, $selected, $xpost_comments,$xpost_community_server,$xpost_summary_only, $comment ); ?>
				<p class="submit">
					<input id="newBlog" name="newBlog" type="submit" class="button-primary" value="<?php _e('Add', 'xpost') ?>" />
				</p>
			</form>
		<?php } ?>
		
		<?php if( $showUpdateBlogForm ) { ?>
			<h3><?php _e('Update Blog', 'xpost'); ?></h3>
			<form method="post">
				<?php echo_edit_blog_tbl( $xmlrpc, $user, $password, $selected, $xpost_comments,$xpost_community_server,$xpost_summary_only, $comment ); ?>
				<input name="id" type="hidden" value="<?php echo $id; ?>" />
				<input name="blogid" type="hidden" value="<?php echo $blogid; ?>" />
				<p class="submit">
					<input id="updateBlog" name="updateBlog" type="submit" class="button-primary" value="<?php _e('Save Changes', 'xpost') ?>" />
				</p>
			</form>
		<?php } ?>
		
	</div><?php 
}

/**
 * Echoes the table head row of the blog list at the options page.
 */
function echo_blogs_tbl_th() { ?>
	<tr>
		<th class="manage-column" scope="col"><?php _e('Selected by Default', 'xpost'); ?></th>
		<th class="manage-column" scope="col"><?php _e('Blog Name', 'xpost'); ?></th>
		<th class="manage-column" scope="col"><?php _e('Blog URL', 'xpost'); ?></th>
		<th class="manage-column" scope="col"><?php _e('Username', 'xpost'); ?></th>
		<th class="manage-column" scope="col"><?php _e('Crosspost comments by default', 'xpost'); ?></th>
		<th class="manage-column" scope="col"><?php _e('Crosspost To Community Server', 'xpost'); ?></th>
		<th class="manage-column" scope="col"><?php _e('Post Summary Only', 'xpost'); ?></th>
		<th class="manage-column" scope="col"><?php _e('Comment', 'xpost'); ?></th>
		<th class="manage-column" scope="col"><?php _e('Change', 'xpost'); ?></th>
		<th class="manage-column" scope="col"><?php _e('Delete', 'xpost'); ?></th>
	</tr>
<?php }

/**
 * Echoes the blog list at the options page.
 */
function echo_blogs_tbl() { ?>
	<?php
		global $wpdb;
		$oddRow = true;
	?>
	<table class="widefat" cellspacing="0">
		<thead><?php echo_blogs_tbl_th(); ?></thead>
		<tfoot><?php echo_blogs_tbl_th(); ?></tfoot>
		<tbody><?php 
			$sql = "SELECT id, selected, name, url, user, xpost_comments,xpost_community_server,xpost_summary_only, comment FROM ".XPOSTCS_TABLE_NAME;
			$blogs = $wpdb->get_results( $sql );
			foreach( $blogs as $blog ) { ?>
				<tr class="<?php echo $oddRow ? 'alternate' : ''; ?>">
					<td><?php $blog->selected ? _e('Yes', 'xpost') : _e('No', 'xpost'); ?></td>
					<td><?php echo esc_html($blog->name); ?></td>
					<td><a href="<?php echo clean_url($blog->url, null, 'url'); ?>"><?php echo clean_url($blog->url); ?></a></td>
					<td><?php echo esc_html($blog->user); ?></td>
					<td><?php $blog->xpost_comments ? _e('Yes', 'xpost') : _e('No', 'xpost'); ?></td>
					<td><?php $blog->xpost_community_server ? _e('Yes', 'xpost') : _e('No', 'xpost'); ?></td>
					<td><?php $blog->xpost_summary_only ? _e('Yes', 'xpost') : _e('No', 'xpost'); ?></td>
					<td><?php echo esc_html($blog->comment); ?></td>
					<td><form method="post">
						<input name="id" type="hidden" value="<?php echo $blog->id; ?>" />
						<input name="changeBlog" type="submit" class="button-secondary" value="<?php _e('Change', 'xpost'); ?>" />
					</form></td>
					<td><form method="post">
						<input name="id" type="hidden" value="<?php echo $blog->id; ?>" />
						<input name="deleteBlog" type="submit" class="button-secondary" value="<?php _e('Delete', 'xpost'); ?>" />
					</form></td>
				</tr>
			<?php
				$oddRow = !$oddRow;
			}
		?></tbody>
	</table>
<?php }

/**
 * Echoes the form table for editing a blog entry. The arguments are the default
 * values to fill in the form fields.
 * 
 * @param string $xmlrpc   URL to XML-RPC file
 * @param string $user     Username to login
 * @param string $password Password to login
 * @param bool   $selected Selected for crosspost by default
 * @param string $comment  Comment
 */
function echo_edit_blog_tbl( $xmlrpc, $user, $password, $selected, $xpost_comments ,$xpost_community_server, $xpost_summary_only,$comment ) { ?>
	<table class="form-table">
		<tr valign="top">
			<th scope="row"><label for="xmlrpc"><?php _e('XML-RPC URL', 'xpost'); ?></label></th>
			<td><input type="text" id="xmlrpc" name="xmlrpc" value="<?php echo esc_html($xmlrpc); ?>" />
				<span class="description">
					<?php _e('The URL of the xmlrpc.php file of the Blog. Usually this is <strong>http://blog-url/xmlrpc.php</strong> (of course you have to replace blog-url with the URL of your blog.', 'xpost')?></span></td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="user"><?php _e('Username', 'xpost'); ?></label></th>
			<td><input type="text" id="user" name="user" value="<?php echo esc_html($user); ?>" />
				<span class="description">
					<?php  _e('Username needed to login into the blog.', 'xpost'); ?></span></td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="password"><?php _e('Password', 'xpost'); ?></label></th>
			<td><input type="password" id="password" name="password" value="<?php echo esc_html($password); ?>" />
				<span class="description">
					<?php  _e('Password needed to login into the blog.', 'xpost'); ?></span></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Default', 'xpost'); ?></th>
			<td><fieldset><legend class="screen-reader-text"><span><?php _e('Default', 'xpost'); ?></span></legend>
				<label for="selected"><input type="checkbox" id="selected" name="selected" <?php echo $selected ? 'checked="checked"' : ''; ?> />
					<?php  _e('Crosspost to this blog by default.', 'xpost'); ?></label></fieldset></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Comments', 'xpost'); ?></th>
			<td><fieldset><legend class="screen-reader-text"><span><?php _e('Comments', 'xpost'); ?></span></legend>
				<label for="xpost_comments"><input type="checkbox" id="xpost_comments" name="xpost_comments" <?php echo $xpost_comments ? 'checked="checked"' : ''; ?> />
					<?php _e('Crosspost comments from and to this blog by default.', 'xpost'); ?></label></fieldset></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('CommunityServer', 'xpost'); ?></th>
			<td><fieldset><legend class="screen-reader-text"><span><?php _e('CommunityServer', 'xpost'); ?></span></legend>
				<label for="xpost_community_server"><input type="checkbox" id="xpost_community_server" name="xpost_community_server" <?php echo $xpost_community_server ? 'checked="checked"' : ''; ?> />
					<?php _e('Crosspost To Community Server.', 'xpost'); ?></label></fieldset></td>
		</tr>
			<tr valign="top">
			<th scope="row"><?php _e('SummaryOnly', 'xpost'); ?></th>
			<td><fieldset><legend class="screen-reader-text"><span><?php _e('SummaryOnly', 'xpost'); ?></span></legend>
				<label for="xpost_summary_only"><input type="checkbox" id="xpost_summary_only" name="xpost_summary_only" <?php echo $xpost_summary_only ? 'checked="checked"' : ''; ?> />
					<?php _e('Crosspost Summary Only.', 'xpost'); ?></label></fieldset></td>
		</tr>
		 
		<tr valign="top">
			<th scope="row"><label for="comment"><?php _e('Comment', 'xpost'); ?></label></th>
			<td><input type="text" id="comment" name="comment" value="<?php echo esc_html($comment); ?>" />
				<span class="description">
					<?php  _e('Here you can enter anything you want.', 'xpost'); ?></span></td>
		</tr>
	</table>
<?php }

/**
 * Generates the error message for too long content in an input field.
 * 
 * @param string           $fieldName Name of the input field to be used in the message.
 * @param unsigned integer $maxLength Maximum length for the input field.
 * @return string The localized error message.
 */
function length_error( $fieldName, $maxLength ) {
	return sprintf( __('The %s cannot exceed the length of %u characters.', 'xpost'), $fieldName, $maxLength );
}

/**
 * Generates teh error message for empty input fields.
 * 
 * @param string $fieldName Name of the input field to be used in the message.
 * @return string The localized error message.
 */
function empty_error( $fieldName ) {
	return sprintf( __('Please enter a %s.', 'xpost'), $fieldName);
}

?>
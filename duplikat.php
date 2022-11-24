<?php

/*
Plugin Name: Duplikat
Version: 1.0
Description: Simple post duplication plugin. Works with any post type, other than Woocommerce products, and duplicates the post, postmeta and taxonomies. Why this plugin name? Duplikat means "duplicate" in German.
Plugin URI: https://github.com/lutrov/duplikat
Copyright: 2020, Ivan Lutrov
Author: Ivan Lutrov
Author URI: http://lutrov.com

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program; if not, write to the Free Software Foundation, Inc., 51 Franklin
Street, Fifth Floor, Boston, MA 02110-1301, USA. Also add information on how to
contact you by electronic and paper mail.
*/

defined('ABSPATH') || die();

//
// Configurable constants used by this plugin.
//
define('DUPLIKAT_ENABLE_POST_DUPLICATION', true);

//
// Duplicate post, postmeta & taxonomies for a specified post.
// Woocommerce has its own duplication functionality so bypass this function
// if we're trying to process one of its products.
//
add_filter('admin_init', 'duplikat_post_duplicate_action', 10, 2);
function duplikat_post_duplicate_action() {
	global $wpdb;
	if (DUPLIKAT_ENABLE_POST_DUPLICATION == true) {
		if (empty($_GET['post']) == false) {
			if (empty($_GET['action']) == false && $_GET['action'] == 'duplicate') {
				if (current_user_can('edit_posts')) {
					check_admin_referer('duplikat');
					$post_id = (int) $_GET['post'];
					if ($post_id > 0) {
						$post = get_post($post_id);
						if (empty($post) == false) {
							if ($post->post_type <> 'product') {
								$args = array(
									'post_type' => $post->post_type,
									'post_content' => $post->post_content,
									'post_excerpt' => $post->post_excerpt,
									'post_parent' => $post->post_parent,
									'post_password' => $post->post_password,
									'post_title' => sprintf('%s (%s)', $post->post_title, __('Copy')),
									'post_name' => sanitize_title_with_dashes(sprintf('%s (%s)', $post->post_title, __('Copy'))),
									'comment_status' => $post->comment_status,
									'ping_status' => $post->ping_status,
									'to_ping' => $post->to_ping,
									'menu_order' => $post->menu_order
								);
								$dupe_id = wp_insert_post($args);
								if ($dupe_id > 0) {
	  								// Copy postmeta
									$meta = get_post_custom($post_id);
			  						foreach ($meta as $key => $values) {
										if (is_array($values) == true && count($values) > 0) {
											foreach ($values as $value) {
												$wpdb->insert($wpdb->prefix . 'postmeta', array(
													'post_id' => $dupe_id,
													'meta_key' => $key,
													'meta_value' => $value
												));
											}
										}
									}
									// Copy taxonomies 
									$taxonomies = get_object_taxonomies($post->post_type);
									foreach($taxonomies as $taxonomy) {
										$terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'names'));
										wp_set_object_terms($dupe_id, $terms, $taxonomy);
									}
			 						$href = remove_query_arg(array('post', 'action', '_wpnonce'), wp_get_referer());
							  		wp_redirect($href);
  									exit;
								}
							}
						}
					}
				}
			}
		}
	}
}

//
// Create "duplicate" link in posts listing.
// Woocommerce has its own duplication functionality so bypass this function
// if we're on its product page.
//
add_filter('post_row_actions', 'duplikat_post_row_action', 10, 2);
add_filter('page_row_actions', 'duplikat_post_row_action', 10, 2);
function duplikat_post_row_action($actions, $post) {
	if (DUPLIKAT_ENABLE_POST_DUPLICATION == true) {
		if (empty($_GET['post_type']) == true || $_GET['post_type'] <> 'product') {
			if (current_user_can('edit_posts')) {
				$href = wp_nonce_url(
					add_query_arg(array('post' => $post->ID, 'action' => 'duplicate')),
					'duplikat'
				);
				$actions['duplikat'] = sprintf('<a href="%s">%s</a>', $href, __('Duplicate'));
			}
		}
	}
	return $actions;
}

?>
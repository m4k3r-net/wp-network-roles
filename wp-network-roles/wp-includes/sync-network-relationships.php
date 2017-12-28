<?php
/**
 * Functions to synchronize the super admins network option with the Administrator network role.
 *
 * @package WPNetworkRoles
 * @since 1.0.0
 */

/**
 * Maybe adds a user to a network when they're added to a site.
 *
 * The user is only added to the site's network if they haven't been granted any capability on that network
 * already through another method.
 *
 * @since 1.0.0
 * @access private
 *
 * @global array $wpnr_users_with_network_roles Internal storage for user objects with network roles.
 *
 * @param int    $user_id User ID.
 * @param string $role    New role on the site.
 * @param int    $site_id Site ID.
 */
function _nr_maybe_add_user_to_network( $user_id, $role, $site_id ) {
	global $wpnr_users_with_network_roles;

	if ( ! $site_id ) {
		return;
	}

	$site = get_site( $site_id );
	if ( ! $site ) {
		return;
	}

	$network_id = $site->network_id;

	if ( ! isset( $wpnr_users_with_network_roles[ $user_id ] ) ) {
		$wpnr_users_with_network_roles[ $user_id ] = new WPNR_User_With_Network_Roles( $user_id, $network_id );
	} elseif ( $wpnr_users_with_network_roles[ $user_id ]->get_network_id() !== $network_id ) {
		$wpnr_users_with_network_roles[ $user_id ]->for_network( $network_id );
	}

	if ( empty( $wpnr_users_with_network_roles[ $user_id ]->network_caps ) ) {
		$wpnr_users_with_network_roles[ $user_id ]->set_network_role( '' );
	}
}
add_action( 'add_user_to_blog', '_nr_maybe_add_user_to_network', 10, 3 );

/**
 * Maybe removes a user from a network when they're removed from a site.
 *
 * The user is only removed from the site's network if there aren't any other site's in that network which they are a
 * member of. Furthermore the user is not removed if they have been granted an actual capability on the network.
 *
 * @since 1.0.0
 * @access private
 *
 * @global array $wpnr_users_with_network_roles Internal storage for user objects with network roles.
 *
 * @param int $user_id User ID.
 * @param int $site_id Site ID.
 */
function _nr_maybe_remove_user_from_network( $user_id, $site_id ) {
	global $wpnr_users_with_network_roles;

	if ( ! $site_id ) {
		return;
	}

	$site = get_site( $site_id );
	if ( ! $site ) {
		return;
	}

	$network_id = $site->network_id;

	$blogs = get_blogs_of_user( $user_id );
	if ( empty( $blogs ) ) {
		return;
	}

	foreach ( $blogs as $blog ) {
		// Bail if there is still another site in the network which the user is part of.
		if ( (int) $blog->site_id === $network_id && (int) $blog->userblog_id !== $site->id ) {
			return;
		}
	}

	if ( ! isset( $wpnr_users_with_network_roles[ $user_id ] ) ) {
		$wpnr_users_with_network_roles[ $user_id ] = new WPNR_User_With_Network_Roles( $user_id, $network_id );
	} elseif ( $wpnr_users_with_network_roles[ $user_id ]->get_network_id() !== $network_id ) {
		$wpnr_users_with_network_roles[ $user_id ]->for_network( $network_id );
	}

	if ( empty( $wpnr_users_with_network_roles[ $user_id ]->network_caps ) ) {
		$wpnr_users_with_network_roles[ $user_id ]->remove_all_network_caps();
	}
}
add_action( 'remove_user_from_blog', '_nr_maybe_remove_user_from_network', 10, 2 );

/**
 * Sets the initial network relationship on a new user.
 *
 * The user is added to the network of the current site.
 *
 * @since 1.0.0
 *
 * @param int $user_id User ID.
 */
function _nr_set_initial_network_relationship_on_new_user( $user_id ) {
	$site_id = get_current_blog_id();

	_nr_maybe_add_user_to_network( $user_id, '', $site_id );
}
add_action( 'user_register', '_nr_set_initial_network_relationship_on_new_user', 10, 1 );
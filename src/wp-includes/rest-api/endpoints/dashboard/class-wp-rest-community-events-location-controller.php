<?php
/**
 * REST API: WP_REST_Community_Events_Location_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 4.8.0
 */

/**
 * Core class to access community events user locations via the REST API.
 *
 * @since 4.8.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Community_Events_Location_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 4.8.0
	 * @access public
	 */
	public function __construct() {
		$this->namespace = 'wp/dashboard/v1';
		$this->rest_base = 'community-events';
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/my-location', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_current_item' ),
				'permission_callback' => array( $this, 'get_current_item_permissions_check' ),
				'args'                => $this->get_item_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Checks whether a given request has permission to read the current user's community events location.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|true True if the request has read access, WP_Error object otherwise.
	 */
	public function get_current_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Retrieves the community events location for the current user.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function get_current_item( $request ) {
		require_once( ABSPATH . 'wp-admin/includes/class-wp-community-events.php' );

		$user_id = get_current_user_id();

		$location = $request->get_param( 'location' );
		$timezone = $request->get_param( 'timezone' );

		$saved_location = get_user_option( 'community-events-location', $user_id );
		$events_client  = new WP_Community_Events( $user_id, $saved_location );
		$events         = $events_client->get_events( $location, $timezone );

		// Store the location network-wide, so the user doesn't have to set it on each site.
		if ( ! is_wp_error( $events ) ) {
			if ( isset( $events['error'] ) && 'no_location_available' === $events['error'] ) {
				return new WP_Error( 'rest_cannot_retrieve_user_location', __( 'The user location could not be retrieved.' ) );
			}

			if ( isset( $events['location'] ) ) {
				update_user_option( $user_id, 'community-events-location', $events['location'], true );

				$data = $this->prepare_item_for_response( $events['location'], $request );

				return rest_ensure_response( $data );
			}
		}

		return $events;
	}

	/**
	 * Prepares a single location output for response.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @param array           $location Location data array from the API.
	 * @param WP_REST_Request $request  Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $location, $request ) {
		$data = array(
			'description' => isset( $location['description'] ) ? $location['description']       : null,
			'country'     => isset( $location['country'] )     ? $location['country']           : null,
			'latitude'    => isset( $location['latitude'] )    ? (float) $location['latitude']  : null,
			'longitude'   => isset( $location['longitude'] )   ? (float) $location['longitude'] : null,
		);

		$response = rest_ensure_response( $data );

		$url = rest_url( 'wp/dashboard/v1/community-events/events/me' );

		$url_args = array();

		if ( ! empty( $request['location'] ) ) {
			$url_args['location'] = $request['location'];
		}
		if ( ! empty( $request['timezone'] ) ) {
			$url_args['timezone'] = $request['timezone'];
		}

		if ( ! empty( $url_args ) ) {
			$url = add_query_arg( $url_args, $url );
		}

		$response->add_links( array(
			'events' => array(
				'href'       => $url,
				'embeddable' => true,
			),
		) );

		return $response;
	}

	/**
	 * Retrieves a community events location schema, conforming to JSON Schema.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		return array(
			'$schema'              => 'http://json-schema.org/schema#',
			'title'                => 'community_events_location',
			'type'                 => 'object',
			'properties'           => array(
				'description' => array(
					'description' => __( 'Location description.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'country'     => array(
					'description' => __( 'Two-letter country code.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'latitude'    => array(
					'description' => __( 'Latitude.' ),
					'type'        => 'number',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'longitude'   => array(
					'description' => __( 'Longitude.' ),
					'type'        => 'number',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			),
		);
	}

	/**
	 * Retrieves the params for a single item.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @return array Item parameters.
	 */
	public function get_item_params() {
		return array(
			'context'  => $this->get_context_param( array( 'default' => 'view' ) ),
			'location' => array(
				'description' => __( 'Optional city name to help determine the location.' ),
				'type'        => 'string',
				'default'     => '',
			),
			'timezone' => array(
				'description' => __( 'Optional timezone to help determine the location.' ),
				'type'        => 'string',
				'default'     => '',
			),
		);
	}
}

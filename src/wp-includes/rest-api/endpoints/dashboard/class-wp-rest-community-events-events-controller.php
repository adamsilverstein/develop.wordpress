<?php
/**
 * REST API: WP_REST_Community_Events_Events_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 4.8.0
 */

/**
 * Core class to access community events via the REST API.
 *
 * @since 4.8.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Community_Events_Events_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 4.8.0
	 * @access public
	 */
	public function __construct() {
		$this->namespace = 'wp/dashboard/v1';
		$this->rest_base = 'community-events/events';
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

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/me', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_current_items' ),
				'permission_callback' => array( $this, 'get_current_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Checks whether a given request has permission to read community events.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|true True if the request has read access, WP_Error object otherwise.
	 */
	public function get_current_items_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Retrieves community events.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function get_current_items( $request ) {
		require_once( ABSPATH . 'wp-admin/includes/class-wp-community-events.php' );

		$user_id = get_current_user_id();

		$location = $request->get_param( 'location' );
		$timezone = $request->get_param( 'timezone' );

		$saved_location = get_user_option( 'community-events-location', $user_id );
		$events_client  = new WP_Community_Events( $user_id, $saved_location );
		$events         = $events_client->get_events( $location, $timezone );

		$data = array();

		// Store the location network-wide, so the user doesn't have to set it on each site.
		if ( ! is_wp_error( $events ) ) {
			if ( isset( $events['location'] ) ) {
				update_user_option( $user_id, 'community-events-location', $events['location'], true );
			}

			if ( isset( $events['events'] ) ) {
				foreach ( $events['events'] as $event ) {
					$data[] = $this->prepare_item_for_response( $event, $request );
				}
			}
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Prepares a single event output for response.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @param array           $event   Event data array from the API.
	 * @param WP_REST_Request $request Request object.
	 * @return array Item prepared for response.
	 */
	public function prepare_item_for_response( $event, $request ) {
		$data = array();

		$keys_to_copy = array( 'type', 'title', 'url', 'meetup', 'meetup_url' );
		foreach ( $keys_to_copy as $key ) {
			if ( isset( $event[ $key ] ) ) {
				$data[ $key ] = $event[ $key ];
			} else {
				$data[ $key ] = null;
			}
		}

		$data['date'] = array(
			'raw'       => isset( $event['date'] ) ? $event['date'] : null,
			'formatted' => array(
				'date' => isset( $event['formatted_date'] ) ? $event['formatted_date'] : null,
				'time' => isset( $event['formatted_time'] ) ? $event['formatted_time'] : null,
			),
		);

		$data['location'] = isset( $event['location'] ) ? $event['location'] : null;

		return $data;
	}

	/**
	 * Retrieves a community event's schema, conforming to JSON Schema.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		return array(
			'$schema'              => 'http://json-schema.org/schema#',
			'title'                => 'community_event',
			'type'                 => 'object',
			'properties'           => array(
				'type'       => array(
					'description' => __( 'Type for the event.' ),
					'type'        => 'string',
					'enum'        => array( 'meetup', 'wordcamp' ),
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'title'      => array(
					'description' => __( 'Title for the event.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'url'        => array(
					'description' => __( 'Website URL for the event.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'meetup'     => array(
					'description' => __( 'Name of the meetup, if the event is a meetup.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'meetup_url' => array(
					'description' => __( 'URL for the meetup on meetup.com, if the event is a meetup.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'date'       => array(
					'description' => __( 'Date and time information for the event.' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
					'properties'  => array(
						'raw'       => array(
							'description' => __( 'Unformatted date and time string.' ),
							'type'        => 'string',
							'format'      => 'date-time',
							'context'     => array( 'view', 'edit', 'embed' ),
							'readonly'    => true,
						),
						'formatted' => array(
							'description' => __( 'Formatted date and time information for the event.' ),
							'type'        => 'object',
							'context'     => array( 'view', 'edit', 'embed' ),
							'readonly'    => true,
							'properties'  => array(
								'date' => array(
									'description' => __( 'Formatted event date.' ),
									'type'        => 'string',
									'context'     => array( 'view', 'edit', 'embed' ),
									'readonly'    => true,
								),
								'time' => array(
									'description' => __( 'Formatted event time.' ),
									'type'        => 'string',
									'context'     => array( 'view', 'edit', 'embed' ),
									'readonly'    => true,
								),
							),
						),
					),
				),
				'location'   => array(
					'description' => __( 'Location information for the event.' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
					'properties'  => array(
						'location'  => array(
							'description' => __( 'Location name for the event.' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit', 'embed' ),
							'readonly'    => true,
						),
						'country'   => array(
							'description' => __( 'Two-letter country code for the event.' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit', 'embed' ),
							'readonly'    => true,
						),
						'latitude'  => array(
							'description' => __( 'Latitude for the event.' ),
							'type'        => 'number',
							'context'     => array( 'view', 'edit', 'embed' ),
							'readonly'    => true,
						),
						'longitude' => array(
							'description' => __( 'Longitude for the event.' ),
							'type'        => 'number',
							'context'     => array( 'view', 'edit', 'embed' ),
							'readonly'    => true,
						),
					),
				),
			),
		);
	}

	/**
	 * Retrieves the query params for collections.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		return array(
			'context'  => $this->get_context_param( array( 'default' => 'view' ) ),
			'location' => array(
				'description' => __( 'Optional city name to help determine the location for the events.' ),
				'type'        => 'string',
				'default'     => '',
			),
			'timezone' => array(
				'description' => __( 'Optional timezone to help determine the location for the events.' ),
				'type'        => 'string',
				'default'     => '',
			),
		);
	}
}

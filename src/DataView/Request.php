<?php declare( strict_types=1 );

namespace Tangible\DataView;

use WP_REST_Request;

/**
 * Wraps a WP_REST_Request instance, gives access to parameters
 * according to method and defines default values
 */
class Request {

    /**
     * @see https://developer.wordpress.org/reference/classes/wp_rest_request/
     */
    protected WP_REST_Request $rest_request;

    protected array $default_params = [
        'id'        => null,
        'action'    => 'list',
        '_wpnonce'  => '',
    ];

    public function __construct() {
        /**
         * Simple implementation based on how WP_REST_Server::serve_request()
         * instantiates WP_REST_Request
         *
         * @see https://developer.wordpress.org/reference/classes/wp_rest_server/serve_request/
         */
        $this->rest_request = new WP_REST_Request(
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['PATH_INFO'] ?? '/'
        );

        $this->rest_request->set_default_params( $this->default_params );
        $this->rest_request->set_query_params( wp_unslash( $_GET ) );
        $this->rest_request->set_body_params( wp_unslash( $_POST ) );
    }

    /**
     * Get the current action from the request.
     *
     * @return string Current action (defaults to 'list').
     */
    public function get_current_action(): string {
        return sanitize_key( (string) $this->rest_request->get_param( 'action' ) );
    }

    /**
     * Get the entity ID from the current request.
     *
     * @return int|null Entity ID or null if not present.
     */
    public function get_current_id(): ?int {
        $id = $this->rest_request->get_param( 'id' );
        return $id !== null ? (int) $id : null;
    }

    /**
     * Get the WordPress nonce from the current request.
     */
    public function get_nonce(): string {
        return (string) $this->rest_request->get_param( '_wpnonce' );
    }

    /**
     * Whether the current request is a POST request.
     */
    public function is_post(): bool {
        return $this->rest_request->is_method( 'POST' );
    }
}

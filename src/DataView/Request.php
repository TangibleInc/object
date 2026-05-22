<?php declare( strict_types=1 );

namespace Tangible\DataView;

/**
 * Represents the current HTTP request.
 *
 * For shared parameters (action, id), POST is checked first and GET is used
 * as a fallback. This lets forms carry the routing parameters in hidden
 * inputs rather than relying on the URL.
 */
class Request {

    /**
     * Get the current action from the request.
     *
     * @return string Current action (defaults to 'list').
     */
    public function get_current_action(): string {
        $action = $this->get_param( 'action' );
        return $action !== null ? sanitize_key( $action ) : 'list';
    }

    /**
     * Get the entity ID from the current request.
     *
     * @return int|null Entity ID or null if not present.
     */
    public function get_current_id(): ?int {
        $id = $this->get_param( 'id' );
        return $id !== null ? (int) $id : null;
    }

    /**
     * Get the WordPress nonce from the current request.
     */
    public function get_nonce(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return (string) ( $_POST['_wpnonce'] ?? $_GET['_wpnonce'] ?? '' );
    }

    /**
     * Whether the current request is a POST request.
     */
    public function is_post(): bool {
        return isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Read a parameter, checking POST first, then GET.
     */
    protected function get_param( string $name ): ?string {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_POST[ $name ] ) ) {
            return (string) $_POST[ $name ];
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET[ $name ] ) ) {
            return (string) $_GET[ $name ];
        }
        return null;
    }
}

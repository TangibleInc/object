<?php declare( strict_types=1 );

namespace Tangible\DataView;

/**
 * URL generation and parsing for DataView admin pages.
 */
class UrlBuilder {

    protected string $menu_page;

    public function __construct( string $menu_page ) {
        $this->menu_page = $menu_page;
    }

    /**
     * Generate an admin URL for a specific action.
     *
     * @param string $action One of: 'list', 'create', 'edit', 'delete'.
     * @param int|null $id Entity ID (required for 'edit' and 'delete').
     * @param array $extra Extra query parameters.
     * @return string Admin URL.
     */
    public function url( string $action = 'list', ?int $id = null, array $extra = [] ): string {
        $params = [ 'page' => $this->menu_page ];

        if ( $action !== 'list' ) {
            $params['action'] = $action;
        }

        if ( $id !== null ) {
            $params['id'] = $id;
        }

        $params = array_merge( $params, $extra );

        return add_query_arg( $params, admin_url( 'admin.php' ) );
    }

    /**
     * Generate a URL with nonce for destructive actions.
     *
     * @param string $action Action name.
     * @param int|null $id Entity ID.
     * @param string $nonce_action Nonce action name.
     * @return string URL with nonce.
     */
    public function url_with_nonce( string $action, ?int $id, string $nonce_action ): string {
        $url = $this->url( $action, $id );
        return wp_nonce_url( $url, $nonce_action );
    }

    /**
     * Get the menu page slug.
     *
     * @return string Menu page slug.
     */
    public function get_menu_page(): string {
        return $this->menu_page;
    }

    /**
     * Generate the nonce action name for a given action and optional ID.
     *
     * @param string $action Action name.
     * @param int|null $id Entity ID.
     * @return string Nonce action name.
     */
    public function get_nonce_action( string $action, ?int $id = null ): string {
        $nonce = $this->menu_page . '_' . $action;
        if ( $id !== null ) {
            $nonce .= '_' . $id;
        }
        return $nonce;
    }
}

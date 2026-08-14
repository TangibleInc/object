<?php declare( strict_types=1 );

namespace Tangible\DataView;

/**
 * URL generation and parsing for DataView admin pages.
 */
class UrlBuilder {

    protected string $menu_page;

    /**
     * The admin file the page hangs off, e.g. 'admin.php' (top level) or
     * 'edit.php' for a post-type submenu.
     *
     * @var string
     */
    protected string $base_file = 'admin.php';

    /**
     * Query parameters the base file needs to resolve the page, e.g.
     * ['post_type' => 'book'] for a post-type submenu. Always merged
     * into generated URLs, and rendered as hidden inputs by list forms.
     *
     * @var array<string, string>
     */
    protected array $base_query = [];

    /**
     * Create a new UrlBuilder.
     *
     * The parent menu follows WordPress's own submenu URL rule: when it
     * names an admin file ('edit.php?post_type=book',
     * 'options-general.php'), page URLs build on that file with its
     * query parameters; a null parent or a plugin-page slug keeps the
     * top-level 'admin.php' base.
     *
     * @param string $menu_page The page slug.
     * @param string|null $parent The parent menu (DataView ui.parent), if any.
     */
    public function __construct( string $menu_page, ?string $parent = null ) {
        $this->menu_page = $menu_page;

        if ( $parent !== null && str_contains( $parent, '.php' ) ) {
            $parts           = explode( '?', $parent, 2 );
            $this->base_file = $parts[0];

            if ( isset( $parts[1] ) ) {
                parse_str( $parts[1], $query );
                foreach ( $query as $key => $value ) {
                    $this->base_query[ (string) $key ] = (string) $value;
                }
            }
        }
    }

    /**
     * The parameters that identify this page: the base file's query
     * parameters plus the page slug. List forms render these as hidden
     * inputs so a GET submit resolves back to the page.
     *
     * @return array<string, string> Parameter name => value.
     */
    public function base_params(): array {
        return $this->base_query + [ 'page' => $this->menu_page ];
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
        $params = $this->base_params();

        if ( $action !== 'list' ) {
            $params['action'] = $action;
        }

        if ( $id !== null ) {
            $params['id'] = $id;
        }

        $params = array_merge( $params, $extra );

        return add_query_arg( $params, admin_url( $this->base_file ) );
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
        return wp_nonce_url( $url, $nonce_action, '_wpnonce_' . $action );
    }
}

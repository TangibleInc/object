<?php declare( strict_types=1 );

namespace Tangible\DataView;

/**
 * Generates WordPress labels from a singular label.
 */
class LabelGenerator {

    /**
     * Generate full WordPress labels from a singular label.
     *
     * @param string $singular Singular label (e.g., "View").
     * @param string|null $plural Plural label (auto-generated if null).
     * @return array WordPress labels array.
     */
    public function generate( string $singular, ?string $plural = null ): array {
        $plural          = $plural ?? $this->pluralize( $singular );
        $singular_lower  = strtolower( $singular );
        $plural_lower    = strtolower( $plural );

        return [
            'name'                     => $plural,
            'singular_name'            => $singular,
            'add_new'                  => 'Add New',
            'add_new_item'             => sprintf( 'Add New %s', $singular ),
            'edit_item'                => sprintf( 'Edit %s', $singular ),
            'new_item'                 => sprintf( 'New %s', $singular ),
            'view_item'                => sprintf( 'View %s', $singular ),
            'view_items'               => sprintf( 'View %s', $plural ),
            'search_items'             => sprintf( 'Search %s', $plural ),
            'not_found'                => sprintf( 'No %s found', $plural_lower ),
            'not_found_in_trash'       => sprintf( 'No %s found in Trash', $plural_lower ),
            'all_items'                => sprintf( 'All %s', $plural ),
            'archives'                 => sprintf( '%s Archives', $singular ),
            'attributes'               => sprintf( '%s Attributes', $singular ),
            'insert_into_item'         => sprintf( 'Insert into %s', $singular_lower ),
            'uploaded_to_this_item'    => sprintf( 'Uploaded to this %s', $singular_lower ),
            'filter_items_list'        => sprintf( 'Filter %s list', $plural_lower ),
            'items_list_navigation'    => sprintf( '%s list navigation', $plural ),
            'items_list'               => sprintf( '%s list', $plural ),
            'item_published'           => sprintf( '%s published.', $singular ),
            'item_published_privately' => sprintf( '%s published privately.', $singular ),
            'item_reverted_to_draft'   => sprintf( '%s reverted to draft.', $singular ),
            'item_scheduled'           => sprintf( '%s scheduled.', $singular ),
            'item_updated'             => sprintf( '%s updated.', $singular ),
            'menu_name'                => $plural,
        ];
    }

    /**
     * Auto-pluralize an English word using common rules.
     *
     * @param string $word Word to pluralize.
     * @return string Pluralized word.
     */
    public function pluralize( string $word ): string {
        // Common irregular plurals.
        $irregulars = [
            'child'  => 'children',
            'person' => 'people',
            'man'    => 'men',
            'woman'  => 'women',
            'foot'   => 'feet',
            'tooth'  => 'teeth',
            'goose'  => 'geese',
            'mouse'  => 'mice',
            'ox'     => 'oxen',
            'leaf'   => 'leaves',
            'life'   => 'lives',
            'knife'  => 'knives',
            'wife'   => 'wives',
            'self'   => 'selves',
            'elf'    => 'elves',
            'loaf'   => 'loaves',
            'potato' => 'potatoes',
            'tomato' => 'tomatoes',
            'cactus' => 'cacti',
            'focus'  => 'foci',
            'fungus' => 'fungi',
            'nucleus' => 'nuclei',
            'syllabus' => 'syllabi',
            'analysis' => 'analyses',
            'diagnosis' => 'diagnoses',
            'oasis'  => 'oases',
            'thesis' => 'theses',
            'crisis' => 'crises',
            'phenomenon' => 'phenomena',
            'criterion'  => 'criteria',
            'datum'      => 'data',
            'medium'     => 'media',
            'index'      => 'indices',
        ];

        $lower = strtolower( $word );
        if ( isset( $irregulars[ $lower ] ) ) {
            // Preserve original case for first letter.
            $plural = $irregulars[ $lower ];
            if ( ctype_upper( $word[0] ) ) {
                return ucfirst( $plural );
            }
            return $plural;
        }

        // Words ending in 's', 'x', 'z', 'ch', 'sh' -> add 'es'.
        if ( preg_match( '/(s|x|z|ch|sh)$/i', $word ) ) {
            return $word . 'es';
        }

        // Words ending in consonant + 'y' -> replace 'y' with 'ies'.
        if ( preg_match( '/[^aeiou]y$/i', $word ) ) {
            return substr( $word, 0, -1 ) . 'ies';
        }

        // Words ending in 'f' or 'fe' -> replace with 'ves'.
        if ( preg_match( '/f$/i', $word ) ) {
            return substr( $word, 0, -1 ) . 'ves';
        }
        if ( preg_match( '/fe$/i', $word ) ) {
            return substr( $word, 0, -2 ) . 'ves';
        }

        // Words ending in 'o' preceded by consonant -> add 'es' (with exceptions).
        // This rule has many exceptions, so we keep it simple.
        if ( preg_match( '/[^aeiou]o$/i', $word ) ) {
            return $word . 'es';
        }

        // Default: add 's'.
        return $word . 's';
    }
}

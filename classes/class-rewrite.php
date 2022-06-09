<?php
/**
 * WPSEO plugin file.
 *
 * @package WPSEO\Frontend
 */

/**
 * This code handles the category rewrites.
 */
class Yoast_SEO_Category_Pages_Rewrite {

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_filter( 'category_rewrite_rules', array( $this, 'category_rewrite_rules' ) );

	}


	/**
	 * This function taken and only slightly adapted from WP No Category Base plugin by Saurabh Gupta
	 *
	 * @return array
	 */
	public function category_rewrite_rules() {
		global $wp_rewrite;

		$category_rewrite = array();

		$taxonomy            = get_taxonomy( 'category' );
		$permalink_structure = get_option( 'permalink_structure' );

		$blog_prefix = '';
		if ( is_multisite() && ! is_subdomain_install() && is_main_site() && 0 === strpos( $permalink_structure, '/blog/' ) ) {
			$blog_prefix = 'blog/';
		}

		$categories = get_categories( array( 'hide_empty' => false ) );
		
		error_log( print_r($categories, true) ); // log
		
		if ( is_array( $categories ) && $categories !== array() ) {
			foreach ( $categories as $category ) {
				$category_nicename = $category->slug;
				if ( $category->parent == $category->cat_ID ) {
					// Recursive recursion.
					$category->parent = 0;
				}
				elseif ( $taxonomy->rewrite['hierarchical'] != 0 && $category->parent !== 0 ) {
						$parents = get_category_parents( $category->parent, false, '/', true );
					if ( ! is_wp_error( $parents ) ) {
						$category_nicename = $parents . $category_nicename;
					}
					unset( $parents );
				}

                if ( 'off' !== get_term_meta( $category->term_id, 'rewrite', true ) ) {
                
    				$category_rewrite = $this->add_category_rewrites( $category_rewrite, $category_nicename, $blog_prefix, $wp_rewrite->pagination_base );
    
    				// Adds rules for the uppercase encoded URIs.
    				$category_nicename_filtered = $this->convert_encoded_to_upper( $category_nicename );
    
    				if ( $category_nicename_filtered !== $category_nicename ) {
    					$category_rewrite = $this->add_category_rewrites( $category_rewrite, $category_nicename_filtered, $blog_prefix, $wp_rewrite->pagination_base );
    				}
                }

			}
			unset( $categories, $category, $category_nicename, $category_nicename_filtered );
		}

		// Redirect support from Old Category Base.
		$old_base                            = $wp_rewrite->get_category_permastruct();
		$old_base                            = str_replace( '%category%', '(.+)', $old_base );
		$old_base                            = trim( $old_base, '/' );
		$category_rewrite[ $old_base . '$' ] = 'index.php?wpseo_category_redirect=$matches[1]';

		return $category_rewrite;
	}

	/**
	 * Adds required category rewrites rules.
	 *
	 * @param array  $rewrites        The current set of rules.
	 * @param string $category_name   Category nicename.
	 * @param string $blog_prefix     Multisite blog prefix.
	 * @param string $pagination_base WP_Query pagination base.
	 *
	 * @return array The added set of rules.
	 */
	protected function add_category_rewrites( $rewrites, $category_name, $blog_prefix, $pagination_base ) {
		$rewrite_name = $blog_prefix . '(' . $category_name . ')';

		$rewrites[ $rewrite_name . '/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$' ]    = 'index.php?category_name=$matches[1]&feed=$matches[2]';
		$rewrites[ $rewrite_name . '/' . $pagination_base . '/?([0-9]{1,})/?$' ] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
		$rewrites[ $rewrite_name . '/?$' ]                                       = 'index.php?category_name=$matches[1]';

		return $rewrites;
	}


	/**
	 * Walks through category nicename and convert encoded parts
	 * into uppercase using $this->encode_to_upper().
	 *
	 * @param string $name The encoded category URI string.
	 *
	 * @return string The convered URI string.
	 */
	protected function convert_encoded_to_upper( $name ) {
		// Checks if name has any encoding in it.
		if ( strpos( $name, '%' ) === false ) {
			return $name;
		}

		$names = explode( '/', $name );
		$names = array_map( array( $this, 'encode_to_upper' ), $names );

		return implode( '/', $names );
	}

	/**
	 * Converts the encoded URI string to uppercase.
	 *
	 * @param string $encoded The encoded string.
	 *
	 * @return string The uppercased string.
	 */
	public function encode_to_upper( $encoded ) {
		if ( strpos( $encoded, '%' ) === false ) {
			return $encoded;
		}

		return strtoupper( $encoded );
	}

} /* End of class */

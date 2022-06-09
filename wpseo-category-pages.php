<?php
/**
 * Category Pages Yoast SEO plugin.
 *
 * @package WPSEO/Category Pages
 *
 * @wordpress-plugin
 * Plugin Name: Yoast SEO: Category Pages
 * Version:     9.2
 * Plugin URI:  
 * Description: This extension to Yoast SEO makes it possible to create custom category archive pages (content hubs).
 * Author:      Christophe de Jonge
 * Author URI:  https://studiomaanstof.nl
 * Depends:     Yoast SEO
 * Text Domain: yoast-category-pages
 * Domain Path: /languages/
 *
 */

if ( ! function_exists( 'add_filter' ) ) {
  header( 'Status: 403 Forbidden' );
  header( 'HTTP/1.1 403 Forbidden' );
  exit();
}

if ( file_exists( dirname( __FILE__ ) . '/classes/class-rewrite.php' ) ) {
  require dirname( __FILE__ ) . '/classes/class-rewrite.php';
}

/**
 * Class Yoast_SEO_Category_Pages
 */
class Yoast_SEO_Category_Pages {

  /**
   * Version of the plugin.
   *
   * @var string
   */
  const VERSION = '1.0.2';

  /**
   * Return the plugin file.
   *
   * @return string
   */
  public static function get_plugin_file() {
    return __FILE__;
  }

  /**
   * Class constructor.
   *
   * @since 1.0
   */
  public function __construct() {
    global $wp_version;

    if ( $this->check_dependencies( $wp_version ) ) {
      $this->initialize();
    }
  }

  /**
   * Checks the dependencies. Sets a notice when requirements aren't met.
   *
   * @param string $wp_version The current version of WordPress.
   *
   * @return bool True whether the dependencies are okay.
   */
  protected function check_dependencies( $wp_version ) {
    if ( ! version_compare( $wp_version, '4.8', '>=' ) ) {
      add_action( 'all_admin_notices', 'yoast_wpseo_category_pages_wordpress_upgrade_error' );

      return false;
    }

    $wordpress_seo_version = $this->get_wordpress_seo_version();

    // When WordPress SEO is not installed.
    if ( ! $wordpress_seo_version ) {
      add_action( 'all_admin_notices', 'yoast_wpseo_category_pages_missing_error' );

      return false;
    }

    // Make sure Yoast SEO is at least 8.1, including the RC versions.
    if ( ! version_compare( $wordpress_seo_version, '8.1-RC0', '>=' ) ) {
      add_action( 'all_admin_notices', 'yoast_wpseo_category_pages_upgrade_error' );

      return false;
    }

    return true;
  }

  /**
   * Returns the WordPress SEO version when set.
   *
   * @return bool|string The version whether it is set.
   */
  protected function get_wordpress_seo_version() {
    if ( ! defined( 'WPSEO_VERSION' ) ) {
      return false;
    }

    return WPSEO_VERSION;
  }


  /**
   * Initializes the plugin, basically hooks all the required functionality.
   *
   * @since 1.0
   *
   * @return void
   */
  protected function initialize() {

    if ( WPSEO_Options::get( 'stripcategorybase' ) === true ) {

      //$GLOBALS['wpseo_rewrite'] = new WPSEO_Rewrite();
      //add_filter( 'category_rewrite_rules', array( $this, 'category_rewrite_rules' ) );
      
      // Remove filter added by Yoast SEO
      remove_filter( 'category_rewrite_rules', array( $GLOBALS['wpseo_rewrite'], 'category_rewrite_rules' ) );

      // add_filter() called from class contructor
      $GLOBALS['yoast_seo_rewrite'] = new Yoast_SEO_Category_Pages_Rewrite();

      // Add field to the "category" taxonomy
      add_action( 'category_edit_form_fields', array( $this, 'category_taxonomy_rewrite_checkbox' ), 10, 2 );
      
      // Update the changes made on the "category" taxonomy
      add_action( 'edited_category', array( $this, 'update_category_rewrite_meta' ), 10, 2 );

    }

  }


  /**
   * Function to ouput checkbox.
   *
   * What if the term has a parent? (check message: $tag->parent != 0)
   *
   * @param WP_Term $tag      Current taxonomy term object.
   * @param string  $taxonomy Current taxonomy slug.
   */
  public function category_taxonomy_rewrite_checkbox( $tag, $taxonomy ) { 
    ?>
    <tr class="form-field term-rewrite-wrap">
      <th scope="row"><label for="rewrite"><?php _e( 'Routing', 'yoast-category-pages' ); ?></label></th>
      <td>
        <?php
            $current = get_term_meta( $tag->term_id, 'rewrite', true ) ? : 'on';
            $this->echo_checkbox( 'rewrite', 'rewrite', 'Create rewrite rule for this category', $current );
        ?>
        <p class="description">
        <?php
            esc_html_e( 'This is an advanced option to disable sending the URL to the category archive.', 'yoast-category-pages' );
        ?>
        <?php
            $title = $this->get_post_title_by_post_name( $tag->slug );
            if ( $title ) {
              printf(
                /* translators: %1$s resolves to a URL, %2$s resolves to post or page title */
                __( 'When disabled, the URL <code>%1$s</code> will be routed to the post or page <code>%2$s</code>.', 'yoast-category-pages' ),
                home_url( $tag->slug . '/' ),
                $this->get_post_title_by_post_name( $tag->slug )
              );
            } else {
              printf(
                /* translators: %1$s resolves to a URL */
                __( 'No post or page seems to exist at the URL <code>%1$s</code>. WordPress will probably still route the URL to the category archive', 'yoast-category-pages' ),
                home_url( $tag->slug . '/' )
              );
            }
        ?>
        </p>
      </td>
    </tr>
    <?php
  }


  /**
   * Simple helper function to show the checkbox.
   *
   * @param string $id    The ID and option name for the checkbox.
   * @param string $label The label for the checkbox.
   */
  public function echo_checkbox( $id, $name, $label, $current ) {

    echo '<input class="checkbox" type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="on" ' . checked( $current, 'on', false ) . '> ';
    echo '<label for="' . esc_attr( $id ) . '" class="checkbox">' . esc_html( $label ) . '</label> ';
  }


  /**
   * Update category rewrite meta.
   *
   * @param int $term_id Term ID.
     * @param int $tt_id   Term taxonomy ID.
     */
    public function update_category_rewrite_meta( $term_id, $tt_id ) {

        update_term_meta( $term_id, 'rewrite', isset( $_POST['rewrite'] ) ? 'on' : 'off' );
        // Or delete_term_meta( $term_id, 'rewrite' ) when !isset( $_POST['rewrite'] )
    }


  /**
   * Helper function to get title for existing posts from slug.
   *
   * @param string        $slug      The slug to check for.
   * @param string|array  $post_type The post types to check.
   */
    public function get_post_title_by_post_name( $slug = '', $post_type = array( 'page', 'post' ) ) {
        //Make sure that we have values set for $slug and $post_type
        if ( !$slug || !$post_type )
            return false;
    
        // We will not sanitize the input as get_page_by_path() will handle that
        $post_object = get_page_by_path( $slug, OBJECT, $post_type );
    
        if ( !$post_object )
            return false;
    
        return apply_filters( 'the_title', $post_object->post_title );
    }

}


/**
 * Throw an error if WordPress SEO is not installed.
 *
 * @since 1.0
 */
function yoast_wpseo_category_pages_missing_error() {
  echo '<div class="error"><p>';
  printf(
    /* translators: %1$s resolves to the plugin search for Yoast SEO, %2$s resolves to the closing tag, %3$s resolves to Yoast SEO, %4$s resolves to Yoast Category Pages */
    esc_html__( 'Please %1$sinstall &amp; activate %3$s%2$s and then enable its "Remove Category Base Slug" functionality to allow the %4$s module to work.', 'yoast-woo-seo' ),
    '<a href="' . esc_url( admin_url( 'plugin-install.php?tab=search&type=term&s=yoast+seo&plugin-search-input=Search+Plugins' ) ) . '">',
    '</a>',
    'Yoast SEO',
    'Yoast Category Pages'
  );
  echo '</p></div>';
}

/**
 * Throw an error if WordPress is out of date.
 *
 * @since 1.0
 */
function yoast_wpseo_category_pages_wordpress_upgrade_error() {
  echo '<div class="error"><p>';
  printf(
    /* translators: %1$s resolves to Yoast Category Pages */
    esc_html__( 'Please upgrade WordPress to the latest version to allow WordPress and the %1$s module to work properly.', 'yoast-category-pages' ),
    'Yoast Category Pages'
  );
  echo '</p></div>';
}

/**
 * Throw an error if WordPress SEO is out of date.
 *
 * @since 1.0
 */
function yoast_wpseo_category_pages_upgrade_error() {
  echo '<div class="error"><p>';
  printf(
    /* translators: %1$s resolves to Yoast SEO, %2$s resolves to Yoast Category Pages */
    esc_html__( 'Please upgrade the %1$s plugin to the latest version to allow the %2$s module to work.', 'yoast-category-pages' ),
    'Yoast SEO',
    'Yoast Category Pages'
  );
  echo '</p></div>';
}


/**
 * Initializes the plugin class, to make sure all the required functionality is loaded, do this after plugins_loaded.
 *
 * @since 1.0
 *
 * @return void
 */
function initialize_yoast_wpseo_category_pages() {
  global $yoast_wpseo_category_pages;

  load_plugin_textdomain( 'yoast-category-pages', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

  // Initializes the plugin.
  $yoast_wpseo_category_pages = new Yoast_SEO_Category_Pages();
}

if ( ! wp_installing() ) {
  add_action( 'plugins_loaded', 'initialize_yoast_wpseo_category_pages', 30 );
}

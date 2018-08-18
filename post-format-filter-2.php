<?php
/**
 * Plugin Name: Post Format Filter 2
 * Description: Adds a Post Format filter to the WP Admin Posts Screen.
 * Author: Jan Boddez
 * Author URI: https://janboddez.be/
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Version: 0.2
 * Text Domain: post-format-filter-2
 * Plugin URI: https://janboddez.be/post-format-filter-2/ 
 * GitHub Plugin URI: https://github.com/janboddez/jb-twikey-gateway-for-woocommerce
 */

/* Prevents this script from being loaded directly. */
defined( 'ABSPATH' ) or exit;

/**
 * Main plugin class.
 *
 * @since 0.1
 */
class Post_Format_Filter {
	/**
	 * Post types that support post formats. set by @see
	 * Post_Format_Filter->admin_init().
	 *
	 * @since 0.1
	 *
	 * @var array $post_types Post types that support post formats..
	 */
	private $post_types = array();

	/**
	 * Supported post formats (for the current post type). Set by @see
	 * Post_Format_Filter->parse_query().
	 *
	 * @since 0.1
	 *
	 * @var array $post_formats Supported post formats.
	 */
	private $post_formats = array();

	/**
	 * Registers the main callback function.
	 *
	 * @since 0.1
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ), 99 );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Enables i18n of this plugin.
	 *
	 * @since 0.1
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'post-format-filter-2', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Registers relevant action hooks, sets post types that support post
	 * formats.
	 *
	 * @since 0.1
	 */
	public function admin_init() {
		add_action( 'parse_query', array( $this, 'parse_query' ) );
		add_action( 'restrict_manage_posts', array( $this, 'restrict_manage_posts' ) );

		$post_types = get_post_types(); // Get all registered post types.

		foreach ( $post_types as $slug => $name ) {
			if ( post_type_supports( $slug, 'post-formats' ) ) {
				$this->post_types[] = $slug;
			}
		}
	}

	/**
	 * Sets the available post formats, and adds the ability the filter out
	 * 'standard' post formats.
	 *
	 * @since 0.1
	 *
	 * @param WP_Query $query The query object that parsed the query.
	 */
	public function parse_query( $query ) {
		if ( ! $this->is_supported_posts_screen() ) {
			return;
		}

		$this->post_formats = get_post_format_strings();

		/* WordPress on its own will never show only 'standard' posts, hence below  workaround. */
		if ( isset( $query->query_vars['post_format'] ) && 'post-format-standard' === $query->query_vars['post_format'] ) {
			/* Remove the 'post_format' query_var ... */
			unset( $query->query_vars['post_format'] );

			$terms = array_keys( $this->post_formats );
			array_shift( $terms );

			foreach ( $terms as $index => $term ) {
				$terms[ $index ] = 'post-format-' . $term;
			}

			/* ... and add our own custom 'taxonomy query'. */
			$tax_query = array( 
				'taxonomy' => 'post_format',
				'field' => 'slug',
				'terms' => $terms,
				'operator' => 'NOT IN',
				'include_children' => 1,
			);
			set_query_var( 'tax_query', array( $tax_query ) );
		}
	}

	/**
	 * Adds the post filter dropdown to relevant admin screens.
	 *
	 * @since 0.1
	 */
	public function restrict_manage_posts() {
		if ( $this->is_supported_posts_screen() ) {
			$format = '';

			if ( ! empty( $_GET['post_format'] ) ) {
				$format = $_GET['post_format'];
			}
			?>
			<select name="post_format" id="post_format">
				<option value=""><?php _e( 'All post formats', 'post-format-filter-2' ); ?></option>
				<?php foreach ( $this->post_formats as $slug => $name ) : ?>
				<option value="<?php echo $slug; ?>" <?php selected( $format === $slug ); ?>><?php _e( $name ); ?></option>
				<?php endforeach;?>
			</select>
			<?php
		}
	}

	/**
	 * Checks if the currently displayed admin screen supports post formats.
	 *
	 * @return bool If the current admin screen supports post formats.
	 *
	 * @since 0.1
	 */
	private function is_supported_posts_screen() {
		$current_screen = get_current_screen();

		if ( ! is_null( $current_screen ) && 'edit' === $current_screen->base && in_array( $current_screen->post_type, $this->post_types ) ) {
			return true;
		}

		return false;
	}
}

new Post_Format_Filter();

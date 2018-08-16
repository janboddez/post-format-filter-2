<?php
/**
 * Plugin Name: Post Format Filter 2
 * Author: Jan Boddez
 * Author URI: https://janboddez.be/
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Version: 0.1
 * Text Domain: post-format-filter-2
 */

/* Prevents this script from being loaded directly. */
defined( 'ABSPATH' ) or exit;

/**
 * Main plugin class.
 */
class Post_Format_Filter {
	/**
	 * Post types that support post formats. set by @see
	 * Post_Format_Filter->admin_init().
	 *
	 * @var array $post_types Post types that support post formats..
	 */
	private $post_types = array();

	/**
	 * Supported post formats (for the current post type). Set by @see
	 * Post_Format_Filter->parse_query().
	 *
	 * @var array $post_formats Supported post formats.
	 */
	private $post_formats = array();

	/**
	 * Registers the main callback function.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ), 99 );
	}

	/**
	 * Registers relevant action hooks, sets post types that support post
	 * formats.
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
	 * On relevant admin_screens, forces filtering by post format by defining a
	 * new `tax_query` query var, and sets the available post formats.
	 *
	 * Triggered after WP_Query->parse_query() has set up query variables.
	 *
	 * @param WP_Query $query The query object that parsed the query.
	 */
	public function parse_query( $query ) {
		if ( ! $this->is_supported_posts_screen() ) {
			// Post formats not relevant or not supported.
			return;
		}

		$this->post_formats = get_post_format_strings(); // All possible post formats WordPress supports.
		array_shift( $this->post_formats );
		$this->post_formats = apply_filters( 'pff_post_formats', $this->post_formats ); // Not changing filter name for backwards compatibility.

		$format = $this->get_format(); // The currently displayed post format. (Slug, or empty string if none.)

		if ( empty( $format ) ) {
			return;
		}

		$tax_query = array( 
			'taxonomy' => 'post_format', 
			'terms' => array( 'post-format-' . $format ), 
			'field' => 'slug', 
			'operator' => 'IN',
			'include_children' => 1,
		);
		set_query_var( 'tax_query', array( $tax_query ) );
	}

	/**
	 * Adds the post filter dropdowm to relevant admin screens.
	 */
	public function restrict_manage_posts() {
		if ( $this->is_supported_posts_screen() ) {
			?>
			<select name="post_format_filter" id="post_format_filter">
				<option value=""> <?php _e( 'Show all post formats', 'post-format-filter-2' ); ?> </option>
				<?php foreach ( $this->post_formats as $slug => $name ) : ?>
				<option value="<?php echo $slug; ?>" <?php selected( $this->get_format() === $slug ); ?>><?php _e( $name ); ?></option>
				<?php endforeach;?>
			</select>
			<?php
		}
	}

	/**
	 * Returns the currently displayed post format.
	 *
	 * @return string Slug of the currently displayed post format, if any. Empty string otherwise.
	 */
	private function get_format() {
		if ( ! empty( $_GET['post_format_filter'] ) && array_key_exists( $_GET['post_format_filter'], $this->post_formats ) ) {
			return  $_GET['post_format_filter'];
		}

		return '';
	}

	/**
	 * Checks if the currently displayed admin screen supports post formats.
	 *
	 * @return bool If the current admin screen supports post formats.
	 */
	private function is_supported_posts_screen() {
		$current_screen = get_current_screen();

		if ( null !== $current_screen && ( 'edit' === $current_screen->base /* || 'edit' === $current_screen->parent_base */ ) && in_array( $current_screen->post_type, $this->post_types ) ) {
			return true;
		}

		return false;
	}
}

new Post_Format_Filter();

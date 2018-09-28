<?php
/**
 * Plugin Name: Post Format Filter 2
 * Description: Adds a Post Format filter to the WP Admin Posts Screen.
 * Author: Jan Boddez
 * Author URI: https://janboddez.be/
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Version: 0.3.1
 * Text Domain: post-format-filter-2
 * Plugin URI: https://janboddez.be/post-format-filter-2/
 * GitHub Plugin URI: https://github.com/janboddez/post-format-filter-2
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

				add_filter( 'manage_' . $slug . '_posts_columns', array( $this, 'add_post_format_column' ) );
				add_filter( 'manage_edit-' . $slug . '_sortable_columns', array( $this, 'make_post_format_column_sortable' ) );
				add_action( 'manage_' . $slug . '_posts_custom_column', array( $this, 'post_format_column' ), 10, 2);
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
	 * Adds the post format filter to relevant admin screens.
	 *
	 * @since 0.1
	 */
	public function restrict_manage_posts() {
		if ( $this->is_supported_posts_screen() ) {
			$post_format = '';

			if ( ! empty( $_GET['post_format'] ) ) {
				$post_format = $_GET['post_format'];
			}
			?>
			<select name="post_format" id="post_format">
				<option value=""><?php _e( 'All post formats', 'post-format-filter-2' ); ?></option>
				<?php foreach ( $this->post_formats as $slug => $name ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $post_format, $slug ); ?>><?php echo esc_html( $name ); ?></option>
				<?php endforeach;?>
			</select>
			<?php
		}
	}

	/**
	 * Adds a post format column to relevant admin screens.
	 *
	 * @since 0.3
	 */
	public function add_post_format_column( $columns ) {
		$columns['post_format'] = __( 'Post Format', 'post-format-filter-2' );
		return $columns;
	}

	/**
	 * Makes the post format column sortable.
	 *
	 * @since 0.3
	 */
	public function make_post_format_column_sortable( $columns ) {
		$columns['post_format'] = 'title';
		return $columns;
	}

	/**
	 * Displays the post format column.
	 *
	 * @since 0.3
	 */
	public function post_format_column( $column, $post_id ) {
		if ( 'post_format' === $column ) {
			$post_format = get_post_format( $post_id );
			$post_type = get_post_type( $post_id );

			if ( false !== $post_format ) {
				echo '<a href="' . esc_url( admin_url( 'edit.php?post_format=' . $post_format . '&post_type=' . $post_type ) ) . '">' . esc_html( ucwords( $post_format ) ) . '</a>';
			} else {
				echo '<a href="' . esc_url( admin_url( 'edit.php?post_format=standard&post_type=' . $post_type ) ) . '">' . __( 'Standard' ) . '</a>';
			}
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

<?php
/**
 * Created by PhpStorm.
 * User: themeum
 * Date: 24/9/18
 * Time: 12:03 PM
 */

use TUTOR\Input;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for displaying a list of items in an ajaxified HTML table.
 *
 * @since 1.1.0
 * @access private
 */
class Tutor_List_Table {

	/**
	 * The current list of items.
	 *
	 * @since 1.1.0
	 * @var array
	 */
	public $items;

	/**
	 * Various information about the current table.
	 *
	 * @since 1.1.0
	 * @var array
	 */
	protected $_args;

	/**
	 * Various information needed for displaying the pagination.
	 *
	 * @since 1.1.0
	 * @var array
	 */
	protected $_pagination_args = array();

	/**
	 * The current screen.
	 *
	 * @since 3.1.0
	 * @var object
	 */
	protected $screen;

	/**
	 * Cached bulk actions.
	 *
	 * @since 1.1.0
	 * @var array
	 */
	private $_actions;

	/**
	 * Cached pagination output.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private $_pagination;

	/**
	 * The view switcher modes.
	 *
	 * @since 1.1.0
	 * @var array
	 */
	protected $modes = array();

	/**
	 * Stores the value returned by ->get_column_info().
	 *
	 * @since 1.1.0
	 * @var array
	 */
	protected $_column_headers;

	/**
	 * {@internal Missing Summary}
	 *
	 * @var array
	 */
	protected $compat_fields = array( '_args', '_pagination_args', 'screen', '_actions', '_pagination' );

	/**
	 * {@internal Missing Summary}
	 *
	 * @var array
	 */
	protected $compat_methods = array(
		'set_pagination_args',
		'get_views',
		'get_bulk_actions',
		'bulk_actions',
		'row_actions',
		'months_dropdown',
		'view_switcher',
		'comments_bubble',
		'get_items_per_page',
		'pagination',
		'get_sortable_columns',
		'get_column_info',
		'get_table_classes',
		'display_tablenav',
		'extra_tablenav',
		'single_row_columns',
	);

	/**
	 * Constructor.
	 *
	 * The child class should call this constructor from its own constructor to override
	 * the default $args.
	 *
	 * @since 1.1.0
	 *
	 * @param array|string $args {
	 *     Array or string of arguments.
	 *
	 *     @type string $plural   Plural value used for labels and the objects being listed.
	 *                            This affects things such as CSS class-names and nonces used
	 *                            in the list table, e.g. 'posts'. Default empty.
	 *     @type string $singular Singular label for an object being listed, e.g. 'post'.
	 *                            Default empty
	 *     @type bool   $ajax     Whether the list table supports Ajax. This includes loading
	 *                            and sorting data, for example. If true, the class will call
	 *                            the _js_vars() method in the footer to provide variables
	 *                            to any scripts handling Ajax events. Default false.
	 *     @type string $screen   String containing the hook name used to determine the current
	 *                            screen. If left null, the current screen will be automatically set.
	 *                            Default null.
	 * }
	 */
	public function __construct( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'plural'   => '',
				'singular' => '',
				'ajax'     => false,
				'screen'   => null,
			)
		);

		$this->screen = convert_to_screen( $args['screen'] );

		add_filter( "manage_{$this->screen->id}_columns", array( $this, 'get_columns' ), 0 );

		if ( ! $args['plural'] ) {
			$args['plural'] = $this->screen->base;
		}

		$args['plural']   = sanitize_key( $args['plural'] );
		$args['singular'] = sanitize_key( $args['singular'] );

		$this->_args = $args;

		if ( $args['ajax'] ) {
			// wp_enqueue_script( 'list-table' );
			add_action( 'admin_footer', array( $this, '_js_vars' ) );
		}

		if ( empty( $this->modes ) ) {
			$this->modes = array(
				'list'    => __( 'List View' ),
				'excerpt' => __( 'Excerpt View' ),
			);
		}
	}

	/**
	 * Make private properties readable for backward compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Property to get.
	 * @return mixed Property.
	 */
	public function __get( $name ) {
		if ( in_array( $name, $this->compat_fields ) ) {
			return $this->$name;
		}
	}

	/**
	 * Make private properties settable for backward compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name  Property to check if set.
	 * @param mixed  $value Property value.
	 * @return mixed Newly-set property.
	 */
	public function __set( $name, $value ) {
		if ( in_array( $name, $this->compat_fields ) ) {
			return $this->$name = $value;
		}
	}

	/**
	 * Make private properties checkable for backward compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Property to check if set.
	 * @return bool Whether the property is set.
	 */
	public function __isset( $name ) {
		if ( in_array( $name, $this->compat_fields ) ) {
			return isset( $this->$name );
		}
	}

	/**
	 * Make private properties un-settable for backward compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Property to unset.
	 */
	public function __unset( $name ) {
		if ( in_array( $name, $this->compat_fields ) ) {
			unset( $this->$name );
		}
	}

	/**
	 * Make private/protected methods readable for backward compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param callable $name      Method to call.
	 * @param array    $arguments Arguments to pass when calling.
	 * @return mixed|bool Return value of the callback, false otherwise.
	 */
	public function __call( $name, $arguments ) {
		if ( in_array( $name, $this->compat_methods ) ) {
			return call_user_func_array( array( $this, $name ), $arguments );
		}
		return false;
	}

	/**
	 * Checks the current user's permissions
	 *
	 * @since 1.1.0
	 * @abstract
	 */
	public function ajax_user_can() {
		die( 'function Tutor_List_Table::ajax_user_can() must be over-ridden in a sub-class.' );
	}

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @uses Tutor_List_Table::set_pagination_args()
	 *
	 * @since 1.1.0
	 * @abstract
	 */
	public function prepare_items() {
		die( 'function Tutor_List_Table::prepare_items() must be over-ridden in a sub-class.' );
	}

	/**
	 * An internal method that sets all the necessary pagination arguments
	 *
	 * @since 1.1.0
	 *
	 * @param array|string $args Array or string of arguments with information about the pagination.
	 */
	protected function set_pagination_args( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'total_items' => 0,
				'total_pages' => 0,
				'per_page'    => 0,
			)
		);

		if ( ! $args['total_pages'] && $args['per_page'] > 0 ) {
			$args['total_pages'] = ceil( $args['total_items'] / $args['per_page'] );
		}

		// Redirect if page number is invalid and headers are not already sent.
		if ( ! headers_sent() && ! wp_doing_ajax() && $args['total_pages'] > 0 && $this->get_pagenum() > $args['total_pages'] ) {
			wp_redirect( add_query_arg( 'paged', $args['total_pages'] ) );
			exit;
		}

		$this->_pagination_args = $args;
	}

	/**
	 * Access the pagination args.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key Pagination argument to retrieve. Common values include 'total_items',
	 *                    'total_pages', 'per_page', or 'infinite_scroll'.
	 * @return int Number of items that correspond to the given pagination argument.
	 */
	public function get_pagination_arg( $key ) {
		if ( 'page' === $key ) {
			return $this->get_pagenum();
		}

		if ( isset( $this->_pagination_args[ $key ] ) ) {
			return $this->_pagination_args[ $key ];
		}
	}

	/**
	 * Whether the table has items to display or not
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function has_items() {
		return ! empty( $this->items );
	}

	/**
	 * Message to be displayed when there are no items
	 *
	 * @since 1.1.0
	 */
	public function no_items() {
		_e( 'No items found.' );
	}

	/**
	 * Displays the search box.
	 *
	 * @param string $text     The 'submit' button label.
	 * @param string $input_id ID attribute value for the search input field.
	 * @since 1.1.0
	 * @deprecated 2.0.0
	 */
	public function search_box( $text, $input_id ) {
		if ( empty( sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) ) ) && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		$order          = sanitize_text_field( wp_unslash( $_REQUEST['order'] ?? '' ) );
		$order_by       = sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ?? '' ) );
		$post_mime_type = sanitize_text_field( wp_unslash( $_REQUEST['post_mime_type'] ?? '' ) );
		$detached       = sanitize_text_field( wp_unslash( $_REQUEST['detached'] ) ?? '' );

		if ( ! empty( $order_by ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $order_by ) . '" />';
		}
		if ( ! empty( $order ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( $order ) . '" />';
		}
		if ( ! empty( $post_mime_type ) ) {
			echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( $post_mime_type ) . '" />';
		}
		if ( ! empty( $detached ) ) {
			echo '<input type="hidden" name="detached" value="' . esc_attr( $detached ) . '" />';
		}
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_attr( $text ); ?>:</label>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" />
			<?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
		</p>
		<?php
	}

	/**
	 * Get course list
	 *
	 * @param $selected selected text.
	 * @since 1.8.0
	 * @deprecated 2.0.0
	 */
	public function course_dropdown( $selected = '' ) {
		$courses = ( current_user_can( 'administrator' ) ) ? tutils()->get_courses() : tutils()->get_courses_by_instructor();
		$markup  = '
			<div class="alignright">
				<label>' . __( 'Course', 'tutor-pro' ) . '</label>
				<select class="tutor-assignment-course-sorting">
					<option value="0">' . __( 'All', 'tutor' ) . '</option>
					OPTIONS_PLACEHOLDER
				</select>
			</div>
			';
		$options = '';
		foreach ( $courses as $course ) {
			$options .= '<option value="' . esc_attr( $course->ID ) . '" ' . selected( $selected, $course->ID, false ) . '> ' . esc_attr( $course->post_title ) . ' </option>';
		}

		$content = str_replace( 'OPTIONS_PLACEHOLDER', $options, $markup );
		$allowed = array(
			'div'    => array( 'class' => true ),
			'label'  => array(),
			'select' => array( 'class' => true ),
			'option' => array(
				'value'    => true,
				'selected' => true,
			),
		);
		echo wp_kses( $content, $allowed );
	}

	/**
	 * get sort by param
	 *
	 * @param string $selected selected value.
	 * @since 1.8.0
	 * @deprecated 2.0.0
	 */

	public function sorting_order( $selected = 'DESC' ) {
		$orders  = array( 'DESC', 'ASC' );
		$markup  = '
			<div class="alignright">
				<label>' . __( 'Sort By', 'tutor' ) . '</label>
				<select class="tutor-assignment-order-sorting">
					OPTION_PLACEHOLDER
				</select>
			</div>
			';
		$options = '';
		foreach ( $orders as $order ) {
			$options .= '<option value="' . $order . '" ' . selected( $selected, $order, false ) . '> ' . __( $order, 'tutor' ) . ' </option>';
		}
		$content = str_replace( 'OPTION_PLACEHOLDER', $options, $markup );
		$allowed = array(
			'div'    => array( 'class' => true ),
			'label'  => array(),
			'select' => array( 'class' => true ),
			'option' => array(
				'value'    => true,
				'selected' => true,
			),
		);
		echo wp_kses( $content, $allowed );
	}

	/**
	 * get sort by param
	 *
	 * @param string $selected selected text.
	 * @since 1.8.0
	 * @deprecated 2.0.0
	 */
	public function sorting_date( $selected = '' ) {
		$placeholder = __( get_option( 'date_format' ), 'tutor' );
		$date_filter = Input::get( 'date', '' );
		$date_input  = '' !== $date_filter ? tutor_get_formated_date( get_option( 'date_format' ), $date_filter ) : '';
		$markup      = '
			<div class="alignright assignment-date-box">
				<label>' . __( 'Date', 'tutor' ) . '</label>
				<input type="" class="tutor_date_picker tutor-assignment-date-sorting" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( $date_input ) . '">
				<i class="tutor-icon-calendar"></i>
			</div>
			';
		$allowed     = array(
			'div'   => array( 'class' => true ),
			'label' => array(),
			'i'     => array( 'class' => true ),
			'input' => array(
				'type'        => true,
				'class'       => true,
				'placeholder' => true,
				'value'       => true,
			),
		);
		echo wp_kses( $markup, $allowed );
	}

	/**
	 * Get an associative array ( id => link ) with the list
	 * of views available on this table.
	 *
	 * @since 3.1.0
	 *
	 * @return array
	 */
	protected function get_views() {
		return array();
	}

	/**
	 * Display the list of views available on this table.
	 *
	 * @since 1.1.0
	 * @deprecated 2.0.0
	 */
	public function views() {
		$views = $this->get_views();
		/**
		 * Filters the list of available list table views.
		 *
		 * The dynamic portion of the hook name, `$this->screen->id`, refers
		 * to the ID of the current screen, usually a string.
		 *
		 * @since 1.5.0
		 *
		 * @param array $views An array of available list table views.
		 */
		$views = apply_filters( "views_{$this->screen->id}", $views );

		if ( empty( $views ) ) {
			return;
		}

		$this->screen->render_screen_reader_content( 'heading_views' );

		$html_content = '<ul class="subsubsub">';
		foreach ( $views as $class => $view ) {
			$views[ $class ] = "\t<li class='$class'>$view";
		}
		$html_content .= implode( ' |</li>', $views ) . '</li>';
		$html_content .= '</ul>';

		$allowed = array(
			'ul' => array( 'class' => true ),
			'li' => array( 'class' => true ),
		);

		echo wp_kses( $html_content, $allowed );
	}

	/**
	 * Get an associative array ( option_name => option_title ) with the list
	 * of bulk actions available on this table.
	 *
	 * @since 1.1.0
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array();
	}

	/**
	 * Display the bulk actions dropdown.
	 *
	 * @since 1.1.0
	 *
	 * @param string $which The location of the bulk actions: 'top' or 'bottom'.
	 *                      This is designated as optional for backward compatibility.
	 */
	protected function bulk_actions( $which = '' ) {
		if ( is_null( $this->_actions ) ) {
			$this->_actions = $this->get_bulk_actions();
			/**
			 * Filters the list table Bulk Actions drop-down.
			 *
			 * The dynamic portion of the hook name, `$this->screen->id`, refers
			 * to the ID of the current screen, usually a string.
			 *
			 * This filter can currently only be used to remove bulk actions.
			 *
			 * @since 3.5.0
			 *
			 * @param array $actions An array of the available bulk actions.
			 */
			$this->_actions = apply_filters( "bulk_actions-{$this->screen->id}", $this->_actions );
			$two            = '';
		} else {
			$two = '2';
		}

		if ( empty( $this->_actions ) ) {
			return;
		}

		echo '<label for="bulk-action-selector-' . esc_attr( $which ) . '" class="screen-reader-text">' . __( 'Select bulk action' ) . '</label>';
		echo '<select name="action' . $two . '" id="bulk-action-selector-' . esc_attr( $which ) . '">';
		echo '<option value="-1">' . __( 'Bulk Actions', 'tutor' ) . '</option>';

		foreach ( $this->_actions as $name => $title ) {
			$class = 'edit' === $name ? ' class="hide-if-no-js"' : '';

			echo '\t' . '<option value="' . $name . '"' . $class . '>' . $title . '</option>';
		}

		echo '</select>';

		submit_button( __( 'Apply' ), 'action', '', false, array( 'id' => 'doaction' . $two ) );
		echo '';
	}

	/**
	 * Get the current action selected from the bulk actions dropdown.
	 *
	 * @since 1.1.0
	 *
	 * @return string|false The action name or False if no action was selected
	 */
	public function current_action() {
		if ( isset( $_REQUEST['filter_action'] ) && ! empty( $_REQUEST['filter_action'] ) ) {
			return false;
		}

		if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ) {
			return $_REQUEST['action'];
		}

		if ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ) {
			return $_REQUEST['action2'];
		}

		return false;
	}

	/**
	 * Generate row actions div
	 *
	 * @since 1.1.0
	 *
	 * @param array $actions The list of actions
	 * @param bool  $always_visible Whether the actions should be always visible
	 * @return string
	 */
	protected function row_actions( $actions, $always_visible = false ) {
		$action_count = count( $actions );
		$i            = 0;

		if ( ! $action_count ) {
			return '';
		}

		$out = '<div class="' . esc_attr( $always_visible ? 'row-actions visible' : 'row-actions' ) . '">';
		foreach ( $actions as $action => $link ) {
			++$i;
			( $i == $action_count ) ? $sep = '' : $sep = ' | ';
			$out                          .= '<span class="' . $action . '">' . $link . $sep . '</span>';
		}
		$out .= '</div>';

		$out .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details' ) . '</span></button>';

		return $out;

	}

	/**
	 * Display a monthly dropdown for filtering items
	 *
	 * @since 1.1.0
	 *
	 * @global wpdb      $wpdb
	 * @global WP_Locale $wp_locale
	 *
	 * @param string $post_type
	 */
	protected function months_dropdown( $post_type ) {
		global $wpdb, $wp_locale;

		/**
		 * Filters whether to remove the 'Months' drop-down from the post list table.
		 *
		 * @since 4.2.0
		 *
		 * @param bool   $disable   Whether to disable the drop-down. Default false.
		 * @param string $post_type The post type.
		 */
		if ( apply_filters( 'disable_months_dropdown', false, $post_type ) ) {
			return;
		}

		$extra_checks = "AND post_status != 'auto-draft'";
		if ( ! Input::has( 'post_status' ) || 'trash' !== Input::get( 'post_status' ) ) {
			$extra_checks .= " AND post_status != 'trash'";
		} elseif ( Input::has( 'post_status' ) ) {
			$extra_checks = $wpdb->prepare( ' AND post_status = %s', Input::get( 'post_status' ) );
		}

		$months = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
			FROM $wpdb->posts
			WHERE post_type = %s
			$extra_checks
			ORDER BY post_date DESC
		",
				$post_type
			)
		);

		/**
		 * Filters the 'Months' drop-down results.
		 *
		 * @since 1.7.0
		 *
		 * @param object $months    The months drop-down query results.
		 * @param string $post_type The post type.
		 */
		$months = apply_filters( 'months_dropdown_results', $months, $post_type );

		$month_count = count( $months );

		if ( ! $month_count || ( 1 == $month_count && 0 == $months[0]->month ) ) {
			return;
		}

		$m = Input::get( 'm', 0, Input::TYPE_INT );
		?>
		<label for="filter-by-date" class="screen-reader-text"><?php _e( 'Filter by date' ); ?></label>
		<select name="m" id="filter-by-date">
			<option<?php selected( $m, 0 ); ?> value="0"><?php _e( 'All dates' ); ?></option>
			<?php
			foreach ( $months as $arc_row ) {
				if ( 0 == $arc_row->year ) {
					continue;
				}

				$month = zeroise( $arc_row->month, 2 );
				$year  = $arc_row->year;

				printf(
					"<option %s value='%s'>%s</option>",
					selected( $m, $year . $month, false ),
					esc_attr( $arc_row->year . $month ),
					/* translators: 1: month name, 2: 4-digit year */
					sprintf( __( '%1$s %2$d' ), $wp_locale->get_month( $month ), $year )
				);
			}
			?>
		</select>
		<?php
	}

	/**
	 * Display a view switcher
	 *
	 * @since 1.1.0
	 *
	 * @param string $current_mode
	 */
	protected function view_switcher( $current_mode ) {
		?>
		<input type="hidden" name="mode" value="<?php echo esc_attr( $current_mode ); ?>" />
		<div class="view-switch">
			<?php
			foreach ( $this->modes as $mode => $title ) {
				$classes = array( 'view-' . $mode );
				if ( $current_mode === $mode ) {
					$classes[] = 'current';
				}
				printf(
					"<a href='%s' class='%s' id='view-switch-$mode'><span class='screen-reader-text'>%s</span></a>",
					esc_url( add_query_arg( 'mode', $mode ) ),
					implode( ' ', $classes ),
					$title
				);
			}
			?>
		</div>
		<?php
	}

	/**
	 * Display a comment count bubble
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id          The post ID.
	 * @param int $pending_comments Number of pending comments.
	 */
	protected function comments_bubble( $post_id, $pending_comments ) {
		$approved_comments = get_comments_number();

		$approved_comments_number = number_format_i18n( $approved_comments );
		$pending_comments_number  = number_format_i18n( $pending_comments );

		$approved_only_phrase = sprintf( _n( '%s comment', '%s comments', $approved_comments ), $approved_comments_number );
		$approved_phrase      = sprintf( _n( '%s approved comment', '%s approved comments', $approved_comments ), $approved_comments_number );
		$pending_phrase       = sprintf( _n( '%s pending comment', '%s pending comments', $pending_comments ), $pending_comments_number );

		// No comments at all.
		if ( ! $approved_comments && ! $pending_comments ) {
			printf(
				'<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">%s</span>',
				__( 'No comments' )
			);
			// Approved comments have different display depending on some conditions.
		} elseif ( $approved_comments ) {
			printf(
				'<a href="%s" class="post-com-count post-com-count-approved"><span class="comment-count-approved" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></a>',
				esc_url(
					add_query_arg(
						array(
							'p'              => $post_id,
							'comment_status' => 'approved',
						),
						admin_url( 'edit-comments.php' )
					)
				),
				$approved_comments_number,
				$pending_comments ? $approved_phrase : $approved_only_phrase
			);
		} else {
			printf(
				'<span class="post-com-count post-com-count-no-comments"><span class="comment-count comment-count-no-comments" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></span>',
				$approved_comments_number,
				$pending_comments ? __( 'No approved comments' ) : __( 'No comments' )
			);
		}

		if ( $pending_comments ) {
			printf(
				'<a href="%s" class="post-com-count post-com-count-pending"><span class="comment-count-pending" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></a>',
				esc_url(
					add_query_arg(
						array(
							'p'              => $post_id,
							'comment_status' => 'moderated',
						),
						admin_url( 'edit-comments.php' )
					)
				),
				$pending_comments_number,
				$pending_phrase
			);
		} else {
			printf(
				'<span class="post-com-count post-com-count-pending post-com-count-no-pending"><span class="comment-count comment-count-no-pending" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></span>',
				$pending_comments_number,
				$approved_comments ? __( 'No pending comments' ) : __( 'No comments' )
			);
		}
	}

	/**
	 * Get the current page number
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	public function get_pagenum() {
		$pagenum = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 0;

		if ( isset( $this->_pagination_args['total_pages'] ) && $pagenum > $this->_pagination_args['total_pages'] ) {
			$pagenum = $this->_pagination_args['total_pages'];
		}

		return max( 1, $pagenum );
	}

	/**
	 * Get number of items to display on a single page
	 *
	 * @since 1.1.0
	 *
	 * @param string $option
	 * @param int    $default
	 * @return int
	 */
	protected function get_items_per_page( $option, $default = 20 ) {
		$per_page = (int) get_user_option( $option );
		if ( empty( $per_page ) || $per_page < 1 ) {
			$per_page = $default;
		}

		/**
		 * Filters the number of items to be displayed on each page of the list table.
		 *
		 * The dynamic hook name, $option, refers to the `per_page` option depending
		 * on the type of list table in use. Possible values include: 'edit_comments_per_page',
		 * 'sites_network_per_page', 'site_themes_network_per_page', 'themes_network_per_page',
		 * 'users_network_per_page', 'edit_post_per_page', 'edit_page_per_page',
		 * 'edit_{$post_type}_per_page', etc.
		 *
		 * @since 1.9.0
		 *
		 * @param int $per_page Number of items to be displayed. Default 20.
		 */
		return (int) apply_filters( "{$option}", $per_page );
	}

	/**
	 * Display the pagination.
	 *
	 * @since 1.1.0
	 *
	 * @param string $which
	 */
	protected function pagination( $which ) {
		if ( empty( $this->_pagination_args ) ) {
			return;
		}

		$total_items     = $this->_pagination_args['total_items'];
		$total_pages     = $this->_pagination_args['total_pages'];
		$infinite_scroll = false;
		if ( isset( $this->_pagination_args['infinite_scroll'] ) ) {
			$infinite_scroll = $this->_pagination_args['infinite_scroll'];
		}

		if ( 'top' === $which && $total_pages > 1 ) {
			$this->screen->render_screen_reader_content( 'heading_pagination' );
		}

		$output = '<span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total_items ), number_format_i18n( $total_items ) ) . '</span>';

		$current              = $this->get_pagenum();
		$removable_query_args = wp_removable_query_args();

		$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

		$current_url = remove_query_arg( $removable_query_args, $current_url );

		$page_links = array();

		$total_pages_before = '<span class="paging-input">';
		$total_pages_after  = '</span></span>';

		$disable_first = $disable_last = $disable_prev = $disable_next = false;

		if ( $current == 1 ) {
			$disable_first = true;
			$disable_prev  = true;
		}
		if ( $current == 2 ) {
			$disable_first = true;
		}
		if ( $current == $total_pages ) {
			$disable_last = true;
			$disable_next = true;
		}
		if ( $current == $total_pages - 1 ) {
			$disable_last = true;
		}

		if ( $disable_first ) {
			$page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&laquo;</span>';
		} else {
			$page_links[] = sprintf(
				"<a class='first-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				esc_url( remove_query_arg( 'paged', $current_url ) ),
				__( 'First page' ),
				'&laquo;'
			);
		}

		if ( $disable_prev ) {
			$page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&lsaquo;</span>';
		} else {
			$page_links[] = sprintf(
				"<a class='prev-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				esc_url( add_query_arg( 'paged', max( 1, $current - 1 ), $current_url ) ),
				__( 'Previous page' ),
				'&lsaquo;'
			);
		}

		if ( 'bottom' === $which ) {
			$html_current_page  = $current;
			$total_pages_before = '<span class="screen-reader-text">' . __( 'Current Page' ) . '</span><span id="table-paging" class="paging-input"><span class="tablenav-paging-text">';
		} else {
			$html_current_page = sprintf(
				'%s<input class="current-page" id="current-page-selector" type="text" name="paged" value="%s" size="%d" aria-describedby="table-paging" /><span class="tablenav-paging-text">',
				'<label for="current-page-selector" class="screen-reader-text">' . __( 'Current Page' ) . '</label>',
				$current,
				strlen( $total_pages )
			);
		}
		$html_total_pages = sprintf( '<span class="total-pages">%s</span>', number_format_i18n( $total_pages ) );
		$page_links[]     = $total_pages_before . sprintf( _x( '%1$s of %2$s', 'paging' ), $html_current_page, $html_total_pages ) . $total_pages_after;

		if ( $disable_next ) {
			$page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&rsaquo;</span>';
		} else {
			$page_links[] = sprintf(
				"<a class='next-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				esc_url( add_query_arg( 'paged', min( $total_pages, $current + 1 ), $current_url ) ),
				__( 'Next page' ),
				'&rsaquo;'
			);
		}

		if ( $disable_last ) {
			$page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&raquo;</span>';
		} else {
			$page_links[] = sprintf(
				"<a class='last-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				esc_url( add_query_arg( 'paged', $total_pages, $current_url ) ),
				__( 'Last page' ),
				'&raquo;'
			);
		}

		$pagination_links_class = 'pagination-links';
		if ( ! empty( $infinite_scroll ) ) {
			$pagination_links_class .= ' hide-if-js';
		}
		$output .= '<span class="' . $pagination_links_class . '">' . join( '', $page_links ) . '</span>';

		if ( $total_pages ) {
			$page_class = $total_pages < 2 ? ' one-page' : '';
		} else {
			$page_class = ' no-pages';
		}
		$this->_pagination = '<div class="tablenav-pages' . $page_class . '">' . $output . '</div>';

		echo $this->_pagination;
	}

	/**
	 * Get a list of columns. The format is:
	 * 'internal-name' => 'Title'
	 *
	 * @since 1.1.0
	 * @abstract
	 *
	 * @return array
	 */
	public function get_columns() {
		die( 'function Tutor_List_Table::get_columns() must be over-ridden in a sub-class.' );
	}

	/**
	 * Get a list of sortable columns. The format is:
	 * 'internal-name' => 'orderby'
	 * or
	 * 'internal-name' => array( 'orderby', true )
	 *
	 * The second format will make the initial sorting order be descending
	 *
	 * @since 1.1.0
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array();
	}

	/**
	 * Gets the name of the default primary column.
	 *
	 * @since 1.3.0
	 *
	 * @return string Name of the default primary column, in this case, an empty string.
	 */
	protected function get_default_primary_column_name() {
		$columns = $this->get_columns();
		$column  = '';

		if ( empty( $columns ) ) {
			return $column;
		}

		// We need a primary defined so responsive views show something,
		// so let's fall back to the first non-checkbox column.
		foreach ( $columns as $col => $column_name ) {
			if ( 'cb' === $col ) {
				continue;
			}

			$column = $col;
			break;
		}

		return $column;
	}

	/**
	 * Public wrapper for Tutor_List_Table::get_default_primary_column_name().
	 *
	 * @since 1.4.0
	 *
	 * @return string Name of the default primary column.
	 */
	public function get_primary_column() {
		return $this->get_primary_column_name();
	}

	/**
	 * Gets the name of the primary column.
	 *
	 * @since 1.3.0
	 *
	 * @return string The name of the primary column.
	 */
	protected function get_primary_column_name() {
		$columns = get_column_headers( $this->screen );
		$default = $this->get_default_primary_column_name();

		// If the primary column doesn't exist fall back to the
		// first non-checkbox column.
		if ( ! isset( $columns[ $default ] ) ) {
			$default = self::get_default_primary_column_name();
		}

		/**
		 * Filters the name of the primary column for the current list table.
		 *
		 * @since 1.3.0
		 *
		 * @param string $default Column name default for the specific list table, e.g. 'name'.
		 * @param string $context Screen ID for specific list table, e.g. 'plugins'.
		 */
		$column = apply_filters( 'list_table_primary_column', $default, $this->screen->id );

		if ( empty( $column ) || ! isset( $columns[ $column ] ) ) {
			$column = $default;
		}

		return $column;
	}

	/**
	 * Get a list of all, hidden and sortable columns, with filter applied
	 *
	 * @since 1.1.0
	 *
	 * @return array
	 */
	protected function get_column_info() {
		// $_column_headers is already set / cached
		if ( isset( $this->_column_headers ) && is_array( $this->_column_headers ) ) {
			// Back-compat for list tables that have been manually setting $_column_headers for horse reasons.
			// In 1.3, we added a fourth argument for primary column.
			$column_headers = array( array(), array(), array(), $this->get_primary_column_name() );
			foreach ( $this->_column_headers as $key => $value ) {
				$column_headers[ $key ] = $value;
			}

			return $column_headers;
		}

		$columns = get_column_headers( $this->screen );
		$hidden  = get_hidden_columns( $this->screen );

		$sortable_columns = $this->get_sortable_columns();
		/**
		 * Filters the list table sortable columns for a specific screen.
		 *
		 * The dynamic portion of the hook name, `$this->screen->id`, refers
		 * to the ID of the current screen, usually a string.
		 *
		 * @since 1.5.0
		 *
		 * @param array $sortable_columns An array of sortable columns.
		 */
		$_sortable = apply_filters( 'manage_' . esc_attr( $this->screen->id ) . '_sortable_columns', $sortable_columns );

		$sortable = array();
		foreach ( $_sortable as $id => $data ) {
			if ( empty( $data ) ) {
				continue;
			}

			$data = (array) $data;
			if ( ! isset( $data[1] ) ) {
				$data[1] = false;
			}

			$sortable[ $id ] = $data;
		}

		$primary               = $this->get_primary_column_name();
		$this->_column_headers = array( $columns, $hidden, $sortable, $primary );

		return $this->_column_headers;
	}

	/**
	 * Return number of visible columns
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	public function get_column_count() {
		list ( $columns, $hidden ) = $this->get_column_info();
		$hidden                    = array_intersect( array_keys( $columns ), array_filter( $hidden ) );
		return count( $columns ) - count( $hidden );
	}

	/**
	 * Print column headers, accounting for hidden and sortable columns.
	 *
	 * @since 1.1.0
	 *
	 * @staticvar int $cb_counter
	 *
	 * @param bool $with_id Whether to set the id attribute or not
	 */
	public function print_column_headers( $with_id = true ) {
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$current_url = remove_query_arg( 'paged', $current_url );

		if ( isset( $_GET['orderby'] ) ) {
			$current_orderby = tutor_sanitize_data( $_GET['orderby'] );
		} else {
			$current_orderby = '';
		}

		if ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) {
			$current_order = 'desc';
		} else {
			$current_order = 'asc';
		}

		if ( ! empty( $columns['cb'] ) ) {
			static $cb_counter = 1;
			$columns['cb']     = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __( 'Select All' ) . '</label>' . '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
			$cb_counter++;
		}

		foreach ( $columns as $column_key => $column_display_name ) {
			$class = array( 'manage-column', "column-$column_key" );

			if ( in_array( $column_key, $hidden ) ) {
				$class[] = 'hidden';
			}

			if ( 'cb' === $column_key ) {
				$class[] = 'check-column';
			} elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ) ) ) {
				$class[] = 'num';
			}

			if ( $column_key === $primary ) {
				$class[] = 'column-primary';
			}

			if ( isset( $sortable[ $column_key ] ) ) {
				list( $orderby, $desc_first ) = $sortable[ $column_key ];

				if ( $current_orderby === $orderby ) {
					$order   = 'asc' === $current_order ? 'desc' : 'asc';
					$class[] = 'sorted';
					$class[] = $current_order;
				} else {
					$order   = $desc_first ? 'desc' : 'asc';
					$class[] = 'sortable';
					$class[] = $desc_first ? 'asc' : 'desc';
				}

				$column_display_name = '<a href="' . esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
			}

			$tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id    = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) {
				$class = "class='" . join( ' ', $class ) . "'";
			}

			echo ( "<$tag $scope $id $class>$column_display_name</$tag>" );
		}
	}

	/**
	 * Display the table
	 *
	 * @since 1.1.0
	 */
	public function display( $enable_sorting_field_with_bulk_action = false ) {
		$singular = $this->_args['singular'];
		if ( $enable_sorting_field_with_bulk_action ) {
			$this->display_sorting_fields();
		} else {
			$this->display_tablenav( 'top' );
		}

		$this->screen->render_screen_reader_content( 'heading_list' );
		?>
		<table class="wp-list-table <?php echo esc_attr( implode( ' ', $this->get_table_classes() ) ); ?>">
			<thead>
			<tr>
				<?php $this->print_column_headers(); ?>
			</tr>
			</thead>

			<tbody id="the-list"
			<?php
			if ( $singular ) {
				echo 'data-wp-lists=list:' . $singular;
			}
			?>
			>
			<?php $this->display_rows_or_placeholder(); ?>
			</tbody>

			<tfoot>
			<tr>
				<?php $this->print_column_headers( false ); ?>
			</tr>
			</tfoot>

		</table>
		<?php
		$this->display_tablenav( 'bottom' );
	}

	/**
	 * Get a list of CSS classes for the Tutor_List_Table table tag.
	 *
	 * @since 1.1.0
	 *
	 * @return array List of CSS classes for the table tag.
	 */
	protected function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped', $this->_args['plural'] );
	}

	/**
	 * Generate the table navigation above or below the table
	 *
	 * @since 1.1.0
	 * @param string $which
	 */
	protected function display_tablenav( $which ) {
		if ( 'top' === $which ) {
			wp_nonce_field( 'bulk-' . $this->_args['plural'] );
		}
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">

			<?php if ( $this->has_items() ) : ?>
				<div class="alignleft actions bulkactions">
					<?php $this->bulk_actions( $which ); ?>
				</div>
				<?php
			endif;
			$this->extra_tablenav( $which );
			$this->pagination( $which );
			?>

			<br class="clear" />
		</div>
		<?php
	}

	/**
	 * Sorting fields added on tutor table
	 *
	 * Course id | Search | Date | Order
	 *
	 * @since 1.9.5
	 */
	protected function display_sorting_fields() {
		$search_filter = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
		$course_id     = isset( $_GET['course-id'] ) ? sanitize_text_field( $_GET['course-id'] ) : '';
		$date_filter   = isset( $_GET['date'] ) ? sanitize_text_field( $_GET['date'] ) : '';
		$order_filter  = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : '';
		$which         = 'top';
		?>
		<div class="tutor-sorting-bulk-action-wrapper">
			<div class="tablenav <?php echo esc_attr( $which ); ?>">
				<?php if ( $this->has_items() ) : ?>
					<div class="alignleft actions bulkactions">
						<?php $this->bulk_actions( $which ); ?>
					</div>
					<?php
				endif;
				$this->extra_tablenav( $which );

				?>
			</div>

			<div class="tutor-admin-search-box-container" style="margin:0px;">

				<div>
					<div class="menu-label"><?php _e( 'Courses', 'tutor' ); ?></div>
					<div>
						<?php
						// get courses
						$courses = ( current_user_can( 'administrator' ) ) ? tutils()->get_courses() : tutils()->get_courses_by_instructor();
						?>

						<select class="tutor-report-category tutor-announcement-course-sorting">

							<option value=""><?php _e( 'All', 'tutor' ); ?></option>

							<?php if ( $courses ) : ?>
								<?php foreach ( $courses as $course ) : ?>
									<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_id, $course->ID, 'selected' ); ?>>
										<?php echo esc_attr( $course->post_title ); ?>
									</option>
								<?php endforeach; ?>
							<?php else : ?>
								<option value=""><?php _e( 'No course found', 'tutor' ); ?></option>
							<?php endif; ?>
						</select>
					</div>
				</div>

				<div>
					<div class="menu-label"><?php _e( 'Sort By', 'tutor' ); ?></div>
					<div>
						<select class="tutor-report-sort tutor-announcement-order-sorting">
							<option <?php selected( $order_filter, 'ASC' ); ?>>ASC</option>
							<option <?php selected( $order_filter, 'DESC' ); ?>>DESC</option>
						</select>
					</div>
				</div>

				<div>
					<div class="menu-label"><?php _e( 'Date', 'tutor' ); ?></div>
					<div class="date-range-input">
						<input type="text" class="tutor_date_picker tutor-announcement-date-sorting" id="tutor-announcement-datepicker" placeholder="<?php _e( get_option( 'date_format' ), 'tutor' ); ?>" value="<?php echo '' !== $date_filter ? tutor_get_formated_date( get_option( 'date_format' ), $date_filter ) : ''; ?>" autocomplete="off" />
						<i class="tutor-icon-calendar"></i>
					</div>
				</div>

				<div class="tutor-search-form-group">
					<div class="menu-label"><?php _e( 'Search', 'tutor' ); ?></div>
					<div style="position:relative;">
						<input type="text" name="search" class="tutor-report-search tutor-announcement-search-field" value="<?php echo esc_attr( $search_filter ); ?>" autocomplete="off" placeholder="<?php _e( 'Search', 'tutor' ); ?>" />
						<button class="tutor-report-search-btn tutor-announcement-search-sorting"><i class="tutor-icon-magnifying-glass"></i></button>
					</div>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination
	 *
	 * @since 1.1.0
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {}

	/**
	 * Generate the tbody element for the list table.
	 *
	 * @since 1.1.0
	 */
	public function display_rows_or_placeholder() {
		if ( $this->has_items() ) {
			$this->display_rows();
		} else {
			echo '<tr class="no-items"><td class="colspanchange" colspan="' . $this->get_column_count() . '">';
			$this->no_items();
			echo '</td></tr>';
		}
	}

	/**
	 * Generate the table rows
	 *
	 * @since 1.1.0
	 */
	public function display_rows() {
		foreach ( $this->items as $item ) {
			$this->single_row( $item );
		}
	}

	/**
	 * Generates content for a single row of the table
	 *
	 * @since 1.1.0
	 *
	 * @param object $item The current item
	 */
	public function single_row( $item ) {
		echo '<tr>';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 *
	 * @param object $item
	 * @param string $column_name
	 */
	protected function column_default( $item, $column_name ) {}

	/**
	 *
	 * @param object $item
	 */
	protected function column_cb( $item ) {}

	/**
	 * Generates the columns for a single row of the table
	 *
	 * @since 1.1.0
	 *
	 * @param object $item The current item
	 */
	protected function single_row_columns( $item ) {
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$classes = "$column_name column-$column_name";
			if ( $primary === $column_name ) {
				$classes .= ' has-row-actions column-primary';
			}

			if ( in_array( $column_name, $hidden ) ) {
				$classes .= ' hidden';
			}

			// Comments column uses HTML in the display name with screen reader text.
			// Instead of using esc_attr(), we strip tags to get closer to a user-friendly string.
			$data = 'data-colname="' . esc_attr( wp_strip_all_tags( $column_display_name ) ) . '"';

			$attributes = 'class="' . esc_attr( $classes ) . '" ' . $data;

			if ( 'cb' === $column_name ) {
				echo '<th scope="row" class="check-column">';
				echo tutor_kses_html( $this->column_cb( $item ) );
				echo '</th>';
			} elseif ( method_exists( $this, '_column_' . $column_name ) ) {
				echo call_user_func(
					array( $this, '_column_' . $column_name ),
					$item,
					$classes,
					$data,
					$primary
				);
			} elseif ( method_exists( $this, 'column_' . $column_name ) ) {
				echo '<td ' . $attributes . '>'; // esc_attr used already
				echo tutor_kses_html( call_user_func( array( $this, 'column_' . $column_name ), $item ) );
				echo tutor_kses_html( $this->handle_row_actions( $item, $column_name, $primary ) );
				echo '</td>';
			} else {
				echo '<td ' . $attributes . '>'; // esc_attr used already
				echo tutor_kses_html( $this->column_default( $item, $column_name ) );
				echo tutor_kses_html( $this->handle_row_actions( $item, $column_name, $primary ) );
				echo '</td>';
			}
		}
	}

	/**
	 * Generates and display row actions links for the list table.
	 *
	 * @since 1.3.0
	 *
	 * @param object $item        The item being acted upon.
	 * @param string $column_name Current column name.
	 * @param string $primary     Primary column name.
	 * @return string The row actions HTML, or an empty string if the current column is the primary column.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		return $column_name === $primary ? '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details' ) . '</span></button>' : '';
	}

	/**
	 * Handle an incoming ajax request (called from admin-ajax.php)
	 *
	 * @since 1.1.0
	 */
	public function ajax_response() {
		$this->prepare_items();

		ob_start();
		if ( ! empty( $_REQUEST['no_placeholder'] ) ) {
			$this->display_rows();
		} else {
			$this->display_rows_or_placeholder();
		}

		$rows = ob_get_clean();

		$response = array( 'rows' => $rows );

		if ( isset( $this->_pagination_args['total_items'] ) ) {
			$response['total_items_i18n'] = sprintf(
				_n( '%s item', '%s items', $this->_pagination_args['total_items'] ),
				number_format_i18n( $this->_pagination_args['total_items'] )
			);
		}
		if ( isset( $this->_pagination_args['total_pages'] ) ) {
			$response['total_pages']      = $this->_pagination_args['total_pages'];
			$response['total_pages_i18n'] = number_format_i18n( $this->_pagination_args['total_pages'] );
		}

		die( wp_json_encode( $response ) );
	}

	/**
	 * Send required variables to JavaScript land
	 */
	public function _js_vars() {
		$args = array(
			'class'  => get_class( $this ),
			'screen' => array(
				'id'   => $this->screen->id,
				'base' => $this->screen->base,
			),
		);

		printf( "<script type='text/javascript'>list_args = %s;</script>", wp_json_encode( $args ) );
	}
}

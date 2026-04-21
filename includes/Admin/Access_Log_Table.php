<?php
namespace WP_LLM_Connector\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Access Log list table.
 *
 * Renders paginated audit log entries with column sorting, filtering, and
 * search. The data source is the wp_llm_connector_audit_log table populated
 * by Security_Manager::log_request().
 */
class Access_Log_Table extends \WP_List_Table {

	const PER_PAGE = 50;

	/** @var array Query filters (range, status, search). */
	private $filters = array();

	public function __construct( array $filters = array() ) {
		parent::__construct(
			array(
				'singular' => 'access_log_entry',
				'plural'   => 'access_log_entries',
				'ajax'     => false,
			)
		);
		$this->filters = $filters;
	}

	/**
	 * Column definitions — keys here must match the array keys in the row
	 * data returned by prepare_items() and the column_* method suffixes.
	 */
	public function get_columns() {
		return array(
			'timestamp'         => __( 'Timestamp', 'wp-llm-connector' ),
			'endpoint'          => __( 'Tool / Endpoint', 'wp-llm-connector' ),
			'ip_address'        => __( 'IP Address', 'wp-llm-connector' ),
			'http_method'       => __( 'Method', 'wp-llm-connector' ),
			'response_code'     => __( 'Status', 'wp-llm-connector' ),
			'execution_time_ms' => __( 'Exec (ms)', 'wp-llm-connector' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'timestamp'         => array( 'timestamp', true ),
			'endpoint'          => array( 'endpoint', false ),
			'ip_address'        => array( 'ip_address', false ),
			'response_code'     => array( 'response_code', false ),
			'execution_time_ms' => array( 'execution_time_ms', false ),
		);
	}

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$paged    = max( 1, absint( $this->get_pagenum() ) );
		$per_page = self::PER_PAGE;
		$offset   = ( $paged - 1 ) * $per_page;

		// Sorting — whitelisted against the column map to avoid SQL injection.
		$allowed_orderby = array( 'timestamp', 'endpoint', 'ip_address', 'response_code', 'execution_time_ms' );
		$orderby         = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby, true ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( $_GET['orderby'] )
			: 'timestamp';
		$order           = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		list( $where_sql, $where_args ) = self::build_where_clause( $this->filters );

		global $wpdb;
		$table = $wpdb->prefix . 'llm_connector_audit_log';

		$count_query = "SELECT COUNT(*) FROM {$table} {$where_sql}";
		$total_items = (int) ( $where_args
			? $wpdb->get_var( $wpdb->prepare( $count_query, $where_args ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: $wpdb->get_var( $count_query ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		$data_query_args = $where_args;
		$data_query_args[] = $per_page;
		$data_query_args[] = $offset;

		$sql = "SELECT id, timestamp, endpoint, http_method, execution_time_ms, response_code, ip_address, user_agent
				FROM {$table}
				{$where_sql}
				ORDER BY {$orderby} {$order}
				LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $data_query_args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

		$this->items = is_array( $rows ) ? $rows : array();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => max( 1, (int) ceil( $total_items / $per_page ) ),
			)
		);
	}

	/**
	 * Build a shared WHERE clause + parameter array for the audit log query.
	 * Exposed as a static helper so CSV export and summary queries can reuse
	 * exactly the same filter semantics as the list table.
	 *
	 * @param array $filters Associative array with keys: range, from, to, status, search.
	 * @return array{0:string,1:array} [ $where_sql, $params ]
	 */
	public static function build_where_clause( array $filters ) {
		$conditions = array();
		$params     = array();

		// Date range.
		$range = $filters['range'] ?? '24h';
		$from  = null;
		$to    = null;

		switch ( $range ) {
			case '24h':
				$from = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
				break;
			case '7d':
				$from = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
				break;
			case '30d':
				$from = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
				break;
			case 'custom':
				if ( ! empty( $filters['from'] ) ) {
					$from = gmdate( 'Y-m-d 00:00:00', strtotime( $filters['from'] ) );
				}
				if ( ! empty( $filters['to'] ) ) {
					$to = gmdate( 'Y-m-d 23:59:59', strtotime( $filters['to'] ) );
				}
				break;
			case 'all':
			default:
				// No date constraint.
				break;
		}

		if ( $from ) {
			$conditions[] = 'timestamp >= %s';
			$params[]     = $from;
		}
		if ( $to ) {
			$conditions[] = 'timestamp <= %s';
			$params[]     = $to;
		}

		// Status filter.
		$status = $filters['status'] ?? 'all';
		if ( 'success' === $status ) {
			$conditions[] = '(response_code >= 200 AND response_code < 300)';
		} elseif ( 'errors' === $status ) {
			$conditions[] = '(response_code >= 400)';
		}

		// Search (IP or endpoint).
		$search = trim( (string) ( $filters['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like         = '%' . $GLOBALS['wpdb']->esc_like( $search ) . '%';
			$conditions[] = '(endpoint LIKE %s OR ip_address LIKE %s)';
			$params[]     = $like;
			$params[]     = $like;
		}

		$where_sql = $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';
		return array( $where_sql, $params );
	}

	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '&mdash;';
	}

	public function column_timestamp( $item ) {
		$ts  = isset( $item['timestamp'] ) ? strtotime( $item['timestamp'] . ' UTC' ) : false;
		if ( ! $ts ) {
			return '&mdash;';
		}
		$absolute = wp_date( 'Y-m-d H:i:s', $ts );
		/* translators: %s: relative time, e.g. "2 mins ago" */
		$relative = sprintf( __( '%s ago', 'wp-llm-connector' ), human_time_diff( $ts, time() ) );

		return sprintf(
			'<span class="wp-llm-log-ts-abs">%s</span><br><span class="wp-llm-log-ts-rel">%s</span>',
			esc_html( $absolute ),
			esc_html( $relative )
		);
	}

	public function column_endpoint( $item ) {
		return '<code>' . esc_html( $item['endpoint'] ?? '' ) . '</code>';
	}

	public function column_ip_address( $item ) {
		return esc_html( $item['ip_address'] ?? '&mdash;' );
	}

	public function column_http_method( $item ) {
		$method = $item['http_method'] ?? '';
		if ( '' === $method ) {
			return '<span class="wp-llm-log-na">&mdash;</span>';
		}
		return '<span class="wp-llm-log-method wp-llm-log-method-' . esc_attr( strtolower( $method ) ) . '">' . esc_html( $method ) . '</span>';
	}

	public function column_response_code( $item ) {
		$code  = (int) ( $item['response_code'] ?? 0 );
		$class = 'wp-llm-log-code-unknown';
		if ( $code >= 200 && $code < 300 ) {
			$class = 'wp-llm-log-code-ok';
		} elseif ( $code >= 400 && $code < 500 ) {
			$class = 'wp-llm-log-code-warn';
		} elseif ( $code >= 500 ) {
			$class = 'wp-llm-log-code-err';
		}
		return '<span class="wp-llm-log-code ' . esc_attr( $class ) . '">' . esc_html( $code ?: '—' ) . '</span>';
	}

	public function column_execution_time_ms( $item ) {
		$ms = $item['execution_time_ms'] ?? null;
		if ( null === $ms || '' === $ms ) {
			return '<span class="wp-llm-log-na">&mdash;</span>';
		}
		return esc_html( number_format_i18n( (float) $ms, 2 ) );
	}

	/**
	 * No-results message. Rendered by WP_List_Table when prepare_items
	 * returns an empty set.
	 */
	public function no_items() {
		esc_html_e( 'No log entries match the current filters.', 'wp-llm-connector' );
	}
}

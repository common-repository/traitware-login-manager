<?php
/**
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class TraitwareLoginsTable
 */
class TraitwareLoginsTable extends WP_List_Table {

	public $data       = array();
	public $found_data = array(); // for pagination

    /**
     * get_columns implementation.
     * @return array
     */
	public function get_columns() {
		return array(
			'login' => 'Login Time',
		);
	}

    /**
     * prepare_items implementation.
     */
	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = array(
			'login' => array( 'login', false ),
		);
		$this->_column_headers = array( $columns, $hidden, $sortable );

		usort( $this->data, array( &$this, 'usort_reorder' ) );

		$per_page     = 100;
		$current_page = $this->get_pagenum();
		$total_items  = count( $this->data );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		$this->items = array_slice( $this->data, ( ( $current_page - 1 ) * $per_page ), $per_page );
	}

    /**
     * column_default implementation.
     * @param object $item
     * @param string $column_name
     * @return string|array
     */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'login':
				return $item[ $column_name ];
			default:
				return '';
		}
	}

    /**
     * usort_reorder implementation.
     * @param $a
     * @param $b
     * @return int
     */
	public function usort_reorder( $a, $b ) {
		// If no sort, default to title.
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? sanitize_key( $_GET['orderby'] ) : 'login';
		// If no order, default to asc.
		$order = ( ! empty( $_GET['order'] ) ) ? sanitize_key( $_GET['order'] ) : 'desc';
		// Determine sort order.
		$result = strcmp( $a[ $orderby ], $b[ $orderby ] );
		// Send final sort direction to usort.
		return ( $order === 'asc' ) ? $result : -$result;
	}

    /**
     * column_login implementation.
     * @param $item
     * @return false|string
     */
	public function column_login( $item ) {
		return date( 'F jS, Y, g:i a', $item['login'] );
	}

    /**
     * no_items implementation.
     */
	public function no_items() {
		echo 'No logins.';
	}
}

if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ) ) ) {
	wp_die( 'Invalid request.' );
}

// validate input
$wpid = 0;
if ( isset( $_REQUEST['userid'] ) ) {
	$wpid = intval( sanitize_key( $_REQUEST['userid'] ) );
}

$user = get_userdata( $wpid );
if ( ! ( $user instanceof WP_User ) ) {
	wp_die( 'No user' );
}

// get twid
global $wpdb;
$results = $wpdb->get_results(
	$wpdb->prepare(
		'SELECT
		id
	FROM
		' . $wpdb->prefix . 'traitwareusers
	WHERE
		userid = %d',
		$wpid
	),
	OBJECT
);

if ( empty( $results ) ) {
	wp_die( 'No user' );
}

$twuserid = intval( $results[0]->id );

$logins_table = new TraitwareLoginsTable();

$sql = 'SELECT UNIX_TIMESTAMP(logintime) AS ts FROM ' . $wpdb->prefix . 'traitwarelogins WHERE `twuserid` = %d ORDER BY logintime DESC';
$stmt = $wpdb->prepare( $sql, array( $twuserid ) );
$results = $wpdb->get_results( $stmt, OBJECT );
$results_count = count( $results );
for ( $n = 0; $n < $results_count; $n++ ) {
	$logins_table->data[] = array(
		'login' => $results[ $n ]->ts,
	);
}

$logins_table->prepare_items();

echo '
	<div class="wrap">
	<h1>TraitWare User Logins: ' . esc_html( $user->user_login ) . '</h1>
';

$logins_table->display();

echo '
	</div>
';

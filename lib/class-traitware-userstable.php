<?php
/**
 * Users table.
 *
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Traitware_UsersTable
 */
class Traitware_UsersTable extends WP_List_Table {

	public $data       = array();
	public $found_data = array(); // for pagination
	public $vars       = array();

	/**
	 * @param $vars
	 */
	public function set_vars( $vars ) {
		$this->vars = $vars;
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'        => '<input type="checkbox" />',
			'username'  => 'Username',
			'status'    => 'TraitWare Status',
			'lastlogin' => 'Last Login',
			'name'      => 'Name',
			'email'     => 'Email',
			'role'      => 'Role',
			'type'      => 'Type',
		);
	}

	/**
	 *
	 */
	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = array(
			'username'  => array( 'username', false ),
			'status'    => array( 'status', false ),
			'lastlogin' => array( 'status', false ),
			'name'      => array( 'name', false ),
			'email'     => array( 'email', false ),
			'role'      => array( 'role', false ),
			'type'      => array( 'type', false ),
		);
		$this->_column_headers = array( $columns, $hidden, $sortable );

		usort( $this->data, array( &$this, 'usort_reorder' ) );

		$per_page     = 50;
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
	 * @param object $item
	 * @param string $column_name
	 * @return string|mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'username':
			case 'status':
			case 'lastlogin':
			case 'name':
			case 'email':
			case 'role':
			case 'type':
				return $item[ $column_name ];
			default:
				return '';
		}
	}

	/**
	 * @param $a
	 * @param $b
	 * @return int
	 */
	public function usort_reorder( $a, $b ) {
		// If no sort, default to title
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'username';
		// If no order, default to asc
		$order = ( ! empty( $_GET['order'] ) ) ? $_GET['order'] : 'asc';
		// Determine sort order
		$result = strcmp( $a[ $orderby ], $b[ $orderby ] );
		// Send final sort direction to usort
		return ( $order === 'asc' ) ? $result : -$result;
	}

	/**
	 * @param object $item
	 * @return string
	 */
	public function column_cb( $item ) {
		$disabled = '';
		if ( $item['status'] === 'Account Owner' ) {
			$disabled = '  disabled'; }
		return '<input type="checkbox" name="bulkrule[]" value="' . $item['ID'] . '" ' . $disabled . '/>';
	}

	/**
	 * @param $item
	 * @return string
	 */
	public function column_username( $item ) {
		$actions = array();
		$val     = $item['username'];
		if ( $item['status'] === 'Account Owner' ) {
			$user = wp_get_current_user();
			if ( $user->ID == $item['ID'] ) { // can't remove yourself
				$val = '<b>' . $val . '</b> (you)';
			} else {
				$val = '<b>' . $val . '</b>';
				if ( $this->vars['isOwner'] ) {
					$actions[] = '<a href="javascript:void(0);" onClick="traitware_useraction(\'removeowner\',[' . $item['ID'] . ']);">Remove as Account Owner</a>';
				}
			}
		} else {
			if ( $item['status'] === 'Active' ) {
				$user = get_userdata( $item['ID'] );
				if ( in_array( 'administrator', $user->roles ) && $this->vars['isOwner'] ) {
					$actions[] = '<a href="javascript:void(0);" onClick="traitware_useraction(\'addowner\',[' . $item['ID'] . ']);">Add as Account Owner</a>';
				}
			}
		}

		return get_avatar( $item['ID'], 32 ) .
			'<a href="user-edit.php?user_id=' . $item['ID'] . '">' . $val . '</a> ' .
			$this->row_actions( $actions );
	}

	/**
	 * @param $item
	 * @return string
	 */
	public function column_status( $item ) {
		$val     = $item['status'];
		$actions = array();

		$userdata = get_userdata( $item['ID'] );
		$isAdmin  = false;

		if ( $userdata instanceof WP_User ) {
			$isAdmin = in_array( 'administrator', $userdata->roles, true );
		}

		$isAdmin = $isAdmin ? '1' : '0';

		if ( $item['status'] === 'Account Owner' ) { // bold this
			$val = '<b>' . $val . '</b>';
		}
		if ( $item['status'] === 'Active' && $this->vars['canChangeStuff'] ) {
			$actions[] = '<a href="javascript:void(0);" onClick="traitware_useraction(\'del\',[' . $item['ID'] . ']);">Deactivate User</a>';
		}
		if ( $item['status'] === 'Inactive' && $this->vars['canChangeStuff'] ) {
			$actions[] = '<a href="javascript:void(0);" class="traitware_activate_user_link" data-user-id="' . $item['ID'] . '" data-user-admin="' . $isAdmin . '">Activate User</a>';
		}
		return $val . $this->row_actions( $actions );
	}

	/**
	 * @param $item
	 * @return string
	 */
	public function column_lastlogin( $item ) {
		if ( $item['status'] == 'Inactive' ) {
			return '-';
		}
		$userlogins_url = esc_url( wp_nonce_url( 'admin.php?page=traitware-userlogins&userid=' . $item['ID'] ) );
		return '<a href="' . $userlogins_url . '">' . traitware_timeago( $item['lastlogin'] ) . '</a>';
	}

	/**
	 * @param $item
	 * @return string
	 */
	public function column_email( $item ) {
		$actions = array();
		if ( $item['status'] !== 'Inactive' && $this->vars['isOwner'] ) {
			$actions[] = '<a href="javascript:void(0);" onClick="traitware_useraction(\'resend\',[' . esc_js( $item['ID'] ) . ']);">Resend Registration Email</a>';
		}
		return $item['email'] . $this->row_actions( $actions );
	}

	/**
	 * @param $item
	 * @return string
	 */
	public function column_type( $item ) {
		$userdata = get_userdata( $item['ID'] );
		$isAdmin  = false;

		$user = wp_get_current_user();

		$isOwner = $this->vars['isOwner'];

		if ( $userdata instanceof WP_User ) {
			$isAdmin = in_array( 'administrator', $userdata->roles, true );
		}

		$actions      = array();
		$display_type = '';
		$typeslug     = '';

		if ( $item['type'] === 'dashboard' ) {
			$display_type = 'Dashboard User';
			$typeslug     = 'dashboard';
		} elseif ( $item['type'] === 'scrub' ) {
			$display_type = 'Site User';
			$typeslug     = 'scrub';
		}

		if ( $item['status'] === 'Account Owner' ) {
			$display_type = 'Account Owner';
			$typeslug     = 'owner';
		}

		if ( $item['status'] !== 'Inactive' && ( $typeslug !== 'owner' || $isOwner ) && $user->ID !== $item['ID'] && $this->vars['canChangeStuff'] ) {
			$actions[] = '<a href="#" class="traitware_change_usertype_link" data-user-id="' . $item['ID'] . '" data-user-type="' . $typeslug . '" data-user-admin="' . $isAdmin . '">Change User Type</a>';
		}

		return $display_type . $this->row_actions( $actions );
	}

	/**
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = array();

		if ( $this->vars['canChangeStuff'] ) {
			$actions = array(
				'add' => 'Add to TraitWare',
				'del' => 'Remove from TraitWare',
			);
		}

		return $actions;
	}

	public function no_items() {
		echo 'No users.'; }
}

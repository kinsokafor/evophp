<?php

namespace EvoPhp\Database;

use EvoPhp\Database\Config;

class Database {

	/**
	 * The number of times to retry reconnecting before dying.
	 *
	 * @since 3.9.0
	 * @see wpdb::check_connection()
	 * @var int
	 */
	protected $reconnect_retries = 5;

	/**
	 * Whether the database queries are ready to start executing.
	 *
	 * @since 2.3.2
	 * @var bool
	 */
	private $ready = false;

	/**
	 * Whether we've managed to successfully connect at some point
	 *
	 * @since 3.9.0
	 * @var bool
	 */
	private $has_connected = false;

	/*
	*Index of the last database user
	*/
	private $last_user_index = -1;

	public $connection;
	private $db_name;
	private $db_user;
	private $db_pass;
	private $db_host;
	private $config;

	public function __construct () {

		$this->config = new Config;

		$this->db_name = $this->config->db_name;

		$this->db_host = $this->config->db_host;

		$this->create_connection();

	}

	public function __destruct()
	{
		mysqli_close($this->connection);
	}

	private function setUser() {
		$this->last_user_index++;
		if(!isset($this->config->db_users[$this->last_user_index])) {
			$this->last_user_index = 0;
		}
		$this->db_user = $this->config->db_users[$this->last_user_index]["username"] ?? "";

		$this->db_pass = $this->config->db_users[$this->last_user_index]["password"] ?? "";
	}

	private function create_connection() {

		$this->setUser();

		$this->connection = mysqli_init();

		$host    = $this->db_host;
		$port    = null;
		$socket  = null;
		$is_ipv6 = false;

		$host_data = $this->parse_db_host( $this->db_host );
		if ( $host_data ) {
			list( $host, $port, $socket, $is_ipv6 ) = $host_data;
		}

		/*
		 * If using the `mysqlnd` library, the IPv6 address needs to be
		 * enclosed in square brackets, whereas it doesn't while using the
		 * `libmysqlclient` library.
		 * @see https://bugs.php.net/bug.php?id=67563
		 */
		if ( $is_ipv6 && extension_loaded( 'mysqlnd' ) ) {
			$host = "[$host]";
		}

		$client_flags = 0;//defined( 'MYSQL_CLIENT_FLAGS' ) ? MYSQL_CLIENT_FLAGS : 0;

		if ( $this->config->devmode ) {
			mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
			$this->connection->real_connect( $host, $this->db_user, $this->db_pass, null, $port, $socket, $client_flags );
		} else {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@$this->connection->real_connect( $host, $this->db_user, $this->db_pass, null, $port, $socket, $client_flags );
		}

		if ( $this->connection->connect_errno ) {
			$this->connection = null;
		}

		if ( $this->connection ) {

			$this->has_connected = true;

			$this->ready = true;
			$this->select_db( $this->db_name, $this->connection );

			return true;
		}

		return false;
	} //create_connection()



	/**
	 * Selects a database using the current database connection.
	 *
	 * The database name will be changed based on the current database
	 * connection. On failure, the execution will bail and display an DB error.
	 *
	 * @since 0.71
	 *
	 * @param string        $db  MySQL database name
	 * @param resource|null $dbh Optional link identifier.
	 */
	public function select_db( $db, $connection = null ) {
		if ( is_null( $connection ) ) {
			$connection = $this->connection;
		}

		$success = mysqli_select_db( $connection, $db );

		if ( ! $success ) {
			$this->ready = false;
			var_dump( 'We were able to connect to the database server (which means your username and password is okay) but not able to select the '.$db.' database.' );
		}
		
	}

	/**
	 * Parse the DB_HOST setting to interpret it for mysqli_real_connect.
	 *
	 * mysqli_real_connect doesn't support the host param including a port or
	 * socket like mysql_connect does. This duplicates how mysql_connect detects
	 * a port and/or socket file.
	 *
	 * @since 4.9.0
	 *
	 * @param string $host The DB_HOST setting to parse.
	 * @return array|bool Array containing the host, the port, the socket and whether
	 *                    it is an IPv6 address, in that order. If $host couldn't be parsed,
	 *                    returns false.
	 */
	public function parse_db_host( $host ) {
		$port    = null;
		$socket  = null;
		$is_ipv6 = false;

		// First peel off the socket parameter from the right, if it exists.
		$socket_pos = strpos( $host, ':/' );
		if ( $socket_pos !== false ) {
			$socket = substr( $host, $socket_pos + 1 );
			$host   = substr( $host, 0, $socket_pos );
		}

		// We need to check for an IPv6 address first.
		// An IPv6 address will always contain at least two colons.
		if ( substr_count( $host, ':' ) > 1 ) {
			$pattern = '#^(?:\[)?(?P<host>[0-9a-fA-F:]+)(?:\]:(?P<port>[\d]+))?#';
			$is_ipv6 = true;
		} else {
			// We seem to be dealing with an IPv4 address.
			$pattern = '#^(?P<host>[^:/]*)(?::(?P<port>[\d]+))?#';
		}

		$matches = array();
		$result  = preg_match( $pattern, $host, $matches );

		if ( 1 !== $result ) {
			// Couldn't parse the address, bail.
			return false;
		}

		$host = '';
		foreach ( array( 'host', 'port' ) as $component ) {
			if ( ! empty( $matches[ $component ] ) ) {
				$$component = $matches[ $component ];
			}
		}

		return array( $host, $port, $socket, $is_ipv6 );
	}

	/**
	 * Retrieves the MySQL server version.
	 *
	 * @since 2.7.0
	 *
	 * @return null|string Null on failure, version number on success.
	 */
	public function db_version() {
		$server_info = mysqli_get_server_info( $this->connection );
		return preg_replace( '/[^0-9.].*/', '', $server_info );
	}

	/**
	 * Checks that the connection to the database is still up. If not, try to reconnect.
	 *
	 * If this function is unable to reconnect, it will forcibly die, or if after the
	 * the {@see 'template_redirect'} hook has been fired, return false instead.
	 *
	 * If $allow_bail is false, the lack of database connection will need
	 * to be handled manually.
	 *
	 * @since 3.9.0
	 *
	 * @param bool $allow_bail Optional. Allows the function to bail. Default true.
	 * @return bool|void True if the connection is up.
	 */
	public function check_connection() {
		if ( ! empty( $this->dbh ) && mysqli_ping( $this->dbh ) ) {
			return true;
		}

		for ( $tries = 1; $tries <= $this->reconnect_retries; $tries++ ) {
			// On the last try, re-enable warnings. We want to see a single instance of the
			// "unable to connect" message on the bail() screen, if it appears.
			// if ( $this->reconnect_retries === $tries && $this->config->devmode ) {
			// 	error_reporting( $error_reporting );
			// }

			if ( $this->create_connection() ) {

				return true;
			}

			sleep( 1 );
		}

		return false;
	}
}
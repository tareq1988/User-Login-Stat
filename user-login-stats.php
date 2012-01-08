<?php
/*
  Plugin Name: User Login Stats
  Plugin URI: http://tareq.weDevs.com/
  Description: Displays and monitors the user login statistics
  Author: Tareq Hasan
  Author URI: http://tareq.weDevs.com/
  Donate URI: http://tareq.weDevs.com/
  Version: 0.1
 */

class User_Login_Stats{

    private $table;

    function __construct() {
        global $wpdb;

        $this->table = $wpdb->prefix . "user_stats";

        register_activation_hook( __FILE__, array( &$this, 'install' ) );
        add_action( 'wp_login', array( &$this, 'login_update' ) );
        add_action( 'wp_head', array( &$this, 'check_user' ) );
        add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
    }

    /**
     * Create the table installing the plugin
     * 
     * @global type $wpdb 
     */
    function install() {
        global $wpdb;

        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
             `id` int(11) NOT NULL AUTO_INCREMENT,
             `date` date NOT NULL,
             `count` int(11) NOT NULL,
             KEY `id` (`id`)
            ) ENGINE=MyISAM";

        $wpdb->query( $sql );
    }

    /**
     * Run the function when the user logs in
     * 
     * If the users last login date is not today, update the visit count
     * and update the last login
     * 
     * @param type $login 
     */
    function login_update( $login ) {
        $user = get_user_by( 'login', $login );

        $last_login = ( isset( $user->last_login ) ) ? strtotime( $user->last_login ) : 0;
        $last_login_date = date( 'Y-m-d', $last_login );

        //if the user already loggedin today, skip him
        if( $last_login_date != date( 'Y-m-d', time() ) ) {
            $this->update( $user->ID );
        }
    }

    /**
     * Runs everytime to check if the user's last login time is more than 24 hours
     * 
     * This function runs on `wp_head` hook and works for only loggedin users
     */
    function check_user() {
        $current_user = wp_get_current_user();

        if( $current_user->ID ) {
            $last_login = ( isset( $current_user->last_login ) ) ? strtotime( $current_user->last_login ) : 0;

            $duration = 24 * 60 * 60; //24hours
            if( (time() - $last_login ) > $duration ) {
                $this->update( $current_user->ID );
            }
        }
    }

    /**
     * Update the database and user last login
     * 
     * If a row is already in the table, increase the count. Otherwise create 
     * a new row and store 1 as the value
     * 
     * @global type $wpdb
     * @param type $user_id 
     */
    function update( $user_id ) {
        global $wpdb;

        //if any rows found, increase the count, else insert new row
        $today = date( 'Y-m-d', time() );
        $row = $wpdb->get_row( "SELECT `count` FROM {$this->table} WHERE `date`='$today'" );
        if( $row ) {
            $wpdb->query( "UPDATE {$this->table} SET `count`=`count`+1 WHERE `date`='$today'" );
        } else {
            $wpdb->insert( $this->table, array(
                'date' => $today,
                'count' => 1
            ) );
        }

        //update user last login
        update_user_meta( $user_id, 'last_login', gmdate( 'Y-m-d H:i:s' ) );
    }

    /**
     * Adds the admin panel menu to the settings main menu
     */
    function admin_menu() {
        add_submenu_page( 'options-general.php', 'User Login Stats', 'User Login Stats', 'administrator', 'user_stats', array( $this, 'admin_page' ) );
    }

    /**
     * Displays the statistics in the admin area
     * 
     * @global type $wpdb
     * @global type $userdata 
     */
    function admin_page() {
        global $wpdb, $userdata;

        $pagenum = ( isset( $_GET['pagenum'] ) ) ? absint( $_GET['pagenum'] ) : 1;
        $limit = 30;
        $offset = ( $pagenum - 1 ) * $limit;

        $sql = "SELECT * FROM {$this->table} ORDER BY `date` DESC LIMIT $offset, $limit";
        $table = $wpdb->get_results( $sql );

        $total_users = count_users();

        //{$this->table}
        $week = $wpdb->get_var( "SELECT sum( `count` ) FROM `{$this->table}` WHERE `date` >= ( DATE_SUB( CURRENT_DATE, INTERVAL 7 DAY ) )" );
        $month = $wpdb->get_var();
        $six_month = $wpdb->get_var();
        $year = $wpdb->get_var();
        //var_dump( $total_users);
        //update_user_meta( $userdata->ID, 'last_login', '' );
        //var_dump( $userdata->last_login );
        ?>
        <div class="wrap">
            <h2><?php _e( 'User Login Statistics' ); ?></h2>

            <table class="widefat">
                <thead>
                    <tr valign="top">
                        <th scope="col"><?php _e( 'Date' ); ?></th>
                        <th scope="col"><?php _e( 'Count' ); ?></th>
                    </tr>
                </thead>
                <?php
                if( $table ) {
                    foreach ($table as $row) {
                        ?>
                        <tr>
                            <td><?php echo $row->date; ?></td>
                            <td><?php echo $row->count; ?></td>
                        </tr>

                        <?php
                    } //foreach
                } else {
                    ?>
                    <tr>
                        <td colspan="7"><?php _e( 'Nothing found' ); ?></td>
                    </tr>
                <?php } ?>
            </table>

        </div>

        <?php
        $total = $wpdb->get_var( "SELECT COUNT(`id`) FROM {$this->table}" );
        $num_of_pages = ceil( $total / $limit );
        $page_links = paginate_links( array(
            'base' => add_query_arg( 'pagenum', '%#%' ),
            'format' => '',
            'prev_text' => __( '&laquo;', 'aag' ),
            'next_text' => __( '&raquo;', 'aag' ),
            'total' => $num_of_pages,
            'current' => $pagenum
                ) );

        if( $page_links ) {
            echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' . $page_links . '</div></div>';
        }
    }

}

$online_stats = new User_Login_Stats();

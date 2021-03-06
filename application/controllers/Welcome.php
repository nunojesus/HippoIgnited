<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once BASEPATH. "autoload.php" ;

class Welcome extends CI_Controller 
{

    public function loadview( $view, $data = array() )
    {
        $data['controller'] = 'welcome';
        $this->template->set( 'header', 'header.php' );
        $this->template->load($view, $data);
    }

    public function index()
    {
        $this->loadview('index.php');
    }

    public function login( )
    {
        $login = __get__( $_POST, 'username', '' );
        $pass = __get__($_POST, 'pass' );

        if( ! ($login && $pass ) )
        {
            echo printWarning( "Empty username or password!" );
            redirect( "welcome");
            return;
        }

        // If user use @instem.ncbs.res.in or @ncbs.res.in, ignore it.
        $ldap = explode( '@', $login)[0];

        $this->session->set_userdata( 'AUTHENTICATED', false);
        $this->session->set_userdata( 'WHOAMI', $login );

        // Check if ldap is available. If it is use LDAP else fallback to imap based 
        // authentication.
        $auth = false;
        if( ldapAlive( 'ldap.ncbs.res.in' ) )
            $auth = authenticateUsingLDAP( $ldap, $pass );
        else
        {
            // Try login using IMAP.
            $auth = authenticateUsingIMAP( $ldap, $pass );
            if( ! $auth )
            {
                $this->session->set_flashdata( 'error', "Loging unsucessful. Try again!" );
                redirect( "welcome" );
            }
        }

        if( $auth )
        {
            $this->session->set_userdata( 'AUTHENTICATED', true);
            $this->session->set_userdata( 'WHOAMI', $login );
            $ldapInfo = getUserInfoFromLdap( $ldap );

            $email = '';
            $type = 'UNKNOWN';
            if( $ldapInfo )
            {
                $email = $ldapInfo[ 'email' ];
                $this->session->set_userdata( 'email', $email );
                $type = __get__( $ldapInfo, 'title', 'UNKNOWN' );
            }

            // In any case, create a entry in database.
            createUserOrUpdateLogin( $ldap, $ldapInfo, $type );

            // Update email id.
            $res = updateTable( 'logins', 'login', 'email'
                , array( 'login' => $ldap, 'email' => $email )
            );

            $this->session->set_flashdata( 'success', "Loging sucessful.!" );
            redirect( "user/home" );
        }
        else 
        {
            $this->session->set_flashdata( 'error', "Loging unsucessful. Try again!" );
            redirect( "welcome" );
        }
    }
}

?>

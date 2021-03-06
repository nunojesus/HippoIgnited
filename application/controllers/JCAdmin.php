<?php
require_once BASEPATH.'autoload.php';
require_once BASEPATH.'extra/jc.php';

trait JCAdmin
{
    // Views.
    public function jc_admin_edit_upcoming_presentation( )
    {
        $this->load_user_view( 'user_jc_admin_edit_upcoming_presentation' );
    }

    public function jcadmin( string $arg='' )
    {
        $this->load_user_view( 'user_jc_admin.php' );
    }

    public function jc_admin_reschedule_request( )
    {
        $this->load_user_view( "user_jc_admin_edit_jc_request" );
    }

    // Actions.
    public function jc_admin( $arg )
    {
        $task = $_POST['response'];
        if( $task == 'transfer_admin' )
        {
            $this->transfer_admin_role( );
            return;
        }
    }


    public function jc_request_action( )
    {
        $action = strtolower( __get__( $_POST, 'response', '' ) );
        if( ! $action )
        {
            redirect( "user/jcadmin");
            return;
        }
        
        else if( $action == 'reschedule' )
        {
            $this->jc_admin_reschedule_request( );
            return;
        }
        else if($action == 'delete')
        {
            $_POST[ 'status' ] = 'CANCELLED';
            $res = updateTable( 'jc_requests', 'id', 'status', $_POST);
            if( $res )
            {
                $entry = getTableEntry( 'jc_requests', 'id', $_POST );
                $presenter = getLoginInfo( $entry[ 'presenter' ] );
                $entryHTML = arrayToVerticalTableHTML($entry, 'info');
                $msg = "<p>Dear " . arrayToName( $presenter ) . "</p>";
                $msg .= "<p>Your presentation request has been cancelled by admin.
                    the latest entry is following. </p>";
                $msg .= $entryHTML;

                $subject = 'Your presentation request is CANCELLED by JC admin';
                $to = $presenter['email'];
                $cclist = 'jccoords@ncbs.res.in,hippo@lists.ncbs.res.in';
                $res = sendHTMLEmail( $msg, $subject, $to, $cclist );
                if( $res )
                {
                    flashMessage( 'Successfully updated presentation entry.' );
                    goToPage( 'user/jcadmin' );
                    return;
                }
            }
        }
        else if( $action == 'DO_NOTHING' )
        {
            redirect( 'user/jcadmin' );
            return;
        }
        else
        {
            flashMessage( "Unknown/unsupported action $action" );
        }

        redirect( "user/jcadmin" );
    }

    public function edit_jc_request( )
    {
        if( __get__( $_POST, 'id', null ) )
        {
            echo '<h1>Edit presentation request</h1>';
            $editables = 'date';
            if( __get__( $_POST, 'response', '' ) == 'Reschedule' )
                $editables = 'date';

            $entry = getTableEntry( 'jc_requests', 'id', $_POST );
            echo '<form action="#" method="post" accept-charset="utf-8">';
            echo dbTableToHTMLTable( 'jc_requests', $entry, $editables );
            echo '</form>';

            echo " <br /> <br /> ";
            echo "<strong>Afer your are finished editing </strong>";
            echo goBackToPageLink( 'user_jc_admin.php', 'Go Back' );
        }
        redirect( "user/jcadmin");
        return;
    }

    public function transfer_admin_role( )
    {
        $newAdmin = explode( '@', $_POST[ 'new_admin' ])[0];
        $error = '';
        if( ! getLoginInfo( $newAdmin ) )
        {
            $error =  "Error: $newAdmin is not a valid user.";
            printWarning( $error );
            redirect( "user/jcadmin");
            return false;
        }

        $jcID = $_POST[ 'jc_id'];
        // Check the new owner is already admin of this JC.
        $admins = getJCAdmins( $jcID );

        foreach( $admins as $admin )
        {
            if( $admin[ 'login' ] == $newAdmin )
            {
                $error = "$newAdmin is already ADMIN of this JC.  Please pick someone else.";
                printWarning( $error );
                break;
            }
        }

        if( ! $error )
        {
            // Add new user to admin.
            $data = [ 'login' => $newAdmin, 'subscription_type' => 'ADMIN'
                        , 'status' => 'VALID', 'jc_id' => $jcID ];

            $res = updateTable( 'jc_subscriptions', 'jc_id,login', 'status,subscription_type', $data );
            if( $res )
            {
                echo printInfo( "Sucessfully assigned $newAdmin as admin" );
                $subject = "You have been made ADMIN of $jcID by " . loginToText( whoAmI() );
                $msg = "<p>Dear " . loginToText( $newAdmin ) . "</p>";
                $msg .= "<p>You have been given admin rights to $jcID. In case this is
                    a mistake, " . loginToText( whoAmI( ) ) . ' is to blame!</p>';

                $cclist = 'hippo@lists.ncbs.res.in';
                $to = getLoginEmail( $newAdmin );
                $res = sendHTMLEmail( $msg, $subject, $to, $cclist );
                if( $res )
                    echo printInfo( "New admin has been notified" );
            }

            // Remove myself.
            $data = array( 'login' => whoAmI( ), 'subscription_type' => 'NORMAL'
                            , 'status' => 'VALID' , 'jc_id' => $jcID
                        );
            $res = updateTable( 'jc_subscriptions', 'login,jc_id', 'subscription_type', $data );

            if( $res )
                echo printInfo( "You are removed from ADMIN list of this JC" );
        }

        if( $error )
            printErrorSevere( "Some error occurred: $error" );

        return true;
    }

    public function jc_admin_reschedule_submit( )
    {
        $_POST[ 'status' ] = 'VALID';
        // In rare case the speaker 'A' may have one invalid entry on date D for
        // which this table is being updated.
        $res = updateTable( 'jc_requests', 'id', 'status,date', $_POST);
        if( $res )
        {
            $entry = getTableEntry( 'jc_requests', 'id', $_POST );
            $presenter = getLoginInfo( $entry[ 'presenter' ] );
            $entryHTML = arrayToVerticalTableHTML($entry, 'info');

            $msg = "<p>Dear " . arrayToName( $presenter ) . "</p>";
            $msg .= "<p>
                Your presentation request has been rescheduled by admin.
                the latest entry is following. Please mark you calendar.
                </p>";
            $msg .= $entryHTML;
            $subject = 'Your presentation request date is changed by JC admin';
            $to = $presenter['email'];
            $cclist = 'jccoords@ncbs.res.in,hippo@lists.ncbs.res.in';
            $res = sendHTMLEmail( $msg, $subject, $to, $cclist );
            flashMessage( 'Successfully updated presentation entry. Presenter has been notified (hopefully)' );
        }
        else
            printWarning( "Something went wrong" );

        redirect( "user/jcadmin" );
    }

}

?>

<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require_once BASEPATH.'autoload.php';
require_once BASEPATH.'calendar/methods.php';
include_once __DIR__ . '/AdminSharedFunc.php';

class Adminbmv extends CI_Controller
{
    // here we must check that user has permission to access this page.
    function __construct( )
    {
        parent::__construct();

        $roles = getRoles( $this->session->userdata("WHOAMI") );

        if(! in_array('BOOKMYVENUE_ADMIN', $roles))
        {
            flashMessage( "You don't have permission to access this page." . json_encode($roles) );
            redirect( "user/home");
            return;
        }
    }

    // PURE VIEWS
    function index()
    {
        $this->home();
    }

    public function loadview( $view, $data = array() )
    {
        // Make sure each view knows from which controller it has been called.
        $data['controller'] = 'adminbmv';
        $this->template->set( 'header', 'header.php' );
        $this->template->load( $view, $data );
    }

    // Show user home.
    public function home()
    {
        $this->loadview( 'bookmyvenue_admin' );
    }

    // BOOKING. Rest of the functions are in Booking traits.
    public function book( $arg = '' )
    {
        $this->manages_talks($arg);
    }

    public function review( )
    {
        $this->loadview( 'bookmyvenue_admin_request_review' );
    }

    public function venues( )
    {
        $data = array();
        $this->loadview( 'bookmyvenue_admin_manages_venues.php', $data);
    }

    public function email_and_docs($arg = '')
    {
        $this->loadview( 'admin_acad_email_and_docs.php' );
    }

    public function bookingrequest($arg = '')
    {
        $this->loadview( 'user_booking_request', $_POST );
    }

    public function block_venues($arg = '')
    {
        $this->loadview('bookmyvenue_admin_block_venues');
    }

    public function browse_talks($arg = '')
    {
        $this->loadview('bookmyvenue_admin_browse_events');
    }

    public function send_email( )
    {
        $this->loadview( 'admin_acad_send_email' );
    }

    public function edittalk( $id )
    {
        $data = [ 'talkid' => $id ];
        $this->loadview( 'admin_manages_talk_update', $data );
    }

    public function edit( )
    {
        $this->loadview( 'bookmyvenue_admin_edit', $_POST );
    }

    // VIEWS WITH ACTION.
    public function edit_action( )
    {
        // If is_public_event is set to NO then purge calendar id and event id.
        if( $_POST[ 'is_public_event' ] == 'NO' )
        {
            if( strlen( $_POST[ 'calendar_event_id' ] ) > 1 )
            {
                $_POST[ 'calendar_id' ] = '';
                $_POST[ 'calendar_event_id' ] = '';
            }
        }

        $where = 'gid,eid';
        if( "Yes" == $_POST['update_all'] )
            $where = 'gid';

        $res = updateTable( 'events', $where
            , array( 'is_public_event', 'class', 'title', 'description', 'status' )
            , $_POST 
        );

        if( $res )
        {
            $gid = $_POST['gid']; $eid = $_POST['eid'];
            flashMessage( "Succesfully update event(s) - $gid $eid." );
            // TODO: may be we can call calendar API here. currently we are relying 
            // on synchronize google calendar feature.
            redirect( 'adminbmv/home' );
            return;
        }
        else
            printWarning( "Above events were not updated" );

        redirect( "adminbmv/home" );
    }

    public function block_venue_submit($arg = '')
    {
        $venues = __get__( $_POST, 'venue' );
        $dates = __get__( $_POST, 'dates' );
        $dates = explode( ',', $dates );
        $startTime = __get__( $_POST, 'start_time' );
        $endTime = __get__( $_POST, 'end_time' );
        $gid = intval( getUniqueFieldValue( 'bookmyvenue_requests', 'gid' ) ) + 1;
        $rid = 0;
        foreach( $venues as $venue )
        {
            foreach( $dates as $date )
            {
                $date = dbDate( trim( $date ) );
                $title = __get__( $_POST, 'reason', '' );
                $class = __get__( $_POST, 'class', 'UNKNOWN' );

                if( strlen( $title ) < 8 )
                {
                    flashMessage( "Reason for blocking '$title' is too small.
                        At least 8 chars are required. Ignoring ...", 'warning' 
                        );
                    continue;
                }

                // We create a request and immediately approve it.
                $user = whoAmI( );
                $data = array(
                    'gid' => $gid, 'rid' => $rid
                    , 'date' => dbDate( $date )
                    , 'start_time' => $startTime
                    , 'end_time' => $endTime
                    , 'venue' => $venue
                    , 'title' => $title
                    , 'class' => $class
                    , 'description' => 'AUTO BOOKED BY Hippo'
                    , 'created_by' => whoAmI( )
                    , 'last_modified_on' => dbDateTime( 'now' )
                );

                $res = insertIntoTable( 'bookmyvenue_requests', array_keys( $data ), $data );
                $res = approveRequest( $gid, $rid );
                if( $res )
                    flashMessage( "Request $gid.$rid is approved and venue has been blocked." );
                $rid ++;
             }
        }
        redirect( 'adminbmv/block_venues' );
    }

    public function update_requests($arg = '')
    {
        $response = strtolower($_POST['response']);
        if( $response == 'edit' )
        {
            flashMessage( "Sorry. But you can not modfiy this request. You must 
                ask its owner to update the booking request.
                ");
            $this->loadview('admin_manages_talks');
        }
        else if( $response == 'delete' )
        {
            flashMessage("Deleting request is not implemented yet." );
            $this->loadview('admin_manages_talks');
        }
        else if($response == 'do_nothing')
        {
            flashMessage("User cancelled the last operation.");
            $this->loadview('admin_manages_talks');
        }
        else
        {
            flashMessage( "$response is not implemented yet.");
            $this->loadview('admin_manages_talks');
        }
    }

    // Set the controller which called it. Since this view can be called by acad
    // admin as well.
    public function manages_talks( $arg = '' )
    {
        $this->loadview( 'admin_manages_talks.php' );
    }

    // ACTIONS
    public function synchronize_calendar( )
    {
        $res = synchronize_google_calendar( );
        redirect( 'adminbmv/home' );
    }

    public function venues_action($arg='')
    {
        $response = __get__( $_POST, 'response', '' );
        if( $response == 'update' )
        {
            $res = updateTable( 
                    'venues'
                    , 'id'
                    , 'name,institute,building_name,floor,location,type,strength,' 
                        . 'distance_from_ncbs,has_projector,' 
                        . 'suitable_for_conference,has_skype'
                    , $_POST
                );
            if( $res )
                flashMessage( "Venue " . $_POST[ 'id' ] . ' is updated successful' );
            else
                flashMessage( 'Failed to update venue ' . $_POST[ 'id ' ] );
        }
        else if( $response == 'add new' ) 
        {
            if( strlen( $_POST[ 'id' ] ) < 2  )
                flashMessage( "The venue id is too short to be legal." );
            else
            {
                $res = insertIntoTable( 
                        'venues'
                        , 'id,name,institute,building_name,floor,location,type,strength,' 
                            . 'distance_from_ncbs,has_projector,' 
                            . 'suitable_for_conference,has_skype'
                        , $_POST
                    );

                if( $res )
                    flashMessage( "Venue " . $_POST[ 'id' ] . ' is successfully added.' );
                else
                    flashMessage( 'Failed to added venue ' . $_POST[ 'id ' ] );
            }
        }
        else if( $response == 'delete' ) 
        {
            $res = deleteFromTable( 'venues' , 'id' , $_POST);
            if( $res )
                flashMessage( "Venue " . $_POST[ 'id' ] . ' is successfully deleted.' );
            else
                flashMessage( 'Failed to added venue ' . $_POST[ 'id ' ] );
        }
        else if( $response == 'DO_NOTHING' ) 
        {
            flashMessage( "User said DO NOTHING. So going back!" );
            redirect( 'adminbmv/venues' );
            return;
        }
        else
            flashMessage( "Unknown command from user $response." );

        redirect('adminbmv/venues');
        return;
    }

    // Views with action.
    public function request_review( )
    {
        $whatToDo = $_POST['response'];
        $isPublic = $_POST['isPublic'];
        $warningMsg = '';

        // If admin is rejecting and have not given any confirmation, ask for it.
        if( $whatToDo == 'REJECT' )
        {
            // If no valid response is given, rejection of request is not possible.
            if( strlen( $_POST[ 'reason' ] ) < 5 )
            {
                flashMessage( "Before you can reject a request, you must provide
                    a valid reason (more than 5 characters long)" );
                redirect("adminbmv/home");
                return;
            }
        }

        // Else start prepare email.
        $msg = p("Your booking request has been acted upon by '" . whoAmI() . "'." );
        $msg .= '<table border="0">';

        $events = $_POST['events'];
        $userEmail = '';
        $eventGroupTitle = '';

        if( count( $events ) < 1 )
        {
            flashMessage( "I could not find an event.", 'warning');
            redirect("adminbmv/home");
            return;
        }

        $group = array( );
        $err = '';
        foreach( $events as $event )
        {
            $event = explode( '.', $event );
            $gid = $event[0]; $rid = $event[1];

            // Get event info from gid and rid of event as passed to $_POST.
            $eventInfo = getRequestById( $gid, $rid );
            if( ! $eventInfo )
            {
                $warningMsg .= p( "No booking request found for gid $gid and rid $rid." );
                continue;
            }

            $userEmail = getLoginEmail(  $eventInfo[ 'created_by' ] );
            $eventText = eventToText( $eventInfo );

            $group[] = $eventInfo;
            $eventGroupTitle = $eventInfo[ 'title' ];

            if( $whatToDo == 'APPROVE' )
                $status = 'APPROVED';
            else
                $status = $whatToDo . 'ED';

            $res = actOnRequest( $gid, $rid, $whatToDo );
            if(! $res)
            {
                $warningMsg .= p("Failed to act on request $event.");
                continue;
            }

            // Check if the status request is changed. If not there is some
            // error.
            $req = getRequestById( $gid, $rid );
            if( $req['status'] != $status )
            {
                $warningMsg .= p( "Failed to $status of request $gid.$rid", true );
                continue;
            }

            $msg .= "<tr><td> $eventText </td><td>". $status ."</td></tr>";
            changeIfEventIsPublic( $gid, $rid, $isPublic );
        }

        $msg .= "</table>";

        // Append user email to front.
        $email = p("Dear " . loginToText( $group[0]['created_by' ], true )) . $msg;

        if( $warningMsg )
        {
            $email .= p( "Also note the following glitch. It is probably an important imformation." );
            $email .= $warningMsg;
        }

        // Name of the admin to append to the email.
        $admin = getLoginEmail( whoAmI() );

        if( $whatToDo == 'REJECT' && strlen( $_POST[ 'reason' ] ) > 5 )
        {
            $email .= p("Following reason was given by $admin");
            $email .= $_POST[ 'reason' ];
        }

        if( $warningMsg )
            printWarning( $warningMsg );
        else
        {
            flashMessage( "Successfuly reviewed '$eventGroupTitle'." );
            $res = sendHTMLEmail( $email
                , "Your booking request '$eventGroupTitle' has been $status"
                , $userEmail
                , 'hippo@lists.ncbs.res.in'
            );
        }
        redirect( 'adminbmv/home' );
    }

    // MANAGES TALK
    public function deletetalk($id)
    {
        $response = $_POST['response'];
        if( $response == 'DO_NOTHING' )
        {
            flashMessage( "User cancelled.");
            redirect( 'adminbmv/manages_talks');
        }

        // Delete this entry from talks.
        $data = array( 'id' => $id );
        $res = deleteFromTable( 'talks', 'id', $data );
        if( $res )
        {
            flashMessage( 'Successfully deleted talk' );
            $success = true;
            $externalId = getTalkExternalId( $id );
            $events = getTableEntries( 'events'
                , 'external_id', "external_id='$externalId' AND status='VALID'" 
            );
            $requests = getTableEntries( 'bookmyvenue_requests'
                , 'external_id', "external_id='$externalId' AND status='PENDING'" 
            );
            foreach( $events as $e )
            {
                echo printInfo( "Cancelling associated booking." );
                echo arrayToTableHTML( $e, 'info' );
                $e[ 'status' ] = 'CANCELLED';
                // Now cancel this talk in requests, if there is any.
                $res = updateTable( 'events', 'external_id', 'status', $e );
            }

            foreach( $requests as $r )
            {
                echo printInfo( "Cancelling associated booking request " );
                echo arrayToTableHTML( $r, 'info' );

                $r[ 'status' ] = 'CANCELLED';
                $res = updateTable( 'bookmyvenue_requests', 'external_id', 'status', $r);
            }

            // /* VALIDATION: Check the bookings are deleted  */
            $events = getTableEntries( 'events'
                , 'external_id', "external_id='$externalId' AND status='VALID'"
            );
            $requests = getTableEntries( 'bookmyvenue_requests'
                , 'external_id', "external_id='$externalId' AND status='VALID'"
            );
            assert( ! $events );
            assert( ! $requests );
            flashMessage( "Successfully deleted related events/requests of talk id $id." );
        }
        else
            printWarning( "Failed to delete the talk." );

        redirect('adminbmv/manages_talks');
    }

    public function updatetalk( $id )
    {
        echo printInfo( "Here you can only change the host, class, title and description
            of the talk." );

        $data = array('id' => $id );
        $talk = getTableEntry( 'talks', 'id', $data );

        echo '<form method="post" action="admin_acad_manages_talks_action_update.php">';
        echo dbTableToHTMLTable('talks', $talk
            , 'class,coordinator,host,title,description'
            , 'submit');
        echo '</form>';
    }

    public function scheduletalk($id)
    {
        // We are sending this to quickbook.php as GET request. Only external_id is 
        // sent to page.

        $external_id = getTalkExternalId( $id );
        $query = "&external_id=".$external_id;

        $data = array( 
            'external_id' => $external_id
            , 'controller', 'adminbmv'
            );
        $this->loadview('user_book', $data );
    }

    public function approve( )
    {
        $gid = $_POST['gid'];
        $rid = $_POST['rid'];
        $res = actOnRequest( $gid, $rid, 'APPROVE', true );
        if( $res )
            flashMessage( "Request $gid.$rid is approved and venue has been blocked." );
        else
            printErrorSevere("Could not approve request $gid.$rid.");

        redirect( 'adminbmv/home' );
    }

    public function send_email_action()
    {
        $res = admin_send_email( $_POST );
        if( $res['error'] )
            printWarning( $res['error'] );
        else
            flashMessage( "Sucessfully sent email. " . $res['message'] );

        redirect( "adminbmv/manages_talks" );
    }

    public function update_talk_action( )
    {
        $msg = admin_update_talk( $_POST );
        redirect( "adminbmv/manages_talks" );
    }

}

?>

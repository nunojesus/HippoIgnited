<?php
include_once BASEPATH. 'autoload.php';

function loginForm()
{
    $table = "";
    $table .= '<form action="' . site_url('/welcome/login').'" method="post" >';
    $table .= '<table class="login_main">';
    $table .= '<tr><td><input type="text" name="username" id="username" 
        placeholder="NCBS/Instem Username" /> </td></tr>';
    $table .= '<tr><td> <input type="password"  name="pass" 
        id="pass" placeholder="Password" > </td></tr>';
    $table .= '<tr><td> 
            <input style="float:right" type="submit" name="response" value="Login" /> 
        </td></tr>';
    $table .= '</table>';
    $table .= '</form>';
    return $table;
}

// Now create a login form.
echo loginForm();

// Show background image only on index.php page.
$thisPage = basename( $_SERVER[ 'PHP_SELF' ] );
if( strpos( $thisPage, 'welcome' ) !== false )
{

    // Select one image from directory _backgrounds.
    // NOTE: In url leading ./../ is important since the url has to be relative
    // to application/views. Not sure why ./../.. did not work.
    // NOTE: When rewrite engine is on; you may have to teak this path a bit.
    $background = random_jpeg( "temp/_backgrounds" );
    if( $background )
    {
        echo '<script type="text/javascript">
            window.onload = function() {
                var div = document.getElementById("div_background_image");
                div.style.backgroundImage = "url(' . "./$background" . ')";
                div.style.width = "100%";
                div.style.height = "100%";
            }; 
            </script>';
    }
}

require_once __DIR__ . '/footer.php';

?>

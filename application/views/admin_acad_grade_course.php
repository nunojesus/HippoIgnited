<?php
require_once BASEPATH.'autoload.php';
echo userHTML( );

echo "<h2>Grades for <tt>$course_id</tt> $semester semester $year. </h2>";

$enrollments = getCourseRegistrations( $course_id, $year, $semester );

echo showEnrollmenTable( $enrollments, $tdintr = 5 );


$hide = 'registered_on,last_modified_on,grade_is_given_on';
$table = '<table class="info sortable">';
$ids = array( );                    /* Collect all student ids.  */
$grades = array( );
$allGradesHTML = '';                // Add all grades to table.

$table .= arrayToTHRow( $enrollments[0], 'info', $hide );
foreach( $enrollments as $enrol )
{
    $ids[ ] =  $enrol[ 'student_id' ];

    $table .= '<tr>';
    $table .= '<form action="'.site_url("adminacad/gradecourse_submit") . '" method="post">';
    $table .= arrayToRowHTML( $enrol, 'info', $hide, true, false );
    $table .= "<td>" . gradeSelect( $enrol['student_id'], $enrol[ 'grade' ] ) . "</td>";

    $gradeAction = 'Change';
    if( __get__($enrol,'grade', 'X') == 'X' )
        $gradeAction = colored('ASSIGN', 'blue');

    $table .= "<td> <button name='response' value='Assign One'>$gradeAction</button> </td>";

    $table .= '<input type="hidden" name="student_id" value="' . $enrol['student_id'] . '" >';
    $table .= '<input type="hidden" name="year" value="' . $enrol[ 'year'] . '" >';
    $table .= '<input type="hidden" name="semester" value="' . $enrol['semester'] . '" >';
    $table .= '<input type="hidden" name="course_id" value="' . $enrol['course_id'] . '" >';
    $table .= '</form>';
    $table .= '</tr>';
}

$table .= '<input type="hidden" name="student_ids" value="' . implode(',', $ids) . '" >';
$table .= '</table>';

echo '<h2>Modify grades </h2>';
echo $table;


echo '<br />';
echo goBackToPageLink( 'adminacad/grades', 'Go back' );


?>

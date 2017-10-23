<?php
/*
 * Consultas modulo registro de notas.
 */
require_once(__DIR__ . '/../../../../config.php');
require_once $CFG->libdir.'/gradelib.php';
//require_once('../../../../querylib.php');
require_once $CFG->dirroot.'/grade/lib.php';
require_once $CFG->dirroot.'/grade/report/user/lib.php';
require_once $CFG->dirroot.'/blocks/ases/managers/lib/student_lib.php'; 
// require_once $CFG->dirroot.'/grade/report/grader/lib.php';
// require_once $CFG->dirroot.'/grade/lib.php';

///*********************************///
///*** Get info calificador methods ***///
///*********************************///

/*
 * Función que retorna un arreglo de todos los cursos donde hay matriculados estudiantes de una instancia determinada organizados segun su profesor.
 * @param $instanceid
 * @return Array 
 */

function get_courses_pilos($instanceid){
    global $DB;
    
    //Se consulta el programa al cual esta asociada la instancia
    $query_prog = "
        SELECT pgr.cod_univalle as cod
        FROM {talentospilos_instancia} inst
        INNER JOIN {talentospilos_programa} pgr ON inst.id_programa = pgr.id
        WHERE inst.id_instancia= $instanceid";
    
    $prog = $DB->get_record_sql($query_prog)->cod;    
   
    //Si el código del programa es 1008 la cohorte comenzará por SP y si no, empezará por el código del programa
    if($prog === '1008'){
        $cohort = 'SP';
    }else{
        $cohort = $prog;
    }
    $query_semestre = "SELECT nombre FROM {talentospilos_semestre} WHERE id = (SELECT MAX(id) FROM {talentospilos_semestre})";
    $sem = $DB->get_record_sql($query_semestre)->nombre;

    $año = substr($sem,0,4);

    if(substr($sem,4,1) == 'A'){
        $semestre = $año.'02';
    }else if(substr($sem,4,1) == 'B'){
        $semestre = $año.'08';
    }
    //print_r($semestre);
    $query_courses = "
        SELECT DISTINCT curso.id,
                        curso.fullname,
                        curso.shortname,

          (SELECT concat_ws(' ',firstname,lastname) AS fullname
           FROM
             (SELECT usuario.firstname,
                     usuario.lastname,
                     userenrol.timecreated
              FROM {course} cursoP
              INNER JOIN {context} cont ON cont.instanceid = cursoP.id
              INNER JOIN {role_assignments} rol ON cont.id = rol.contextid
              INNER JOIN {user} usuario ON rol.userid = usuario.id
              INNER JOIN {enrol} enrole ON cursoP.id = enrole.courseid
              INNER JOIN {user_enrolments} userenrol ON (enrole.id = userenrol.enrolid
                                                           AND usuario.id = userenrol.userid)
              WHERE cont.contextlevel = 50
                AND rol.roleid = 3
                AND cursoP.id = curso.id
              ORDER BY userenrol.timecreated ASC
              LIMIT 1) AS subc) AS nombre
        FROM {course} curso
        INNER JOIN {enrol} ROLE ON curso.id = role.courseid
        INNER JOIN {user_enrolments} enrols ON enrols.enrolid = role.id
        WHERE SUBSTRING(curso.shortname FROM 15 FOR 6) = '$semestre' AND enrols.userid IN
            (SELECT user_m.id
     FROM  mdl_user user_m
     INNER JOIN mdl_user_info_data data ON data.userid = user_m.id
     INNER JOIN mdl_user_info_field field ON data.fieldid = field.id
     INNER JOIN mdl_talentospilos_usuario user_t ON data.data = CAST(user_t.id AS VARCHAR)
     INNER JOIN mdl_talentospilos_est_estadoases estado_u ON user_t.id = estado_u.id_estudiante
     INNER JOIN mdl_talentospilos_estados_ases estados ON estados.id = estado_u.id_estado_ases
     WHERE estados.nombre = 'ACTIVO/SEGUIMIENTO' AND field.shortname = 'idtalentos'

    INTERSECT

    SELECT user_m.id
    FROM mdl_user user_m INNER JOIN mdl_cohort_members memb ON user_m.id = memb.userid INNER JOIN mdl_cohort cohorte ON memb.cohortid = cohorte.id
    WHERE cohorte.idnumber LIKE '$cohort%')";
    $result = $DB->get_records_sql($query_courses);
    
    $result = processInfo($result);
    return $result;
}
 //get_courses_pilos(19);

/*
 * Función que retorna un arreglo de profesores, dado un objeto consulta
 * @param $info
 * @return Array con el siguiente formato: array("$nomProfesor" => array(array("id" => $id_curso, "nombre"=>$nom_curso,"shortname"=>$shortname_curso), array(...)))
 */
function processInfo($info){
    $profesores = [];
    
    foreach ($info as $course) {
        $profesor = $course->nombre;
        $id = $course->id;
        $nombre = $course->fullname;
        $shortname = $course->shortname;
        $curso=["id"=>$id,"nombre"=>$nombre,"shortname"=>$shortname];
        if(!isset($profesores[$profesor])){
            $profesores[$profesor] = [];
        }
        
        array_push($profesores[$profesor],$curso) ;
    }
    return $profesores;
}


?>

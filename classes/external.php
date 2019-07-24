<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    local_exportquestionary
 * @copyright  Pimenko 2019
 * @author     Revenu Sylvain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/local/exportquestionary/classes/exportquestionnaire.php');
require_once($CFG->libdir . '/dataformatlib.php');

class local_exportquestionary_external extends external_api {

    /**
     * Returns data users list
     *
     * @param $title
     *
     * @return mixed $data
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function exportcsvresponses( $title ) {
        global $DB;

        $params = self::validate_parameters(
                self::exportcsvresponses_parameters(),
                [
                        'title' => $title
                ]
        );

        // Selected template name
        $questionarys = $DB->get_records_sql(
                'SELECT 
            * 
          FROM {pimenkoquestionnaire_survey} qs 
          LEFT JOIN {pimenkoquestionnaire} q 
            ON qs.id = q.sid
          WHERE ( qs.title = "' . $params['title'] . '" OR q.name = "' . $params['title'] . '" )
          AND qs.realm != "template"'
        );
        $csv = [];
        foreach ($questionarys as $questionary) {
            $course = $DB->get_record(
                    "course",
                    ["id" => $questionary->course]
            );
            $cm = get_coursemodule_from_instance(
                    "pimenkoquestionnaire",
                    $questionary->id,
                    $course->id
            );
            $questionary = new exportquestionnaire(
                    0,
                    $questionary,
                    $course,
                    $cm
            );
            if (count($csv) < 1) {
                $csv = $questionary->generate_csv(
                        null,
                        null,
                        null,
                        null,
                        null,
                        null
                );
                // Use the questionary name as the file name. Clean it and change any non-filename characters to '_'.
                $name = "export_questionnaire";
                $name = preg_replace(
                        "/[^A-Z0-9]+/i",
                        "_",
                        trim($name)
                );
            } else {
                $temp = $questionary->generate_csv(
                        null,
                        null,
                        null,
                        null,
                        null,
                        null
                );
                array_shift($temp);
                $csv = array_merge(
                        $csv,
                        $temp
                );
            }
        }

        $response = [];
        $response['name'] = $name;
        $response['data'] =  json_encode($csv);
        return $response;
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function exportcsvresponses_parameters() {
        $title = new external_value(
                PARAM_TEXT,
                'template title',
                VALUE_DEFAULT,
                ''
        );
        $params = [
                'title' => $title
        ];
        return new external_function_parameters($params);
    }

    /**
     * Returns description of method result value
     *
     * @return void
     */
    public static function exportcsvresponses_returns() {
        return null;
    }

    /**
     * Returns data users list
     *
     * @param $title
     *
     * @return mixed $data
     */
    public static function exportcsvreport( $title ) {
        global $DB;

        $params = self::validate_parameters(
                self::exportcsvreport_parameters(),
                [
                        'title' => $title
                ]
        );

        //generate CSV header
        $columns = [];
        $output = [];

        $options = ['course', 'shortname', 'summary', 'category', 'responsenumber', 'studentnumber', 'returnnumber'];
        foreach ($options as $option) {
            if (in_array(
                    $option,
                    [
                            'responsenumber',
                            'studentnumber',
                            'returnnumber'
                    ]
            )) {
                $columns[] = get_string(
                        $option,
                        'local_exportquestionary'
                );
            } else {
                $columns[] = get_string($option);
            }
        }

        array_push(
                $output,
                $columns
        );

        // Generate csv name
        $name = "export_questionnaire";
        $name = preg_replace(
                "/[^A-Z0-9]+/i",
                "_",
                trim($name)
        );

        // Selected template name
        $questionarys = $DB->get_records_sql(
                'SELECT 
            * 
          FROM {pimenkoquestionnaire_survey} qs 
          LEFT JOIN {pimenkoquestionnaire} q 
            ON qs.id = q.sid
          WHERE ( qs.title = "' . $params['title'] . '" OR q.name = "' . $params['title'] . '" )
          AND qs.realm != "template"'
        );

        foreach ($questionarys as $questionary) {
            $course = $DB->get_record(
                    "course",
                    ["id" => $questionary->course]
            );
            $row = self::generatecsv_row($course, $questionary);
            array_push(
                    $output,
                    $row
            );
        }

        $response = [];
        $response['name'] = $name;
        $response['data'] =  json_encode($output);
        return $response;
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function exportcsvreport_parameters() {
        $title = new external_value(
                PARAM_TEXT,
                'template title',
                VALUE_DEFAULT,
                ''
        );
        $params = [
                'title' => $title
        ];
        return new external_function_parameters($params);
    }

    public static function generatecsv_row( $course, $questionary ) {
        global $DB;
        //['course', 'shortname', 'summary', 'category', 'responsenumber', 'studentnumber', 'returnnumber']

        $sql = "SELECT
                (SELECT
                    COUNT(DISTINCT pr.id) as responsenumber
                FROM {pimenko_response} pr
                LEFT JOIN {pimenkoquestionnaire} pq
                    ON pr.pimenkoquestionnaireid = pq.id
                WHERE pr.pimenkoquestionnaireid = " . $questionary->id . ") as responsenumber,
                (SELECT
                    COUNT(DISTINCT u.id)
                FROM {user} u
                LEFT JOIN {user_enrolments} ue ON u.id = ue.userid
                LEFT JOIN {enrol} e ON ue.enrolid=e.id
                WHERE e.courseid=" . $course->id . " AND u.suspended = 0 AND u.deleted = 0) AS user_enrol";

        $data = $DB->get_record_sql($sql);
        if ($data->responsenumber > 0 && $data->user_enrol > 0) {
            $returnnumber = round(($data->responsenumber * 100) / $data->user_enrol,4);
        } else {
            $returnnumber = 0;
        }
        $row = [$course->fullname, $course->shortname, $course->summary, $course->coursecat, $data->responsenumber,
                $data->user_enrol, $returnnumber];

        return $row;
    }

    /**
     * Returns description of method result value
     *
     * @return void
     */
    public static function exportcsvreport_returns() {
        return null;
    }
}
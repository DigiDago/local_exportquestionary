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

require_once( $CFG->libdir . "/externallib.php" );
require_once( $CFG->libdir . '/csvlib.class.php' );
require_once( $CFG->dirroot . '/local/exportquestionary/classes/questionnaire.php' );
require_once( $CFG->libdir . '/dataformatlib.php' );

class local_exportquestionary_external extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function exportcsv_parameters() {
        $title  = new external_value(
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
     * Returns data users list
     *
     * @param $title
     *
     * @return mixed $data
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function exportcsv($title) {
        global $DB;

        $params = self::validate_parameters(
            self::exportcsv_parameters(),
            [
                'title' => $title
            ]
        );

        // Selected template name
        $questionarys = $DB->get_records_sql(
            'SELECT 
            * 
          FROM {questionnaire_survey} qs 
          LEFT JOIN {questionnaire} q 
            ON qs.id = q.sid
          WHERE ( qs.title = "' . $params['title'] . '" OR q.name = "' . $params['title'] . '" )
          AND qs.realm != "template"'
        );

        $csv = [];
        foreach ($questionarys as $questionary) {
            $course      = $DB->get_record(
                "course",
                [ "id" => $questionary->course ]
            );
            $cm          = get_coursemodule_from_instance(
                "questionnaire",
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
                $name = clean_param(
                    $questionary->title,
                    PARAM_FILE
                );
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

        $response         = [];
        $response['name'] = $name;
        $response['data'] = $csv;

        return $response;
    }


    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function exportcsv_returns() {
        return null;
    }
}
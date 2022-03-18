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
 * @package    mod_pimenkoquestionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/locallib.php');
require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/pimenkoquestionnaire.class.php');

class exportquestionnaire extends pimenkoquestionnaire {
    /**
     *
     * Generate CSV
     *
     * @param string $rid
     * @param string $userid
     * @param int $choicecodes
     * @param int $choicetext
     * @param        $currentgroupid
     * @param int $showincompletes
     *
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    final public function generate_csv(
        $rid = '', $userid = '', $choicecodes = 1, $choicetext = 0, $currentgroupid, $showincompletes = 0

    ): array {
        global $DB, $PAGE;

        $context = context_system::instance();
        $PAGE->set_context($context);

        raise_memory_limit('1G');

        $output = [];
        $stringother = get_string(
            'other',
            'pimenkoquestionnaire'
        );

        $config = get_config(
            'pimenkoquestionnaire',
            'downloadoptions'
        );
        $replace = 'course,shortname,summary,category,';
        $find = 'course,';
        $pos = strpos(
            $config,
            'course,'
        );
        $config = substr_replace(
            $config,
            $replace,
            $pos,
            strlen($find)
        );
        $options = empty($config) ? [] : explode(
            ',',
            $config
        );

        if ($showincompletes == 1) {
            $options[] = 'complete';
        }

        $columns = [];
        $types = [];

        foreach ($options as $option) {
            if (in_array(
                $option,
                [
                    'response',
                    'submitted',
                    'id'
                ]
            )) {
                $columns[] = get_string(
                    $option,
                    'pimenkoquestionnaire'
                );
                $types[] = 0;
            } else {
                $columns[] = format_string($option);
                $types[] = 1;
            }
        }

        $nbinfocols = count($columns);

        $idtocsvmap = [
            '0',
            // 0: unused
            '0',
            // 1: bool -> boolean
            '1',
            // 2: text -> string
            '1',
            // 3: essay -> string
            '0',
            // 4: radio -> string
            '0',
            // 5: check -> string
            '0',
            // 6: dropdn -> string
            '0',
            // 7: rating -> number
            '0',
            // 8: rate -> number
            '1',
            // 9: date -> string
            '0'
            // 10: numeric -> number.
        ];

        if (!$survey = $DB->get_record(
            'pimenkoquestionnaire_survey',
            ['id' => $this->survey->id]
        )) {
            print_error(
                'surveynotexists',
                'pimenkoquestionnaire'
            );
        }

        // Get all responses for this survey in one go.
        $allresponsesrs = $this->get_survey_all_responses(
            $rid,
            $userid,
            $currentgroupid,
            $showincompletes
        );

        // Do we have any questions of type RADIO, DROP, CHECKBOX OR RATE? If so lets get all their choices in one go.
        $choicetypes = $this->choice_types();

        // Get unique list of question types used in this survey.
        $uniquetypes = $this->get_survey_questiontypes();

        if (count(
                array_intersect(
                    $choicetypes,
                    $uniquetypes
                )
            ) > 0) {
            $choiceparams = [$this->survey->id];

            $choicesql = "
                SELECT DISTINCT c.id as cid, q.id as qid, q.precise AS precise, q.name, c.content, c.value
                  FROM {pimenko_question} q
                  JOIN {pimenko_quest_choice} c ON question_id = q.id
                 WHERE q.surveyid = ? ORDER BY cid ASC
            ";

            $choicerecords = $DB->get_records_sql(
                $choicesql,
                $choiceparams
            );
            $choicesbyqid = [];
            if (!empty($choicerecords)) {
                // Hash the options by question id.
                foreach ($choicerecords as $choicerecord) {
                    if (!isset($choicesbyqid[$choicerecord->qid])) {
                        // New question id detected, intialise empty array to store choices.
                        $choicesbyqid[$choicerecord->qid] = [];
                    }
                    $choicesbyqid[$choicerecord->qid][$choicerecord->cid] = $choicerecord;
                }
            }
        }

        $num = 1;

        $questionidcols = [];

        foreach ($this->questions as $question) {
            // Skip questions that aren't response capable.
            if (!isset($question->response)) {
                continue;
            }
            // Establish the table's field names.
            $qid = $question->id;
            $qpos = $question->position;
            $col = $question->name;
            $type = $question->type_id;
            if (in_array(
                $type,
                $choicetypes
            )) {
                /* single or multiple or rate */
                if (!isset($choicesbyqid[$qid])) {
                    throw new coding_exception(
                        'Choice question has no choices!',
                        'question id ' . $qid . ' of type ' . $type
                    );
                }
                $choices = $choicesbyqid[$qid];

                switch ($type) {
                    case QUESTEACHERSELECT:
                    case QUESRADIO: // Single.
                    case QUESDROP:
                        $columns[][$qpos] = $col;
                        $questionidcols[][$qpos] = $qid;
                        array_push($types, $idtocsvmap[$type]);
                        $thisnum = 1;
                        foreach ($choices as $choice) {
                            $content = $choice->content;
                            // If "Other" add a column for the actual "other" text entered.
                            if (preg_match('/^!other/', $content)) {
                                $col = $choice->name . '_' . $stringother;
                                $columns[][$qpos] = $col;
                                $questionidcols[][$qpos] = null;
                                array_push($types, '0');
                            }
                        }
                        break;
                    case QUESCHECK: // Multiple.
                        $thisnum = 1;
                        foreach ($choices as $choice) {
                            $content = $choice->content;
                            $modality = '';
                            $contents = pimenkoquestionnaire_choice_values($content);
                            if ($contents->modname) {
                                $modality = $contents->modname;
                            } else if ($contents->title) {
                                $modality = $contents->title;
                            } else {
                                $modality = strip_tags($contents->text);
                            }
                            $col = $choice->name . '->' . $modality;
                            $columns[][$qpos] = $col;
                            $questionidcols[][$qpos] = $qid . '_' . $choice->cid;
                            array_push($types, '0');
                            // If "Other" add a column for the "other" checkbox.
                            // Then add a column for the actual "other" text entered.
                            if (preg_match('/^!other/', $content)) {
                                $content = $stringother;
                                $col = $choice->name . '->[' . $content . ']';
                                $columns[][$qpos] = $col;
                                $questionidcols[][$qpos] = null;
                                array_push($types, '0');
                            }
                        }
                        break;
                    case QUESRATE: // Rate.
                        foreach ($choices as $choice) {
                            $nameddegrees = 0;
                            $modality = '';
                            $content = strip_tags(format_string($choice->content));
                            $osgood = false;
                            if ($choice->precise == 3) {
                                $osgood = true;
                            }
                            if (preg_match("/^[0-9]{1,3}=/", $content, $ndd)) {
                                $nameddegrees++;
                            } else {
                                if ($osgood) {
                                    list($contentleft, $contentright) = array_merge(preg_split('/[|]/', $content), [' ']);
                                    $contents = pimenkoquestionnaire_choice_values($contentleft);
                                    if ($contents->title) {
                                        $contentleft = $contents->title;
                                    }
                                    $contents = pimenkoquestionnaire_choice_values($contentright);
                                    if ($contents->title) {
                                        $contentright = $contents->title;
                                    }
                                    $modality = strip_tags($contentleft . '|' . $contentright);
                                    $modality = preg_replace("/[\r\n\t]/", ' ', $modality);
                                } else {
                                    $contents = pimenkoquestionnaire_choice_values($content);
                                    if ($contents->modname) {
                                        $modality = $contents->modname;
                                    } else if ($contents->title) {
                                        $modality = $contents->title;
                                    } else {
                                        $modality = strip_tags($contents->text);
                                        $modality = preg_replace("/[\r\n\t]/", ' ', $modality);
                                    }
                                }
                                $col = $choice->name . '->' . $modality;
                                $columns[][$qpos] = $col;
                                $questionidcols[][$qpos] = $qid . '_' . $choice->cid;
                                array_push($types, $idtocsvmap[$type]);
                            }
                        }
                        break;
                }
            } else {
                $columns[][$qpos] = $col;
                $questionidcols[][$qpos] = $qid;
                array_push(
                    $types,
                    $idtocsvmap[$type]
                );
            }
            $num++;
        }

        array_push(
            $output,
            $columns
        );

        $numrespcols = count($output[0]); // Number of columns used for storing question responses.

        // Flatten questionidcols.
        $tmparr = [];
        for ($c = 0; $c < $nbinfocols; $c++) {
            $tmparr[] = null; // Pad with non question columns.
        }
        foreach ($questionidcols as $i => $positions) {
            foreach ($positions as $position => $qid) {
                $tmparr[] = $qid;
            }
        }
        $questionidcols = $tmparr;

        // Create array of question positions hashed by question / question + choiceid.
        // And array of questions hashed by position.
        $questionpositions = [];
        $questionsbyposition = [];
        $p = 0;
        foreach ($questionidcols as $qid) {
            if ($qid === null) {
                // This is just padding, skip.
                $p++;
                continue;
            }
            $questionpositions[$qid] = $p;
            if (strpos(
                    $qid,
                    '_'
                ) !== false) {
                $tmparr = explode(
                    '_',
                    $qid
                );
                $questionid = $tmparr[0];
            } else {
                $questionid = $qid;
            }
            $questionsbyposition[$p] = $this->questions[$questionid];
            $p++;
        }

        $formatoptions = new stdClass();
        $formatoptions->filter = false;  // To prevent any filtering in CSV output.

        // Get textual versions of responses, add them to output at the correct col position.
        $prevresprow = false; // Previous response row.
        $row = [];
        foreach ($allresponsesrs as $responserow) {
            $rid = $responserow->rid;
            $qid = $responserow->question_id;

            // It's possible for a response to exist for a deleted question. Ignore these.
            if (!isset($this->questions[$qid])) {
                break;
            }

            $question = $this->questions[$qid];
            $qtype = intval($question->type_id);
            $questionobj = $this->questions[$qid];

            if ($prevresprow !== false && $prevresprow->rid !== $rid) {
                $output[] = $this->process_csv_row($row, $prevresprow, $currentgroupid, $questionsbyposition,
                    $nbinfocols, $numrespcols, $showincompletes);
                $row = [];
            }
            if ($qtype === QUESRATE || $qtype === QUESCHECK) {
                $key = $qid . '_' . $responserow->choice_id;
                $position = $questionpositions[$key];
                if ($qtype === QUESRATE) {
                    // Can't get choice value.
                    // For no reason in tab scale value is save as 1,2,3,4,5,6.
                    $choicetxt = $responserow->rankvalue + 1;
                } else {
                    $content = $choicesbyqid[$qid][$responserow->choice_id]->content;
                    if (preg_match('/^!other/', $content)) {
                        // If this is an "other" column, put the text entered in the next position.
                        $row[$position + 1] = $responserow->response;
                        $choicetxt = empty($responserow->choice_id) ? '0' : '1';
                    } else if (!empty($responserow->choice_id)) {
                        $choicetxt = '1';
                    } else {
                        $choicetxt = '0';
                    }
                }
                $responsetxt = $choicetxt;
                $row[$position] = $responsetxt;
            } else {
                $position = $questionpositions[$qid];
                if ($questionobj->has_choices()) {
                    // This is choice type question, so process as so.
                    $c = 0;
                    if (in_array(intval($question->type_id), $choicetypes)) {
                        $choices = $choicesbyqid[$qid];
                        // Get position of choice.
                        foreach ($choices as $choice) {
                            $c++;
                            if ($responserow->choice_id === $choice->cid) {
                                break;
                            }
                        }
                    }

                    $content = $choicesbyqid[$qid][$responserow->choice_id]->content;

                    if (preg_match('/^!other/', $content)) {
                        // If this has an "other" text, use it.
                        $responsetxt = preg_replace(["/^!other=/", "/^!other/"],
                            ['', get_string('other', 'pimenkoquestionnaire')], $content);
                        $responsetxt1 = $responserow->response;
                    } else if (($choicecodes == 1) && ($choicetext == 1)) {
                        if ($question->type_id == 11) {
                            $responsetxt = $content;
                        } else {
                            $responsetxt = $c . ' : ' . $content;
                        }
                    } else if ($choicecodes == 1) {
                        $responsetxt = $c;
                    } else {
                        $responsetxt = $content;
                    }

                } else if (intval($qtype) === QUESYESNO) {
                    // At this point, the boolean responses are returned as characters in the "response"
                    // field instead of "choice_id" for csv exports (CONTRIB-6436).
                    $responsetxt = $responserow->response === 'y' ? "1" : "0";
                } else {
                    // Strip potential html tags from modality name.
                    $responsetxt = $responserow->response;
                    if (!empty($responsetxt)) {
                        $responsetxt = $responserow->response;
                        $responsetxt = strip_tags($responsetxt);
                        $responsetxt = preg_replace("/[\r\n\t]/", ' ', $responsetxt);
                    }
                }
                if (isset($row[$position]) && $question->type_id == 11) {
                    $row[$position] = $row[$position] . " - " . $responsetxt;
                } else {
                    $row[$position] = $responsetxt;
                }
                // Check for "other" text and set it to the next position if present.
                if (!empty($responsetxt1)) {
                    $row[$position + 1] = $responsetxt1;
                    unset($responsetxt1);
                }
            }

            $prevresprow = $responserow;
        }

        if ($prevresprow !== false) {
            // Add final row to output. May not exist if no response data was ever present.
            $output[] = $this->process_csv_row(
                $row,
                $prevresprow,
                $currentgroupid,
                $questionsbyposition,
                $nbinfocols,
                $numrespcols,
                $showincompletes
            );
        }

        // Change table headers to incorporate actual question numbers.
        $numquestion = 0;
        $oldkey = 0;

        for ($i = $nbinfocols; $i < $numrespcols; $i++) {
            $sep = '';
            $thisoutput = current($output[0][$i]);
            $thiskey = key($output[0][$i]);
            // Case of unnamed rate single possible answer (full stop char is used for support).
            if (strstr(
                $thisoutput,
                '->.'
            )) {
                $thisoutput = str_replace(
                    '->.',
                    '',
                    $thisoutput
                );
            }
            // If variable is not named no separator needed between Question number and potential sub-variables.
            if ($thisoutput == '' || strstr(
                    $thisoutput,
                    '->.'
                ) || substr(
                    $thisoutput,
                    0,
                    2
                ) == '->' || substr(
                    $thisoutput,
                    0,
                    1
                ) == '_') {
                $sep = '';
            } else {
                $sep = '_';
            }
            if ($thiskey > $oldkey) {
                $oldkey = $thiskey;
                $numquestion++;
            }
            // Abbreviated modality name in multiple or rate questions (COLORS->blue=the color of the sky...).
            $pos = strpos(
                $thisoutput,
                '='
            );
            if ($pos) {
                $thisoutput = substr(
                    $thisoutput,
                    0,
                    $pos
                );
            }
            $out = 'Q' . sprintf(
                    "%02d",
                    $numquestion
                ) . $sep . $thisoutput;
            $output[0][$i] = $out;
        }

        return $output;
    }

    /**
     * Process individual row for csv output
     *
     * @param array $row
     * @param stdClass $resprow resultset row
     * @param int $currentgroupid
     * @param array $questionsbyposition
     * @param int $nbinfocols
     * @param int $numrespcols
     * @param int $showincompletes
     *
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    final public function process_csv_row(
        array &$row, stdClass $resprow, $currentgroupid, array &$questionsbyposition, $nbinfocols, $numrespcols,
        $showincompletes = 0
    ): array {
        global $DB;

        static $config = null;
        // If using an anonymous response, map users to unique user numbers so that number of unique anonymous users can be seen.
        static $anonumap = [];

        if ($config === null) {
            $config = get_config(
                'pimenkoquestionnaire',
                'downloadoptions'
            );
            $replace = 'course,shortname,summary,category,';
            $find = 'course,';
            $pos = strpos(
                $config,
                'course,'
            );
            $config = substr_replace(
                $config,
                $replace,
                $pos,
                strlen($find)
            );
        }
        $options = empty($config) ? [] : explode(
            ',',
            $config
        );

        if ($showincompletes == 1) {
            $options[] = 'complete';
        }

        $positioned = [];
        $user = new stdClass();
        foreach ($this->user_fields() as $userfield) {
            $user->$userfield = $resprow->$userfield;
        }
        $user->id = $resprow->userid;
        $isanonymous = ($this->respondenttype == 'anonymous');

        // Moodle:
        // Get the course name that this questionnaire belongs to.
        $ispublic = null;

        $version = get_config('mod_pimenkoquestionnaire', 'version');

        if ($version < '2018050100') {
            $ispublic = $this->survey->realm == 'public';
        } else {
            $ispublic = $this->survey_is_public();
        }
        if (!$ispublic) {
            $courseid = $this->course->id;
            $coursename = $this->course->fullname;
            $shortname = $this->course->shortname;
            $coursesummary = $this->course->summary;
            $coursecat = $DB->get_record(
                'course_categories',
                ['id' => $this->course->category]
            );
        } else {
            // For a public questionnaire, look for the course that used it.
            $sql = 'SELECT q.id, q.course, c.fullname, c.summary , cc.name as coursecat' . 'FROM {pimenko_response} qr ' .
                'INNER JOIN {pimenkoquestionnaire} q ON qr.questionnaireid = q.id ' .
                'INNER JOIN {course} c ON q.course = c.id ' . 'INNER JOIN {course_categories} cc ON c.category = cc.id ' .
                'WHERE qr.id = ? AND qr.complete = ? ';
            if ($record = $DB->get_record_sql(
                $sql,
                [
                    $resprow->rid,
                    'y'
                ]
            )) {
                $courseid = $record->course;
                $coursename = $record->fullname;
                $shortname = $record->shortname;
                $coursesummary = $record->summary;
                $coursecat = $record->coursecat;
            } else {
                $courseid = $this->course->id;
                $coursename = $this->course->fullname;
                $shortname = $this->course->shortname;
                $coursesummary = $this->course->summary;
                $coursecat = $this->course->coursecat;
            }
        }

        $coursecatpath = explode('/', $coursecat->path);
        if ($coursecatpath[2]) {
            $coursecatparent = $DB->get_record(
                'course_categories',
                ['id' => $coursecatpath[2]]
            );
            $coursecat = $coursecatparent->name;
        } else {
            $coursecat = $coursecat->name;
        }
        // Moodle:
        // Determine if the user is a member of a group in this course or not.
        // TODO - review for performance.
        $groupname = '';
        if (groups_get_activity_groupmode(
            $this->cm,
            $this->course
        )) {
            if ($currentgroupid > 0) {
                $groupname = groups_get_group_name($currentgroupid);
            } else {
                if ($user->id) {
                    if ($groups = groups_get_all_groups(
                        $courseid,
                        $user->id
                    )) {
                        foreach ($groups as $group) {
                            $groupname .= $group->name . ', ';
                        }
                        $groupname = substr(
                            $groupname,
                            0,
                            strlen($groupname) - 2
                        );
                    } else {
                        $groupname = ' (' . get_string('groupnonmembers') . ')';
                    }
                }
            }
        }

        $cohortname = "";
        $sql = "SELECT
                    GROUP_CONCAT(c.name SEPARATOR ' - ') as cohort
                FROM {cohort_members} cm
                INNER JOIN {cohort} c ON cm.cohortid = c.id
                INNER JOIN {enrol} e ON c.id = e.customint1 AND enrol = 'cohort' AND e.courseid = " . $courseid . "
                WHERE cm.userid = " . $user->id;
        $data = $DB->get_record_sql($sql);
        $cohortname = $data->cohort;

        if ($isanonymous) {
            if (!isset($anonumap[$user->id])) {
                $anonumap[$user->id] = count($anonumap) + 1;
            }
            $fullname = get_string(
                    'anonymous',
                    'pimenkoquestionnaire'
                ) . $anonumap[$user->id];
            $username = '';
            $uid = '';
        } else {
            $uid = $user->id;
            $fullname = fullname($user);
            $username = $user->username;
        }
        if (in_array(
            'response',
            $options
        )) {
            array_push(
                $positioned,
                $resprow->rid
            );
        }
        if (in_array(
            'submitted',
            $options
        )) {
            // For better compabitility & readability with Excel.
            $submitted = date(
                get_string(
                    'strfdateformatcsv',
                    'pimenkoquestionnaire'
                ),
                $resprow->submitted
            );
            array_push(
                $positioned,
                $submitted
            );
        }
        if (in_array(
            'institution',
            $options
        )) {
            array_push(
                $positioned,
                $user->institution
            );
        }
        if (in_array(
            'department',
            $options
        )) {
            array_push(
                $positioned,
                $user->department
            );
        }
        if (in_array(
            'course',
            $options
        )) {
            array_push(
                $positioned,
                $coursename
            );
        }
        if (in_array(
            'shortname',
            $options
        )) {
            array_push(
                $positioned,
                $shortname
            );
        }
        if (in_array(
            'summary',
            $options
        )) {
            array_push(
                $positioned,
                $coursesummary
            );
        }
        if (in_array(
            'category',
            $options
        )) {
            array_push(
                $positioned,
                $coursecat
            );
        }
        if (in_array(
            'group',
            $options
        )) {
            array_push(
                $positioned,
                $groupname
            );
        }
        if (in_array(
            'cohort',
            $options
        )) {
            array_push(
                $positioned,
                $cohortname
            );
        }
        if (in_array(
            'id',
            $options
        )) {
            array_push(
                $positioned,
                $uid
            );
        }
        if (in_array(
            'fullname',
            $options
        )) {
            array_push(
                $positioned,
                $fullname
            );
        }
        if (in_array(
            'username',
            $options
        )) {
            array_push(
                $positioned,
                $username
            );
        }
        if (in_array(
            'complete',
            $options
        )) {
            array_push(
                $positioned,
                $resprow->complete
            );
        }
        for ($c = $nbinfocols; $c < $numrespcols; $c++) {
            if (isset($row[$c])) {
                $positioned[] = $row[$c];
            } else if (isset($questionsbyposition[$c])) {
                $question = $questionsbyposition[$c];
                $qtype = intval($question->type_id);
                if ($qtype === QUESCHECK) {
                    $positioned[] = '0';
                } else {
                    $positioned[] = null;
                }
            } else {
                $positioned[] = null;
            }
        }
        return $positioned;
    }
}
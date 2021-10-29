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

require_once(__DIR__ . '/../../config.php');

require_once($CFG->dirroot . '/mod/pimenkoquestionnaire/locallib.php');

require_login();
$context = context_system::instance();

require_capability(
    'local/exportquestionary:exportall',
    $context
);

// Set your page.
$PAGE->set_pagetype('exportall');
$PAGE->set_url(
    new moodle_url(
        "/local/exportquestionary/exportall.php",
        []
    )
);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(
    get_string(
        'pluginname',
        'local_exportquestionary'
    )
);
$PAGE->set_heading(
    get_string(
        'pluginname',
        'local_exportquestionary'
    )
);

$PAGE->requires->css('/local/exportquestionary/assets/css/jquery-ui.min.css');
$PAGE->requires->js(new moodle_url('/local/exportquestionary/assets/js/xlsx.js'));
$PAGE->requires->js(new moodle_url('/local/exportquestionary/assets/js/exportquestionary.js'));

// Get all template questionary
$templatequestionary = $DB->get_records_sql(
    "SELECT 
            qs.title 
          FROM {pimenkoquestionnaire_survey} qs 
          LEFT JOIN {pimenkoquestionnaire} q 
          ON qs.id = q.sid 
          WHERE  qs.realm = 'template'"
);

$template = [];

foreach ($templatequestionary as $key => $questionary) {
    $questionary->title = strip_tags(format_text($questionary->title));
}

$template['templatequestionary'] = array_values($templatequestionary);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template(
    'local_exportquestionary/exportform',
    $template
);

echo $OUTPUT->footer();

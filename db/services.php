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

// We defined the web service functions to install.
$functions = [
        'local_exportquestionary_exportcsvresponses' => [
                'classname' => 'local_exportquestionary_external',
                'methodname' => 'exportcsvresponses',
                'classpath' => 'local/exportquestionary/classes/external.php',
                'description' => 'return csv file responses',
                'type' => 'read',
                'ajax' => true
        ],
        'local_exportquestionary_exportcsvreport' => [
                'classname' => 'local_exportquestionary_external',
                'methodname' => 'exportcsvreport',
                'classpath' => 'local/exportquestionary/classes/external.php',
                'description' => 'return csv file general report',
                'type' => 'read',
                'ajax' => true
        ]
];

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = [
        'Return a csv file' => [
                'functions' => ['local_exportquestionary_exportcsvresponses'],
                'restrictedusers' => 0,
                'enabled' => 1
        ],
        'Return csv file general report' => [
                'functions' => ['local_exportquestionary_exportcsvreport'],
                'restrictedusers' => 0,
                'enabled' => 1
        ]
];

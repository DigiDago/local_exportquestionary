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
    'local_exportquestionary_exportcsv' => [
        'classname'   => 'local_exportquestionary_external',
        'methodname'  => 'exportcsv',
        'classpath'   => 'local/exportquestionary/classes/external.php',
        'description' => 'return csv file',
        'type'        => 'read',
        'ajax'        => true
    ]
];

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = [
    'Return a csv file' => [
        'functions'       => [ 'local_exportquestionary_exportcsv' ],
        'restrictedusers' => 0,
        'enabled'         => 1
    ]
];

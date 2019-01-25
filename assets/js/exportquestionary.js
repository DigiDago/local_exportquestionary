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

require(["core/ajax", "jquery", "jqueryui"], function (ajax, $) {
    $(".datepicker").datepicker();
    $(".exportcsv").on('click', function (e) {
        let title = $('#templatetitle').val();
        ajax.call([
            {
                methodname: "local_exportquestionary_exportcsv",
                args: {
                    title: title,
                },
                done: function (response) {
                    var csv = convertArrayOfObjectsToCSV(response);
                    if (csv == null) return;
                    var date = new Date();
                    var year = date.getFullYear().toString();
                    var month = (date.getMonth() + 1).toString(); // getMonth() is zero-based
                    var day = date.getDate().toString();
                    var date = '_' + day + '_' + month + '_' + year;
                    var filename = response.name + date + '.csv';

                    if (!csv.match(/^data:text\/csv/i)) {
                        csv = 'data:text/csv;charset=utf-8,' + csv;
                    }
                    data = encodeURI(csv);

                    link = document.createElement('a');
                    link.setAttribute('href', data);
                    link.setAttribute('download', filename);
                    link.click();
                },
                fail: function (response) {
                }
            }
        ], true, true);
    });
});

function convertArrayOfObjectsToCSV(args) {
    let result, ctr, keys, columnDelimiter, lineDelimiter, data;
    data = args.data || null;
    if (data == null || !data.length) {
        return null;
    }
    columnDelimiter = ';';
    lineDelimiter = '\n';
    //Get 1st item to find field label
    keys = Object.keys(data[0]);
    result = '';
    data.forEach(function (item) {
        ctr = 0;
        keys.forEach(function (key) {
            if (ctr > 0) result += columnDelimiter;
            if (item[key] && item[key].visitnbre != undefined) {
                result += item[key].visitnbre;
            }
            else {
                result += '\"' + item[key] + '\"';
            }
            ctr++;
        });
        result += lineDelimiter;
    });
    return result;
}
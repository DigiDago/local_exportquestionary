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

require(["core/ajax", "jquery", "jqueryui"], function(ajax, $) {
    $(".datepicker").datepicker();
    $(".exportcsvresponses").on('click', function() {
        ajax.call([
            {
                methodname: "local_exportquestionary_exportcsvresponses",
                args: {
                    title: $('#templatetitle').val(),
                },
                done: function(response) {
                    let date = new Date();
                    let year = date.getFullYear().toString();
                    let month = (date.getMonth() + 1).toString(); // getMonth() is zero-based
                    let day = date.getDate().toString();
                    date = '_' + day + '_' + month + '_' + year;
                    let filename = response.name + date + '.csv';

                    exportToCsv(filename, response.data);

                },
                fail: function(response) {
                }
            }
        ], true, true);
    });
    $(".exportcsvreport").on('click', function() {
        let title = $('#templatetitle').val();
        ajax.call([
            {
                methodname: "local_exportquestionary_exportcsvreport",
                args: {
                    title: title,
                },
                done: function(response) {
                    let date = new Date();
                    let year = date.getFullYear().toString();
                    let month = (date.getMonth() + 1).toString(); // getMonth() is zero-based
                    let day = date.getDate().toString();
                    date = '_' + day + '_' + month + '_' + year;
                    let filename = response.name + date + '.csv';

                    exportToCsv(filename, response.data);

                },
                fail: function(response) {
                }
            }
        ], true, true);
    });
});

function exportToCsv(filename, rows) {
    let processRow = function(row) {
        let finalVal = '';
        for (let j = 0; j < row.length; j++) {
            let innerValue = row[j] === null ? '' : row[j].toString();
            if (row[j] instanceof Date) {
                innerValue = row[j].toLocaleString();
            }
            let result = innerValue.replace(/"/g, '""');
            result = result.replace(/&nbsp;/g, ' ');
            result = strip(result);
            if (result.search(/([",\n])/g) >= 0)
                result = '"' + result + '"';
            if (j > 0)
                finalVal += ',';
            finalVal += result;
        }
        return finalVal + '\n';
    };

    let csvFile = '';
    for (let i = 0; i < rows.length; i++) {
        csvFile += processRow(rows[i]);
    }

    let blob = new Blob([csvFile], {type: 'text/csv;charset=utf-8;'});
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, filename);
    } else {
        let link = document.createElement("a");
        if (link.download !== undefined) { // feature detection
            // Browsers that support HTML5 download attribute
            let url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
}

function strip(html) {
    let tmp = document.createElement("DIV");
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || "";
}

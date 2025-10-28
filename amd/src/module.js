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
/* eslint-disable */
/**
 * Manager for the blc_modules block.
 *
 * @module block_blc_modules/module
 * @author      Yahya Rais <yahya@teruselearning.co.uk>
 * @copyright   2022 Terus Technology LTD
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// import $ from 'jquery';

var root = M.cfg.wwwroot;
var x = '';

define(['jquery', 'block_blc_modules/tippy', 'block_blc_modules/select2', 'core/ajax'], function ($, tippy, select2,Ajax) {
    return {
        getUrlParameter: getUrlParameter,
        fillSubject: fillSubject,
        checkVersion: checkVersion,
        fillscorm: fillscorm,
        updateScorm: updateScorm,
        init: init,
        tippyInit: tippyInit,
        bulkUpdateInit: bulkUpdateInit,
        bulkUpdateProgressInit: bulkUpdateProgressInit,
        createButtonAddBlc: createButtonAddBlc
    };

    function getUrlParameter(url, sParam) {

        var sPageURL = decodeURIComponent(url.split("?")[1]),

            sURLVariables = sPageURL.split('#'),

            sParameterName,

            i;

        for (i = 0; i < sURLVariables.length; i++) {

            sParameterName = sURLVariables[i].split('=');

            if (sParameterName[0] === sParam) {

                return sParameterName[1] === undefined ? true : sParameterName[1];

            }

        }

        return false;

    }

    function fillSubject() {
        var apikey = $('#apikey').val();
        $("#scormurls").html('');

        var request = {
            methodname: 'blocks_blc_modules_get_blc_modules_scormsubject',
            args: {
                apikey: apikey,
                requesturi: M.cfg.wwwroot,
                version: 5
            }
        };

        Ajax.call([request])[0]
            .done((data) => {
                const items = ["<option value='0' selected>Select Subject</option>"];
                
                if (data.length > 0) {
                    items.push(...data.map(item => `<option value='${item}'>${item}</option>`));
                }

                $("#scormsubject").html(items.join(""));
            })
            .fail((error) => {
                console.error('Error loading subjects:', error);
                $('.submitForm').prop("disabled", true);
                $('.statusMsg').html('<span style="color:red;">An error has occurred.</span>');
            });
    }

    function checkVersion(id) {
        var apikey = $('#apikey').val();

        var request = {
            methodname: 'blocks_blc_modules_get_blc_modules_version',
            args: {
                apikey: apikey,
                requesturi: M.cfg.wwwroot,
                version: 5
            }
        };

        Ajax.call([request])[0]
            .done(function(data) {
                $.each(data, function(key, val) {
                    $("#module-" + key + " .mod-indent-outer .activityinstance").append('<li class="cmid-version" id="' + key + '-' + val + '"><i id="updatescorm" style="cursor: pointer;" class="icon fa fa-refresh fa-fw " title="New version available" aria-label="Update"></i>');
                });
            })
            .fail(function(error) {
                console.error('Error checking version:', error);
            });
    }

    function fillscorm() {
        var scormsubject = $('#scormsubject option:selected').text();
        var apikey = $('#apikey').val();

        if (scormsubject != 0) {
            var request = {
                methodname: 'blocks_blc_modules_get_blc_modules_scormurl',
                args: {
                    apikey: apikey,
                    requesturi: M.cfg.wwwroot,
                    version: 5,
                    scormsubject: scormsubject  // You may need to modify the webservice to accept this parameter
                }
            };
           

            Ajax.call([request])[0]
                .done(function(data) {
                    var items = [];
                    $.each(data, function(index, item) {
                        var sanVal = item.scormname.replace(".zip", "");
                        var key = item.scormurl.replace("'", "'");
                        items.push("<option style='-moz-white-space: pre-wrap; -o-white-space: pre-wrap; white-space: pre-wrap;' value='" + key + "'>" + sanVal + "</option>");
                    });

                    $("#scormurls").html(items.join(""));
                })
                .fail(function(error) {
                    console.error('Error loading SCORM URLs:', error);
                });
        }
    }

    function updateScorm(cmid, version) {

        $.ajax({

            type: 'GET',

            url: root + '/blocks/blc_modules/update_scorm.php',

            data: 'cmid=' + cmid + '&version=' + version,

            beforeSend: function () {

                $("#module-" + cmid + " .mod-indent-outer .cmid-version").append('<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>');

            },

            success: function () {

                location.reload(true);

            }

        });

    }

    function createButtonAddBlc() {
        const buttons = document.querySelectorAll('.btn-blc-modules');

        const defaultBackgroundColor = '#cfe2f2';
        const hoverBackgroundColor = '#0f6cbf';
        const hoverTextColor = 'white';
        const defaultTextColor = 'black';

        buttons.forEach(button => {
            button.addEventListener('mouseenter', () => {
                button.style.backgroundColor = hoverBackgroundColor;
                button.style.color = hoverTextColor;
            });

            button.addEventListener('mouseleave', () => {
                button.style.backgroundColor = defaultBackgroundColor;
                button.style.color = defaultTextColor;
            });
        });
    }
    function init() {

        var pageURL = $(location).attr("href");



        pageURL = pageURL.split("&")[0];

        pageURL = pageURL.split("#")[0];
        var id = getUrlParameter(pageURL, "id");

        var notifyeditingon = $("#userediting").val();

        if (notifyeditingon == 1) {

            checkVersion(id);

        }

        if ($("#addscorm").hasClass("block_blc_modules")) {

            notifyeditingon = $("#userediting").val();

            var allowstealthval = $("#allowstealthvalue").val();

            var allowstealthstring = '';

            if (allowstealthval == 1) {

                allowstealthstring = '<option value="-1">Make available but not shown</option>';

            }

            var completionon = $("#completionon").val();
            var completionstring = '';

            if (completionon == 1) {

                completionstring = '<select class="custom-select " name="completion" id="id_completion"> <option value="0">Off</option> <option value="1" >Manual</option> <option value="2" selected="">Automatic</option> </select> <div class="form-control-feedback invalid-feedback" id="id_error_completion">';

            } else {

                completionstring = '<p>Activity completion is not enabled on this course.</p><div class="form-control-feedback invalid-feedback" id="id_error_completion">';

            }

            // Determine Bootstrap version for correct data attributes
            // In Moodle 5.0+, force Bootstrap 5 attributes
            var bsVersion = 5; // Force Bootstrap 5 for Moodle 5.0+
            var dataDismiss = 'data-bs-dismiss';
            var dataToggle = 'data-bs-toggle';

            // Append modal to body instead of .course-content for better compatibility
            $(document.body).append(`
                <div style="display:none;" class="modal fade" id="bsModal3" role="dialog" aria-labelledby="mySmallModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-md">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title" id="mySmallModalLabel">
                                    Select BLC Modules
                                </h4>
                            </div>
                        <div class="modal-body">
                            <p class="statusMsg"></p>
                            <form role="form">
                            <div class="form-group">
                                <select style="width:100%;" class="form-control select2" id="scormsubject" name="scormsubject">
                                    <option disabled selected>Select a module</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <select style="min-width:100%;" class="form-control" id="scormurls" name="scormurls" multiple></select>
                            </div>
                            <div class="additional_settings">
                                <h3>SCORM Settings</h3>
                                <div class="blcrow">
                                    <div class="blcrow">
                                        <span style="float:left; margin-right:10px;" class="text-nowrap">
                                            <a style="box-shadow: none; background: none; padding-bottom:3px !important; border:none;" class="btn btn-link p-0 avail" id="" role="button" data-container="body" ${dataToggle}="popover" data-placement="right" data-content="<div class=&quot;no-overflow&quot;><p>If the availability is set to 'Show on course page', the activity or resource is available to students (subject to any access restrictions which may be set).<br /><br />If the availability is set to 'Hide from students', the activity or resource is only available to users with permission to view hidden activities (by default, users with the role of teacher or non-editing teacher).<br /><br />If the course contains many activities or resources, the course page may be simplified by setting the availability to 'Make available but not shown on course page'. In this case, a link to the activity or resource must be provided from elsewhere, such as from a page resource. The activity would still be listed in the gradebook and other reports.</p></div>" data-html="true" tabindex="0" data-trigger="focus">
                                            <i class="icon fa fa-circle-question text-info fa-fw " title="Help with Availability" role="img" aria-label="Help with Availability"></i>
                                            </a>
                                        </span>
                                        <label style="line-height: 18px;" class="blclabel" for="id_visible">
                                            Availability
                                        </label>
                                        </div>
                                        <div class="blcrow" data-fieldtype="modvisible">
                                            <select class="custom-select " name="visible" id="id_visible">
                                                <option value="1" selected="">Show on course page</option>
                                                <option value="0">Hide from students</option>
                                                ` + allowstealthstring + `
                                            </select>
                                            <div class="form-control-feedback invalid-feedback" id="id_error_visible"></div>
                                        </div>
                                    </div>
                                    <br/>
                                    <div class="blcrow">
                                        <div class="blccolmd6">
                                            <span style="float:left; margin-right:10px;" class="text-nowrap">
                                                <a style="box-shadow: none; background: none; padding-bottom:3px !important; border:none;" class="btn btn-link p-0 prev" role="button" id="" data-container="body" ${dataToggle}="popover" data-placement="right" data-content="<div class=&quot;no-overflow&quot;><p>Preview mode allows a student to browse an activity before attempting it. If preview mode is disabled, the preview button is hidden.</p> </div> " data-html="true" tabindex="0" data-trigger="focus">
                                                   
                                                <i class="icon fa fa-circle-question text-info fa-fw " title="Help with Availability" role="img" aria-label="Help with Availability"></i>                                               
                                                </a>
                                            </span>
                                            <label style="line-height: 18px;" class="blclabel" for="id_hidebrowse">
                                                Disable preview mode
                                            </label>
                                        </div>
                                        <div class="blcrow" style="" data-fieldtype="selectyesno">
                                            <select class="custom-select" name="hidebrowse" id="id_hidebrowse">
                                                <option value="0">No</option>
                                                <option value="1" selected="">Yes</option>
                                            </select>
                                            <div class="form-control-feedback invalid-feedback" id="id_error_hidebrowse"></div>
                                        </div>
                                    </div>
                                    <br/>
                                    <div class="blcrow">
                                        <div class="blccolmd6">
                                            <span style="float:left; margin-right:10px;" class="text-nowrap">
                                                <a style="box-shadow: none; background: none; padding-bottom:3px !important; border:none;" class="btn btn-link p-0 comp" id="" role="button" data-container="body" ${dataToggle}="popover" data-placement="right" data-content="<div class=&quot;no-overflow&quot;><p>If enabled, activity completion is tracked, either manually or automatically.<br/> If Automatic is selected, the best options for BLC modules are set, whereas if manual is selected, the studnt must manually tick a box next to the activity for it to register as complete.</p> <p>A tick next to the activity name on the course page indicates when the activity is complete.</p> </div>" data-html="true" tabindex="0" data-trigger="focus">
                                                        <i class="icon fa fa-circle-question text-info fa-fw " title="Help with Availability" role="img" aria-label="Help with Availability"></i>                                               
                                            
                                                </a>
                                            </span>
                                            <label class="blclabel" style="line-height: 18px;" for="id_completion">
                                                Completion tracking
                                            </label>
                                        </div>
                                        <div class="blccolmd6" style="" data-fieldtype="select">
                                            ` + completionstring + `
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default closeModal" ${dataDismiss}="modal">
                                Close
                            </button>
                            <button type="button" disabled="disabled" class="btn btn-primary submitForm">
                                Add Modules
                            </button>
                        </div>
                    </div>
                </div>
            `);

            var mright = $("#section-0 .content").css("margin-right");
            var count = 0;
            var wwwroothidden = $('#wwwroot_hidden').html();

            $(".section.main").each(function () {

                if (notifyeditingon == 1) {
                    // Determine Bootstrap version for correct data attributes
                    // In Moodle 5.0+, force Bootstrap 5 attributes
                    var bsVersion = 5; // Force Bootstrap 5 for Moodle 5.0+
                    var dataToggle = 'data-bs-toggle';
                    var dataTarget = 'data-bs-target';
                    
                    $(this).append(`
                            <button class="btn add-content btn-blc-modules d-flex justify-content-center align-items-center p-1 icon-no-margin pull-right add-scrom" 
                                    ${dataToggle}='modal' ${dataTarget}='#bsModal3' style="float:right">
                                <div class="px-1">
                                    <i class="icon fa fa-plus fa-fw" aria-hidden="true"></i>
                                    <span class="activity-add-text pr-1">Add BLC modules</span>
                                </div>
                            </button>
                        `);

                    createButtonAddBlc();
                }

                var margin = $(".row").css("margin-left");

                if (margin == "-30px" || margin == "-20px") {

                    $(".form-group").css("margin-left", "5px");

                    $(".float-sm-right").css("float", "left");

                }

            });

            $("#scormsubject").change(function () {

                fillscorm();

            });

            $(document).on("click", ".add-scrom", function () {

                fillSubject();

                var secId = $(this).closest(".section").attr('id');

                var secNum = secId.split("-")[1];

                x = secNum;

            });

            $("#scormurls").click(function () {

                $('.submitForm').removeAttr("disabled");

            });

            $(document).on("click", ".submitForm", function () {
                var apikey = $('#apikey').val();
                var scormurls = [];
                var visibility = $("#id_visible").val();
                var hidebrowse = $("#id_hidebrowse").val();
                var completion = $("#id_completion").val();

                $.each($("#scormurls option:selected"), function () {
                    var urll = $(this).val();
                    urll = urll.replace(",", "qqq");
                    scormurls.push(urll);
                });

                $('.statusMsg').html('');
                $('.submitForm').attr("disabled", "disabled");
                $('.closeModal').attr("disabled", "disabled");
                $(".modal-header").append('<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>');

                // Show processing message with file count
                var fileCount = scormurls.length;
                var processingMsg = fileCount > 1 
                    ? 'Processing ' + fileCount + ' files. This may take several minutes...'
                    : 'Processing file. This may take a moment...';
                $('.statusMsg').html('<span style="color:#0f6cbf;"><i class="fa fa-info-circle"></i> ' + processingMsg + '</span>');

                var request = {
                    methodname: 'blocks_blc_modules_load_scorm_modules',
                    args: {
                        courseid: parseInt(id),
                        sectionnumber: parseInt(x),
                        apikey: apikey,
                        scormurls: scormurls,
                        visibility: parseInt(visibility),
                        hidebrowse: parseInt(hidebrowse),
                        completion: parseInt(completion)
                    }
                };

                Ajax.call([request], { timeout: 300000 })[0] // 5 minutes timeout
                    .done(function(data) {
                        console.log('BLC Modules: AJAX success response:', data);
                        if (data.success) {
                            // Show success message briefly before reload
                            var successMsg = data.successful > 1 
                                ? data.successful + ' modules loaded successfully!' 
                                : 'Module loaded successfully!';
                            $('.statusMsg').html('<span style="color:green;"><i class="fa fa-check-circle"></i> ' + successMsg + '</span>');
                            
                            // Reload after short delay to show success message
                            setTimeout(function() {
                                location.reload(true);
                            }, 1000);
                        } else {
                            var errorMessage = data.messages ? data.messages.join('<br>') : 'Unknown error occurred';
                            var errorDetails = '';
                            if (data.failed > 0 && data.successful > 0) {
                                errorDetails = '<br><small>' + data.successful + ' modules loaded successfully, ' + data.failed + ' failed.</small>';
                            }
                            $('.statusMsg').html('<span style="color:red;"><i class="fa fa-exclamation-circle"></i> ' + errorMessage + errorDetails + '</span>');
                            $('.submitForm').removeAttr("disabled");
                            $('.closeModal').removeAttr("disabled");
                            $(".modal-header .fa.fa-spinner").remove();
                        }
                    })
                    .fail(function(error) {
                        console.error('AJAX request failed:', error);
                        console.log('BLC Modules: Full error object:', error);

                        // Check if this is actually a successful response with error data
                        if (error && error.responseJSON) {
                            console.log('BLC Modules: Found responseJSON in error, treating as success with error data');
                            // Treat this as a successful response but with error data
                            var data = error.responseJSON;
                            console.log('BLC Modules: Error response data:', data);

                            if (data.success) {
                                // Show success message briefly before reload
                                var successMsg = data.successful > 1
                                    ? data.successful + ' modules loaded successfully!'
                                    : 'Module loaded successfully!';
                                $('.statusMsg').html('<span style="color:green;"><i class="fa fa-check-circle"></i> ' + successMsg + '</span>');

                                // Reload after short delay to show success message
                                setTimeout(function() {
                                    location.reload(true);
                                }, 1000);
                                return;
                            } else {
                                var errorMessage = data.messages ? data.messages.join('<br>') : 'Unknown error occurred';
                                var errorDetails = '';
                                if (data.failed > 0 && data.successful > 0) {
                                    errorDetails = '<br><small>' + data.successful + ' modules loaded successfully, ' + data.failed + ' failed.</small>';
                                }
                                $('.statusMsg').html('<span style="color:red;"><i class="fa fa-exclamation-circle"></i> ' + errorMessage + errorDetails + '</span>');
                                $('.submitForm').removeAttr("disabled");
                                $('.closeModal').removeAttr("disabled");
                                $(".modal-header .fa.fa-spinner").remove();
                                return;
                            }
                        }

                        // Check if error is timeout - the process may still be running in background
                        var isTimeout = error && (error.error === 'timeout' || error.exception === 'moodle_exception' || (error.statusText && error.statusText === 'timeout'));

                        if (isTimeout) {
                            // Timeout - show message that process is still running
                            $('.statusMsg').html(
                                '<span style="color:#ff9800;"><i class="fa fa-clock-o"></i> ' +
                                'Request timed out, but the process may still be running in the background. ' +
                                'Please wait a moment and refresh the page to check if modules were loaded.</span>'
                            );
                        } else {
                            // Real error - show error message with more details
                            var errorMsg = 'An error occurred while loading SCORM modules';
                            if (error && error.message) {
                                errorMsg += ': ' + error.message;
                            } else if (error && error.statusText) {
                                errorMsg += ': ' + error.statusText;
                            } else if (error && error.responseText) {
                                // Try to extract meaningful error from response
                                try {
                                    var errorData = JSON.parse(error.responseText);
                                    if (errorData.message) {
                                        errorMsg += ': ' + errorData.message;
                                    }
                                } catch (e) {
                                    // Ignore JSON parse errors
                                }
                            }
                            $('.statusMsg').html('<span style="color:red;"><i class="fa fa-exclamation-circle"></i> ' + errorMsg + '</span>');
                        }
                        
                        $('.submitForm').removeAttr("disabled");
                        $('.closeModal').removeAttr("disabled");
                        $(".modal-header .fa.fa-spinner").remove();
                    });
            });

            $('.select2').select2({
                dropdownParent: $("#bsModal3")
            });

        }

        $(".activityinstance").on("click", ".fa-refresh", function () {

            var data = $(this).closest(".cmid-version").attr('id');

            var cmid = data.split("-")[0];

            var version = data.split("-")[1];

            updateScorm(cmid, version);

        });
    }

    function tippyInit() {

        tippy('.avail', {
            content: "<div class=&quot;no-overflow&quot;><p>If the availability is set to 'Show on course page', the activity or resource is available to students (subject to any access restrictions which may be set).<br /><br /> If the availability is set to 'Hide from students', the activity or resource is only available to users with permission to view hidden activities (by default, users with the role of teacher or non-editing teacher).<br /><br /> If the course contains many activities or resources, the course page may be simplified by setting the availability to 'Make available but not shown on course page'. In this case, a link to the activity or resource must be provided from elsewhere, such as from a page resource. The activity would still be listed in the gradebook and other reports.</p> </div> ",
            theme: "light",
            arrow: true,
            placement: "right"
        });

        tippy('.prev', {
            content: "<div class=&quot;no-overflow&quot;><p>Preview mode allows a student to browse an activity before attempting it. If preview mode is disabled, the preview button is hidden.</p> </div> ",
            theme: "light",
            arrow: true,
            placement: "right"
        });

        tippy('.comp', {
            content: "<div class=&quot;no-overflow&quot;><p>If enabled, activity completion is tracked, either manually or automatically.<br/> If Automatic is selected, the best options for BLC modules are set, whereas if manual is selected, the studnt must manually tick a box next to the activity for it to register as complete.</p> <p>A tick next to the activity name on the course page indicates when the activity is complete.</p> </div>",
            theme: "light",
            arrow: true,
            placement: "right"
        });

    }

    function bulkUpdateInit() {

        $(document).ready(function () {

            var checkExist = setInterval(function () {

                if ($("#modalForm").modal) {

                    $("#modalForm").modal('show');

                    $("#bulkupdatecont").click(function () {

                        $("#bulkupdatesubmit").submit();

                    });

                    clearInterval(checkExist);
                }

            }, 500);

        });

    }

    /**
     * Initialize bulk update progress page with real-time updates
     */
    function bulkUpdateProgressInit(config) {
        $(document).ready(function() {
            var progressConfig = {
                endpoint: config.endpoint || M.cfg.wwwroot + '/blocks/blc_modules/bulk_update_processor.php',
                sesskey: config.sesskey,
                pollInterval: 1000,
                timeoutSeconds: 1800
            };
            
            var stats = { total: 0, processed: 0, success: 0, failed: 0 };
            var startTime = Date.now();
            var isComplete = false;
            var consecutiveErrors = 0;  // Track consecutive errors
            var maxConsecutiveErrors = 20;  // Max errors before giving up
            
            function updateUI(data) {
                if (data.total !== undefined) stats.total = data.total;
                if (data.success !== undefined) stats.success = data.success;
                if (data.failed !== undefined) stats.failed = data.failed;
                stats.processed = stats.success + stats.failed;
                
                $('#statTotal').text(stats.total);
                $('#statProcessed').text(stats.processed);
                $('#statSuccess').text(stats.success);
                $('#statFailed').text(stats.failed);
                
                if (stats.total > 0) {
                    var percentage = Math.round((stats.processed / stats.total) * 100);
                    $('#progressBar').css('width', percentage + '%');
                    $('#progressBar .progress-percentage').text(percentage + '%');
                    $('#progressBar').attr('aria-valuenow', percentage);
                }
                
                if (data.status) $('#progressStatus').text(data.status);
                
                if (data.current_module) {
                    $('#currentModule').show();
                    $('#currentModuleName').text(data.current_module.name || '-');
                    $('#currentModuleCM').text('CM ID: ' + (data.current_module.cmid || '-'));
                }
            }
            
            function addLog(message, type) {
                type = type || 'info';
                var time = new Date().toLocaleTimeString();
                var logEntry = $('<div class="log-entry log-' + type + '">' +
                    '<span class="log-time">[' + time + ']</span>' +
                    '<span class="log-message">' + $('<div>').text(message).html() + '</span>' +
                    '</div>');
                $('#progressLog').append(logEntry);
                var logEl = $('#progressLog')[0];
                if (logEl) logEl.scrollTop = logEl.scrollHeight;
            }
            
            function pollProgress() {
                if (isComplete) return;
                
                $.ajax({
                    url: progressConfig.endpoint,
                    type: 'POST',
                    data: { sesskey: progressConfig.sesskey, action: 'get_progress' },
                    dataType: 'json',
                    timeout: 20000,  // Increased to 20 seconds
                    success: function(response) {
                        consecutiveErrors = 0;  // Reset error counter on success
                        
                        if (response.success) {
                            updateUI(response.data);
                            if (response.data.complete) {
                                handleCompletion(response.data);
                            } else {
                                setTimeout(pollProgress, progressConfig.pollInterval);
                            }
                        } else {
                            addLog('Error: ' + response.message, 'error');
                            setTimeout(pollProgress, progressConfig.pollInterval * 2);
                        }
                    },
                    error: function(xhr, status, error) {
                        consecutiveErrors++;
                        
                        // Only log every 5th error to avoid spam
                        if (consecutiveErrors % 5 === 1) {
                            console.warn('Polling timeout/error (attempt ' + consecutiveErrors + '):', status);
                        }
                        
                        // Check if we should give up
                        if (consecutiveErrors >= maxConsecutiveErrors) {
                            addLog('Too many connection errors - process may have failed', 'error');
                            isComplete = true;
                            $('#completionSection').show();
                            $('#warningAlert').show();
                            $('#warningMessage').text('Lost connection to server. Process may still be running.');
                            return;
                        }
                        
                        // Retry with exponential backoff (but max 5 seconds)
                        var retryDelay = Math.min(5000, progressConfig.pollInterval * (1 + consecutiveErrors * 0.5));
                        setTimeout(pollProgress, retryDelay);
                    }
                });
            }
            
            function handleCompletion(data) {
                isComplete = true;
                
                // Stop all spinning icons
                $('.spinner-icon').hide();
                $('.stat-processing .fa-spin').removeClass('fa-spin');
                
                // Update title and UI
                $('#progress-title').text('Bulk Update Complete');
                $('#currentModule').hide();
                $('#completionSection').show();
                
                // Check if there are remaining modules
                var remainingCount = data.remaining || 0;
                
                // Check if there were any modules to update
                if (stats.total === 0) {
                    // No modules needed updating
                    $('#successAlert').show();
                    $('#successMessage').text('All modules are up to date. No updates were needed.');
                } else if (stats.failed === 0) {
                    // All updates successful
                    $('#successAlert').show();
                    var successMsg = '';
                    if (stats.success === 1) {
                        successMsg = '1 module was updated successfully!';
                    } else {
                        successMsg = 'All ' + stats.success + ' modules were updated successfully!';
                    }
                    
                    // Add remaining count if applicable
                    if (remainingCount > 0) {
                        successMsg += '\n\n📦 ' + remainingCount + ' more modules still need updating.';
                    }
                    
                    $('#successMessage').text(successMsg);
                    
                    // Show "Check for More Updates" button if there are remaining modules
                    if (remainingCount > 0) {
                        var checkMoreBtn = $('<button class="btn btn-primary mt-3" id="checkMoreUpdates">' +
                            '<i class="fa fa-refresh"></i> Check for More Updates (' + remainingCount + ' remaining)' +
                            '</button>');
                        $('#successAlert').append(checkMoreBtn);
                        
                        // Handle click
                        $('#checkMoreUpdates').on('click', function() {
                            location.reload();  // Reload to start new batch
                        });
                    }
                } else {
                    // Some updates failed
                    $('#warningAlert').show();
                    var warningMsg = stats.success + ' modules updated successfully, ' + stats.failed + ' failed.';
                    
                    if (remainingCount > 0) {
                        warningMsg += ' ' + remainingCount + ' more modules still need updating.';
                    }
                    
                    $('#warningMessage').text(warningMsg);
                    
                    if (data.errors && data.errors.length > 0) {
                        var errorHtml = '<ul>';
                        data.errors.forEach(function(error) {
                            errorHtml += '<li>' + $('<div>').text(error).html() + '</li>';
                        });
                        errorHtml += '</ul>';
                        $('#errorDetails').html(errorHtml);
                    }
                    
                    // Show "Check for More Updates" button even if some failed
                    if (remainingCount > 0) {
                        var checkMoreBtn = $('<button class="btn btn-primary mt-3" id="checkMoreUpdates">' +
                            '<i class="fa fa-refresh"></i> Check for More Updates (' + remainingCount + ' remaining)' +
                            '</button>');
                        $('#warningAlert').append(checkMoreBtn);
                        
                        $('#checkMoreUpdates').on('click', function() {
                            location.reload();
                        });
                    }
                }
                
                var elapsed = Math.round((Date.now() - startTime) / 1000);
                addLog('Process completed in ' + elapsed + ' seconds', 'success');
            }
            
            function startBulkUpdate() {
                addLog('Initializing bulk update process...', 'info');
                
                $.ajax({
                    url: progressConfig.endpoint,
                    type: 'POST',
                    data: { sesskey: progressConfig.sesskey, action: 'start' },
                    dataType: 'json',
                    timeout: 60000,  // Increased to 60 seconds
                    success: function(response) {
                        if (response.success) {
                            addLog('Bulk update started successfully', 'success');
                            // Start polling immediately
                            setTimeout(pollProgress, 500);
                        } else {
                            addLog('Failed to start: ' + response.message, 'error');
                            isComplete = true;
                            $('#completionSection').show();
                            $('#warningAlert').show();
                            $('#warningMessage').text('Failed to start: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Start Error:', status, error);
                        if (status === 'timeout') {
                            // Process might still be running, start polling anyway
                            addLog('Request timeout - checking progress...', 'warning');
                            setTimeout(pollProgress, 1000);
                        } else {
                            addLog('Failed to start: ' + error, 'error');
                            isComplete = true;
                            $('#completionSection').show();
                            $('#warningAlert').show();
                            $('#warningMessage').text('Failed to connect to processor.');
                        }
                    }
                });
            }
            
            $('#clearLog').on('click', function() {
                $('#progressLog').empty();
                addLog('Log cleared', 'info');
            });
            
            startBulkUpdate();
        });
    }
}
);
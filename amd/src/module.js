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

define(['jquery', 'block_blc_modules/tippy', 'block_blc_modules/select2', 'core/ajax', 'core/modal_factory'], function ($, tippy, select2, Ajax, ModalFactory) {
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

    function fillSubject(root) {
        var apikey = $('#apikey').val();
        root.find("#scormurls").html('');

        // Add loading spinner inside the select2 container
        var $subjectSelect = $(root).find("#scormsubject");
        var $select2Container = $subjectSelect.next('.select2-container');

        // Position spinner inside the Select2 dropdown
        $select2Container.append('<div class="subject-loading-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center; z-index: 10; border-radius: 4px;"><i class="fa fa-spinner fa-spin" style="color: #0f6cbf; font-size: 16px;"></i></div>');

        // Make sure container has relative positioning for absolute overlay
        $select2Container.css('position', 'relative');

        // Temporarily disable the select2 dropdown
        $subjectSelect.prop('disabled', true);
        $select2Container.addClass('select2-container--disabled');

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
                // Remove loading spinner overlay
                root.find('.subject-loading-overlay').remove();

                // Re-enable select2
                $subjectSelect.prop('disabled', false);
                $select2Container.removeClass('select2-container--disabled');

                // Store current selection
                var currentValue = $subjectSelect.val();

                // Clear and repopulate options
                $subjectSelect.html('<option value="0">Select Subject</option>');

                if (data.length > 0) {
                    data.forEach(item => {
                        $subjectSelect.append(`<option value="${item}">${item}</option>`);
                    });
                }

                // Update select2 without breaking it
                $subjectSelect.trigger('change.select2');
            })
            .fail((error) => {
                // Remove loading spinner overlay
                root.find('.subject-loading-overlay').remove();

                // Re-enable select2
                $subjectSelect.prop('disabled', false);
                $select2Container.removeClass('select2-container--disabled');

                console.error('Error loading subjects:', error);

                // Show user-friendly error message
                root.find('.statusMsg').html('<span style="color:red;"><i class="fa fa-exclamation-circle"></i> Failed to load subjects. Please try again.</span>');
                root.find('.submitForm').prop("disabled", true);
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

    function fillscorm(root) {
        var scormsubject = root.find('#scormsubject option:selected').text();
        var apikey = $('#apikey').val();

        if (scormsubject && scormsubject !== 'Select Subject') {

            var $scormSelect = root.find('#scormurls');
            var $scormSelect2Container = $scormSelect.next('.select2-container');

            $scormSelect2Container.after(`
                <div class="scorm-loading-indicator"
                    style="margin:8px 0 16px 0;padding:8px;background:#f0f8ff;
                    border:1px solid #b3d9ff;border-radius:4px;
                    display:flex;align-items:center;gap:8px;">
                    <i class="fa fa-spinner fa-spin"></i>
                    <span>Loading SCORM packages...</span>
                </div>
            `);

            $scormSelect.prop('disabled', true);
            $scormSelect2Container.addClass('select2-container--disabled');

            root.find('.submitForm').prop('disabled', true);

            var request = {
                methodname: 'blocks_blc_modules_get_blc_modules_scormurl',
                args: {
                    apikey: apikey,
                    requesturi: M.cfg.wwwroot,
                    version: 5,
                    scormsubject: scormsubject
                }
            };

            Ajax.call([request])[0]
                .done(function (data) {

                    root.find('.scorm-loading-indicator').remove();

                    $scormSelect.prop('disabled', false);
                    $scormSelect2Container.removeClass('select2-container--disabled');

                    $scormSelect.empty();

                    if (data && data.length > 0) {
                        $.each(data, function (index, item) {
                            $scormSelect.append(
                                `<option value="${item.scormurl}">
                                ${item.scormname.replace('.zip', '')}
                            </option>`
                            );
                        });
                    }

                    $scormSelect.trigger('change.select2');

                    root.find('.statusMsg').html('');
                })
                .fail(function (error) {

                    root.find('.scorm-loading-indicator').remove();

                    $scormSelect.prop('disabled', false);
                    $scormSelect2Container.removeClass('select2-container--disabled');

                    console.error(error);

                    root.find('.statusMsg').html(
                        '<span style="color:red;"><i class="fa fa-exclamation-circle"></i>Failed to load SCORM packages.</span>'
                    );
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
        const button = document.querySelector('.btn-blc-modules');

        const defaultBackgroundColor = '#cfe2f2';
        const hoverBackgroundColor = '#0f6cbf';
        const hoverTextColor = 'white';
        const defaultTextColor = 'black'; 
        
        button.addEventListener('mouseenter', () => {
            button.style.backgroundColor = hoverBackgroundColor;
            button.style.color = hoverTextColor;
        });

        button.addEventListener('mouseleave', () => {
            button.style.backgroundColor = defaultBackgroundColor;
            button.style.color = defaultTextColor;
        });
    }
    function init() {

        // Add CSS for smooth loading animations
        $('<style>')
            .prop('type', 'text/css')
            .html(`
                .subject-loading, .scorm-loading {
                    opacity: 0;
                    animation: fadeIn 0.3s ease-in forwards;
                }

                .processing-indicator {
                    opacity: 0;
                    animation: fadeIn 0.5s ease-in forwards;
                }

                .progress-container {
                    opacity: 0;
                    animation: slideDown 0.4s ease-out forwards;
                }

                .progress-bar {
                    transition: width 0.3s ease, background-color 0.3s ease;
                }

                .progress-bar:hover {
                    background: linear-gradient(90deg, #0d5cbf, #3a80d2);
                }

                .statusMsg span {
                    display: inline-block;
                    opacity: 0;
                    animation: fadeInUp 0.4s ease-out forwards;
                }

                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }

                @keyframes slideDown {
                    from {
                        opacity: 0;
                        transform: translateY(-10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                @keyframes fadeInUp {
                    from {
                        opacity: 0;
                        transform: translateY(10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .btn-blc-modules {
                    transition: all 0.2s ease;
                }

                .btn-blc-modules:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
            `)
            .appendTo('head');

        var pageURL = $(location).attr("href");



        pageURL = pageURL.split("&")[0];

        pageURL = pageURL.split("#")[0];
        var id = getUrlParameter(pageURL, "id");
        
        // Detect if we're on section.php (id = section ID) or view.php (id = course ID)
        var courseId = id;
        var isSectionPage = pageURL.indexOf('/course/section.php') !== -1;
        
        if (isSectionPage) {
            // On section.php, need to get course ID from page body attribute
            // Moodle adds data-courseid to body or page-course-view-* class
            var bodyClasses = $('body').attr('class');
            var courseMatch = bodyClasses.match(/course-(\d+)/);
            if (courseMatch && courseMatch[1]) {
                courseId = courseMatch[1];
            }
        }

        var notifyeditingon = $("#userediting").val();

        if (notifyeditingon == 1) {

            checkVersion(courseId);

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

            $(".course-content").append(`
                <div style="display:none;" class="modal fade" id="bsModal3" role="dialog" aria-labelledby="mySmallModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
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
                                            <a style="box-shadow: none; background: none; padding-bottom:3px !important; border:none;" class="btn btn-link p-0 avail" id="" role="button" data-container="body" data-toggle="popover" data-placement="right" data-content="<div class=&quot;no-overflow&quot;><p>If the availability is set to 'Show on course page', the activity or resource is available to students (subject to any access restrictions which may be set).<br /><br />If the availability is set to 'Hide from students', the activity or resource is only available to users with permission to view hidden activities (by default, users with the role of teacher or non-editing teacher).<br /><br />If the course contains many activities or resources, the course page may be simplified by setting the availability to 'Make available but not shown on course page'. In this case, a link to the activity or resource must be provided from elsewhere, such as from a page resource. The activity would still be listed in the gradebook and other reports.</p></div>" data-html="true" tabindex="0" data-trigger="focus">
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
                                                <a style="box-shadow: none; background: none; padding-bottom:3px !important; border:none;" class="btn btn-link p-0 prev" role="button" id="" data-container="body" data-toggle="popover" data-placement="right" data-content="<div class=&quot;no-overflow&quot;><p>Preview mode allows a student to browse an activity before attempting it. If preview mode is disabled, the preview button is hidden.</p> </div> " data-html="true" tabindex="0" data-trigger="focus">
                                                   
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
                                                <a style="box-shadow: none; background: none; padding-bottom:3px !important; border:none;" class="btn btn-link p-0 comp" id="" role="button" data-container="body" data-toggle="popover" data-placement="right" data-content="<div class=&quot;no-overflow&quot;><p>If enabled, activity completion is tracked, either manually or automatically.<br/> If Automatic is selected, the best options for BLC modules are set, whereas if manual is selected, the studnt must manually tick a box next to the activity for it to register as complete.</p> <p>A tick next to the activity name on the course page indicates when the activity is complete.</p> </div>" data-html="true" tabindex="0" data-trigger="focus">
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
                            <button type="button" class="btn btn-default closeModal" data-action="cancel">
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

            // Step 1: Append "Add BLC modules" button to each section (loop is ONLY for buttons).
            // NOTE: Removed data-toggle='modal' data-target='#bsModal3' from buttons —
            // ModalFactory handles showing the modal; Bootstrap's data-toggle causes a
            // second competing modal-show which leads to duplicated event firing.
            $(".section.main").each(function () {
                if (notifyeditingon == 1) {
                    $(this).append(`
                        <button class="btn add-content btn-blc-modules d-flex justify-content-center align-items-center p-1 icon-no-margin pull-right add-scrom"
                                style="float:right">
                            <div class="px-1">
                                <i class="icon fa fa-plus fa-fw" aria-hidden="true"></i>
                                <span class="activity-add-text pr-1">Add BLC modules</span>
                            </div>
                        </button>
                    `);
                }

                var margin = $(".row").css("margin-left");

                if (margin == "-30px" || margin == "-20px") {

                    $(".form-group").css("margin-left", "5px");

                    $(".float-sm-right").css("float", "left");

                }

            });

            // Step 2: Initialize hover effects and modal ONCE (outside the loop).
            if (notifyeditingon == 1) {
                createButtonAddBlc();

                var modalElement = $('#bsModal3');
                var blcModal = null;

                ModalFactory.create({
                    title: modalElement.find('.modal-title').text(),
                    body: modalElement.find('.modal-body').html(),
                    footer: modalElement.find('.modal-footer').html(),
                    large: true,
                })
                .then(function(modal) {
                    blcModal = modal;

                    const root = modal.getRoot();

                    // Use .off().on() for ALL handlers to guarantee single binding.
                    $('.btn-blc-modules').off('click').on('click', function() {
                        // Capture which section this button belongs to.
                        var secId = $(this).closest(".section").attr('id');
                        if (secId) {
                            var parts = secId.split("-");
                            // Take the last numeric part to handle formats like "section-0", "section-1-2", etc.
                            x = parts[parts.length - 1];
                        } else {
                            // Fallback: try data-section-number attribute or default to 0.
                            x = $(this).closest(".section").data('section-number') || '0';
                        }

                        // Validate x is a valid number.
                        if (isNaN(parseInt(x, 10))) {
                            x = '0';
                        }

                        blcModal.show();

                        root.find('.select2').select2({
                            dropdownParent: root
                        });

                        fillSubject(root);
                    });

                    root.off('change', '#scormsubject').on('change', '#scormsubject', function () {
                        fillscorm(root);
                    });

                    root.off('change', '#scormurls').on('change', '#scormurls', function () {
                        var selectedCount = $(this).find('option:selected').length;

                        if (selectedCount > 0) {
                            root.find('.submitForm').removeAttr('disabled');

                            var selectionMsg = selectedCount > 1
                                ? selectedCount + ' modules selected'
                                : '1 module selected';

                            root.find('.statusMsg').html(
                                '<span style="color:#0f6cbf;">' +
                                '<i class="fa fa-info-circle"></i> ' +
                                selectionMsg +
                                '. Ready to add to course.</span>'
                            );
                        } else {
                            root.find('.submitForm').prop('disabled', true);
                            root.find('.statusMsg').html('');
                        }
                    });

                    root.off('click', '.closeModal').on('click', '.closeModal', function () {
                        $(this).blur();

                        if (blcModal) {
                            blcModal.hide();
                        }
                    });

                    root.off('click', '.submitForm').on('click', '.submitForm', function () {
                        submitModules(root, courseId);
                    });

                    // Clean up when modal is hidden.
                    root.off('hidden.bs.modal').on('hidden.bs.modal', function () {
                        $('.subject-loading, .scorm-loading, .processing-indicator').remove();
                        $('.progress-container').remove();
                        root.find('.statusMsg').html('');
                        root.find('.submitForm').prop('disabled', true);
                    });
                })
                .catch(function(error) {
                    console.error("Failed to create modal", error);
                });
            }

        }

        $(".activityinstance").on("click", ".fa-refresh", function () {

            var data = $(this).closest(".cmid-version").attr('id');

            var cmid = data.split("-")[0];

            var version = data.split("-")[1];

            updateScorm(cmid, version);

        });
    }

    function submitModules(root, courseId) {
        // Guard: prevent concurrent executions (e.g. double-click, duplicate handlers).
        if (root.data('blcProcessing')) {
            return;
        }
        root.data('blcProcessing', true);

        var apikey = $('#apikey').val();
        var scormurls = [];

        var visibility = root.find("#id_visible").val();
        var hidebrowse = root.find("#id_hidebrowse").val();
        var completion = root.find("#id_completion").val();

        $.each(root.find("#scormurls option:selected"), function () {
            var urll = $(this).val();
            urll = urll.replace(",", "qqq");
            scormurls.push(urll);
        });

        root.find('.statusMsg').html('');

        root.find('.submitForm').attr("disabled", "disabled");
        root.find('.closeModal').attr("disabled", "disabled");

        root.find(
            '#scormsubject, #scormurls, #id_visible, #id_hidebrowse, #id_completion'
        ).prop('disabled', true);

        root.find(".modal-header .fa-spinner").remove();

        root.find(".modal-header")
            .append(
                '<div class="processing-indicator" style="margin-left:15px;display:inline-block;">' +
                '<i class="fa fa-spinner fa-spin" style="font-size:18px;color:#0f6cbf;"></i>' +
                '</div>'
            );

        root.find('.progress-container').remove();

        root.find('.statusMsg').after(
            '<div class="progress-container" style="margin-top:15px;">' +
            '<div class="progress" style="height:20px;">' +
            '<div class="progress-bar progress-bar-striped active" role="progressbar" ' +
            'style="width:0%; background: linear-gradient(90deg, #0f6cbf, #4a90e2);" ' +
            'aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">' +
            '<span class="progress-text">0%</span>' +
            '</div>' +
            '</div>' +
            '<div class="progress-status"></div>' +
            '</div>'
        );

        var processorUrl = M.cfg.wwwroot + '/blocks/blc_modules/scorm_load_processor.php';
        var sesskey = M.cfg.sesskey;
        var consecutiveErrors = 0;
        var maxErrors = 10;
        var fileCount = scormurls.length;

        if (fileCount === 0) {
            showError('No modules selected.');
            return;
        }

        root.find('.statusMsg').html(
            '<span style="color:#0f6cbf;"><i class="fa fa-download"></i> Starting process...</span>'
        );
        root.find('.progress-status').text('Preparing to process ' + fileCount + ' modules...');

        function startProcess() {
            $.ajax({
                url: processorUrl,
                type: 'POST',
                data: {
                    action: 'start',
                    sesskey: sesskey,
                    courseid: parseInt(courseId, 10),
                    sectionnumber: parseInt(x, 10),
                    apikey: apikey,
                    scormurls: JSON.stringify(scormurls),
                    visibility: parseInt(visibility, 10),
                    hidebrowse: parseInt(hidebrowse, 10),
                    completion: parseInt(completion, 10)
                },
                dataType: 'json',
                timeout: 30000,
                success: function(response) {
                    if (response.success) {
                        // Verify the start action actually initialized the progress data.
                        if (response.data && response.data.total > 0) {
                            root.find('.statusMsg').html(
                                '<span style="color:#0f6cbf;"><i class="fa fa-cog fa-spin"></i> Processing modules. This may take several minutes for large files...</span>'
                            );
                            setTimeout(processNext, 500);
                        } else {
                            showError('Process started but no modules were queued. Total: ' +
                                (response.data ? response.data.total : 'unknown'));
                        }
                    } else {
                        showError('Failed to start: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    showError('Failed to initialize process: ' + error);
                }
            });
        }

        function processNext() {
            $.ajax({
                url: processorUrl,
                type: 'POST',
                data: {
                    action: 'process',
                    sesskey: sesskey
                },
                dataType: 'json',
                timeout: 120000,
                success: function(response) {
                    consecutiveErrors = 0;

                    if (response.success && response.data) {
                        var data = response.data;

                        // Defensive check: if total is 0 but we know we started with modules,
                        // the session data was likely lost. Show an error instead of a stuck bar.
                        if (data.total === 0 && !data.complete && fileCount > 0) {
                            showError('Session data lost. The progress tracking was interrupted. Please try again.');
                            return;
                        }

                        if (data.total > 0) {
                            var percentage = Math.round((data.processed / data.total) * 100);
                            root.find('.progress-bar').css('width', percentage + '%');
                            root.find('.progress-bar').attr('aria-valuenow', percentage);
                            root.find('.progress-text').text(percentage + '%');
                            root.find('.progress-status').text('Processing ' + data.processed + ' of ' + data.total + ' modules...');
                        }

                        if (data.current_module && data.current_module.name) {
                            root.find('.statusMsg').html(
                                '<span style="color:#0f6cbf;"><i class="fa fa-cog fa-spin"></i> ' + data.current_module.name + '</span>'
                            );
                        }

                        if (data.complete) {
                            handleCompletion(data);
                        } else {
                            setTimeout(processNext, 100);
                        }
                    } else {
                        showError('Process error: ' + (response.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    consecutiveErrors++;

                    // Show retry feedback on the progress bar so the user knows something is happening.
                    root.find('.progress-status').text(
                        'Connection issue, retrying (' + consecutiveErrors + '/' + maxErrors + ')...'
                    );

                    if (consecutiveErrors % 3 === 1) {
                        root.find('.statusMsg').html(
                            '<span style="color:#ff9800;"><i class="fa fa-exclamation-triangle"></i> Retrying... (' +
                            consecutiveErrors + ' attempts)</span>'
                        );
                    }

                    if (consecutiveErrors < maxErrors) {
                        setTimeout(processNext, 2000);
                    } else {
                        showError('Too many errors. Process may have failed. Last error: ' + (error || status));
                    }
                }
            });
        }

        function handleCompletion(data) {
            root.find('.processing-indicator').remove();
            root.find('.progress-bar').css('width', '100%').attr('aria-valuenow', 100);
            root.find('.progress-text').text('100%');

            if (data.failed === 0 && data.success > 0) {
                var msg = data.success === 1 ? '1 module loaded!' : data.success + ' modules loaded successfully!';
                root.find('.statusMsg').html('<span style="color:green;"><i class="fa fa-check-circle"></i> ' + msg + '</span>');
                root.find('.progress-status').text('All modules processed successfully!');

                setTimeout(function() {
                    location.reload(true);
                }, 1500);
            } else if (data.success > 0) {
                root.find('.statusMsg').html('<span style="color:#ff9800;"><i class="fa fa-exclamation-triangle"></i> ' + data.success + ' succeeded, ' + data.failed + ' failed</span>');
                root.find('.progress-status').text('Process completed with some errors');

                if (data.errors && data.errors.length > 0) {
                    root.find('.progress-status').append('<br><small style="color:red;">' + data.errors.join('<br>') + '</small>');
                }

                setTimeout(function() {
                    location.reload(true);
                }, 3000);
            } else {
                showError('All modules failed to load');
                if (data.errors && data.errors.length > 0) {
                    root.find('.progress-status').html('<small style="color:red;">' + data.errors.join('<br>') + '</small>');
                }
            }

            root.find('.submitForm').removeAttr('disabled');
            root.find('.closeModal').removeAttr('disabled');
            root.find('#scormsubject, #scormurls, #id_visible, #id_hidebrowse, #id_completion').prop('disabled', false);
            root.removeData('blcProcessing');
        }

        function showError(message) {
            root.find('.progress-container').remove();
            root.find('.processing-indicator').remove();
            root.find('.statusMsg').html('<span style="color:red;"><i class="fa fa-exclamation-circle"></i> ' + message + '</span>');
            root.find('.submitForm').removeAttr('disabled');
            root.find('.closeModal').removeAttr('disabled');
            root.find('#scormsubject, #scormurls, #id_visible, #id_hidebrowse, #id_completion').prop('disabled', false);
            root.removeData('blcProcessing');
        }

        startProcess();
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
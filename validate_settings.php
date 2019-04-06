<?php

//this will validate the settings

require_once(dirname(__FILE__).'/../../config.php');
//require_once('curl.php');
global $DB,$USER,$CFG;
require_login(null, false);

// PERMISSION.
require_capability('moodle/user:viewdetails', context_system::instance(), $USER->id);

$title = get_string('pluginname', 'block_blc_modules');
$heading = $SITE->fullname;
$url = '/blocks/blc_modules/validate_settings.php';


$baseurl = new moodle_url($url);

$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_context(context_system::instance());
$PAGE->set_title("Validate BLC Settings");
$PAGE->set_heading($heading);
$PAGE->set_cacheable(false);
// $PAGE->requires->jquery();

echo $OUTPUT->header();


$requesturi = $CFG->wwwroot;	
$apikey = get_config('block_blc_modules', 'api_key');

$token = get_config('block_blc_modules', 'token');

$domainname = get_config('block_blc_modules', 'domainname');
$function_name = 'validate_blc_settings';
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
 . '&wsfunction='.$function_name . '&apikey='.$apikey. '&requesturi='.$requesturi;
$curl = new curl;
$curl->setHeader('Content-Type: application/json; charset=utf-8');


$responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));



$apisuccess = '<div class="alert alert-success alert-block fade in " role="alert">
    
    Success: Your <b>API Key</b> is configured correctly.
</div>';

$urlsuccess = '<div class="alert alert-success alert-block fade in " role="alert">
    
    Success: Your <b>Moodle URL</b> is configured correctly.
</div>';

$apifail = '<div class="alert alert-warning alert-block fade in " role="alert">
    
    Failure: Your <b>API Key</b> is not configured correctly.
</div>';

$urlfail = '<div class="alert alert-warning alert-block fade in " role="alert">
    
    Failure: Your <b>Moodle URL</b> is not configured correctly.
</div>';



$successboth = '

<h2>Fully Working</h2>
<p>We can\'t see anything wrong with your configuration. If you experience any issues, please contact us at <a href="mailto:blc@howcollege.ac.uk">blc@howcollege.ac.uk</a></p>

';

$failboth = '

<h2>Configuration problem</h2>
<p>There is a problem with both your API key and your URL. We suggest revisiting <a href="blc.howcollege.ac.uk">blc.howcollege.ac.uk</a> and rechecking the details. If you continue to experience issues, please contact us at <a href="mailto:blc@howcollege.ac.uk">blc@howcollege.ac.uk</a></p>

';

$failone = '

<h2>Configuration problem</h2>
<p>There is a problem with some of your settings. We suggest revisiting <a href="blc.howcollege.ac.uk">blc.howcollege.ac.uk</a> and checking the details. If you continue to experience issues, please contact us at <a href="mailto:blc@howcollege.ac.uk">blc@howcollege.ac.uk</a></p>

';

$returntosettings = '

<div class="row">
                <div class="col-sm-3" >
				<button onclick="window.location.href = \''.$CFG->wwwroot.'/blocks/blc_modules/validate_settings.php\';" class="btn btn-primary" >Refresh</button>
				</div>
                <div class="col-sm-3" >    
                    <button onclick="window.location.href = \''.$CFG->wwwroot.'/admin/settings.php?section=blocksettingblc_modules\';" class="btn btn-secondary" >Return to Settings</button>
                </div>
            </div>




';


$bothresponses = explode(",",$responses);
$urlokay = $bothresponses[0];
$apiokay = $bothresponses[1];

//print_r($bothresponses);

//echo $apiokay . "  ". $urlokay;



if(strpos($urlokay, 'true') !== false && strpos($apiokay, 'true') !== false) {
    echo $apisuccess . "<br />" . $urlsuccess . "<br />";
    echo $successboth;
    echo $returntosettings;

}else if(strpos($urlokay, 'true') !== false){
    echo $urlsuccess . "<br />" . $apifail . "<br />";
    echo $failone;	
    echo $returntosettings;

}else if(strpos($apiokay, 'true') !== false){
    echo $apisuccess . "<br />" . $urlfail . "<br />";
    echo $failone;	
    echo $returntosettings;

}else if(strpos($urlokay, 'true') == false && strpos($apiokay, 'true') == false){
	echo $apifail . "<br />" . $urlfail . "<br />";
	echo $failboth;	
	echo $returntosettings;
}

echo $OUTPUT->footer();
?>
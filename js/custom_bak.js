	
	
(function ($) {
	 
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
    }
    
     function fillSubject() {
			var apikey = $('#apikey').val();
			//console.log("apikey: " + apikey);
				
			$.getJSON("../blocks/blc_modules/load_scormsubject.php?apikey="+encodeURIComponent(apikey), function( data ) {
                var items = [];
                //console.log(data);
                if(data.length==0){
					$('.submitForm').attr("disabled","disabled");
			  		$('.statusMsg').html('<span style="color:red;"><b>An error has occured.</b></p>');
				}
					$.each( data, function( key, val ) {
							items.push( "<option value='" + key + "'>" + val + "</option>" );
					});

				  $("#scormsubject").html( items.join( "" ) );
              
			
			}); 
	 }
     function checkVersion(id) {
						console.log("id: " + id);
			var apikey = $('#apikey').val();

			$.getJSON("../blocks/blc_modules/version_check.php?apikey="+encodeURIComponent(apikey)+"&id="+id, function( data ) {
                var items = [];
                $.each( data, function( key, val ) {

					$("#module-"+key+" .mod-indent-outer .activityinstance").append('<li class="cmid-version" id="'+key+'-'+val+'"><i id="updatescorm"  style="cursor: pointer;" class="icon fa fa-refresh fa-fw " title="New version available" aria-label="Update"></i>');
					
                });

             // $("#scormsubject").html( items.join( "" ) );
			}); 
	 }
     function fillscorm() {
			var scormsubject = $('#scormsubject option:selected').text();
			var apikey = $('#apikey').val();

			//console.log("scormsubject: " + scormsubject);
			if(scormsubject != 0){	
				$.getJSON("../blocks/blc_modules/load_scormurls.php?apikey="+encodeURIComponent(apikey)+"&subject="+encodeURIComponent(scormsubject), function( data ) {
					var items = [];
					$.each( data, function( key, val ) {
							var sanVal = val.replace(".zip", "");
							items.push( "<option style='-moz-white-space: pre-wrap; -o-white-space: pre-wrap; white-space: pre-wrap;' value='" + key + "'>" + sanVal + "</option>" );
					});

				  $("#scormurls").html( items.join( "" ) );
				}); 
			}
		}
		function updateScorm(cmid,version) {
		
				$.ajax({
				type:'GET',
				url:'../blocks/blc_modules/update_scorm.php',
				data:'cmid='+cmid+'&version='+version,
				beforeSend: function () {
					$("#module-"+cmid+" .mod-indent-outer .cmid-version").append('<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>');

				},
				success:function(){
					location.reload(true);
				}
			});		
					
	}
	
	jQuery( document ).ready(function() {
		var pageURL = $(location).attr("href");
			pageURL = pageURL.split("&")[0];
			pageURL = pageURL.split("#")[0];
			//console.log("pageURL: " + pageURL);

			var id = getUrlParameter(pageURL, "id");
			var notifyeditingon = $("#userediting").val();
			if(notifyeditingon==1)
				checkVersion(id);
		    
		if ( $( "#addscorm" ).hasClass( "block_blc_modules" ) ) {
			
			fillSubject() ;
			
			var notifyeditingon = $("#userediting").val();
			var allowstealthval = $("#allowstealthvalue").val();

			if(allowstealthval == 1){
				allowstealthstring = '<option value="-1">Make available but not shown</option>';
			}else{
				allowstealthstring = '';
			}
			var completionon = $("#completionon").val();
			
			if(completionon == 1){
				completionstring='<select class="custom-select " name="completion" id="id_completion"> <option value="0">Off</option> <option value="1" >Manual</option> <option value="2" selected="">Automatic</option> </select> <div class="form-control-feedback invalid-feedback" id="id_error_completion">';
			}else{
				completionstring = '<p>Activity completion is not enabled on this course.</p><div class="form-control-feedback invalid-feedback" id="id_error_completion">';
			}

			$(".course-content").append('<div class="modal fade" id="bsModal3" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel" aria-hidden="true"> <div class="modal-dialog modal-md"> <div class="modal-content"> <div class="modal-header"> <h4 class="modal-title" id="mySmallModalLabel">Select BLC Modules</h4> </div> <div class="modal-body"> <p class="statusMsg"></p> <form role="form"> <div class="form-group"> <select style="min-width:100%;" class="form-control" id="scormsubject" name="scormsubject"> </select> </div> <div class="form-group"> <select style="min-width:100%;" class="form-control" id="scormurls" name="scormurls" multiple > </select> </div> <div class="additional_settings"> <h3>SCORM Settings</h3> <div class="blcrow"> <div class="blcrow"> <span style="float:left; margin-right:10px;" class="text-nowrap"> <a style="background: none; padding-bottom:3px !important; border:none;" class="btn btn-link p-0 avail" id=""  role="button" data-container="body" data-toggle="popover" data-placement="right" data-content="<div class=&quot;no-overflow&quot;><p>If the availability is set to \'Show on course page\', the activity or resource is available to students (subject to any access restrictions which may be set).<br /><br /> If the availability is set to \'Hide from students\', the activity or resource is only available to users with permission to view hidden activities (by default, users with the role of teacher or non-editing teacher).<br /><br /> If the course contains many activities or resources, the course page may be simplified by setting the availability to \'Make available but not shown on course page\'. In this case, a link to the activity or resource must be provided from elsewhere, such as from a page resource. The activity would still be listed in the gradebook and other reports.</p> </div> " data-html="true" tabindex="0" data-trigger="focus"> <img src="../blocks/blc_modules/pix/question.png" style="max-width: 20px;" onclick="event.preventDefault();" aria-hidden="true" title="Help with Availability" aria-label="Help with Availability" /> </a> </span> <label style="line-height: 18px;" class="blclabel" for="id_visible"> Availability </label> </div> <div class="blcrow" data-fieldtype="modvisible"> <select class="custom-select " name="visible" id="id_visible"> <option value="1" selected="">Show on course page</option> <option value="0">Hide from students</option>'+allowstealthstring+'</select> <div class="form-control-feedback invalid-feedback" id="id_error_visible"> </div> </div> </div> <br/> <div class="blcrow" > <div class="blccolmd6"> <span style="float:left; margin-right:10px;" class="text-nowrap"> <a style="background: none; padding-bottom:3px !important; border:none;" class="btn btn-link p-0 prev" role="button" id="" data-container="body" data-toggle="popover" data-placement="right" data-content="<div class=&quot;no-overflow&quot;><p>Preview mode allows a student to browse an activity before attempting it. If preview mode is disabled, the preview button is hidden.</p> </div> " data-html="true" tabindex="0" data-trigger="focus"> <img src="../blocks/blc_modules/pix/question.png" style="max-width: 20px;" onclick="event.preventDefault();" aria-hidden="true" title="Help with Availability" aria-label="Help with Availability" /> </a> </span> <label style="line-height: 18px;" class="blclabel" for="id_hidebrowse"> Disable preview mode </label> </div> <div class="blcrow" style="" data-fieldtype="selectyesno"> <select class="custom-select " name="hidebrowse" id="id_hidebrowse"> <option value="0">No</option> <option value="1" selected="">Yes</option> </select> <div class="form-control-feedback invalid-feedback" id="id_error_hidebrowse"> </div> </div> </div> <br/> <div class="blcrow" > <div class="blccolmd6"> <span style="float:left; margin-right:10px;" class="text-nowrap"> <a style="background: none; padding-bottom:3px !important; border:none;" class="btn btn-link p-0 comp" id="" role="button" data-container="body" data-toggle="popover" data-placement="right" data-content="<div class=&quot;no-overflow&quot;><p>If enabled, activity completion is tracked, either manually or automatically.<br/> If Automatic is selected, the best options for BLC modules are set, whereas if manual is selected, the studnt must manually tick a box next to the activity for it to register as complete.</p> <p>A tick next to the activity name on the course page indicates when the activity is complete.</p> </div>" data-html="true" tabindex="0" data-trigger="focus"> <img src="../blocks/blc_modules/pix/question.png" style="max-width: 20px;" onclick="event.preventDefault();" aria-hidden="true" title="Help with Availability" aria-label="Help with Availability" /> </a> </span> <label class="blclabel" style="line-height: 18px;" for="id_completion"> Completion tracking </label> </div> <div class="blccolmd6"  style="" data-fieldtype="select" >'+completionstring+'</div> </div> </div> </div> </div> <div class="modal-footer"> <button type="button" class="btn btn-default closeModal" data-dismiss="modal">Close</button> <button type="button" disabled="disabled" class="btn btn-primary submitForm" >Add Modules</button> </div> </div> </div> </div> ');
			var mright = $("#section-0 .content").css("margin-right");



			var count=0;
			var wwwroothidden = $('#wwwroot_hidden').html();
			$(".section.main").each(function( ) {			
				if(notifyeditingon==1)
					$(this).append("<a style='float:right; margin-right:"+mright+"; cursor: pointer; color: #a864a8;' class='add-scrom' data-toggle='modal' data-target='#bsModal3'><img style='padding-bottom:3px;' src='../blocks/blc_modules/pix/blc.png'/>   Add BLC modules</a>");
						var margin = $(".row").css("margin-left");
				    	if(margin == "-30px" || margin == "-20px"){
				    		$(".form-group").css("margin-left", "5px");
				    		$(".float-sm-right").css("float", "left");
				    		
				    	}
					
			});
			$("#scormsubject").change(function(){
                fillscorm();
        });
			$(".course-content").on("click",".add-scrom", function() {
				var secId = $(this).closest(".section").attr('id');
				var secNum = secId.split("-")[1];
				x = secNum;
				
			});

			$(".course-content").on("click","#scormurls option", function() {
				$('.submitForm').prop("disabled", false);
			});
			$(".course-content").on("click",".submitForm", function() {
				var apikey = $('#apikey').val();

				var scormurls = [];
				var visibility = $("#id_visible").val();
				var hidebrowse = $("#id_hidebrowse").val();
				var completion = $("#id_completion").val();

				$.each($("#scormurls option:selected"), function(){   
					var urll = $(this).val();
					urll = urll.replace(",", "qqq");    
					scormurls.push(urll);
				});
				 $('.submitForm').attr("disabled","disabled");
				 $('.closeModal').attr("disabled","disabled");
	                $(".modal-header").append('<i class="fa fa-spinner fa-spin" style="font-size:24px"></i>');

	                console.log(scormurls);
				$.get("../blocks/blc_modules/load_scorm.php",
				{
					id: id,
					sectionNumber: x,
					scormurls : scormurls,
					apikey : apikey,
					visibility: visibility,
					hidebrowse: hidebrowse,
					completion: completion

				},
				function(data, status){
				location.reload(true);
				});
			});
							

			
		}
		$(".activityinstance").on("click",".fa-refresh", function() {
			var data = $(this).closest(".cmid-version").attr('id');
			var cmid = data.split("-")[0];
			var version = data.split("-")[1];
			updateScorm(cmid,version);

		});
				
	});
					
}(jQuery));
var x;

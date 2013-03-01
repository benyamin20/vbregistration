AJAX_Compatible = true;
var md5_loaded = false;

var spinner = '<img id="ajax-spinner" src="' + sr_path_img
        + '/img/ajax-loader.gif" />';
var spinner_secondary = '<img id="ajax-spinner-secondary" src="' + sr_path_img
        + '/img/ajax-loader.gif" />';

jQuery.getScript(sr_path_js + "/js/bootbox.min.js", function() {
});

/**
 * check if something exists
 */
jQuery.fn.exists = function() {
    return this.length > 0;
};

/**
 * bind function to check if enter is pressed
 */
jQuery.fn.enterKey = function(fnc) {
    return this.each(function() {
        jQuery(this).keypress(function(ev) {
            var keycode = (ev.keyCode ? ev.keyCode : ev.which);
            if (keycode == '13') {
                fnc.call(this, ev);
            }
        });
    });
};

/*
 * Get value from a checkbox
 */
$.fn.realVal = function() {
    var $obj = $(this);
    var val = $obj.val();
    var type = $obj.attr('type');
    if (type && type === 'checkbox') {
        var un_val = $obj.attr('data-unchecked');
        if (typeof un_val === 'undefined')
            un_val = '';
        return $obj.prop('checked') ? val : un_val;
    } else {
        return val;
    }
};

// alternative function to close a facebox through a trigger
function closeTnC() {
    jQuery(document).trigger('close.facebox');
}

/**
 * close term and conditions dialog
 */
function closeTnCAccept() {
    jQuery(document).trigger('close.facebox');
    jQuery('#terms-and-conditions').attr('checked', true);
}

/**
 * Try to set the default value for timezone on last step
 */
function get_time_zone_offset() {
    var current_date = new Date();
    var gmt_offset = (current_date.getTimezoneOffset() * -1) / 60;
    return gmt_offset;
}

/*
 * Set up security token
 */
function regenerate_token() {
    // regenerate token to avoid getting security errors
    if (jQuery('#token').exists() || jQuery('#securitytoken').exists()) {

        jQuery.getJSON(sr_path_php
                + "/php/index.php?op=regenerate_security_token",

        function(json) {
            jQuery('#token').val(json.token)
                    || jQuery('#securitytoken').val(json.token);
        });

    }
}

/**
 * Clear errors
 */
function clear_errors() {

    if (jQuery('.sr-error-label').exists()) {
        jQuery('.sr-error-label').empty();
    }

    if (jQuery('.large-sr-input-error-container').exists()) {
        jQuery('.large-sr-input-error-container').removeClass(
                "large-sr-input-error-container");
    }

    if (jQuery('.sr-input-error-container').exists()) {
        jQuery('.sr-input-error-container').removeClass(
                "sr-input-error-container");
    }

    if (jQuery('.terms-and-conditions-sr-input-error-container').exists()) {
        jQuery('.terms-and-conditions-sr-input-error-container').removeClass(
                "terms-and-conditions-sr-input-error-container");
    }

    if (jQuery('span.add-on').exists()) {
        jQuery('span.add-on').removeClass("sr-input-error");
    }

    if (jQuery('.sr-input-error').exists()) {
        jQuery('.sr-input-error').removeClass("sr-input-error");
    }

}

/**
 * Show spinners
 */
function initialize_spinner() {
    if (jQuery('#ajax-loader').exists()) {
        jQuery('#ajax-loader').empty();
        jQuery('#ajax-loader').append(spinner);
    }
}

/**
 * AJAX error handling ACP-455
 */
jQuery
        .ajaxSetup({

            error : function(jqXHR, exception) {
                if (jQuery('#ajax-spinner').exists()) {
                    jQuery('#ajax-spinner').remove();
                }
                
                if (jQuery('#ajax-spinner-secondary').exists()) {
                    jQuery('#ajax-spinner-secondary').remove();
                }

                if (jqXHR.status === 0) {
                    //msg = 'Please try again later.\n Not connected.\n Verify Network connectivity.';
                } else if (jqXHR.status == 404) {
                    msg = 'Please try again later.\n Requested page not found. [404]';
                } else if (jqXHR.status == 500) {
                    msg = 'Please try again later.\n Internal Server Error [500].';
                } else if (exception === 'parsererror') {

                    var ct = jqXHR.getResponseHeader("content-type") || "";

                    if (ct != "application/json") {
                        var xml = jqXHR.responseText, xmlDoc = $.parseXML(xml), $xml = $(xmlDoc), $error = $xml
                                .find("error");

                        msg = $error.text();
                    } else {
                        msg = 'Please try again later.\n Requested JSON parse failed.';
                    }

                } else if (exception === 'timeout') {
                    msg = 'Please try again later.\n Time out error.';
                } else if (exception === 'abort') {
                    msg = 'Please try again later.\n Ajax request aborted.';
                } else {
                    msg = 'Please try again later.\n Uncaught Error.\n'
                            + jqXHR.responseText;
                }
 
                if (msg && bootbox) {
                    bootbox.alert(msg);
                }else{
                    alert(msg);
                }

            }
        });


function convertToEntities(tstr) {     
    var bstr = '';  

    for (i = 0; i < tstr.length; i++) {  
        if (tstr.charCodeAt(i) > 127) { 
            bstr += '\&#' + tstr.charCodeAt(i) + ';'; 
        } else { 
            bstr += tstr.charAt(i);
        }
    } 

    return bstr;
}



/**
 * lazy load and bootstrap all elements
 */
jQuery(document).ready(function (jQuery) {

    // jQuery(':input[placeholder]').placeholder();

    // try to automatically assign timezone
    if (jQuery("#timezone").exists()) {
        try {
            jQuery("#timezone").val(get_time_zone_offset());
        } catch (e) {

        }
    }

    if (jQuery('input[id*="password"]').exists() && !md5_loaded) {
        jQuery.getScript(sr_path_js + "/js/md5.js", function () {
            md5_loaded = true;
        });
    }

    //initialize any facebox
    if (jQuery('a[rel*=facebox]').exists()) {
        jQuery.getScript(sr_path_js + "/facebox/facebox.js", function () {
            jQuery('a[rel*=facebox]').facebox({
                loadingImage: sr_path_img +  '/facebox/loading.gif',
                closeImage: sr_path_img + '/facebox/closelabel.png'
            });
        });

    }

    //feature to alternatively close facebox
    if (jQuery('a[class*=close-facebox]').length > 0) {
        jQuery('a[class*=close-facebox]').bind('click', function () {
            jQuery(document).trigger('close.facebox');
        });
    }

    // initialize date picker
    if (jQuery("#datepicker").exists()) {
        var current_year = new Date().getFullYear();
        var year = 1970;

        var offset = current_year - year;
        var default_date = "-" + offset + "y";


        //check if coppa is enabled
        jQuery.getJSON(sr_path_php + "/php/index.php?op=check_coppa", function (json) {
            if (json.use_coppa == 2) {
                max_date = "-13y";
            } else {
                max_date = "0";
            }

            //initialize date picker
            jQuery.getScript(sr_path_js + "/jquery-ui/js/jquery-ui-1.9.2.custom.min.js", function () {
                jQuery(function () {
                    jQuery("#datepicker").datepicker({
                        autoSize: true,
                        minDate: '-120y',
                        maxDate: max_date,
                        changeYear: true,
                        changeMonth: true,
                        constrainInput: true,
                        hideIfNoPrevNext: false,
                        yearRange: '1900:2100',
                        onSelect: function (date) {
                            if (jQuery("#placeholder-datepicker").exists()) {
                                jQuery("#placeholder-datepicker").remove();
                            }
                        },
                        defaultDate: default_date
                    });
                });
            });

        });


        //check if the calendar icon exists and bind the click action
        // to show datepicker
        if (jQuery('.add-on').exists()) {
            jQuery('.add-on').bind('click', function () {
                jQuery("#datepicker").datepicker("show");
            });
        }
    }




    //refresh captcha on click
    if (jQuery('#refresh-captcha').exists()) {
        jQuery("#refresh-captcha").bind('click', function () {
            var token = escape(jQuery("#token").val());
            jQuery.ajax({
                url: sr_path_php + "/php/index.php?op=regenerate_token",
                context: document.body,
                dataType: 'json',
                type: 'POST',
                cache: false,
                data: 'securitytoken=' + token,
                beforeSend: function () {
                    jQuery('#ajax-loader-secondary').empty();
                    jQuery('#ajax-loader-secondary').append(spinner_secondary);
                    regenerate_token();

                },
                success: function (response) {

                    var src = response.url;
                    jQuery("#imagereg").attr('src', src);


                    if (jQuery('#ajax-spinner-secondary').exists()) {
                        jQuery('#ajax-spinner-secondary').remove();
                    }

                }
            });

        });
    }


    //assign default image to upload file   
    if (jQuery("#use-default").exists()) {
        jQuery("#use-default").bind('click', function () {

            //set hidden to use default image
            jQuery("#use-default-image").val("true");
            jQuery("input[name=use_default_image]").val("true");

            //change image preview thumb
            jQuery("#selected-avatar").attr("src", sr_path_img + "/img/unknown.png");
        });


    }


    if (jQuery(".fileupload-exists").exists()) {
        jQuery(".fileupload-exists").bind('click', function () {

            //set hidden to use default image
            jQuery("#use-default-image").val("true");
            jQuery("input[name=use_default_image]").val("true");

            //change image preview thumb
            jQuery("#selected-avatar").attr("src", sr_path_img + "/img/unknown.png");
            jQuery("#upload-sr-error-label").empty();
            jQuery("#upload-wrapper").removeClass("terms-and-conditions-sr-input-error-container");
        });
    }


    if (jQuery(".fileupload-new").exists()) {
        jQuery(".fileupload-new").bind('click', function () {

            //set hidden to use default image
            jQuery("#use-default-image").val("");
            jQuery("input[name=use-default-image]").val("");


        });
    }


    




    //activates account on last step
    if (jQuery("#save-account-activated").exists()) {
        
        //remove table width
        var tables = jQuery("#sr-container table");
        tables.removeAttr("width");
 
        //bind enter event to custom fields
        jQuery('input[name^="userfield"]').enterKey(function () {
            jQuery("#save-account-activated").trigger('click');
        });
 
        // prepare Options Object 
        var options = {
            type: 'POST',
            dataType: 'json',
            beforeSend: function () {
                jQuery("#progress-indicator-container").addClass("progress-striped active");

                initialize_spinner();
                regenerate_token();
                
                //remove wrapper for custom fields
                jQuery('[name^="userfield"]').unwrap();
            },
            success: function (response) {
                if (jQuery('#ajax-spinner').exists()) {
                    jQuery('#ajax-spinner').remove();
                }

                if (response.valid_entries == false) {
                    //stop animated progress bar
                    jQuery("#progress-indicator-container").removeClass("progress-striped active");

                    clear_errors();
                    
                    var pattern="userfield";
                    var error = 'Required field missing or has an invalid value.';

                    jQuery.each(response.messages.fields, function (index, value) {
                        
                        //upload might need a different error container
                        if (value == 'upload') {
                            jQuery('#' + value + '-wrapper').addClass("terms-and-conditions-sr-input-error-container");
                        } else {
                            //handle custom field errors
                            if(value.indexOf(pattern) !=-1){
                                jQuery('[name="' + value + '"]').addClass("sr-input-error");
                                jQuery('[name="' + value + '"]')
                                    .wrap('<div class="large-sr-input-error-container" id="' + value + '-sr-error-label-container" />');
                                
                                jQuery('span[id="' + value + '-sr-error-label"]').empty();
                                jQuery('[id="' + value + '-sr-error-label"]').remove();
                                
                                if(!jQuery('[id="' + value + '-sr-error-label"]').exists()){
                                    jQuery('[name="' + value + '"]')
                                        .after('<span id="'+value+'-sr-error-label" class="sr-error-label"></span>');
                                }
                                
                                
                                
                                jQuery('span[id="' + value + '-sr-error-label"]')   
                                    .html(error);
                                
                                
                            }else{
                                jQuery('#' + value + '-wrapper').addClass("large-sr-input-error-container");
                            }
                        }
                        
                        jQuery('#' + value).addClass("sr-input-error");
                        jQuery('#' + value + '-sr-error-label').empty();
                        jQuery('#' + value + '-sr-error-label').append(response.messages.errors[index]);    

                    });

                } else {
                    //valid entries
                    var i = 50;
                    for (i = 50; i <= 100; i++) {
                        jQuery('#progress-indicator').css("width", i + '%');
                        jQuery('#percentage-indicator').html(i);
                    }

                    try {
                        clear_errors();
                    } catch (e) {

                    }

                    jQuery('#would-you-like').hide();
                    jQuery('#show-great').empty();
                    jQuery('#show-great').html("Thanks!");
                    jQuery('#complete-your-profile-form').remove();
                    jQuery("#progress-indicator-container").removeClass("progress-striped active");
                    jQuery("#icon-ok").removeClass('hidden');
                    jQuery("#icon-ok").show();
                    jQuery("#user-cp").removeClass('hidden');
                    jQuery("#user-cp").show();

                }
            }
        };
 

        jQuery('#complete-your-profile-form').ajaxForm(options);
 
    

    }
    
    
  //prepopulate any required fields
    if(jQuery("#pre-populate-fields").exists()){ 
        var token = escape(jQuery("#securitytoken").val());
                
        if(typeof token == undefined){
        	token = "guest";
        }
        
        
        jQuery.ajax({
            url: sr_path_php + "/php/index.php?op=prepopulate_fields",
            context: document.body,
            dataType: 'json',
            type: 'POST',
            cache: false,
            data: 'securitytoken=' + token,
            beforeSend: function () {
                jQuery('#ajax-loader-secondary').empty();
                jQuery('#ajax-loader-secondary').append(spinner_secondary);
                regenerate_token();

            },
            success: function (response) {
            	
            	jQuery.each(response.names, function (index, value) {
            		
            		type = jQuery('[name^="'+value+'"]').attr('type');
            		
            		if(type == "radio"){
            			//clear any default value 
                		var $radios = jQuery('input:radio[name="'+value+'"]');
                		$radios.attr('checked', false);
                		
                		//select proper input according to value from response
            			jQuery("label:contains('"+response.values[index]+"')").children().attr('checked', true);
                		
            		}else if(type == 'select'){
            			jQuery('[name^="'+value+'"]').val(0);
            			
            			jQuery("[name^="+value+"] option[text=" + response.values[index] +"]").get(0).selected = true;
            			
            		}else{
            			jQuery('[name^="'+value+'"]').val(response.values[index]);
            		}
            		
            	});

                if (jQuery('#ajax-spinner-secondary').exists()) {
                    jQuery('#ajax-spinner-secondary').remove();
                }

            }
        });
 
    }



    //site account details
    if (jQuery("#site-account-deails-create-account").exists()) {
        //bind enter event to already have an account fields
        jQuery("#username").enterKey(function () {
            jQuery("#site-account-deails-create-account").trigger('click');
        });

        jQuery("#password").enterKey(function () {
            jQuery("#site-account-deails-create-account").trigger('click');
        });

        jQuery("#confirm-password").enterKey(function () {
            jQuery("#site-account-deails-create-account").trigger('click');
        });

        jQuery("#security-code").enterKey(function () {
            jQuery("#site-account-deails-create-account").trigger('click');
        });
        
        jQuery("#parent-guardian-email").enterKey(function () {
            jQuery("#site-account-deails-create-account").trigger('click');
        });
        
        //bind enter event to custom fields
        jQuery('input[name^="userfield"]').enterKey(function () {
            jQuery("#site-account-deails-create-account").trigger('click');
        });



        //submit and validate fields
        jQuery("#site-account-deails-create-account").bind('click', function () {
            jQuery('#have-account-error').empty();
            var username = escape(convertToEntities(jQuery("#username").val()));
            var password = md5(jQuery("#password").val());
            var confirm_password = md5(jQuery("#confirm-password").val());
            var security_code = escape(jQuery("#security-code").val());
            var terms_and_conditions = jQuery("#terms-and-conditions").is(':checked') ? 1 : 0;
            var token = escape(jQuery("#token").val());
            var parent_guardian_email= escape(jQuery("#parent-guardian-email").val());
            var extra_fields = '';
            var elements = [];
            
            //serialize custom profile fields name and values
            jQuery('[name^="userfield"]').each(function( index ) {
            	
            	var type = jQuery(this).attr('type');
            	
            	//check if we need to extract value from a checked input
            	if(type  == 'radio' || type == 'checkbox'){
            		name 	= jQuery(this).attr('name');
            		
            		val 	= jQuery('input[name="' + name + '"]:checked').val();
            		
            		//avoid duplicates
            		if(val && jQuery.inArray(name, elements) == -1){
            			extra_fields += "&" + name + "=" + val;
            			elements.push(name);
            		}
            		
            	}else if(type == 'input'){
            		val = '';
            		
            		if(jQuery(this).val() == "null" || jQuery(this).val() == null){
            			val = '';
            		}else{
            			val = jQuery(this).val();
            		}
            		
            		extra_fields += "&" + jQuery(this).attr('name') + "=" + escape(jQuery(this).val());
            	}else{
            		val = '';
            		
            		if(jQuery(this).val() == "null" || jQuery(this).val() == null){
            			val = '';
            		}else{
            			val = jQuery(this).val();
            		}
            		
            		extra_fields += "&" + jQuery(this).attr('name') + "=" + val;
            	}
            	
            	
            });

            jQuery.ajax({
                url: sr_path_php + "/php/index.php?op=validate_site_account_details",
                context: document.body,
                dataType: 'json',
                type: 'POST',
                cache: false,
                data: 'username=' + username + '&password=' + password + '&confirm_password=' + confirm_password + '&security_code=' + security_code + '&terms_and_conditions=' + terms_and_conditions + '&securitytoken=' + token + '&parent-guardian-email='+parent_guardian_email + extra_fields,
                beforeSend: function () {
                    initialize_spinner();
                    regenerate_token();
                },
                success: function (response) {

                    if (jQuery('#ajax-spinner').exists()) {
                        jQuery('#ajax-spinner').remove();
                    }

                    if (response.valid_entries == false) {

                        clear_errors();
                        var pattern="userfield";
                        var error = 'Required field missing or has an invalid value.';
                        var multiple = "[]";

                        jQuery.each(response.messages.fields, function (index, value) {

                            if (value == 'terms-and-conditions') {
                                jQuery('#' + value + '-wrapper').addClass("terms-and-conditions-sr-input-error-container");
                            } else {
                            	//handle custom field errors
                            	if(value.indexOf(multiple) !=-1){
                            		
                            		var str = value;
                            		var fieldname=str.replace("userfield",""); 
                            		var fieldname2 =fieldname.replace("[","");
                            		var fieldname3=fieldname2.replace("]","");
                            		var fieldname4=fieldname3.replace("[]","");
                            		
                            		bootbox.alert("Required field: "+ fieldname4 + " is missing or has invalid value");
                            		jQuery('[name="' + value + '"]').first().focus();
                            		
                            	}else if(value.indexOf(pattern) !=-1){
                                    jQuery('[name="' + value + '"]').addClass("sr-input-error");
                                    jQuery('[name="' + value + '"]')
                                        .wrap('<div class="grid_7 sr-no-margin large-sr-input-error-container" id="' + value + '-sr-error-label-container" />');
                                    
                                    jQuery('span[id="' + value + '-sr-error-label"]').empty();
                                    jQuery('[id="' + value + '-sr-error-label"]').remove();
                                    
                                    if(!jQuery('[id="' + value + '-sr-error-label"]').exists()){
                                        jQuery('[name="' + value + '"]')
                                            .after('<span id="'+value+'-sr-error-label" class="sr-error-label"></span>');
                                    }
                                    
                                    
                                    
                                    jQuery('span[id="' + value + '-sr-error-label"]')   
                                        .html(error);
                                    
                                    
                                }else{
                                    jQuery('#' + value + '-wrapper').addClass("sr-input-error-container");
                                    jQuery('#' + value).addClass("sr-input-error");
                                    jQuery('#' + value + '-sr-error-label').empty();
                                    jQuery('#' + value + '-sr-error-label').append(response.messages.errors[index]);
                                }

                            }
 
                            
                        });

                    } else {
                        try {
                            clear_errors();
                        } catch (e) {
                        	
                        }

                        //redirect user to proper url
                        var url = response.url;
                        jQuery(location).attr('href', url);
                    }
                }
            }).done(function () {
                if (jQuery('#ajax-spinner').exists()) {
                    jQuery('#ajax-spinner').remove();
                }
            });
        });

    }




    //already have an account features
    if (jQuery("#login-button").exists()) {

        //bind enter event to already have an account fields
        jQuery("#username").enterKey(function (e) {
            jQuery("#login-button").trigger('click');
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

        });

        jQuery("#password").enterKey(function (e) {
            jQuery("#login-button").trigger('click');
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        });

        //submit and validate authentication
        jQuery("#login-button").bind('click', function (e) {
            jQuery('#have-account-error').empty();
            var username = escape(convertToEntities(jQuery("#username").val()));
            var password = md5(jQuery("#password").val());
            var s = '';
            var login = 'do';
            var target_url = '';

            var securitytoken = escape(jQuery('#token').val());

            if (securitytoken == '') {
                securitytoken = 'guest';
            }
            
            if(jQuery("#sr-use-form").exists()){ 
            	target_url = 'login.php?do=login';
            }else{
            	target_url = sr_path_php + "/php/index.php?op=validate_login";
            }
            

            jQuery.ajax({
                url: target_url,
                context: document.body,
                type: 'POST',
                cache: false,
                data: 'vb_login_username=' + username + '&vb_login_password=' + password + '&s=' + s + '&login=' + login + '&securitytoken=' + securitytoken,
                beforeSend: function () {
                    jQuery('#ajax-loader-secondary').empty();
                    jQuery('#ajax-loader-secondary').append(spinner_secondary);
                    regenerate_token();
                },
                success: function (response, status, xhr) {

                    if (jQuery('#ajax-spinner-secondary').exists()) {
                        jQuery('#ajax-spinner-secondary').remove();
                    }

                    var ct = xhr.getResponseHeader("content-type") || "";

                    if (ct == "application/json") {
                        if (response.valid_login == false) {
                            //mark elements as invalid
                            jQuery('#have-account-error').html(response.message);
                            jQuery('#have-account-spacer').addClass("sr-clear_15");

                            jQuery('#username').addClass("sr-input-error").wrap('<div class="large-sr-input-error-container" />');
                            jQuery('#password').addClass("sr-input-error").wrap('<div class="large-sr-input-error-container" />');

                        } else {
                            //redirect user to proper url
                            var url = response.url;
                            jQuery(location).attr('href', url);
                        }
                    } else {
                        var error = '<b>Wrong username or password.</b> You have used up your failed login quota! <br /><br /> Please wait 15 minutes before trying again.';

                        jQuery('#have-account-error').empty();
                        jQuery('#have-account-error').html(error);
                        jQuery('#have-account-spacer').addClass("sr-clear_15");

                        jQuery('#username').addClass("sr-input-error").wrap('<div class="large-sr-input-error-container" />');
                        jQuery('#password').addClass("sr-input-error").wrap('<div class="large-sr-input-error-container" />');
                    }



                }
            }).done(function () {

            });


        });



    }



    //resens email functionality 
    if (jQuery("#sr-resend-email").exists()) {
    	regenerate_token();
        jQuery("#sr-resend-email").bind('click', function () {

            var token = escape(jQuery('#token').val());

            jQuery.ajax({
                url: sr_path_php + "/php/index.php?op=resend_email",
                context: document.body,
                dataType: 'json',
                type: 'POST',
                cache: false,
                data: 'securitytoken=' + token,
                beforeSend: function () {
                    jQuery('#sr-email-sent').empty();
                    initialize_spinner();
                    regenerate_token();
                },
                success: function (response) {
                    if (jQuery('#ajax-spinner').exists()) {
                        jQuery('#ajax-spinner').remove();
                    }
                    jQuery('#sr-email-sent').empty();
                    jQuery('#sr-email-sent').append(response.message);
                }
            });
        });

    }


    //create site account functionality
    if (jQuery("#create-new-account-button").exists()) {
        jQuery('#create-new-account-error').empty();


        //bind enter event to already have an account fields
        jQuery("#email").enterKey(function (e) {
            jQuery("#create-new-account-button").trigger('click');
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        });

        jQuery("#datepicker").enterKey(function (e) {
            jQuery("#create-new-account-button").trigger('click');
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        });


        jQuery("#create-new-account-button").bind('click', function () {
            
        	var email = escape(jQuery("#email").val());
            
            if(jQuery("#datepicker").exists()){
            	birthdate = escape(jQuery("#datepicker").val());
            }else{
            	birthdate = '';
            }
            	
            
        	var token = escape(jQuery('#token').val());

            if (token == '') {
                token = 'guest';
            }

            jQuery.ajax({
                url: sr_path_php + "/php/index.php?op=create_site_account_first_step",
                context: document.body,
                dataType: 'json',
                type: 'POST',
                cache: false,
                data: 'email=' + email + '&birthdate=' + birthdate + '&securitytoken=' + token,
                beforeSend: function () {
                    if (jQuery('#ajax-loader').exists()) {
                        initialize_spinner();
                    }
                    regenerate_token();
                },
                success: function (response) {
                    if (response.valid_entries == false) {
                        //mark elements as invalid

                        clear_errors();

                        jQuery.each(response.messages.fields, function (index, value) {
                            jQuery('#' + value + '-wrapper').addClass("sr-input-error-container");
                            jQuery('#' + value).addClass("sr-input-error");
                            jQuery('#' + value + '-sr-error-label').empty();
                            jQuery('#' + value + '-sr-error-label').append(response.messages.errors[index]);

                            if (value == 'datepicker') {
                                jQuery('span.add-on').addClass("sr-input-error");
                            }


                        });

                    } else {
                        //redirect user to proper url
                        var url = response.url;
                        jQuery(location).attr('href', url);
                    }
                }
            }).done(function () {
                if (jQuery('#ajax-spinner').exists()) {
                    jQuery('#ajax-spinner').remove();
                }
            });
        });


    }

    //Log-in
    if (jQuery("#log-in").exists()) {

        jQuery("#username").enterKey(function (e) {
            jQuery("#log-in").trigger('click');
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        });

        jQuery("#email").enterKey(function (e) {
            jQuery("#log-in").trigger('click');
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        });

        jQuery("#datepicker").enterKey(function (e) {
            jQuery("#log-in").trigger('click');
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        });
        
      //bind enter event to custom fields
        jQuery('input[name^="userfield"]').enterKey(function () {
        	 jQuery("#log-in").trigger('click');
             e.preventDefault();
             e.stopPropagation();
             e.stopImmediatePropagation();
        });

        jQuery("#log-in").bind('click', function () {
            var username = escape(convertToEntities(jQuery("#username").val()));
            var email = escape(jQuery("#email").val());
            var birthdate = '';
            
            if(jQuery("#datepicker").exists()){
            	birthdate = escape(jQuery("#datepicker").val());
            }
            
            if(typeof birthdate == undefined){
            	birthdate = '';
            }
 
            var avatar = escape(jQuery("#avatar").val());
            var terms_and_conditions = jQuery("#terms-and-conditions").is(':checked') ? 1 : 0;
            var token = escape(jQuery('#token').val());
            var extra_fields = '';
            var elements = [];
            
            //serialize custom profile fields name and values
            jQuery('[name^="userfield"]').each(function( index ) {
            	
            	var type = jQuery(this).attr('type');
            	
            	//check if we need to extract value from a checked input
            	if(type  == 'radio' || type == 'checkbox'){
            		name 	= jQuery(this).attr('name');
            		
            		val 	= jQuery('input[name="' + name + '"]:checked').val();
            		
            		//avoid duplicates
            		if(val && jQuery.inArray(name, elements) == -1){
            			extra_fields += "&" + name + "=" + val;
            			elements.push(name);
            		}
            		
            	}else if(type == 'input'){
            		extra_fields += "&" + jQuery(this).attr('name') + "=" + escape(jQuery(this).val());
            	}else{
            		extra_fields += "&" + jQuery(this).attr('name') + "=" + jQuery(this).val();
            	}
            	
            	
            });



            jQuery.ajax({
                url: sr_path_php + "/php/index.php?op=activate",
                context: document.body,
                dataType: 'json',
                type: 'POST',
                cache: false,
                data: 'from=facebook&avatar=' + avatar + '&username=' + username + '&email=' + email + '&birthdate=' + birthdate + '&securitytoken=' + token + '&terms_and_conditions=' + terms_and_conditions + extra_fields ,
                beforeSend: function () {
                    if (jQuery('#ajax-loader').exists()) {
                        jQuery('#ajax-loader').append(spinner);
                    }
                    regenerate_token();
                },
                success: function (response) {
                    if (response.valid_entries == false) {
                        //mark elements as invalid

                        clear_errors();
                        
                        var pattern="userfield";
                        var error = 'Required field missing or has an invalid value.';

                        jQuery.each(response.messages.fields, function (index, value) {
                            if (value == 'terms_and_conditions') {
                                jQuery('#' + value + '-wrapper').addClass("terms-and-conditions-sr-input-error-container");
                            } else {
                            	//handle custom field errors
                                if(value.indexOf(pattern) !=-1){
                                    jQuery('[name="' + value + '"]').addClass("sr-input-error");
                                    jQuery('[name="' + value + '"]')
                                        .wrap('<div class="grid_7 sr-no-margin large-sr-input-error-container" id="' + value + '-sr-error-label-container" />');
                                    
                                    jQuery('span[id="' + value + '-sr-error-label"]').empty();
                                    jQuery('[id="' + value + '-sr-error-label"]').remove();
                                    
                                    if(!jQuery('[id="' + value + '-sr-error-label"]').exists()){
                                        jQuery('[name="' + value + '"]')
                                            .after('<span id="'+value+'-sr-error-label" class="sr-error-label"></span>');
                                    }
                                    
                                    
                                    
                                    jQuery('span[id="' + value + '-sr-error-label"]')   
                                        .html(error);
                                    
                                    
                                }else{
                                    jQuery('#' + value + '-wrapper').addClass("large-sr-input-error-container");
                                }
                            }

                            jQuery('#' + value).addClass("sr-input-error");
                            jQuery('#' + value + '-sr-error-label').empty();
                            jQuery('#' + value + '-sr-error-label').append(response.messages.errors[index]);

                            if (value == 'datepicker') {
                                jQuery('span.add-on').addClass("sr-input-error");
                            }



                        });

                    } else {
                        //redirect user to proper url
                        var url = response.url;
                        jQuery(location).attr('href', url);
                    }
                }
            }).done(function () {
                if (jQuery('#ajax-spinner').exists()) {
                    jQuery('#ajax-spinner').remove();
                }
            });
        });
    }

    //Log-in
    if (jQuery("#link-account").exists()) {

        jQuery("#username-member").enterKey(function (e) {
            jQuery("#link-account").trigger('click');
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        });

        jQuery("#password-member").enterKey(function (e) {
            jQuery("#link-account").trigger('click');
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        });


        jQuery("#link-account").bind('click', function () {
            var username = escape(convertToEntities(jQuery("#username-member").val()));
            var password = md5(jQuery("#password-member").val());
            var token = escape(jQuery('#token').val());

            if (token == '') {
                token = 'guest';
            }

            jQuery.ajax({
                url: sr_path_php + "/php/index.php?op=linkaccount",
                context: document.body,
                type: 'POST',
                cache: false,
                data: 'securitytoken=' + token + '&username=' + username + '&password=' + password + '&security_token=' + token,
                beforeSend: function () {
                    if (jQuery('#ajax-loader-secondary').exists()) {
                        jQuery('#ajax-loader-secondary').append(spinner_secondary);
                    }
                    regenerate_token();
                },
                success: function (response, status, xhr) {

                    var ct = xhr.getResponseHeader("content-type") || "";

                    if (ct == "application/json") {
                        if (response.valid_entries == false) {
                            //mark elements as invalid

                            clear_errors();

                            jQuery.each(response.messages.fields, function (index, value) {
                                jQuery('#' + value + '-wrapper').addClass("large-sr-input-error-container");
                                jQuery('#' + value).addClass("sr-input-error");
                                jQuery('#' + value + '-sr-error-label').empty();
                                jQuery('#' + value + '-sr-error-label').append(response.messages.errors[index]);

                                if (value == 'datepicker') {
                                    jQuery('span.add-on').addClass("sr-input-error");
                                }
                            });

                        } else {
                            //redirect user to proper url
                            var url = response.url;
                            jQuery(location).attr('href', url);
                        }
                    } else {
                        var error = '<b>Wrong username or password.</b> You have used up your failed login quota! <br /><br /> Please wait 15 minutes before trying again.';

                        jQuery('#password-member-sr-error-label').empty();
                        jQuery('#password-member-sr-error-label').html(error);

                        jQuery('#username-member').addClass("sr-input-error").wrap('<div class="large-sr-input-error-container" />');
                        jQuery('#password-member').addClass("sr-input-error").wrap('<div class="large-sr-input-error-container" />');
                    }

                }
            }).done(function () {
                if (jQuery('#ajax-spinner-secondary').exists()) {
                    jQuery('#ajax-spinner-secondary').remove();
                }
            });
        });
    }


});

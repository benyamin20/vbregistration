
AJAX_Compatible = true;

/**
 * check if something exists
 */
jQuery.fn.exists = function() {
    return this.length > 0;
}


/**
 * bind function to check if enter is pressed
 */
jQuery.fn.enterKey = function (fnc) {
    return this.each(function () {
        jQuery(this).keypress(function (ev) {
            var keycode = (ev.keyCode ? ev.keyCode : ev.which);
            if (keycode == '13') {
                fnc.call(this, ev);
            }
        })
    })
}

$.fn.realVal = function(){
    var $obj = $(this);
    var val = $obj.val();
    var type = $obj.attr('type');
    if (type && type==='checkbox') {
        var un_val = $obj.attr('data-unchecked');
        if (typeof un_val==='undefined') un_val = '';
        return $obj.prop('checked') ? val : un_val;
    } else {
        return val;
    }
};

//alternative function to close a facebox thru a trigger
closeTnC = function() {
    jQuery(document).trigger('close.facebox');
}


 
 

/**
* lazy load and bootstrap all elements
*/
jQuery(document).ready(function(jQuery) {

    //initialize any facebox
    if (jQuery('a[rel*=facebox]').exists()) {
        jQuery.getScript("includes/site-registration/facebox/facebox.js", function() {
            jQuery('a[rel*=facebox]').facebox({
                loadingImage : 'includes/site-registration/facebox/loading.gif',
                closeImage : 'includes/site-registration/facebox/closelabel.png' 
            });
        });

    }

    //feature to alternatively close facebox
    if (jQuery('a[class*=close-facebox]').length > 0) {
        jQuery('a[class*=close-facebox]').bind('click', function() {
            jQuery(document).trigger('close.facebox');
        });
    }

    // initialize date picker
    if (jQuery("#datepicker").exists()) {
        jQuery.getScript("includes/site-registration/jquery-ui/js/jquery-ui-1.9.2.custom.min.js", function() {
            jQuery(function() {
                jQuery("#datepicker").datepicker({
                    autoSize : true,
                    minDate : '-90y',
                    maxDate : '-13y',
                    changeYear : true,
                    changeMonth : true,
                    constrainInput : true

                });
            });         
        }); 
        
        //check if the calendar icon exists and bind the click action
        // to show datepicker
        if (jQuery('.add-on').exists()) {
            jQuery('.add-on').bind('click', function() {
                jQuery("#datepicker").datepicker("show");
            });
        }
    }
    

    //assign default image to upload file   
    if(jQuery("#use-default").exists()){
        jQuery("#use-default").bind('click', function(){
        
            //check if remove buton exists
            if(jQuery('.fileupload-exists').exists()){
                jQuery('.fileupload-exists').trigger('click');
            }
            
            //set hidden to use default image
            jQuery("#use-default-image").val("true");
            
            //change image preview thumb
            jQuery("#selected-avatar").attr("src","images/misc/unknown.gif");
        });
    }
    
    
    if( jQuery(".fileupload-exists").exists() ){
        jQuery(".fileupload-exists").bind('click', function(){
            //set hidden to not use default image
            jQuery("#use-default-image").val("");
            jQuery("#selected-avatar").attr("src","includes/site-registration/img/thumbnail.png");
        });
    }
    
    if(jQuery("#save-account-activated").exists()){
        //bind enter event to  fields
        jQuery("#secret_question").enterKey(function () {
            jQuery("#save-account-activated").trigger('click');
        });

        jQuery("#secret_answer").enterKey(function () {
            jQuery("#save-account-activated").trigger('click');
        });
        
        // prepare Options Object 
        var options = { 
            type: 'POST',
            dataType: 'json',
            beforeSubmit: function(){
                jQuery("#progress-indicator-container").addClass("progress-striped active");
            },
            success:    function(response) { 
                if(response.valid_entries == false){
                        jQuery("#progress-indicator-container").removeClass("progress-striped active"); 
                        jQuery('.error-label').empty();
                        jQuery('.input-error-container').removeClass("input-error-container");
                        jQuery('.input-error').removeClass("input-error");
                        
                        jQuery.each(response.messages.fields, function(index, value) {        
                            jQuery('#'+value+'-wrapper').addClass("input-error-container");
                            jQuery('#'+value).addClass("input-error");
                            jQuery('#'+value+'-error-label').empty();
                            jQuery('#'+value+'-error-label').append(response.messages.errors[index]);
                        });
                    
                }else{
                    //valid entries
                    for(i = 50; i <= 100; i++ ){
                        jQuery('#progress-indicator').css("width", i + '%');
                        jQuery('#percentage-indicator').html(i);
                    }
                    
                    try{
                        jQuery('.error-label').empty();
                        jQuery('.input-error-container').removeClass("input-error-container");
                        jQuery('.input-error').removeClass("input-error");
                    }catch(e){
                    
                    }
                    
                    jQuery('#show-great').empty();
                    jQuery('#show-great').html("Great!");
                    jQuery('#complete-your-profile-form').remove();
                    jQuery('.complete-your-profile').css('min-height',420);
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
    

    
    //site account details
    if(jQuery("#site-account-deails-create-account").exists()){
        //bind enter event to already have an account fields
        jQuery("#username").enterKey(function () {
            jQuery("#site-account-deails-create-account").trigger('click');
        });

        jQuery("#password").enterKey(function () {
            jQuery("#site-account-deails-create-account").trigger('click');
        });
        
        //submit and validate fields
        jQuery("#site-account-deails-create-account").bind('click', function(){
            jQuery('#have-account-error').empty(); 
            var username = escape(jQuery("#username").val());
            var password = escape(jQuery("#password").val());
            var confirm_password = escape(jQuery("#confirm-password").val());
            var security_code = escape(jQuery("#security-code").val());
            var terms_and_conditions = jQuery("#terms-and-conditions").is(':checked') ? 1 : 0;
            
            jQuery.ajax({
              url: "includes/site-registration/php/index.php?op=validate_site_account_details",
              context: document.body, 
              dataType: 'json',
              type: 'POST',
              cache: false,
              data: 'username='+username+'&password='+password+'&confirm_password='+confirm_password +'&security_code='+security_code + '&terms_and_conditions=' + terms_and_conditions ,
              beforeSend: function(){
                if(jQuery('#ajax-loader').exists()){
                    jQuery('#ajax-loader').append('<img id="ajax-spinner" src="includes/site-registration/img/ajax-loader.gif" />');
                }
              },
              success: function( response ) {
                
                if(jQuery('#ajax-spinner').exists()){
                        jQuery('#ajax-spinner').remove();
                }
                    
                if(response.valid_entries == false){
                
                    jQuery('.error-label').empty();
                    jQuery('.input-error-container').removeClass("input-error-container");
                    jQuery('.input-error').removeClass("input-error");
                    
                    jQuery.each(response.messages.fields, function(index, value) {        
                        jQuery('#'+value+'-wrapper').addClass("input-error-container");
                        jQuery('#'+value).addClass("input-error");
                        jQuery('#'+value+'-error-label').empty();
                        jQuery('#'+value+'-error-label').append(response.messages.errors[index]);
                    });
 
                }else{
                    try{
                        jQuery('.error-label').empty();
                        jQuery('.input-error-container').removeClass("input-error-container");
                        jQuery('.input-error').removeClass("input-error");
                    }catch(e){
                    }
                    
                    //redirect user to proper url
                    var url = response.url;    
                    jQuery(location).attr('href',url);
                }
              }
            }).done(function() { 
                //nothing here
            });
        });
        
    }
    
    
    
    
    //already have an account features
    if (jQuery("#login-button").exists()) {
    
        //bind enter event to already have an account fields
        jQuery("#username").enterKey(function () {
            jQuery("#login-button").trigger('click');
        });

        jQuery("#password").enterKey(function () {
            jQuery("#login-button").trigger('click');
        });
    
        //submit and validate authentication
        jQuery("#login-button").bind('click', function(){
            jQuery('#have-account-error').empty();
            var form = jQuery('#already-have-an-account-form');
            var username = escape(jQuery("#username").val());
            var password = escape(jQuery("#password").val());
            var s = '';
            var login = 'do';
            var securitytoken = 'guest';
        
            jQuery.ajax({
              url: "includes/site-registration/php/index.php?op=validate_login",
              context: document.body, 
              dataType: 'json',
              type: 'POST',
              cache: false,
              data: 'vb_login_username='+ username +'&vb_login_password='+ password +'&s='+s+'&login='+login+'&securitytoken='+securitytoken,
              success: function( response ) {
                if(response.valid_login == false){
                    //mark elements as invalid
                    jQuery('#have-account-error').html(response.message);
                    jQuery('#have-account-spacer').addClass("clear_15");
                    
                    jQuery('#username').addClass("input-error").wrap('<div class="input-error-container" />');
                    jQuery('#password').addClass("input-error").wrap('<div class="input-error-container" />');
                    
                }else{
                    //redirect user to proper url
                    var url = response.url;    
                    jQuery(location).attr('href',url);
                } 
              }
            }).done(function() { 
                //nothing here
            });
            
            
        });
    }
    
    
    
    //resens email functionality 
    if (jQuery("#resend-email").exists()) {   
    
        var token = escape(jQuery('#token').val());
    
        jQuery("#resend-email").bind('click', function(){
            jQuery.ajax({
                url: "includes/site-registration/php/index.php?op=resend_email",
                context: document.body,
                dataType: 'json',
                type: 'POST',
                cache: false,
                data: 'securitytoken=' + token, 
                beforeSend: function(){
                    jQuery('#ajax-loader').append('<img id="ajax-spinner" src="includes/site-registration/img/ajax-loader.gif" />');
                },
                success: function (response) {
                    if(jQuery('#ajax-spinner').exists()){
                        jQuery('#ajax-spinner').remove();
                    }
                    jQuery('#email-sent').empty();
                    jQuery('#email-sent').append(response.message);
                }
            });  
        });
      
    }
    
    
    
    //create site account functionality
    if(jQuery("#create-new-account-button").exists()){
        jQuery('#create-new-account-error').empty();
        
        
       //bind enter event to already have an account fields
        jQuery("#email").enterKey(function () {
            jQuery("#create-new-account-button").trigger('click');
        });

        jQuery("#datepicker").enterKey(function () {
            jQuery("#create-new-account-button").trigger('click');
        });
    
        
         jQuery("#create-new-account-button").bind('click', function(){
            var email = escape(jQuery("#email").val());
            var birthdate = escape(jQuery("#datepicker").val());
        
             jQuery.ajax({
                  url: "includes/site-registration/php/index.php?op=create_site_account_first_step",
                  context: document.body, 
                  dataType: 'json',
                  type: 'POST',
                  cache: false,
                  data: 'email='+ email +'&birthdate='+ birthdate,
                  success: function( response ) {
                    if(response.valid_entries == false){
                        //mark elements as invalid
                        jQuery('#create-site-account-error').html(response.message);
                        jQuery('#create-site-account-spacer').addClass("clear_15");
                        
                        if(response.error_type != "datepicker"){
                            jQuery('#'+response.error_type+'').addClass("input-error").wrap('<div class="input-error-container" />');
                        }else{
                            if(email == ""){
                                jQuery('#email').addClass("input-error").wrap('<div class="input-error-container" />');
                            }
                            jQuery('#'+response.error_type+'').addClass("input-error");
                            jQuery('span.add-on').addClass("input-error");
                        } 
                        
                    }else{
                        //redirect user to proper url
                        var url = response.url;    
                        jQuery(location).attr('href',url);
                    } 
                  }
                }).done(function() { 
                    //nothing here
                });
         });
        
            
    }

    //Log-in
    if(jQuery("#log-in").exists()) {        
        jQuery("#log-in").bind('click', function() {
            var username = escape(jQuery("#username").val());
            var email = escape(jQuery("#email").val());
            var birthdate = escape(jQuery("#datepicker").val());
            var avatar = escape(jQuery("#avatar").val());
        
            jQuery.ajax({
                url: "includes/site-registration/php/index.php?op=activate",
                context: document.body, 
                dataType: 'json',
                type: 'POST',
                cache: false,
                data: 'avatar='+ avatar + '&username='+ username + '&email='+ email +'&birthdate='+ birthdate,
                success: function(response) {
                    if(response.valid_entries == false) {                                                                        
                        if(response.error_type != "datepicker") {
                            jQuery('#'+response.error_type+'').addClass("input-error").wrap('<div class="input-error-container" />');
                        } else {
                            if(email == "") {
                                jQuery('#email').addClass("input-error").wrap('<div class="input-error-container" />');
                            }

                            if(username == "") {
                                jQuery('#username').addClass("input-error").wrap('<div class="input-error-container" />');   
                            }

                            jQuery('#'+response.error_type+'').addClass("input-error");
                            jQuery('span.add-on').addClass("input-error");
                        }                         
                    } else {
                        //redirect user to proper url
                        var url = response.url;    
                        jQuery(location).attr('href', url);
                    } 
                }
            }).done(function() { 
                //nothing here
            });
        });
    }
    

});


    SITE REGISTRATION


    -  copy the contents of the upload folder into the "include" folder
       of your current vb install
    -  login to your admin account 
    -  load a new product an select the xml file
    -  get into the general settings and and look for the "site registration options"
       from here you may change all of visual the content.
       
       
       
    FACEBOOK INTEGRATION
    
    	1. You need to access to https://developers.facebook.com/apps
	2. Click on Create New App
		-Set the App Name and click continue
	3. You need to set up the App Domains (where the app will run), need to enable the Sandbox Mode 
	   if you want to test it and make click on the option "Website with Facebook Login" and then you need to specify the 
	   Site URL (the exact URL where you want do the login, ex: http://yourdomain.com/register.php?step=confirm-facebook-details), and finally click on Save Changes.
	4. Copy your App ID and your App Secret and paste it on the VBulletin Options.
	

    
    
  
    
    - there's a group setting under "site registration options" (may appear as blank --this is a bug)
      from there you may change all the FB connect details.
      
      **BUG**
      looks like for now the site_registration_settingsfb_fbapi doesn't work sometimes
      so you may need to edit the xml file directly on line 1363
      
        $client_id = "304416966345499"; //$vbulletin->options['site_registration_settingsfb_fbapi'];  
       
      then the fb connect should work, the rest of the options work OK as of now.
    
    

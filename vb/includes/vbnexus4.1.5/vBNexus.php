<?php
/**
 * Singleton Class vBNexus
 *
 * Holds the most relevant parameters of the plugin for persistence and easy
 * access and serves as a layer to communicate with the actual connection classes
 * (services) like vBNexus_fb (Facebook Social Plugin) and vBNexus_gfc (Google
 * Friends Connect).
 */

require_once(dirname(__FILE__) . '/vBNexus_fb.php');
require_once(dirname(__FILE__) . '/vBNexus_gfc.php');


/**
 * @options most relevant keys:
 *      -
 *
 * @config most relevant keys:
 *      gfc_key         Google Friend Connect API Key
 *      fb_appId        Facebook AppId (former API Key)
 *      fb_secret       Facebook Application Secret
 */
class vBNexus {

    private $options = array();     // $vbulletin options for vBNexus
    private $config = array();
    private $enabled = array();     // {fb: bool, gfc: bool}

    private $flags = array();

    private $linkedService;

    private $cookie;

    /**
     * Creates / returns the singleton instance of the class
     */
    public static function getInstance() {
        static $instance;

        if (!$instance) {
            $instance = new vBNexus;
            $instance->init();
        }

        return $instance;
    }

    public function init() {
        // Allow only one initialization
        static $initiated;
        if ($initiated) return;
        $initiated = true;

        // Load all vBNexus options
        foreach($GLOBALS['vbulletin']->options as $option => $value) {
            preg_match('/^vbnexusconfig_(.+)/', $option, $matches);
            if ($matches) $this->options[$matches[1]] = $value;
        }

        // Some redundancy for coherence (these keys are config not options)
        $this->config['gfc_key'] = $this->options['google_apikey'];
        $this->config['fb_appId'] = $this->options['facebook_appid'];
        $this->config['fb_secret'] = $this->options['facebook_applicationsecret'];
        $this->config['buttons_position'] = $this->options['buttons_position'];

        // Set the right scope for our Facebook App
        $this->config['fb_perms'] = 'email';
        if ($this->options['enable_feeds']) {
            $this->config['fb_perms'] .= ',publish_stream';
        }

        // Quick checks to set flags in order to avoid unnecessary checks later
        $this->enabled['fb'] = !empty($this->config['fb_appId']) && !empty($this->config['fb_secret']);
        $this->enabled['gfc'] = !empty($this->config['gfc_key']);

        $this->importCookie();
    }

    /**
     * Read, unserialize and store vB Nexus cookie as @cookie
     */
    private function importCookie() {
        $this->cookie = (array)unserialize($_COOKIE[COOKIE_PREFIX . 'vbnexus']);
    }

    /**
     * Add entry $key to @config array
     */
    public function setConfig($key, $val) {
        $this->config[$key] = $val;
    }

    /**
     * Get the value of vBNexus config parameter labaled $key
     */
    public function getConfig($key=NULL) {
        return is_null($key) ? $this->config : (isset($this->config[$key]) ? $this->config[$key] : NULL);
    }

    /**
     * Add entry $key to @options array
     */
    public function setOption($key, $val) {
        $this->options[$key] = $val;
    }

    /**
     * Get the value of vBNexus option labaled $key
     */
    public function getOption($key=NULL) {
        return is_null($key) ? $this->options : (isset($this->options[$key]) ? $this->options[$key] : NULL);
    }

    public function setFlag($key, $val=1) {
        $this->flags[$key] = $val;
    }

    public function getFlag($key) {
        return isset($this->flags[$key]) ? $this->flags[$key] : NULL;
    }

    /**
     * Whether a service (FB Connect | Google Friend Connect) is enabled
     */
    public function isEnabled($svc) {      
        $a = !empty($this->enabled[$svc]);

        return $a;
    }


/*******************************************************************************/
/******************** R E G I S T E R I N G   /   L O G I N ********************/
/*******************************************************************************/

    public function login($service) {
        $this->setLinkedService( $service );
        $success = $this->linkedService ? $this->linkedService->login() : NULL;

        // Null means something failed on the service's side not ours
        if (is_null($success)) {
            $errmsg = "The selected method is not responding or has rejected given credentials.
                       Please choose another method to connect or try again in a few minutes.";
            return eval(standard_error($errmsg));
        }

        return $success;
    }

    public function setLinkedService($service) {
        $this->linkedService = NULL;
        
        if ($this->isEnabled($service)) {
            // Verify the service is valid
            $class = "vBNexus_{$service}";
            
            if (class_exists($class)) {
                $this->linkedService = new $class;
                return $this->linkedService;
            }
        } else {
            die(":(");
        }
    }

    public function getLinkedService($refresh=false) {
        if ($refresh) {
            // Attempt to read current service from vbnexus cookie...
            $this->importCookie();
            // ... or leave @linkedService NULL if no valid vBNexus session is on
            $this->linkedService = NULL;
            $userid = $GLOBALS['vbulletin']->userinfo['userid'];
            if ($userid && ($this->cookie['userid'] == $userid)) {
                if ($this->cookie['service'] && $this->cookie['nexusid']) {
                    $this->setLinkedService($this->cookie['service']);
                }
            }
        }

        return $this->linkedService;
    }

    public function register($data) {
        $this->setLinkedService($data['service']);
        if (!$this->linkedService) {
            return 'vbnexus_registration_failed';
        }

        if ($data['publish']) {
            $this->publishFeed ('joined');
        }

        return $this->linkedService->register($data);
    }

    /**
     * Gets profile information about the user currently logged through vBNexus
     */
    public function getUserData() {
        if (!$this->linkedService) $data = array();
        else $data = $this->linkedService->getUserData();

        return $data;
    }

    public function codedEmail($email) {
        return md5($email.$this->getConfig('fb_secret'));
    }

    public function deleteUser($userid) {
        global $vbulletin;

        $vbulletin->db->query_write("
            DELETE FROM " . TABLE_PREFIX . "vbnexus_user
            WHERE userid = '{$userid}'
        ");
    }


/*******************************************************************************/
/********************************** F E E D S **********************************/
/*******************************************************************************/

    /**
     * Conditions that need be met in order to have feed options:
     *   - vbnexus option 'enable_feeds' is On
     *   - a user is online, using vbnexus services
     *   - active service has feed options
     */
    public function hasFeedOptions() {
        $this->importCookie();

        if ($this->getOption('enable_feeds')) {
            $service = $this->getLinkedService(true);   // $refresh := true
            return ($service && $this->cookie['can_publish'] && $service->hasFeedOptions());
        }

        return false;
    }

    public function canPublish() {
        return ($this->getOption('enable_feeds')
             && $this->linkedService
             && $this->linkedService->canPublish());
    }

    /**
     * Adds the HTML of publish options in the template named $template, by
     * calling preg_replace with the given parameters. $replacement must have
     * the string '$options' in it.
     *
     * @param string $template      Name of the template to write the options into
     * @param string $start         First parameter for preg_replace
     * @param string $replacement   Second parameter for preg_replace
     */
    public function addFeedOptions($event) {
        return $this->linkedService
            ? $this->linkedService->addFeedOptions($event)
            : false;
    }

    public function isPublishChecked() {
        $input = $GLOBALS['vbulletin']->input;
        $checked = $input->clean_gpc('p', 'vbnexus_publish', TYPE_BOOL);
        return ($this->hasFeedOptions() && $checked);
    }

    public function publishFeed($type) {
        return ($this->linkedService && $this->getOption('enable_feeds'))
            ? $this->linkedService->publishFeed($type)
            : false;
    }



/*******************************************************************************/
/************************** D Y N A M I C   H O O K S **************************/
/*******************************************************************************/

    /**
     * Adds phpcode to a given hook as if a plugin containing that code was
     * defined for that hook.
     *
     * @param string $hook
     * @param string $phpcode
     */
    private function registerHook($hook, $phpcode) {
        $list =& vBulletinHook::init()->pluginlist;

        // Make sure the key exists, even if as an empty string
        if (empty($list[$hook])) $list[$hook] = '';
        // Append new code
        $list[$hook] .= "\n{$phpcode}";
    }

    /**
     * Just a static shortcut for #register_{feedoptions,publishing}_hooks
     */
    static function register_feed_hooks() {
        self::getInstance()->register_feedoptions_hooks();
        self::getInstance()->register_publishing_hooks();
    }

    public function register_feedoptions_hooks() {
        static $hooks;

        // Don't register these same hooks more than once
        if ($hooks) return false;

        $hooks = array('newthread'  => 'newthread_form_complete',
                       'newpost'    => 'newreply_form_complete',
                       'quickreply' => 'showthread_complete',
                       'newalbum'   => 'album_album_edit',
                       'newphoto'   => 'album_picture_add');

        foreach($hooks as $hookid => $hook) {
            $vBNexus = vBNexus::getInstance();
            $phpcode = "if (vBNexus::getInstance()->hasFeedOptions())";
            $phpcode .= "vBNexus::getInstance()->addFeedOptions('{$hookid}');";
            $this->registerHook($hook, $phpcode);
        }

        return true;
    }

    /**
     *
     */
    public function register_publishing_hooks() {
        static $hooks;

        // Don't register these same hooks more than once
        if ($hooks) return false;

        $hooks = array('newthread'  => 'newthread_post_complete',
                       'newpost'    => 'newreply_post_complete',
                       'quickreply' => 'newreply_post_ajax',
                       'newalbum'   => 'albumdata_postsave',
                       'newphoto'   => 'album_picture_upload_complete');

        foreach($hooks as $hookid => $hook) {
            $phpcode = "if (vBNexus::getInstance()->isPublishChecked())";
            $phpcode .= "vBNexus::getInstance()->publishFeed('{$hookid}');";
            $this->registerHook($hook, $phpcode);
        }

        return true;
    }

}
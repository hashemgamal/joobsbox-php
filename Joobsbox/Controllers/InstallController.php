<?php
/**
 * Install Controller
 * 
 * Manages the application installation
 *
 * @author Valentin Bora <contact@valentinbora.com>
 * @version 1.0
 * @package Joobsbox_Controller
 * @copyright  Copyright (c) 2009 Joobsbox. (http://www.joobsbox.com)
 * @license	   http://www.joobsbox.com/joobsbox-php-license
 */
 
/**
 * Manages the application installation
 * @package Joobsbox_Controller
 * @category Joobsbox
 * @copyright  Copyright (c) 2009 Joobsbox. (http://www.joobsbox.com)
 * @license	   http://www.joobsbox.com/joobsbox-php-license
 */
class InstallController extends Zend_Controller_Action {
	protected $_model;
	protected $prefDBAdapter = "PDO_MySQL";
	
	public function init() {
	    Zend_Registry::get("PluginLoader")->disablePlugins();
	    
	    $session = new Zend_Session_Namespace("Joobsbox_Install");
	    if(isset($session->prefDBAdapter)) {
	      $this->prefDBAdapter = $session->prefDBAdapter;
	    }
	  
	    $params = $this->getRequest()->getParams();
	    
	    $config = new Zend_Config_Xml("config/config.xml", null, array(
			  'skipExtends'        => true,
        'allowModifications' => true)
      );
	    
	    if(isset($_GET['lang'])) {
	      $config->general->locale = $params['lang'];

        // Write the configuration file
        $writer = new Zend_Config_Writer_Xml(array(
          'config'   => $config,
          'filename' => 'config/config.xml')
        );
        $writer->write();
        $this->_redirect("install");
	    }
	    
	    if(isset($_GET['dbadapter'])) {
	      $session->prefDBAdapter = $_GET['dbadapter'];
	      $this->_redirect("install");
	    }
	    
      if(isset($config->general->restrict_install) && $config->general->restrict_install && file_exists("config/db.xml")) {
		      throw new Exception($this->view->translate("This JoobsBox is already installed. Manually remove the restrict_install line from config/config.xml if you want to reinstall it."));
	    }
	}
	
	public function indexAction(){
		$this->_redirect("install/step1");
	}
	
	public function step1Action() {
		configureTheme(APPLICATION_THEME, 'install');
		$locales = Zend_Registry::get("Zend_Locale")->getTranslationList('language', 'en');
		foreach($locales as $key => $value) {
		  if(!file_exists("Joobsbox/Languages/$key")) {
		    unset($locales[$key]);
		  }
		}
		
		// Make the form
		$this->mainForm = new Zend_Form;
		$this->mainForm->setAction($this->view->baseUrl . "/install/step1")->setMethod('post')->setLegend('Administrator credentials');
	
	  $notEmpty = new Zend_Validate_NotEmpty();
		$sitename = $this->mainForm->createElement('text', 'sitename')
			->setLabel($this->view->translate('Site name:'))
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib("class", "validate[required]")
			->addValidator($notEmpty->setMessage($this->view->translate("You must have a site name. I won't accept it otherwise.")))
			->setRequired(true);

		$language = $this->mainForm->createElement('select', 'locale')
			->setLabel($this->view->translate('Language:'))
			->setMultiOptions($locales)
			->setValue(Zend_Registry::get("conf")->general->locale)
			->setRequired(true);
			
		$this->mainForm
		  ->addElement($sitename)
		  ->addElement($language);
			
		$this->mainForm->addDisplayGroup(array('sitename', 'locale'), 'general', array(
       'legend' => $this->view->translate('General information')
    ));
    
    $adapters = array(
		  'PDO_MySQL' => "MySQL",
		  'PDO_SQLite' => "SQLite 3"
		);
		
		if(!isset($adapters[$this->prefDBAdapter])) {
		  $this->prefDBAdapter = "PDO_MySQL";
		}
		
    $dbadapter = $this->mainForm->createElement('select', 'dbadapter')
			->setLabel($this->view->translate('Database type:'))
			->setMultiOptions($adapters)
			->setValue($this->prefDBAdapter)
			->setRequired(true);
			
		$notEmpty = clone $notEmpty;
		$dbname = $this->mainForm->createElement('text', 'dbname')
			->setLabel($this->view->translate('Database name:'))
			->addFilter('StripTags')
			->setAttrib("class", "validate[required]")
			->addFilter('StringTrim')
			->addValidator($notEmpty->setMessage($this->view->translate("Database name is mandatory")))
			->setRequired(true);
			
		$this->mainForm
		  ->addElement($dbadapter)
		  ->addElement($dbname);
		
		$dbitems = array("dbadapter", "dbname");
		
		switch($this->prefDBAdapter) {
      case "PDO_MySQL":
    	  $notEmpty = new Zend_Validate_NotEmpty();
    		$dbuser = $this->mainForm->createElement('text', 'dbuser')
    			->setLabel($this->view->translate('Database user:'))
    			->addFilter('StripTags')
    			->addFilter('StringTrim')
    			->setAttrib('class', 'validate[required]')
    			->addValidator($notEmpty->setMessage($this->view->translate("Database user is mandatory")))
    			->setRequired(true);
    		$this->mainForm->addElement($dbuser);
    		$dbitems[] = "dbuser";

    		$dbpass = $this->mainForm->createElement('text', 'dbpass')
    			->setLabel($this->view->translate('Database password:'))
    			->addFilter('StripTags')
    			->addFilter('StringTrim');
    		$this->mainForm->addElement($dbpass);
    		$dbitems[] = "dbpass";

    		$notEmpty = clone $notEmpty;
    		$dbhost = $this->mainForm->createElement('text', 'dbhost')
    			->setLabel($this->view->translate('Database host:'))
    			->addFilter('StripTags')
    			->setAttrib("class", "validate[required]")
    			->addFilter('StringTrim')
    			->addValidator($notEmpty->setMessage($this->view->translate("Database host is mandatory")))
    			->setValue('localhost')
    			->setRequired(true);
    		$this->mainForm->addElement($dbhost);
    		$dbitems[] = "dbhost";

    		$dbprefix = $this->mainForm->createElement('text', 'dbprefix')
    			->setLabel($this->view->translate('Table prefix:'))
    			->addFilter('StripTags')
    			->addFilter('StringTrim');
    		$this->mainForm->addElement($dbprefix);
    		$dbitems[] = "dbprefix";
    		break;
    }
    
    $submit = $this->mainForm->createElement("submit", "submit")->setLabel("Next");
    $this->mainForm->addElement($submit);
    $dbitems[] = "submit";
    
    $this->mainForm->addDisplayGroup($dbitems, 'database', array(
       'legend' => $this->view->translate('Database information')
    ));
    
		if ($this->getRequest()->isPost()) {
        $this->validateConfiguration();
		    return;
    }
		$this->view->mainForm = $this->mainForm->render();
		
	}	
		
	private function validateConfiguration() {
		$form = $this->mainForm;

    if($form->isValid($_POST)) {
			$values = $form->getValues();
			
			// Gather site data
			$sitename = $values['sitename'];

      $dbadapter = $values['dbadapter'];
      switch($dbadapter) {
        case "PDO_MySQL":
          // Gather database info
          $dbconfig   = array(
            "zend_db" => array(
              "dbadapter" => $dbadapter,
      			  "dbname" => $values['dbname'],
      			  "username" => $values['dbuser'],
      			  "password" => $values['dbpass'],
      			  "host" => $values['dbhost'],
      			  "dbprefix" => $values['dbprefix'],
      			  "dbstart" => "SET NAMES UTF8"
      			),
      			"doctrine" => array(
      			  "connection_string" => "mysql://" . $values['dbuser'] . ':' . $values['dbpass'] . '@' . $values['dbhost'] . '/' . $values['dbname'],
      			  "models_path"       => APPLICATION_DIRECTORY . "/Joobsbox/Db/Models",
      			  "migrations_path"   => APPLICATION_DIRECTORY . "/Joobsbox/Db/Migrations",
      			  "sql_path"          => APPLICATION_DIRECTORY . "/Joobsbox/Db/Sql",
      			  "yaml_schema_path"  => APPLICATION_DIRECTORY . "/Joobsbox/Db/Schemas",
      			  "data_fixtures_path"=> APPLICATION_DIRECTORY . "/Joobsbox/Db/Fixtures",
      			  "class_name"        => "Joobsbox_Migration"
      			)
    			);
    			$dbprefix = $values['dbprefix'];
    			$db = Zend_Db::factory($dbadapter, $dbconfig['zend_db']);
    			
    			
    			break;
    		case "PDO_SQLite":
    		  // Gather database info
    		  $dbconfig   = array(
    		    "zend_db" => array(
    		      "dbadapter" => $dbadapter,
    			    "dbname" => APPLICATION_DIRECTORY . "/Joobsbox/Db/" . $values['dbname'] . ".sqlite"
    			  ),
        			"doctrine" => array(
        			  "connection_string" => "sqlite:///" . APPLICATION_DIRECTORY . "/Joobsbox/Db/" . $values['dbname'] . ".sqlite",
        			  "models_path"       => APPLICATION_DIRECTORY . "/Joobsbox/Db/Models",
        			  "migrations_path"   => APPLICATION_DIRECTORY . "/Joobsbox/Db/Migrations",
        			  "sql_path"          => APPLICATION_DIRECTORY . "/Joobsbox/Db/Sql",
        			  "yaml_schema_path"  => APPLICATION_DIRECTORY . "/Joobsbox/Db/Schemas",
        			  "data_fixtures_path"=> APPLICATION_DIRECTORY . "/Joobsbox/Db/Fixtures",
        			  "class_name"        => "Joobsbox_Migration"
        			)
    			);
    			$dbprefix = "";
			    $db = Zend_Db::factory($dbadapter, $dbconfig['zend_db']);
    			break;
      }
      
      try {
          $a = $db->getConnection();
      } catch (Zend_Db_Adapter_Exception $e) {
          if(Zend_Registry::get("conf")->general->dev) {
            $this->view->dberror = $e;
          } else {
            $this->view->dberror = $this->view->translate("There was an error connecting to the database. Make sure the connection information you provided is correct.");
          }
      } catch (Zend_Exception $e) {
          if(Zend_Registry::get("conf")->general->dev) {
            $this->view->dberror = $e;
          } else {
            $this->view->dberror = $this->view->translate("There was an error connecting to the database. Make sure the connection information you provided is correct.");
          }
      }

			if(!isset($this->view->dberror)) {
				// Connection works - we save the data
				$config = new Zend_Config_Xml("config/config.xml", null, array(
  			  'skipExtends'        => true,
          'allowModifications' => true)
        );

    		$config->general->common_title = $sitename;
				$config->db->prefix = $dbprefix;

        // Write the configuration file
        $writer = new Zend_Config_Writer_Xml(array(
          'config'   => $config,
          'filename' => 'config/config.xml')
        );
        
        try {
          $writer->write();
        } catch (Exception $e) {
          $this->view->dberror = $this->view->translate("config/config.xml is not writable. Please adjust the file permissions using FTP or SSH.");
        }
				
				  
				// Save database info
				$config = new Zend_Config($dbconfig);
				
				if(!is_writable("config/")) {
				  $this->view->dberror = $this->view->translate("config directory is not writable. Please adjust the directory permissions using FTP or SSH.");
				  return;
				}
				
				$configWriter = new Zend_Config_Writer_Xml();
				$configWriter->write('config/db.xml', $config);
				
				$this->_redirect("install/step2");
			}
		} else {
		  $values = $form->getValues();
			$form->populate($values);
			$this->mainForm = $form;
			$this->view->mainForm = $form->render();
		}
	}
	
	public function step2Action() {
	  $this->reloadConfig();
	  
		configureTheme(APPLICATION_THEME, 'install');
		$session = new Zend_Session_Namespace('Install');
		
		// Set up Doctrine DB
		require_once APPLICATION_DIRECTORY . '/Joobsbox/Db/Doctrine.php';        
    $loader = Zend_Loader_Autoloader::getInstance();
    $loader->pushAutoloader(array('Doctrine', 'autoload'));
    $doctrineConfig = new Zend_Config_Xml(APPLICATION_DIRECTORY . "/config/db.xml", "doctrine");

    $manager = Doctrine_Manager::getInstance();
    $manager->setAttribute(Doctrine::ATTR_TBLNAME_FORMAT, $this->config->db->prefix . '%s');
    $manager->setCollate('utf8_unicode_ci');
    $manager->setCharset('utf8');
    $manager->openConnection($doctrineConfig->connection_string);
    
    Doctrine::createTablesFromModels($doctrineConfig->models_path);

	  $db = Zend_Registry::get("db");
	  $db->delete($this->config->db->prefix . "categories", array("Name='Uncategorized'"));
		$db->insert($this->config->db->prefix . "categories", array(
		    'ID'    => 0,
		    'Name'  => 'Uncategorized',
		    'Link'  => 'Uncategorized',
		    'OrderIndex' => 100,
		    'Parent'=> 0
		));
		
		// Make the form
		$this->adminForm = new Zend_Form;
		$this->adminForm->setAction($this->view->baseUrl . "/install/step2")->setMethod('post')->setLegend('Administrator credentials');
	
	  $notEmpty = new Zend_Validate_NotEmpty();
		$realname = $this->adminForm->createElement('text', 'realname')
			->setLabel('Your name:')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addFilter('HtmlEntities')
			->setAttrib('class', 'validate[required]')
			->addValidator($notEmpty->setMessage($this->view->translate("Real name is mandatory")))
			->setRequired(true);
			
		$notEmpty = clone $notEmpty;
		$username = $this->adminForm->createElement('text', 'username')
			->setLabel('Username:')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addFilter('HtmlEntities')
			->setAttrib('class', 'validate[required]')
			->addValidator($notEmpty->setMessage($this->view->translate("Username is mandatory")))
			->setRequired(true);
			
		$notEmpty = clone $notEmpty;
		$password = $this->adminForm->createElement('text', 'password')
			->setLabel('Password:')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addFilter('HtmlEntities')
			->setAttrib('class', 'validate[required]')
			->addValidator($notEmpty->setMessage($this->view->translate("Password is mandatory")))
			->setRequired(true);
		
		$notEmpty = clone $notEmpty;
		$emailValidator    = new Zend_Validate_EmailAddress();
		$email = $this->adminForm->createElement('text', 'email')
			->setLabel('Email:')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addFilter('HtmlEntities')
			->setAttrib('class', 'validate[email,required]')
			->addValidator($notEmpty->setMessage($this->view->translate("Email is mandatory")))
			->addValidator($emailValidator->setMessage($this->view->translate("Email is invalid")))
			->setRequired(true);
			
		$submit = $this->adminForm->createElement('submit', 'Save')
			->setLabel('Save');
			
		$this->adminForm
		  ->addElement($realname)
		  ->addElement($username)
		  ->addElement($password)
		  ->addElement($email)
		  ->addElement($submit);
		  
		$dg = $this->adminForm->addDisplayGroup(array('realname', 'username', 'password', 'email', 'Save'), 'user');
		
		if ($this->getRequest()->isPost()) {
        $this->validateAdminUser();
		    return;
    }
		$this->view->form = $this->adminForm->render();
	}
	
	public function validateAdminUser() {
	  $form = $this->adminForm;
		$values = $form->getValues();
    
    if ($form->isValid($_POST)) {
      $db = Zend_Registry::get("db");
      $values = $form->getValues();

      $username = $values['username'];
      $password = $values['password'];
      
      $this->reloadConfig();
      
	    $db->delete($this->config->db->prefix . 'users', array("username='$username'"));
	    $db->insert($this->config->db->prefix . 'users', array(
		    'username' => $values['username'],
    		'password' => md5(Zend_Registry::get('staticSalt') . $values['password'] . sha1($password)),
    		'password_salt' => sha1($values['password']),
    		'realname' => $values['realname'],
    		'email' => $values['email']
	    ));
	    
	    $config = new Zend_Config_Xml(APPLICATION_DIRECTORY . '/config/config.xml', null, array('allowModifications' => true));
  		$config->general->restrict_install = 1;

      $writer = new Zend_Config_Writer_Xml(array('config' => $config, 'filename' => APPLICATION_DIRECTORY . '/config/config.xml'));
      $writer->write();

      $model = new Joobsbox_Model_Users;
  		$result = $model->authenticate($username, $password);
  		
  		$session = new Zend_Session_Namespace('AdminPanel');
  		$session->notices[] = $this->view->translate("Congratulations! Your JoobsBox is working now. Feel free to configure some categories.");
  		$this->_redirect("admin");
  	} else {
  		$values = $form->getValues();
  		$messages = $form->getMessages();
  		$form->populate($values);
  		$this->view->form = $form->render();
  	}
	}
	
	private function reloadConfig() {
	  $this->config = new Zend_Config_Xml(APPLICATION_DIRECTORY . "/config/config.xml");
	}
}

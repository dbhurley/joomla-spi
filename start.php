<?php

class JoomlaSingleStart
{
	// The temporarly local zip file created
	public $localfile = 'jlatest.zip';

	// The URL where the current download link can be found
	public $downloadURL = 'http://joomla.org/download';

	public function __construct()
	{
		// Controller
		if(isset($_REQUEST['task']) && $_REQUEST['task']=='go')
		{
			self::download();
		} elseif(isset($_REQUEST['task']) && $_REQUEST['task']=='finalise')
		{
			self::install();
		} else
		{
			self::start();
		}
	}

	// Model
	function download()
	{
		if(self::getZip())
		{
			// get the absolute path to $file
			$path = pathinfo(realpath($this->localfile), PATHINFO_DIRNAME);

			$zip = new ZipArchive;
			$res = $zip->open($this->localfile);

			if ($res === TRUE) {
			  // extract it to the path we determined above
			  $zip->extractTo($path);
			  $zip->close();

			  unlink($this->localfile);
			  exit( true );
			} else
			{
			  echo "No Go, Bro.";
			}
		} else
		{
			echo "Dude, no DL.";
		}
	}

	function getZip()
	{
		$download_page = file_get_contents($this->downloadURL);
		preg_match('/id="latest" href="(.*?)"/', $download_page, $matches);
		$link = $matches[1];

		set_time_limit(0);
		$fp = fopen($this->localfile, 'ab+');
		$ch = curl_init($link);

		curl_setopt($ch, CURLOPT_TIMEOUT, 50);
		curl_setopt($ch, CURLOPT_FILE, $fp); // write curl response to file
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt( $ch, CURLOPT_NOPROGRESS, false );
		curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ( $total, $downloaded ) {
			file_put_contents( dirname( __FILE__).'/progress', json_encode( array( 'progress' => round( ( $downloaded / $total ) * 100 ) ) ) );
		} );
		$result = curl_exec($ch);
		curl_close($ch);
		fclose($fp);
		
		return $result;
	}

	function install()
	{
		$config = $_POST['config'];
		$path = pathinfo(realpath(__FILE__), PATHINFO_DIRNAME);

		//Random database prefix
		$str = "";
		$chars = "abcdefghijklmnopqrstuvwxyz";
		$size = strlen( $chars );
		for( $i = 0; $i < 5; $i++ ) {
			$str .= $chars[ rand( 0, $size - 1 ) ];
		}

		$dbprefix = $str . '_';
		$random = 'ASDFASDFASDvasd3';

		$config_data = "<?php
	class JConfig {
		public \$offline = '0';
		public \$offline_message = 'This site is down for maintenance.<br /> Please check back again soon.';
		public \$display_offline_message = '1';
		public \$offline_image = '';
		public \$sitename = '" . $config['sitename'] . "';
		public \$editor = 'tinymce';
		public \$captcha = '0';
		public \$list_limit = '20';
		public \$access = '1';
		public \$debug = '1';
		public \$debug_lang = '0';
		public \$dbtype = 'mysqli';
		public \$host = '" . $config['hostname'] . "';
		public \$user = '" . $config['dbusername'] . "';
		public \$password = '" . $config['dbpassword'] . "';
		public \$db = '" . $config['dbname'] . "';
		public \$dbprefix = '" . $dbprefix . "';
		public \$live_site = '';
		public \$secret = '" . $random . "';
		public \$gzip = '0';
		public \$error_reporting = 'maximum';
		public \$helpurl = 'http://help.joomla.org/proxy/index.php?option=com_help&keyref=Help{major}{minor}:{keyref}';
		public \$ftp_host = '127.0.0.1';
		public \$ftp_port = '21';
		public \$ftp_user = '';
		public \$ftp_pass = '';
		public \$ftp_root = '';
		public \$ftp_enable = '0';
		public \$offset = 'UTC';
		public \$mailer = 'mail';
		public \$mailfrom = '';
		public \$fromname = '';
		public \$sendmail = '/usr/sbin/sendmail';
		public \$smtpauth = '0';
		public \$smtpuser = '';
		public \$smtppass = '';
		public \$smtphost = 'localhost';
		public \$smtpsecure = 'none';
		public \$smtpport = '25';
		public \$caching = '0';
		public \$cache_handler = 'file';
		public \$cachetime = '15';
		public \$MetaDesc = '';
		public \$MetaKeys = '';
		public \$MetaTitle = '1';
		public \$MetaAuthor = '1';
		public \$MetaVersion = '0';
		public \$robots = '';
		public \$sef = '1';
		public \$sef_rewrite = '0';
		public \$sef_suffix = '0';
		public \$unicodeslugs = '0';
		public \$feed_limit = '10';
		public \$log_path = '" . $path . "/logs';
		public \$tmp_path = '" . $path . "/tmp';
		public \$lifetime = '15';
		public \$session_handler = 'database';
		public \$MetaRights = '';
		public \$sitename_pagetitles = '0';
		public \$force_ssl = '0';
		public \$feed_email = 'author';
		public \$cookie_domain = '';
		public \$cookie_path = '';
	}";
		$config_file = $path . '/configuration.php';

		file_put_contents($config_file, $config_data);

		$joomla_sql = file_get_contents($path . '/installation/sql/mysql/joomla.sql');
		$sample_sql = file_get_contents($path . '/installation/sql/mysql/sample_brochure.sql');

		$joomla_sql = str_replace('#__', $dbprefix, $joomla_sql);
		$sample_sql = str_replace('#__', $dbprefix, $sample_sql);

		file_put_contents($path . '/installation/sql/mysql/joomla.sql',$joomla_sql);
		file_put_contents($path . '/installation/sql/mysql/sample_brochure.sql',$sample_sql);

			$cmd = 'mysql'
		        . ' --host=' . $config['hostname']
		        . ' --user=' . $config['dbusername']
		        . ' --password=' . $config['dbpassword']
		        . ' --database=' . $config['dbname']
			;

			echo shell_exec($cmd . ' --execute="SOURCE ' . $path .'/installation/sql/mysql/joomla.sql" 2>&1');
			
			$connection = mysql_connect($config['hostname'], $config['dbusername'], $config['dbpassword']);
			mysql_select_db($config['dbname']);
			mysql_query("INSERT INTO " . $dbprefix . "users (id, name, username, email, password, block, sendEmail, activation) VALUES (296, 'Super User', '" . $config['user'] . "', '" . $config['email']. "', '" . md5($config['pass']) . "', 0, 1, 0)");
			mysql_query("INSERT INTO " . $dbprefix . "user_usergroup_map (user_id, group_id) VALUES (296, 8);");

			if($config['data']) 
			{
				echo shell_exec($cmd . ' --execute="SOURCE ' . $path . '/installation/sql/mysql/sample_brochure.sql" 2>&1');
			}
			
			echo shell_exec('rm -rf '.$path . '/installation');
			unlink( dirname( __FILE__).'/progress' );
			unlink( dirname( __FILE__).'/start.php' );
			
			echo json_encode(array('success'=>true));
	}

	// View
	function start()
	{ ?>

		<!DOCTYPE html>
		<html lang="en">
			<head>
				<title>Joomla! Single Page Installer</title>
				<link href="//netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap.min.css" rel="stylesheet">
				<link rel="icon" href="http://cdn.joomla.org/favicon.ico">
				<style type="text/css">
					body {padding: 30px;}
					a.navbar-brand {background: url('http://cdn.joomla.org/images/four-dots.gif') no-repeat 10px 48%; padding-left: 30px;}
				</style>
				<meta name="viewport" content="width=device-width, initial-scale=1.0">

			</head>
			<body>
				 <div class="container">
				      <!-- Static navbar -->
				      <div class="navbar navbar-default">
				        <div class="navbar-header">
				          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
				            <span class="icon-bar"></span>
				            <span class="icon-bar"></span>
				            <span class="icon-bar"></span>
				          </button>
				          <a class="navbar-brand" href="#">Joomla! Single Page Installer</a>
				        </div>
				        <div class="navbar-collapse collapse">
				          <ul class="nav navbar-nav pull-right">
				            <li><a href="http://joomla.org" target="_blank">Joomla.org</a></li>
				            <li><a href="http://joomla.org/download" target="_blank">Download Page</a></li>
				            <li class="dropdown">
				              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Support <b class="caret"></b></a>
				              <ul class="dropdown-menu">
				                <li><a href="http://docs.joomla.org" target="_blank">Official Docs</a></li>
				                <li><a href="http://forum.joomla.org" target="_blank">Support Forum</a></li>
				                <li class="divider"></li>
				                <li class="dropdown-header">Resources</li>
				                <li><a href="http://extensions.joomla.org" target="_blank">Joomla Extensions</a></li>
								<li><a href="http://community.joomla.org/translations.html" target="_blank">Joomla Translations</a></li>
								<li><a href="http://resources.joomla.org" target="_blank">Joomla Resources</a></li>
								<li><a href="http://developer.joomla.org" target="_blank">Developer Resources</a></li>
				                <li><a href="http://community.joomla.org" target="_blank">Community Portal</a></li>
				              </ul>
				            </li>
				          </ul>
				        </div><!--/.nav-collapse -->
				      </div>

						<?php
						if (!is_writable(dirname($this->localfile))) {
							$dir_class = "alert alert-danger";
							$msg = "<span class='glyphicon glyphicon-warning-sign'></span> The following path is not writable. Please change permissions before continuing";
							$continue = "disabled";
						} else
						{
							file_put_contents( dirname( __FILE__).'/progress', null );
							$dir_class = "well well-small";
							$msg = "<span class='glyphicon glyphicon-ok'></span> The following directory will be used";
							$continue = "";
						}
						?>

				      <!-- Main component for a primary marketing message or call to action -->
				      <div class="">
				        <h1>Start Installation</h1>
				        <p>This installer will download the latest Joomla! release and prepare the installation directly on your server. The following directory will be used:</p>
				        <p class="<?php echo $dir_class; ?>">
				        	<?php echo $msg; ?>: 
				        	<?php echo "<strong>" . dirname(__FILE__) . "</strong>"; ?>
				       	</p>
					  </div>

					<h3>Config Details</h3>
					<div class="row">
					<form id="config_form" role="form">
						<div class="col-md-6">
							<div class="form-group">
								<label for="config[sitename]">Site Name</label>
								<input type="text" class="form-control" name="config[sitename]" value="" placeholder="Enter the site name" />
							</div>
							<div class="form-group">
								<label for="config[user]">Admin User</label>
								<input type="text" class="form-control" name="config[user]" value="" placeholder="Enter a username"/>
							</div>
							<div class="form-group">
								<label for="config[pass]">Admin Pass</label>
								<input type="text" class="form-control" name="config[pass]" value="" placeholder="Enter a password" />
							</div>
							<div class="form-group">
								<label for="config[pass]">Admin Email</label>
								<input type="text" class="form-control" name="config[email]" value="" placeholder="Enter an email address" />
							</div>
							<div class="checkbox">
						    	<label>
						      		<input type="checkbox" name="config[data]"> Install sample data
						    	</label>
						  	</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="database[host]">Database Host</label>
								<input type="text" class="form-control" name="config[hostname]" value="" placeholder="localhost"/>
							</div>
							<div class="form-group">
								<label for="database[name]">Database Name</label>
								<input type="text" class="form-control" name="config[dbname]" value="" placeholder="Enter database name" />
							</div>
							<div class="form-group">
								<label for="database[username]">Database Username</label>
								<input type="text" class="form-control" name="config[dbusername]" value="" placeholder="Enter database username" />
							</div>
							<div class="form-group">
								<label for="database[password]">Database Password</label>
								<input type="text" class="form-control" name="config[dbpassword]" value="" placeholder="Enter database password" />
							</div>
						</div>
					</form>
					</div>
					<hr />
				      <div class="hide progressMsg">
					      <h3>Progress: <span class="label label-info pull-right">Downloading Joomla!</span></h3>
				      <div class="progress progress-striped ">
				        <div class="progress-bar progress-bar-info" role="progressbar" style="width: 0%">
				        </div>
				      </div>
				      </div>
			       	<p><a id="go" class="btn btn-primary btn-lg <?php echo $continue; ?>" data-loading-text="Downloading...">Download & Start Install</a></p>

				    </div> <!-- /container -->

				     <script src="//code.jquery.com/jquery.js"></script>
				     <script src="//netdna.bootstrapcdn.com/bootstrap/3.0.0/js/bootstrap.min.js"></script>
				 <script type="text/javascript">
					 var finished = false;
					 function progress()
					 {
						 jQuery.ajax( { 'url': 'progress', 'type': 'post', 'dataType': 'json' } )
								 .done( function( msg ) {
									 jQuery( '.progress-bar' ).css( 'width',  msg.progress + '%' );
									 jQuery( '.label-info' ).html(  msg.progress + '%' );
								 } );
						 if( !( finished ) ) {
							 setTimeout( function() { progress(); }, 100 );
						 }
					 }

					 function install()
					 {
					 	//generate data string for ajax call
						var dataString = '';
						var $form = jQuery('#config_form :input');
						var id = null;
						$form.each(function(){
							if ( this.type != "button" ){
								var val = ( this.type == 'checkbox' || this.type == 'radio' ) ? ( ( jQuery(this).is(':checked') ) ? jQuery(this).val() : null ) : jQuery(this).val();
								if(val != null) {
									dataString += "&"+this.name+"="+val;
								}
							}
						});
					 	
						//make ajax call
						jQuery.ajax({
							type	:	"POST",
							url		:	'start.php?task=finalise',
							data	:	dataString,
							dataType:	'json',
							success	:	function(data)
										{
											window.location = 'index.php';
										}
						});

					 }
						jQuery( document ).ready( function ()
						{
							jQuery( '#go' ).click( function( ) {
								jQuery( '.progress' ).removeClass( 'hide' );
								jQuery( '.progressMsg' ).removeClass( 'hide' );
								//jQuery( '#go' ).addClass( 'hide' );
								setTimeout( function() { progress(); }, 1000 );
								jQuery.ajax( { 'url': 'start.php', 'data': { 'task': 'go' }, 'type': 'post', 'dataType': 'text' } )
										.done( function() {
											jQuery( '.progress-bar' ).css( 'width',  '100%' );
											jQuery( '.label-info' ).html(  '100%' );
											finished = true;
											install();
										} );
							} );
						} )
					</script>
			</body>
		</html>
	<?php }
}

new JoomlaSingleStart;

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
		} else
		{
			self::start();
			file_put_contents( dirname( __FILE__).'/progress', null );
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
		unlink( dirname( __FILE__).'/progress' );
		unlink( dirname( __FILE__).'/start.php' );
		return $result;
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
							$msg = "<p class='alert alert-danger'><span class='glyphicon glyphicon-warning-sign'></span> This path is not writable. Please change permissions before continuing.</p>";
							$continue = "disabled";
						} else
						{
							$dir_class = "well well-small";
							$msg = "";
							$continue = "";
						}
						?>

						<?php echo $msg; ?>

				      <!-- Main component for a primary marketing message or call to action -->
				      <div class="jumbotron">
				        <h1>Start Installation</h1>
				        <p>This installer will download the latest Joomla! release and prepare the installation directly on your server. The following directory will be used:</p>
				        <p class="<?php echo $dir_class; ?>"><?php echo dirname(__FILE__); ?></p>
					    <p>
					      <div class="hide progressMsg">
						      Progress: <span class="label label-info">downloading Joomla!</span>
					      </div>
					      <div class="progress progress-striped ">
					        <div class="progress-bar progress-bar-info" role="progressbar" style="width: 0%">
					        </div>
					      </div>
					    </p>
				       	<p><a id="go" class="btn btn-primary btn-lg <?php echo $continue; ?>" data-loading-text="Downloading...">Download & Start Install</a></p>
				      </div>

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
						jQuery( document ).ready( function ()
						{
							jQuery( '#go' ).click( function( ) {
								jQuery( '.progress' ).removeClass( 'hide' );
								jQuery( '.progressMsg' ).removeClass( 'hide' );
								jQuery( '#go' ).addClass( 'hide' );
								setTimeout( function() { progress(); }, 1000 );
								jQuery.ajax( { 'url': 'start.php', 'data': { 'task': 'go' }, 'type': 'post', 'dataType': 'text' } )
										.done( function() {
											jQuery( '.progress-bar' ).css( 'width',  '100%' );
											jQuery( '.label-info' ).html(  '100%' );
											finished = true;
											window.location = 'index.php';
										} );
							} );
						} )
					</script>
			</body>
		</html>
	<?php }
}

new JoomlaSingleStart;

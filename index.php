<?php
	ini_set('display_errors', 1);
	error_reporting(E_ALL);


	//On config la locale pour le time
	date_default_timezone_set('Europe/Berlin');
	setlocale(LC_ALL, "fr_FR");


	if( isset($_POST) && isset($_POST['optionsPage']) && !empty($_POST['optionsPage']) ){

		$str_endDate = $_POST['optionsDateTo'];
		$str_startDate = $_POST['optionsDateFrom'];
		$str_pageToQuery = $_POST['optionsPage'];

		//On init ce qu'on a besoin
		//On récupère les timeStamps de ces dates
		$temp = new DateTime($str_startDate);
		$str_startDateStamp = $temp->getTimestamp();
		$temp = new DateTime($str_endDate);
		$str_endDateStamp = $temp->getTimestamp();
		$str_endDateFound = false;
		$feeds = array();
		$compteur = 0;
		$lastID = "";

		//Twitter API
		require_once('TwitterAPIExchange.php');
		$settings = array(
		    'oauth_access_token' => "82060249-Xpp4ZlL81fzfMbgKL7gtmA1KUqKRKYFmlf1i8aC6X",
		    'oauth_access_token_secret' => "DACDPIzQfu4Bik7j8lp4IjVzI51rZmFyH0dfypvTkERMg",
		    'consumer_key' => "25DfjhQk8TAxMX8MaiPHeAM05",
		    'consumer_secret' => "56UDOEdvnMWG2YCOWU4WPK01FW1B2jfpaiinUKPpjSxpO01a2n"
		);
		$twitter = new TwitterAPIExchange($settings);
		$requestMethod = "GET";



		//On récupère les informations du compte
		$infos = $twitter->setGetfield("?screen_name=$str_pageToQuery")
             ->buildOauth("https://api.twitter.com/1.1/users/show.json", $requestMethod)
             ->performRequest();
        if(json_decode($infos) == null ){
	        $message = $infos;
        }
        else{
        	$infos = json_decode($infos);
        }


		function doCurlFeedsAPI($twitter, $params){
			$feed_twitter = $twitter->setGetfield($params)
             ->buildOauth("https://api.twitter.com/1.1/statuses/user_timeline.json", 'GET')
             ->performRequest();

			return json_decode($feed_twitter);;
		}


		while($str_endDateFound == false)
		{
			if($compteur == 0)
			{
				// Première query
				$feed_twitter = doCurlFeedsAPI($twitter, '?screen_name='.$str_pageToQuery.'&count=200&trim_user=1');
			}
			else
			{
				if( $lastID != "" ){
					$feed_twitter = doCurlFeedsAPI($twitter, '?screen_name=' . $str_pageToQuery . '&max_id=' . $lastID . '&count=200&trim_user=1');
				}
				else{
					$str_endDateFound = true;
					break;
				}
			}
			//On recupère les resultat

			$dataCount = count($feed_twitter);
			if($dataCount <= 0 ){
				break;
			}

			if( !isset($feed_twitter->errors)){

				//On recupère la date et le timestamp du dernier resultat
				$lastCreated = $feed_twitter[$dataCount-1]->created_at;
				$temp = new DateTime($lastCreated);
				$lastCreated = $temp->getTimestamp();

				//Si la date < date de début, on stop la bouche
				if( $lastCreated <= $str_startDateStamp)
				{
					$str_endDateFound = true;
				}
				else{
					$lastID = $feed_twitter[$dataCount-1]->id;
				}

				//On push l'array dans l'array global
				$feeds = array_merge($feeds, $feed_twitter);

				//On incrémente
				$compteur++;
			}
			else{
				var_dump($feed_twitter->errors);
				$message = $feed_twitter->errors[0]->message;
				$str_endDateFound = true;
			}
		}

		if( isset($feeds) && !isset($message) ){

			//On inverse les resultats pour les avoir dans l'ordre chrono
			$feeds = array_reverse($feeds);

			//On init les varaibles pour le csv
			$file = 'export/export_twitter_'.$str_pageToQuery.'_'.date('d-m-Y').'.csv';


			//On supprime le csv si il existe
			if( file_exists($file) ){
				unlink($file);
			}

			//On charge le fichier
			$fp	= fopen($file, "wb");


			//Forcer UTF8 for excel
			fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));


			//On ajoute le header
			$header	= array('Date','Reponse','Retweet','Favoris','Présence Lien','Nombre Hashtags','Hashtags','Utilisateurs');
			fputcsv($fp, $header,';');

			$compteurFeed = 0;
			$oldName = "";
			$compteurOldName = 0;


			for($i = 0, $j = count($feeds); $i < $j; $i++){
				$feed = $feeds[$i];
				$createdDate = $feed->created_at;
				$temp = new DateTime($createdDate);
				$createdDate = $temp->getTimestamp();

				if( $createdDate > $str_startDateStamp){
					$line = array();

					//La date
					array_push($line, strftime('%A %d %B %Y', strtotime($feed->created_at)));


					//Reponse à un status
					if( !empty($feed->type) ){
						array_push($line, 'Oui');
					}
					else{
						array_push($line, 'Non');
					}


					//Nombre de retweet
					array_push($line, $feed->retweet_count);


					//Nombre de favoris
					array_push($line, $feed->favorite_count);


					//Nombre de lien
					if( isset($feed->entities) && isset($feed->entities->urls)){
						array_push($line, count($feed->entities->urls));
					}
					else{
						array_push($line, 0);
					}


					//Nombre de hashtags
					if( isset($feed->entities) && isset($feed->entities->hashtags)){
						array_push($line, count($feed->entities->hashtags));
					}
					else{
						array_push($line, 0);
					}

					//Les hashtags
					if( isset($feed->entities) && isset($feed->entities->hashtags)){
						$hashtags = "";
						for( $z = 0, $y = count($feed->entities->hashtags); $z < $y; $z++){
							$item = $feed->entities->hashtags[$z];
							if( $z == 0){
								$hashtags .= $item->text;
							}
							else{
								$hashtags .= " | " . $item->text;
							}
						}
						array_push($line, $hashtags);
					}
					else{
						array_push($line, "");
					}


					//Mentions Utlisateurs
					//Nombre de hashtags
					if( isset($feed->entities) && isset($feed->entities->user_mentions)){
						array_push($line, count($feed->entities->user_mentions));
					}
					else{
						array_push($line, 0);
					}
					//On ecris la ligne
					fputcsv($fp, $line, ';');

					//On incrémente le compteur
					$compteurFeed++;
				}
			}

			//On save u csv
			if( fclose($fp))
			{
				//On crée le message d'affichage
				$message = '<p>Page: <a href="http://twitter.com/'.$str_pageToQuery.'" target="_blank" >'.$infos->name.'</a><br/>Followers: '.$infos->followers_count.'</p>';
				$message .= '<p> Il y a '.$compteurFeed.' posts entre le '.$str_startDate.' et le '.$str_endDate.'.</p>';
				$message .= '<a href="'.$file.'"> Télécharger l\'export csv </a>';
			}
			else{
				$message = '<p class="warning">ERREUR d\'enregistrement du fichier';
			}
		}


		//https://dev.twitter.com/docs/api/1.1/get/statuses/user_timeline
		//On fait un appel curl pour récuperer 200 tweets
		//On regarde les dates, si on a pas atteints les 200 tweets, on refait un appel avec le param max_id

		//https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name=twitterapi&count=200

	}



?>


<!doctype html>
<!-- paulirish.com/2008/conditional-stylesheets-vs-css-hacks-answer-neither/ -->
<!--[if lt IE 7]> <html class="no-js lt-ie9 lt-ie8 lt-ie7" lang="fr" xml:lang="fr" dir="ltr" > <![endif]-->
<!--[if IE 7]>    <html class="no-js lt-ie9 lt-ie8" lang="fr" xml:lang="fr" dir="ltr" > <![endif]-->
<!--[if IE 8]>    <html class="no-js lt-ie9" lang="fr" xml:lang="fr" dir="ltr" > <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js" xml:lang="fr" lang="fr" dir="ltr" > <!--<![endif]-->
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta content="width=device-width, initial-scale=1" name="viewport" />
	<meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible" />
	<title>Export des tweets d'un compte twitter - Casus Belli</title>

	<link href='http://fonts.googleapis.com/css?family=Open+Sans:300italic,400,300,600' rel='stylesheet' type='text/css'>

	<link href='css/knacss.css' rel='stylesheet' type='text/css'>
	<link href='css/global.css' rel='stylesheet' type='text/css'>

	<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css" />
	<script src="http://code.jquery.com/jquery-1.9.1.js"></script>
	<script src="http://code.jquery.com/ui/1.10.3/jquery-ui.js"></script>
	<script>
		$(function() {
			$( ".date" ).datepicker();
		});
  </script>
</head>

<body>
	<section class="content">
		<header>
			<h1>Export des tweets d'un compte twitter</h1>
		</header>

		<?php if( isset($message) && !empty($message) ): ?>
			<section class="message" style="color:red;">
				<?php echo $message; ?>
			</section>
		<?php endif; ?>

		<form method="post" action="#" id="formOptions" name="formOptions">
			<p class="element">
				<label for="optionsPage">Identifiant du compt twitter</label>
				<input type="text" required="required" name="optionsPage" id="optionsPage" value="<?php if( isset($str_pageToQuery) ){ echo $str_pageToQuery; } ?>" placeholder="_casusbelli" />
			</p>

			<p class="element">
				<label for="optionsDateFrom">Date de début de la recherche</label>
				<input type="text" class="date" required="required" value="<?php if( isset($str_startDate) ){ echo $str_startDate; } ?>" name="optionsDateFrom" id="optionsDateFrom" placeholder="01/01/2014" />
			</p>

			<p class="element">
				<label for="optionsDateTo">Date de fin de la recherche</label>
				<input type="text" class="date" required="required" value="<?php if( isset($str_endDate) ){ echo $str_endDate; } ?>" name="optionsDateTo" id="optionsDateTo" placeholder="12/31/2014" />
			</p>

			<p class="submit">
				<input type="submit" name="optionsSubmit" id="optionsSubmit" value="Récuperer" />
			</p>
		</form>

	</section>
</body>

</html>
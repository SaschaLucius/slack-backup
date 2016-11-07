<?php
/////////////////////
// slack2html
// by @levelsio
/////////////////////
//
/////////////////////
// WHAT DOES THIS DO?
/////////////////////
//
// Slack lets you export the chat logs (back to the first messages!), even if
// you are a free user (and have a 10,000 user limit)
//
// This is pretty useful for big chat groups (like mine, #nomads), where you
// do wanna see the logs, but can't see them within Slack
//
// Problem is that Slack exports it as JSON files, which is a bit unusable,
// so this script makes it into actual HTML files that look like Slack chats
//
///////////////////////
// CHANGES IN THIS FORK
///////////////////////
//
// This fork was created to allow the script to also work for Slack history exported using
// https://github.com/hisabimbola/slack-history-export - a command line module to allow you to download your Slack history.
//
// When you export all your history from Slack, it does not include history from private groups/channels and DMs. The
// slack-history-export tool provides a way to export history from these private groups/channels and DMs, but the
// original version of this script was not compatible with the exported JSON files from this tool. This fork updates
// the script to make it compatible so that you can combine both your exported public channel history from Slack, and
// your private group/channel and DM history from the slack-history-export tool and convert all of this to HTML.
//
// The slack-history-export tool exports individual JSON files, but this script expects all history to be contained in
// folders, so you need to put each JSON file inside its own folder and place these folders alongside the folders for
// the public channels exported from Slack. The name you give to the folder is the name that will be used for the
// private group/channel or DM when it appears in the index.html file, and will also be the name of the HTML file that
// is generated for the private group/channel.
//
// For example, if the file exported from the slack-history-export tool is called
// 1461648973171-my-private-group-history.json then you should place this file inside a new folder called
// my-private-group and then put this my-private-group folder alongside the folders for the public channels exported
// from Slack.
//
///////////////////
// INSTRUCTIONS
///////////////////
//
// Run this script inside the directory of an extracted (!) Slack export zip
// e.g. "/tmp/#nomads Slack export Aug 25 2015" like this:
// MacBook-Pro:#nomads Slack export Aug 25 2015 mbp$ php slack2html.php
//
// To have a reversed chronological order, use reverse argument:
// $ ./slack2html.php reverse
//
// It will then make two dirs:
// 	/slack2html/json
// 	/slack2html/html
//
// In the JSON dir it will put each channels chat log combined from all the
// daily logs that Slack outputs (e.g. /channel/2014-11-26.json)
//
// In the HTML dir it will generate HTML files with Slack's typical styling.
// It will also create an index.html that shows all channels
// 
///////////////////
// FEEDBACK
///////////////////
//
// Let me know any bugs by tweeting me @levelsio
//
// Hope this helps!
// 
// Pieter @levelsio
//
/////////////////////

function IsNullOrEmptyString($question){
	return (!isset($question) || trim($question)==='');
}

	// Allow not to reverse output
	$reverse_arg = "noreverse";

	if ($argc == 2) {
		$reverse_arg = $argv[1];
	}

	ini_set('memory_limit', '1024M');
	date_default_timezone_set('UTC');
	mb_internal_encoding("UTF-8");
	error_reporting(E_ERROR);

	// <config>
		$stylesheet="
			* {
				font-family:sans-serif;
			}
			body {
				text-align:center;
				padding:1em;
			}
			.messages {
				width:100%;
				max-width:700px;
				text-align:left;
				display:inline-block;
				word-wrap: break-word;
			}
			.messages img {
				background-color:rgb(248,244,240);
				width:36px;
				height:36px;
				border-radius:0.2em;
				display:inline-block;
				vertical-align:top;
				margin-right:0.65em;
			}
			.messages .time {
				display:inline-block;
				color:rgb(200,200,200);
				margin-left:0.5em;
			}
			.messages .username {
				display:inline-block;
				font-weight:600;
				line-height:1;
			}
			.messages .message {
				display:inline-block;
				vertical-align:top;
				line-height:1;
				width:calc(100% - 3em);
			}
			.messages .message .msg {
				line-height:1.5;
			}
		";
	// </config>

	// <compile daily logs into single channel logs>
		$files=scandir(__DIR__);
		$baseDir=__DIR__.'/../slack2html';
		$jsonDir=$baseDir.'/'.'json';
		if(!is_dir($baseDir)) mkdir($baseDir);
		if(!is_dir($jsonDir)) mkdir($jsonDir);

		foreach($files as $channel) {
			if($channel=='.' || $channel=='..') continue;
			if(is_dir($channel)) {
				$channelJsonFile=$jsonDir.'/'.$channel.'.json';
				if(file_exists($channelJsonFile)) {
					echo "JSON already exists ".$channelJsonFile."\n";
					continue;
				}

				unset($chats);
				$chats=array();

				echo '====='."\n";
				echo 'Combining JSON files for #'.$channel."\n";
				echo '====='."\n";

				$dates=scandir(__DIR__.'/'.$channel);
				foreach($dates as $date) {
					if(!is_dir($date)) {
						echo '.';
						$messages=json_decode(file_get_contents(__DIR__.'/'.$channel.'/'.$date),true);
						if(empty($messages)) continue;
						foreach($messages as $message) {
							array_push($chats,$message);
						}
					}
				}
				echo "\n";

				file_put_contents($channelJsonFile,json_encode($chats));
				echo number_format(count($chats)).' messages exported to '.$channelJsonFile."\n";
			}
		}
	// </compile daily logs into single channel logs>

	// <load users file>
		$users=json_decode(file_get_contents(__DIR__.'/'.'users.json'),true);
		$usersById=array();
		$usersByName=array();
		foreach($users as $user) {
			$usersById[$user['id']]=$user;
			$usersByName[$user['name']]=$user;
		}
	// </load users file>

	// <load channels file>
		$channels=json_decode(file_get_contents(__DIR__.'/'.'channels.json'),true);
		$channelsById=array();
		foreach($channels as $channel) {
			$channelsById[$channel['id']]=$channel;
		}
	// </load channels file>

	// <generate html from channel logs>
		$htmlDir=$baseDir.'/'.'html';
		if(!is_dir($htmlDir)) mkdir($htmlDir);
		$channels=scandir($jsonDir);
		$channelNames=array();
		$mostRecentChannelTimestamps=array();
		foreach($channels as $channel) {
			$htmlMessagesArray = array();
			if($channel=='.' || $channel=='..') continue;
			if(is_dir($channel)) continue;

			$mostRecentChannelTimestamp=0;

			if($message['ts']) {
				$message_timestamp = $message['ts'];
			}
			else {
				$message_timestamp = strtotime($message['date']);
			}
			
			if($message_timestamp>$mostRecentChannelTimestamp) {
				$mostRecentChannelTimestamp=$message_timestamp;
			}
			$array=explode('.json',$channel);
			$channelName=$array[0];
			
			$channelHtmlFile=$htmlDir.'/'.$channelName.'.html';
			if(file_exists($channelHtmlFile)) {
				echo "HTML already exists ".$channelJsonFile."\n";
				continue;
			}

			array_push($channelNames,$channelName);
			echo '====='."\n";
			echo 'Generating HTML for #'.$channelName."\n";
			echo '====='."\n";
			$messages=json_decode(file_get_contents($jsonDir.'/'.$channel),true);
			if(empty($messages)) continue;
			$htmlMessages='<html><body><style>'.$stylesheet.'</style><div class="messages">';
			foreach($messages as $message) {
				if(empty($message)) continue;
				if(empty($message['text'])) continue;
				echo '.';
				
				// change <@U38A3DE9> into levelsio
				if(stripos($message['text'],'<@')!==false) {
					$usersInMessage=explode('<@',$message['text']);
					foreach($usersInMessage as $userInMessage) {
						$array=explode('>',$userInMessage);
						$userHandleInBrackets=$array[0];
						$array=explode('|',$array[0]);
						$userInMessage=$array[0];
						$username=$array[1];
						if(empty($username)) {
							$username=$usersById[$userInMessage]['name'];
						}

						if(empty($username)) {
							$username=$usersByName[$userInMessage]['name'];
						}

						$message['text']=str_replace('<@'.$userHandleInBrackets.'>','@'.$username,$message['text']);
					}
				}

				// change <#U38A3DE9> into #_chiang-mai
				if(stripos($message['text'],'<#')!==false) {
					$channelsInMessage=explode('<#',$message['text']);
					foreach($channelsInMessage as $channelInMessage) {
						$array=explode('>',$channelInMessage);
						$channelHandleInBrackets=$array[0];
						$array=explode('|',$array[0]);
						$channelInMessage=$array[0];
						$channelNameInMessage=$array[1];
						if(empty($username)) {
							$channelNameInMessage=$channelsById[$channelInMessage]['name'];
						}
						if(!empty($username)) {
							$message['text']=str_replace('<#'.$channelHandleInBrackets.'>','#'.$channelNameInMessage,$message['text']);
						}
					}
				}
				// change <http://url> into link
				if(stripos($message['text'],'<http')!==false) {
					$linksInMessage=explode('<http',$message['text']);
					foreach($linksInMessage as $linkInMessage) {
						$array=explode('>',$linkInMessage);
						$linkTotalInBrackets=$array[0];
						$array=explode('|',$array[0]);
						$linkInMessage=$array[0];
						$message['text']=str_replace('<http'.$linkTotalInBrackets.'>','<a href="http'.$linkInMessage.'">http'.$linkInMessage.'</a>',$message['text']);
					}
				}

				// change @levelsio has joined the channel into
				// @levelsio\n has joined #channel
				if(stripos($message['text'],'has joined the channel')!==false) {
					$message['text']=str_replace('the channel','#'.$channelName,$message['text']);
					$message['text']=str_replace('@'.$usersById[$message['user']]['name'].' ','',$message['text']);
				}

				$array=explode('.',$message['ts']);
				$time=$array[0];
				
				if($message['ts']) {
					$time_formatted = date('Y-m-d H:i',$message['ts']);
				}
				else {
					$time_formatted = date('Y-m-d H:i',strtotime($message['date']));
				}

				//$message['text']=utf8_decode($message['text']);
				$message_user_identifier = $message['user'];
				
				if(IsNullOrEmptyString($message_user_identifier)) {
					$message_user_identifier = $message['comment']['user'];
				}
				
				$message_user = $usersById[$message_user_identifier];

				if(IsNullOrEmptyString($message_user)) {
					$message_user = $usersByName[$message_user_identifier];
				}
				
				$message_text = $message['text'];

				// Convert quotes and apostrophes
				$message_text = str_replace('“', '"', $message_text);
				$message_text = str_replace('\u201c', '"', $message_text);
				$message_text = str_replace('”', '"', $message_text);
				$message_text = str_replace('\u201d', '"', $message_text);
				$message_text = str_replace('’', "'", $message_text);
				$message_text = str_replace('\u2019', "'", $message_text);

				// Convert new lines
				$message_text = nl2br($message_text);

				// Convert remaining unicode characters
				$message_text_working = json_encode($message_text);
				$message_text_working = preg_replace('/\\\u([0-9a-z]{4})/', '&#x$1;', $message_text_working);
				$message_text = json_decode($message_text_working);
				
				$htmlMessage='';
				$htmlMessage.='<div><img src="'.$message_user['profile']['image_72'].'" /><div class="message"><div class="username">'.$message['user'].'</div><div class="time">'.$time_formatted.'</div><div class="msg">'.$message_text."</div></div></div><br/>\n";
				array_push($htmlMessagesArray,$htmlMessage);
			}

			if ($reverse_arg == "noreverse") {
				$htmlMessagesArray = array_reverse($htmlMessagesArray);
			}

			$htmlMessagesImplodedArray=implode($htmlMessagesArray);
			$htmlMessages.=$htmlMessagesImplodedArray;

			$htmlMessages.='</div></body></html>';
			file_put_contents($channelHtmlFile,$htmlMessages);
			$mostRecentChannelTimestamps[$channelName]=$mostRecentChannelTimestamp;
			echo "\n";
		}
		asort($mostRecentChannelTimestamps);
		$mostRecentChannelTimestamps=array_reverse($mostRecentChannelTimestamps);
	// </generate html from channel logs>

	// <make index html>
		$html='<html><body><style>'.$stylesheet.'</style><div class="messages">';
		foreach($mostRecentChannelTimestamps as $channel => $timestamp) {
			$html.='<a href="./'.$channel.'.html">#'.$channel.'</a> '.date('Y-m-d H:i',$timestamp).'<br/>'."\n";
		}
		$html.='</div></body></html>';
		file_put_contents($htmlDir.'/index.html',$html);
	// </make index html>

?>
<?php
    # Super Mod and Admin to edit comments.  Check with canmodcp and issupermod
    # Write script to convert EZTrader to this system
    # Option to moderate, default no moderation required

    $plugins->add_hook("postbit", "trader_postbit");
    $plugins->add_hook("member_profile_end", "trader_member_profile");
    $plugins->add_hook("usercp_start", "trader_usercp");
    $plugins->add_hook("fetch_wol_activity_end", "trader_wol");
    $plugins->add_hook("build_friendly_wol_location_end", "trader_build_friendly_location");
    $plugins->add_hook("global_start", "trader_alertregister");

    function trader_info()
    {
        return array(
		"name"		=> "Trader",
		"description"		=> "A trade reputation system with high performance.",
		"website"		=> "",
		"author"		=> "Mark Janssen",
		"authorsite"		=> "",
		"version"		=> "1.0",
		"guid" 			=> "",
		"compatibility"	=> "16*, 18*"
		);
    }

    function trader_install()
    {
        global $db, $cache;
        $db->query("CREATE TABLE " . TABLE_PREFIX . "trade_feedback (
        `fid` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `giver` INT UNSIGNED DEFAULT 1,
        `receiver` INT UNSIGNED NOT NULL DEFAULT 1,
        `dateline` BIGINT NOT NULL,
        `approved` TINYINT(1),
        `comments` TEXT,
        `type` VARCHAR(15) DEFAULT 'receiver',
        `value` TINYINT(1) DEFAULT 0,
        `reported` TINYINT(1) DEFAULT 0,
        `threadlink` TEXT,
        KEY giver(giver),
        KEY receiver(receiver)
        ) ENGINE=Innodb " . $db->build_create_table_collation());

        // Now alter the users table
        $db->query("ALTER TABLE " . TABLE_PREFIX . "users 
        ADD posreps INT UNSIGNED DEFAULT 0,
        ADD neutreps INT UNSIGNED DEFAULT 0,
        ADD negreps INT UNSIGNED DEFAULT 0");

        // MyAlerts Integration
        // Check if MyAlerts exists and is compatible
        if(function_exists("myalerts_info")){
            // Load myalerts info into an array
            $my_alerts_info = myalerts_info();
            // Set version info to a new var
            $verify = $my_alerts_info['version'];
            // If MyAlerts 2.0 or better then do this !!!
            if($verify >= "2.0.0"){
                // Load cache data and compare if version is the same or not
                $myalerts_plugins = $cache->read('mybbstuff_myalerts_alert_types');
                if($myalerts_plugins['tradefeedback']['code'] != 'tradefeedback'){
                    $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
                    $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
                    $alertType->setCode("tradefeedback");
                    $alertType->setEnabled(true);
                    $alertTypeManager->add($alertType);
                }
            }
        }
        
        
    }

    function trader_is_installed()
    {
        global $db;
        if($db->table_exists("trade_feedback"))
        {
            return TRUE;
        }
        return FALSE;
    }

    function trader_activate()
    {
        global $db;
        // make the templates
        $new_template['member_profile_trade_feedback_link'] = '<tr>
            <td class="trow2" colspan="2"><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=give&uid={$mybb->input[\'uid\']}">Leave feedback for {$memprofile[\'username\']}</a></td>
            </tr>';

        $new_template['member_profile_trade_stats'] = '<table border="0" class="tborder" width="100%">
<tr>
<th class="thead">Trader Feedback</th>
</tr>
<tr>
<td class="trow2">
<div class="left_feedback">Trade Count:</div>
<div class="right_feedback">(<a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$mybb->input[\'uid\']}">{$memprofile[\'repcount\']}</a>)</div>
<br class="clear" />
<div class="left_feedback">Positive Feedback:</div>
<div class="right_feedback">(<a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$memprofile[\'uid\']}&value=1">{$memprofile[\'posreps\']}</a>)</div>
<br class="clear" />
<div class="left_feedback">Neutral Feedback:</div>
<div class="right_feedback">(<a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$memprofile[\'uid\']}&value=0">{$memprofile[\'neutreps\']}</a>)</div>
<br class="clear" />
<div class="left_feedback">Negative Feedback:</div>
<div class="right_feedback">(<a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$memprofile[\'uid\']}&value=-1">{$memprofile[\'negreps\']}</a>)</div>
{$feedbacklink}
</td>
</tr>
</table>';

    $new_template['tradefeedback_confirm_delete'] = '<html>
<head>
<title>Delete Feedback</title>
{$headerinclude}
</head>
<body>
{$header}
<form action="{$mybb->settings[\'bburl\']}/tradefeedback.php" method="post">
{$mypostkey}
<input type="hidden" name="action" value="delete" />
<input type="hidden" name="fid" value="{$mybb->input[\'fid\']}" />
Are You Sure? <select name="confirm">
<option value="0" selected="selected">No</option>
<option value="1">Yes</option>
</select>
<br />
<input type="submit" value="Go" />
</form>
{$footer}
</body>
</html>';

$new_template['tradefeedback_give_form'] = '<html>
<head>
<title>Give Feedback</title>
{$headerinclude}
</head>
<body>
{$header}
<form action="{$mybb->settings[\'bburl\']}/tradefeedback.php" method="post">
{$mypostkey}
<input type="hidden" name="fid" value="{$mybb->input[\'fid\']}" />
<input type="hidden" name="uid" value="{$mybb->input[\'uid\']}" />
<input type="hidden" name="action" value="{$action}" />
Your role: <select name="type">
<option value="trader" {$traderselect}>Trader</option>
<option value="buyer"  {$buyerselect}>Buyer</option>
<option value="seller" {$sellerselect}>Seller</option>
</select>
<br />
Rating: <select name="value">
{$repselect}
<option value="1">Positive</option>
<option value="0">Neutral</option>
<option value="-1">Negative</option>
</select>
<br />
Link to thread: <input type="text" name="threadlink" value="{$threadlink_value}" />
<br />
Comments: <textarea name="comments" rows="5" cols="70">{$feedback[\'comments\']}</textarea>
<br />
<input type="submit" value="Add Feedback" />
</form>
{$footer}
</body>
</html>';

    $new_template['tradefeedback_mod'] = '<br />
<a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=edit&fid={$feedback[\'fid\']}&amp;my_post_key={$mybb->post_code}&amp;uid={$mybb->input[\'uid\']}">Edit</a>
<br />
<a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action={$approvedlinkpart}&fid={$feedback[\'fid\']}&amp;my_post_key={$mybb->post_code}">{$approvedtext}</a>
<br />
<a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=delete&fid={$feedback[\'fid\']}&amp;my_post_key={$mybb->post_code}">Delete</a>';

$new_template['tradefeedback_report'] = '<a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=report&amp;fid={$feedback[\'fid\']}&amp;my_post_key={$mybb->post_code}">Report</a>';

$new_template['tradefeedback_report_form'] = '<html>
<head>
<title>Report Feedback</title>
{$headerinclude}
</head>
<body>
{$header}
<form action="{$mybb->settings[\'bburl\']}/tradefeedback.php" method="post">
{$mypostkey}
<input type="hidden" name="action" value="report" />
<input type="hidden" name="fid" value="{$mybb->input[\'fid\']}" />
Your Reason For Reporting: <textarea name="reason" rows="5" cols="70"></textarea><br />
<input type="submit" value="Report" />
</form>
<br />
{$footer}
</body>
</html>';

$new_template['tradefeedback_view_page'] = '<html>
<head>
<title>Viewing Feedback</title>
{$headerinclude}
</head>
<body>
{$header}
<div id="feedback_stats_container">
<div  class="left_feedback"><b>Feedback Stats for {$receiverusername}</b></div>
<div class="right_feedback"><b>Contact</b></div>
<br class="clear" />
<div class="left_feedback">Positive: <a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?uid={$mybb->input[\'uid\']}&amp;value=1">{$posreps}</div>
<div class="right_feedback"><a href="{$mybb->settings[\'bburl\']}/member.php?action=profile&amp;uid={$mybb->input[\'uid\']}">View Profile</a></div>
<br class="clear" />
<div class="left_feedback">Neutral: <a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?uid={$mybb->input[\'uid\']}&amp;value=0">{$neutreps}</a></div>
<div class="right_feedback"><a href="{$mybb->settings[\'bburl\']}/private.php?action=send&amp;uid={$mybb->input[\'uid\']}">Send PM</a></div>
<br class="clear" />
<div class="left_feedback">Negative: <a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?uid={$mybb->input[\'uid\']}&amp;value=-1">{$negreps}</a></div>
<br class="clear" />
<div class="left_feedback">Total: <a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?uid={$mybb->input[\'uid\']}">{$totalreps}</a></div>
<br class="clear" />
</div>
<br />
<br />
<div id="givebar">
<a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=give&amp;uid={$mybb->input[\'uid\']}">Submit feedback for {$receiverusername}</a>
</div>
<hr />
<div id="valuebar">
<a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$mybb->input[\'uid\']}">View All Feedback</a>
| <a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$mybb->input[\'uid\']}&amp;type=seller">View Seller Feedback</a>
| <a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$mybb->input[\'uid\']}&amp;type=buyer">View Buyer Feedback</a>
| <a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$mybb->input[\'uid\']}&amp;type=trader">View Trade Feedback</a>
</div>
<hr />
<br />
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr class="trow">
<th class="thead" colspan="{$colspan}" border="0">Feedback for {$receiverusername}</th>
</tr>
<tr>
<th class="thead">Rating</th>
<th class="thead" width="50%">Comments</th>
<th class="thead">From</th>
{$detailcolumn}
<th class="thead">Date</th>
<th class="thead">Options</th>
</tr>
{$tradefeedback}
{$noresults}
</table>
<br />
{$footer}';

$new_template['tradefeedback_view_rep'] = '<tr class="trow">
<td class="{$tdclass}"><img src="{$feedback[\'smilyurl\']}" alt="" /></td>
<td class="{$tdclass}"><div style="margin-right:10%">{$feedback[\'comments\']}<br />{$threadlink}</div></td>
<td class="{$tdclass}">{$feedback[\'type\']} {$feedback[\'profilelink\']}</td>
{$detaillink}
<td class="{$tdclass}">{$feedback[\'dateline\']}</td>
<td class="{$tdclass}">{$report}{$modbit}<br /></td>
</tr>';

$new_template['tradefeedback_postbit_link'] = '<a href="tradefeedback.php?action=give&amp;uid={$post[\'uid\']}&amp;tid={$post[\'tid\']}" title="Give Feedback" class="postbit_tradefeedback"><span>Give Feedback</span></a>';

        foreach($new_template as $title => $template)
	    {
	    	$new_template = array('title' => $db->escape_string($title), 'template' => $db->escape_string($template), 'sid' => '-1', 'version' => '1600', 'dateline' => TIME_NOW);
	    	$db->insert_query('templates', $new_template);
	    }

        // Add Trade Feedback link to showthread template
        include MYBB_ROOT."/inc/adminfunctions_templates.php";
        find_replace_templatesets("postbit", "#".preg_quote('{$post[\'button_rep\']}')."#i", '{$post[\'button_rep\']}{$post[\'button_tradefeedback\']}');
        find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'button_rep\']}')."#i", '{$post[\'button_rep\']}{$post[\'button_tradefeedback\']}');
    }

    function trader_deactivate()
    {
        global $db;
        $deletedtemplates = "'member_profile_trade_feedback_link','member_profile_trade_stats','tradefeedback_confirm_delete','tradefeedback_give_form','tradefeedback_mod','tradefeedback_report','tradefeedback_report_form','tradefeedback_view_page','tradefeedback_view_rep'";
        $db->delete_query("templates", "title IN(".$deletedtemplates.")");

        // Remove Trade Feedback Link from showthread template
        include MYBB_ROOT."/inc/adminfunctions_templates.php";
        find_replace_templatesets("postbit", "#".preg_quote('{$post[\'button_tradefeedback\']}')."#i", '', 0);
        find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'button_tradefeedback\']}')."#i", '', 0);
    }

    function trader_uninstall()
    {
        global $db, $cache;
        $db->drop_table("trade_feedback");
        $db->drop_column("users", "posreps");
        $db->drop_column("users", "neutreps");
        $db->drop_column("users", "negreps");
        

        // MyAlerts Integration
        // Check if MyAlerts exists and remove tradefeedback alert
        if(function_exists("myalerts_info")){
            // Load myalerts info into an array
            $my_alerts_info = myalerts_info();
            // Set version info to a new var
            $verify = $my_alerts_info['version'];
            // If MyAlerts 2.0 or better then do this !!!
            if($verify >= "2.0.0"){
                $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
                $alertTypeManager->deleteByCode("tradefeedback");
            }
        }
    }

    function trader_postbit(&$post)
    {
        global $mybb, $templates;

        $post['totalrep'] = $post['posreps'] - $post['negreps'];
        $post['repcount'] = $post['posreps'] + $post['neutreps'] + $post['negreps'];

        // Feedback Button
        $post['button_tradefeedback'] = '';
        if($mybb->user['uid'] && !$mybb->user['isbannedgroup'] && $mybb->user['uid'] != $post['uid'])
        {
            eval("\$post['button_tradefeedback'] = \"".$templates->get("tradefeedback_postbit_link")."\";");
        }
    }

    function trader_member_profile()
    {
        global $mybb, $templates, $traderinfo, $db, $memprofile;
        $memprofile['totalrep'] = $memprofile['posreps'] - $memprofile['negreps'];
        $memprofile['repcount'] = $memprofile['posreps'] + $memprofile['neutreps'] + $memprofile['negreps'];
		$feedbacklink = '';
        if($mybb->user['uid'] && !$mybb->user['isbannedgroup'])
        {
            eval("\$feedbacklink = \"".$templates->get("member_profile_trade_feedback_link")."\";");
        }
        eval("\$traderinfo = \"".$templates->get("member_profile_trade_stats")."\";");
    }

    function trader_usercp()
    {
        global $mybb, $color;
        $mybb->user['repcount'] = $mybb->user['posreps'] + $mybb->user['neutreps'] + $mybb->user['negreps'];
        if($mybb->user['posreps'] > $mybb->user['negreps'])
        {
            $color = "reputation_positive";
        }
        if($mybb->user['posreps'] == $mybb->user['negreps'])
        {
            $color = "reputation_neutral";
        }
        if($mybb->user['posreps'] < $mybb->user['negreps'])
        {
            $color = "reputation_negative";
        }
    }

    function trader_rebuild_reputation($userid)
    {
        global $db;
        $db->write_query("UPDATE " . TABLE_PREFIX . "users SET posreps=(SELECT COUNT(fid) FROM " . TABLE_PREFIX . "trade_feedback WHERE receiver=$userid AND value=1 AND approved=1) WHERE uid=$userid");
        $db->write_query("UPDATE " . TABLE_PREFIX . "users SET neutreps=(SELECT COUNT(fid) FROM " . TABLE_PREFIX . "trade_feedback WHERE receiver=$userid AND value=0 AND approved=1) WHERE uid=$userid");
        $db->write_query("UPDATE " . TABLE_PREFIX . "users SET negreps=(SELECT COUNT(fid) FROM " . TABLE_PREFIX . "trade_feedback WHERE receiver=$userid AND value=-1 AND approved=1) WHERE uid=$userid");
    }

    function trader_wol($user_activity)
    {
        global $mybb, $userid, $location;
        if(strpos($user_activity['location'], "tradefeedback.php"))
        {
            $user_activity['activity'] == "tradefeedback";
        }
        return $user_activity;
    }

    function trader_build_friendly_location($array)
    {
        global $mybb, $user_activity;
        if($array['user_activity']['activity'] == "tradefeedback")
        {
            $array['location_name'] = "Viewing Trade Feedback";
        }
        return $array;
    }

    function trader_send_pm($toid, $fid)
    {
        global $db, $mybb;
        require_once MYBB_ROOT."inc/datahandlers/pm.php";
        $pmhandler = new PMDataHandler();
		$pmhandler->admin_override = true;
        	$pm = array(
			"subject" => "You have received new feedback.",
			"message" => "You have received new feedback from " . $mybb->user['username'] . ".  You may view it [url=" . $mybb->settings['bburl'] . "/tradefeedback.php?action=view&uid=$toid&fid=$fid]here[/url].",
			"icon" => "-1",
			"toid" => $toid,
			"fromid" => $mybb->user['uid'],
			"do" => '',
			"pmid" => ''
		);
		$pm['options'] = array(
			"signature" => "0",
			"disablesmilies" => "0",
			"savecopy" => "0",
			"readreceipt" => "0"
		);
	
		$pmhandler->set_data($pm);
				
		if(!$pmhandler->validate_pm())
		{
			// There some problem sending the PM
		}
		else
		{
			$pminfo = $pmhandler->insert_pm();
		}
    }

    // MyAlerts Formatter Register
    function trader_alertregister() {
        global $mybb, $lang;
        
        if($mybb->user['uid']) {
            $lang->load('tradefeedback');

            $code = 'tradefeedback';
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
            $formatterManager->registerFormatter(new TradeFeedbackFormatter($mybb, $lang, $code));
        }
    }  

    // MyAlerts Function
    function trader_myalerts($toid, $fid)
    {
        
        $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('tradefeedback');
        $alert = new MybbStuff_MyAlerts_Entity_Alert($toid, $alertType, 0);
                $alert->setExtraDetails(
                array(
                    'toid'       => $toid,
                    'fid'       => $fid                
                )); 
        MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
    }

    // MyAlerts Trade Feedback Formatter
    if(class_exists("MybbStuff_MyAlerts_Formatter_AbstractFormatter")){

        class TradeFeedbackFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
        {
            public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
            {
                return $this->lang->sprintf(
                    $this->lang->myalerts_tradefeedback,
                    $outputAlert['from_user'],
                    $outputAlert['dateline']
                    );
            }

            public function init()
            {
                if (!$this->lang->tradefeedback) {
                    $this->lang->load('tradefeedback');
                }
            }

            public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
            {
                $alertContent = $alert->getExtraDetails();
                $feedbackLink = $this->mybb->settings['bburl'] . '/tradefeedback.php?action=view&uid='.$mybb->user['uid'].'&fid='.$alertContent['fid'];

                return $feedbackLink;

            }
        }
    }

?>

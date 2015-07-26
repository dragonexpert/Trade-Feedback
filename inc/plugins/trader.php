<?php
    # Super Mod and Admin to edit comments.  Check with canmodcp and issupermod
    # Write script to convert EZTrader to this system
    # Option to moderate, default no moderation required

    $plugins->add_hook("postbit", "trader_postbit");
    $plugins->add_hook("member_profile_end", "trader_member_profile");
    $plugins->add_hook("usercp_start", "trader_usercp");
    $plugins->add_hook("fetch_wol_activity_end", "trader_wol");
    $plugins->add_hook("build_friendly_wol_location_end", "trader_build_friendly_location");
    $plugins->add_hook("global_start", "trader_alertregister", 0);
    $plugins->add_hook("modcp_reports_report", "trader_modcp_reports_report");
    $plugins->add_hook("modcp_allreports_report", 'trader_modcp_allreports_report');
    $plugins->add_hook("admin_config_plugins_begin", "trader_update");

    function trader_info()
    {
        return array(
		"name"		=> "Trader",
		"description"		=> "A trade reputation system with high performance.  <a href='index.php?module=config-plugins&action=update_trader_plugin'>Click here</a> to update your database.",
		"website"		=> "",
		"author"		=> "Mark Janssen",
		"authorsite"		=> "",
		"version"		=> "3.0",
		"codename" 			=> "trade_feedback",
		"compatibility"	=> "18*"
		);
    }

    function trader_install()
    {
        global $db, $cache;
        $db->write_query("CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "trade_feedback (
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
        `tid` INT NOT NULL DEFAULT 0,
        `forum_id` INT NOT NULL DEFAULT 0,
        KEY giver(giver),
        KEY receiver(receiver)
        ) ENGINE=Innodb " . $db->build_create_table_collation());

        // Now alter the users table
        if(!$db->field_exists("posreps", "users"))
        {
            $db->write_query("ALTER TABLE " . TABLE_PREFIX . "users 
            ADD posreps INT UNSIGNED DEFAULT 0,
            ADD neutreps INT UNSIGNED DEFAULT 0,
            ADD negreps INT UNSIGNED DEFAULT 0");
        }

        // Usergroup Permissions
        if(!$db->field_exists("cantradefeedback", "usergroups"))
        {
            $db->write_query("ALTER TABLE " . TABLE_PREFIX . "usergroups
            ADD cantradefeedback INT UNSIGNED DEFAULT 1");
        }

        // Banned usergroups can't leave feedback
        $db->write_query("UPDATE " . TABLE_PREFIX . "usergroups SET cantradefeedback=0 WHERE isbannedgroup=1");
        $cache->update_usergroups();

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
        $new_template['member_profile_trade_feedback_link'] ='<a class="postbit_edit" href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=give&uid={$mybb->input[\'uid\']}">{$lang->leave_feedback}</span></a>';

        $new_template['member_profile_trade_stats'] = '<div class="faux-table">
<div class="thead cf">
<a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$mybb->input[\'uid\']}"><strong>{$lang->trader_feedback}</strong></a>
</div>

<div class="feedback">
<dl>
  <dt><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$mybb->input[\'uid\']}">{$lang->trade_count}</a></dt>
  <dd><span class="badge"><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$mybb->input[\'uid\']}">{$memprofile[\'repcount\']}</a></span></dd>
</dl>
<dl class="positive">
  <dt><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$memprofile[\'uid\']}&value=1">{$lang->positive_feedback}</a></dt>
  <dd><span class="badge badge-pos"><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$memprofile[\'uid\']}&amp;value=1">{$memprofile[\'posreps\']}</a></span></dd>
</dl>
<dl class="neutral">
  <dt><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$memprofile[\'uid\']}&amp;value=0">{$lang->neutral_feedback}</a></dt>
  <dd><span class="badge badge-neut"><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$memprofile[\'uid\']}&amp;value=0">{$memprofile[\'neutreps\']}</a></span></dd>
</dl>
<dl class="negative">
  <dt><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$memprofile[\'uid\']}&amp;value=-1">{$lang->negative_feedback}</a></dt>
  <dd><span class="badge badge-neg"><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$memprofile[\'uid\']}&amp;value=-1">{$memprofile[\'negreps\']}</a></span></dd>
</dl>
<div class="postbit_buttons tfoot">
{$feedbacklink}
<a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$mybb->input[\'uid\']}">{$lang->view_feedback}</a>
</div>
</div>
</div>
<br/>';

    $new_template['tradefeedback_confirm_delete'] = '<html>
<head>
<title>{$lang->delete_feedback}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="faux-table">
<div class="thead">{$lang->delete_feedback}</div>
<form action="{$mybb->settings[\'bburl\']}/tradefeedback.php" method="post">
{$mypostkey}
<input type="hidden" name="action" value="delete" />
<input type="hidden" name="fid" value="{$mybb->input[\'fid\']}" />
<div class="cf">
<p>
{$lang->delete_feedback_message}
</p>
<select name="confirm" class="float_left">
<option value="0" selected="selected">{$lang->no}</option>
<option value="1">{$lang->yes}</option>
</select>
<input type="submit" class="button float_left" value="{$lang->go}" />
</form>
</div>
</div>
<br/>
{$footer}
</body>
</html>';

$new_template['tradefeedback_give_form'] = '<html>
<head>
<title>{$lang->give_feedback}</title>
{$headerinclude}
</head>
<body>
{$header}
<form action="{$mybb->settings[\'bburl\']}/tradefeedback.php" method="post">
{$mypostkey}
<div class="faux-table">
<div class="thead">{$lang->give_feedback}</div>
<input type="hidden" name="fid" value="{$mybb->input[\'fid\']}" />
<input type="hidden" name="uid" value="{$mybb->input[\'uid\']}" />
<input type="hidden" name="action" value="{$action}" />
<div class="formLayout">
<div class="cf">
<label>{$lang->give_feedback_role}</label>
<select name="type">
<option value="trader" {$traderselect}>{$lang->give_feedback_role_trader}</option>
<option value="buyer"  {$buyerselect}>{$lang->give_feedback_role_buyer}</option>
<option value="seller" {$sellerselect}>{$lang->give_feedback_role_seller}</option>
</select>
</div>
<div class="cf">
<label>{$lang->give_feedback_rating}</label>
<select name="value">
{$repselect}
<option value="1">{$lang->give_feedback_positive}</option>
<option value="0">{$lang->give_feedback_neutral}</option>
<option value="-1">{$lang->give_feedback_negative}</option>
</select>
</div>
<div class="cf">
<label>{$lang->give_feedback_threadlink}</label>
 <input type="text" class="textbox" name="threadlink" value="{$threadlink_value}" />
</div>
<div class="cf">
<label>{$lang->give_feedback_comments}</label>
<textarea name="comments" rows="5" cols="70">{$feedback[\'comments\']}</textarea>
</div>
</div>
<div class="postbit_buttons tfoot cf">
<input type="submit" class="button" value="{$lang->give_feedback}" />
</div>
</div>
</form>
{$footer}
</body>
</html>';

$new_template['tradefeedback_mod'] = '<li><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=edit&amp;fid={$feedback[\'fid\']}&amp;my_post_key={$mybb->post_code}&amp;uid={$mybb->input[\'uid\']}">{$lang->feedback_options_edit}</a></li>
<li><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action={$approvedlinkpart}&amp;fid={$feedback[\'fid\']}&amp;my_post_key={$mybb->post_code}">{$approvedtext}</a></li>
<li><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=delete&amp;fid={$feedback[\'fid\']}&amp;my_post_key={$mybb->post_code}">{$lang->feedback_options_delete}</a></li>';

$new_template['tradefeedback_report'] = '<li><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=report&amp;fid={$feedback[\'fid\']}&amp;my_post_key={$mybb->post_code}">{$lang->feedback_options_report}</a></li>';

$new_template['tradefeedback_report_form'] = '<html>
<head>
<title>{$lang->report_feedback}</title>
{$headerinclude}
</head>
<body>
{$header}
<form action="{$mybb->settings[\'bburl\']}/tradefeedback.php" method="post">
{$mypostkey}
<input type="hidden" name="action" value="report" />
<input type="hidden" name="fid" value="{$mybb->input[\'fid\']}" />
<div class="faux-table">
<div class="thead">{$lang->report_feedback}</div>
<div class="formLayout">     
<div class="cf">
<label>{$lang->report_feedback_reason}</label>
<textarea name="reason" rows="5" cols="70"></textarea>
</div>
</div>
<div class="postbit_buttons tfoot cf">
<input type="submit" class="button" value="{$lang->report_feedback}" />
</div>
</div>
</form>
<br />
{$footer}
</body>
</html>';

$new_template['tradefeedback_view_page'] = '<html>
<head>
<title>{$lang->viewing_feedback}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="faux-table">
<div class="thead">{$lang->feedback_stats}</div>
<div class="feedback">
<dl>
  <dt>{$lang->feedback_total}</dt>
  <dd><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?uid={$mybb->input[\'uid\']}"><span class="badge"><span class="subtext">{$lang->feedback_total_all}</span>{$totalreps}</span></a></dd>
</dl>
<dl class="positive">
  <dt>{$lang->positive_feedback}</dt>
  <dd><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?uid={$mybb->input[\'uid\']}&amp;value=1"><span class="badge badge-pos"><span class="subtext">{$lang->positive_feedback_all}</span>{$posreps}</span></a></dd>
</dl>
<dl class="neutral">
  <dt>{$lang->neutral_feedback}</dt>
  <dd><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?uid={$mybb->input[\'uid\']}&amp;value=0"><span class="badge badge-neut"><span class="subtext">{$lang->neutral_feedback_all}</span>{$neutreps}</span></a></dd>
</dl>
<dl class="negative">
  <dt>{$lang->negative_feedback}</dt>
  <dd><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?uid={$mybb->input[\'uid\']}&amp;value=-1"><span class="badge badge-neg"><span class="subtext">{$lang->negative_feedback_all}</span>{$negreps}</span></a></dd>
</dl>
<div class="postbit_buttons tfoot">
<a class="postbit_pm" href="{$mybb->settings[\'bburl\']}/private.php?action=send&amp;uid={$mybb->input[\'uid\']}">{$lang->feedback_pm}</a>
<a class="postbit_profile" href="{$mybb->settings[\'bburl\']}/member.php?action=profile&amp;uid={$mybb->input[\'uid\']}">{$lang->feedback_view_profile}</a>
<a class="postbit_quote" href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=give&amp;uid={$mybb->input[\'uid\']}">{$lang->leave_feedback}</a>
<a href="javascript:;" id="trade_modes">{$lang->feedback_change_view}</a>
</div>
</div>
</div>
<br/>
<div class="container-stats">
<div class="faux-table">
<div class="thead">{$lang->feedback_page_title}</div>
<table width="100%" cellpadding="1" cellspacing="0" border="0">
<tr>
<th class="tcat" width="1">{$lang->feedback_rating}</th>
<th class="tcat" width="50%">{$lang->feedback_comments}</th>
<th class="tcat">{$lang->feedback_from}</th>
{$detailcolumn}
<th class="tcat">{$lang->feedback_date}</th>
<th class="tcat">{$lang->feedback_options}</th>
</tr>
{$tradefeedback}
{$noresults}
</table>
<div class="postbit_buttons tfoot">
{$pagination}
</div>
</div>
</div>
<br />
{$footer}
<div id="trade_modes_popup" class="smalltext popup_menu" style="display: none;">
<div class="popup_item_container trow1"><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$mybb->input[\'uid\']}">{$lang->feedback_view_all}</a></div>
<div class="popup_item_container trow2"><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$mybb->input[\'uid\']}&amp;type=seller">{$lang->feedback_view_seller}</a></div>
<div class="popup_item_container trow1"><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$mybb->input[\'uid\']}&amp;type=buyer">{$lang->feedback_view_buyer}</a></div>
<div class="popup_item_container trow2"><a href="{$mybb->settings[\'bburl\']}/tradefeedback.php?action=view&amp;uid={$mybb->input[\'uid\']}&amp;type=trader">{$lang->feedback_view_trader}</a></div>
</div>
	<script type="text/javascript">
	// <!--
		if(use_xmlhttprequest == "1")
		{
			$("#trade_modes").popupMenu();
		}
	// -->
	</script>
</body>
</html>';

$new_template['tradefeedback_view_rep'] = '<tr class="trow">
<td class="{$tdclass}"><img src="{$feedback[\'smilyurl\']}" alt="" /></td>
<td class="{$tdclass}"><div style="margin-right:10%">{$feedback[\'comments\']}<br />{$threadlink}</div></td>
<td class="{$tdclass}">{$feedback[\'type\']} {$feedback[\'profilelink\']}</td>
{$detaillink}
<td class="{$tdclass}">{$feedback[\'dateline\']}</td>
<td class="{$tdclass}"><ul class="reset">{$report}{$modbit}</ul></td>
</tr>';

$new_template['tradefeedback_postbit_link'] = '<a href="tradefeedback.php?action=give&amp;uid={$post[\'uid\']}&amp;tid={$post[\'tid\']}" title="{$lang->give_feedback}" class="postbit_tradefeedback"><span>{$lang->give_feedback}</span></a>';

        foreach($new_template as $title => $template)
	    {
	    	$new_template = array('title' => $db->escape_string($title), 'template' => $db->escape_string($template), 'sid' => '-1', 'version' => '1600', 'dateline' => TIME_NOW);
	    	$db->insert_query('templates', $new_template);
	    }

        // Add Trade Feedback link to showthread template
        include MYBB_ROOT."/inc/adminfunctions_templates.php";
        find_replace_templatesets("postbit", "#".preg_quote('{$post[\'button_rep\']}')."#i", '{$post[\'button_rep\']}{$post[\'button_tradefeedback\']}');
        find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'button_rep\']}')."#i", '{$post[\'button_rep\']}{$post[\'button_tradefeedback\']}');
		
		$css = array(
	"name" => "trade.css",
	"tid" => 1,
	"attachedto" => "tradefeedback.php|member.php",
	"stylesheet" => ".faux-table {
    border-radius: 6px;
    border: 1px solid hsl(0, 0%, 80%);
    padding: 1px;
}
.postbit_buttons.tfoot {
    border-bottom-left-radius: 6px;
    border-bottom-right-radius: 6px;
    min-height: 20px;
}
.feedback dl {
    border-bottom: 1px solid #E8E8E8;
    padding: 0.5em;
    margin-top: 0;
    margin-bottom: 0;
}
.feedback dt {
    float: left;
    clear: left;
    font-weight: bold;
}

.feedback dd {
    margin: 5px 0 0 110px;
    padding: 0 0 0.5em 0;
    text-align: right;
}
.negative,
.negative a,
.negative a:visited {
    color: red;
}
.positive,
.positive a,
.positive a:visited {
    color: green;
}
.neutral,
.neutral a,
.neutral a:visited {
    color: grey;
}
.badge {
    padding: 4px 8px;
    border-radius: 16px;
    background-color: black;
    font-size: 11px;
}
.badge a,
.badge a:visited {
    color: white;
    font-weight: 700;
}
.badge-neg {
    background-color: red;
}
.badge-neut {
    background-color: gray;
}
.badge-pos {
    background-color: green;
}
a .badge .subtext {
    display: none;
    color: white;
    margin-right: 5px;
}
.feedback dl:hover > dd a .badge .subtext,
.badge:hover > .subtext {
    display: inline-block;
}
dl:hover > .subtext {
    display: inline-block;
}
a.badge,
a:link .badge,
a:visited .badge {
    color: white
}
#trade_modes_popup.popup_menu .popup_item_container {
    padding: 5px;
}
.popup_item_container.trow1:hover a,
.popup_item_container.trow2:hover a {
    color: #333;
}
.popup_item_container:first-of-type {
    border-top-left-radius: 6px;
    border-top-right-radius: 6px;
}
.popup_item_container:last-of-type {
    border-bottom-left-radius: 6px;
    border-bottom-right-radius: 6px;
}
.container-stats tr:last-of-type>td:last-child {
    border-bottom-right-radius: 6px;
}
.container-stats tr:last-of-type>td:first-child {
    border-bottom-left-radius: 6px;
}
ul.reset {
    list-style-type: none;
    padding-left: 5px;
}
.formLayout {
    padding: 8px 0;
}
.formLayout label,
.formLayout input,
.formLayout select {
    display: block;
    width: 200px;
    float: left;
    margin-bottom: 10px;
}
.formLayout label {
    width: 160px;
    text-align: right;
    padding-right: 20px;
    padding-top: 4px;
}
.cf:before,
.cf:after {
    content: \" \";
    display: table;
}
.cf:after {
    clear: both;
}
/**
 * For IE 6/7 only
 * Include this rule to trigger hasLayout and contain floats.
 */

.cf {
    *zoom: 1;
}",
    "cachefile" => $db->escape_string(str_replace('/', '', trade.css)),
	"lastmodified" => TIME_NOW
	);
		require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
		$sid = $db->insert_query("themestylesheets", $css);
		$db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=".$sid), "sid = '".$sid."'", 1);
		$tids = $db->simple_select("themes", "tid");
		while($theme = $db->fetch_array($tids))
		{
			update_theme_stylesheet_list($theme['tid']);
		}
	}

    function trader_deactivate()
    {
        global $db;
        $deletedtemplates = "'member_profile_trade_feedback_link','member_profile_trade_stats','tradefeedback_confirm_delete','tradefeedback_give_form','tradefeedback_mod','tradefeedback_report','tradefeedback_report_form','tradefeedback_view_page','tradefeedback_view_rep','tradefeedback_postbit_link'";
        $db->delete_query("templates", "title IN(".$deletedtemplates.")");

        // Remove Trade Feedback Link from showthread template
        include MYBB_ROOT."/inc/adminfunctions_templates.php";
        find_replace_templatesets("postbit", "#".preg_quote('{$post[\'button_tradefeedback\']}')."#i", '', 0);
        find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'button_tradefeedback\']}')."#i", '', 0);
		
		require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
		$db->delete_query("themestylesheets", "name = 'trade.css'");
		$query = $db->simple_select("themes", "tid");
		while($theme = $db->fetch_array($query))
		{
			update_theme_stylesheet_list($theme['tid']);
		}
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
        global $mybb, $templates, $lang;

        $post['totalrep'] = $post['posreps'] - $post['negreps'];
        $post['repcount'] = $post['posreps'] + $post['neutreps'] + $post['negreps'];

        // Feedback Button
        $post['button_tradefeedback'] = '';
        if($mybb->user['uid'] && !$mybb->usergroup['isbannedgroup'] && $mybb->user['uid'] != $post['uid'])
        {
            $lang->load("tradefeedback");
            eval("\$post['button_tradefeedback'] = \"".$templates->get("tradefeedback_postbit_link")."\";");
        }
    }

    function trader_member_profile()
    {
        global $mybb, $templates, $traderinfo, $db, $memprofile, $lang;
        $lang->load("tradefeedback");
        $memprofile['totalrep'] = $memprofile['posreps'] - $memprofile['negreps'];
        $memprofile['repcount'] = $memprofile['posreps'] + $memprofile['neutreps'] + $memprofile['negreps'];
		$feedbacklink = '';
        if($mybb->user['uid'] && !$mybb->usergroup['isbannedgroup'])
        {
            $lang->leave_feedback = $lang->sprintf($lang->leave_feedback, $memprofile['username']);
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
        global $mybb, $user_activity, $lang;
        if($array['user_activity']['activity'] == "tradefeedback")
        {
            $lang->load("tradefeedback");
            $array['location_name'] = $lang->feedback_online_list;
        }
        return $array;
    }

    function trader_send_pm($toid, $fid)
    {
        global $db, $mybb, $lang;
        $lang->load("tradefeedback");
        require_once MYBB_ROOT."inc/datahandlers/pm.php";
        $pmhandler = new PMDataHandler();
        $message_url = $mybb->settings['bburl'] . "/tradefeedback.php?action=view&uid=$toid&fid=$fid";
		$pmhandler->admin_override = true;
        	$pm = array(
			"subject" => $lang->feedback_pm_subject,
            "message" => $lang->sprintf($lang->feedback_pm_message, $mybb->user['username'], $message_url),
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
        if(class_exists("MybbStuff_MyAlerts_AlertFormatterManager"))
        {
            if($mybb->user['uid']) {
                $lang->load('tradefeedback');

                $code = 'tradefeedback';
                $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
                $formatterManager->registerFormatter(new TradeFeedbackFormatter($mybb, $lang, $code));
            }
        }
    }  

    // MyAlerts Function
    function trader_myalerts($toid, $fid)
    {
        if(class_exists("MybbStuff_MyAlerts_AlertTypeManager"))
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
    }

    // MyAlerts Trade Feedback Formatter
    if(class_exists("MybbStuff_MyAlerts_Formatter_AbstractFormatter")) {

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

    // Report Center Integration
    function trader_modcp_reports_report()
    {
        global $report;

        if($report['type'] != 'tradefeedback')
        {
            return;
        }

        global $mybb, $reputation_link, $bad_user, $lang, $good_user, $usercache, $report_data;

        $user = get_user($report['id3']);

        $reputation_link = $mybb->settings['bburl']."/tradefeedback.php?action=view&uid={$user['uid']}&amp;fid={$report['id']}";
        $bad_user = build_profile_link($usercache[$report['id2']]['username'], $usercache[$report['id2']]['uid']);
        $good_user = build_profile_link($user['username'], $user['uid']);
        $report_data['content'] = $lang->sprintf($lang->tradefeedback_report_info, $reputation_link, $bad_user, $good_user);
    }

    function trader_modcp_allreports_report()
    {
        global $report;

        if($report['type'] != 'tradefeedback')
        {
            return;
        }

        global $mybb, $reputation_link, $bad_user, $lang, $good_user, $report_data;

        $user = get_user($report['id3']);
        $bad_user_info = get_user($report['id2']);

        $reputation_link = $mybb->settings['bburl']."/tradefeedback.php?action=view&uid={$user['uid']}&amp;fid={$report['id']}";
        $bad_user = build_profile_link($bad_user_info['username'], $bad_user_info['uid']);
        $good_user = build_profile_link($user['username'], $user['uid']);
        $report_data['content'] = $lang->sprintf($lang->tradefeedback_report_info, $reputation_link, $bad_user, $good_user);
    }

    // Update from the ACP
    function trader_update()
    {
        global $db, $cache, $mybb;
        if($mybb->get_input("action") != "update_trader_plugin")
        {
            return;
        }
        $dbtables = array(
        "trade_feedback" => array(
        "tid" => "INT UNSIGNED NOT NULL DEFAULT 0",
        "forum_id" => "INT UNSIGNED NOT NULL DEFAULT 0"
        ),
        "usergroups" => array(
        "cantradefeedback" => "INT UNSIGNED NOT NULL DEFAULT 1"
        )
        );

        foreach($dbtables as $table => $value)
        {
            if(!$db->table_exists($table))
            {
                $db->query("CREATE TABLE " . TABLE_PREFIX . $table . " ( `id` INT NOT NULL DEFAULT 0 ) ENGINE=Innodb " . $db->build_create_table_collation());
 
            }
           $tablekeys = array_keys($value);
            foreach($tablekeys as $key)
            {
                if(!$db->field_exists($key, $table))
                {
                    $db->write_query("ALTER TABLE " . TABLE_PREFIX . $table . " ADD " . $key . " " . $dbtables[$table][$key]);
                }
            }
        }
        flash_message("Trade Feedback has been updated.", "success");
        admin_redirect("index.php?module=config-plugins");
    }
?>

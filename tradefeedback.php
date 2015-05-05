<?php
define("IN_MYBB", 1);
$templatelist = "tradefeedback_page,tradefeedback_form,tradefeedback_give_form,tradefeedback_view_page,tradefeedback_view_rep,tradefeedback_mod,tradefeedback_report,tradefeedback_report_form,tradefeedback_confirm_delete";
require_once "global.php";
$fid = intval($mybb->input['fid']);
$mypostkey = "<input type=\"hidden\" name=\"my_post_key\" value=\"".$mybb->post_code."\" />";
switch($mybb->input['action'])
{
    case "give":
    trader_give_rep($mybb->input['uid']);
    break;
    case "view":
    trader_view_rep($mybb->input['uid']);
    break;
    case "delete":
    trader_delete_rep($fid);
    break;
    case "report":
    trader_report($fid);
    break;
    case "approve":
    trader_approve($fid);
    break;
    case "unapprove":
    trader_unapprove($fid);
    break;
    case "edit":
    trader_edit($fid);
    break;
    default:
    trader_view_rep($mybb->input['uid']);
    break;
}

function trader_give_rep($uid=1)
{
    global $mybb, $db, $tradefeedbackform, $mypostkey, $templates, $header, $headerinclude, $footer;
    $uid = intval($uid);
    $action = "give";
    if($mybb->user['uid'] == $uid)
    {
        error("You can't give yourself a rep.");
    }
    if(!$uid)
    {
        error("Invalid user.");
    }
    if($mybb->request_method == "post" && verify_post_check($mybb->input['my_post_key']))
    {
        // Make sure they haven't given the same user feedback within the last 24 hours
        $cutoff = TIME_NOW - 86400;
        $queryfirst = $db->simple_select("trade_feedback", "fid", "dateline >= $cutoff AND receiver=$uid & giver=" . $mybb->user['uid']);
        if($db->num_rows($queryfirst) >= 1)
        {
            error("You must wait 24 hours between giving the same user feedback.");
        }
        $new_rep = array(
        "giver" => $mybb->user['uid'],
        "receiver" => $uid,
        "dateline" => TIME_NOW,
        "approved" => 1,
        "value" => intval($mybb->input['value']),
        "type" => $db->escape_string($mybb->input['type']),
        "threadlink" => $db->escape_string($mybb->input['threadlink']),
        "comments" => $db->escape_string($mybb->input['comments'])
        );
        if($mybb->settings['trade_approval'] == 1 && $mybb->usergroup['canmodcp'] == 0)
        {
            $new_rep['approved'] = 0;
        }
        $db->insert_query("trade_feedback", $new_rep);
        $fid = $db->insert_id();
        trader_send_pm($new_rep['receiver'], $fid);
        if($new_rep['approved'] == 1)
        {
            trader_rebuild_reputation($uid);
            $message = "Your feedback has been added.";
        }
        else
        {
            $message = "Your feedback is awaiting approval from the forum administration.";
        }
        $url = $mybb->settings['bburl']. "/tradefeedback.php?action=view&uid=$uid";
        redirect($url, $message);
    }
    else
    {
        // Get the member username for confirmation
        $query = $db->simple_select("users", "uid, username", "uid=$uid");
        $member = $db->fetch_array($query);
		$member['username'] = htmlspecialchars_uni($member['username']);
        add_breadcrumb($member['username'] . "'s Profile", get_profile_link($uid));
        add_breadcrumb($member['username'] . "'s Reputations", "tradefeedback.php?action=view&uid=$uid");
        add_breadcrumb("Giving Reputation", "tradefeedback.php?action=give&uid=$uid");
		$feedback = array('comments' => htmlspecialchars_uni($mybb->input['comments']));
        eval("\$tradefeedbackform = \"".$templates->get("tradefeedback_give_form")."\";");
        output_page($tradefeedbackform);
    }
}

function trader_view_rep($uid=1)
{
    global $mybb, $db, $templates, $pagination, $mypostkey, $header, $headerinclude, $footer, $theme, $posreps, $negreps, $neutreps, $totalreps;
    $uid = intval($uid);
    if(!$uid)
    {
        $uid = $mybb->user['uid'];
    }
    if(!$uid)
    {
        error("Invalid user.");
    }
    add_breadcrumb("Trade Feedback", "tradefeedback.php?action=view&uid=$uid");
    if($mybb->input['fid'])
    {
        $colspan = 5;
        $fidonly = "AND f.fid=".intval($mybb->input['fid'])." ";
    }
    else
    {
        $colspan = 6;
        $detailcolumn = "<th class=\"thead\">Detail</th>";
    }
    $url = "tradefeedback.php?action=view&uid=$uid";
    if(isset($mybb->input['value']))
    {
        $value = intval($mybb->input['value']);
        $valuesql = " AND f.value=$value";
        $url .= "&value=$value";
    }
    if(isset($mybb->input['type']))
    {
        $type = $db->escape_string($mybb->input['type']);
        $typesql = " AND f.type='$type'";
        $url .= "&type=$type";
    }
    $approved = 1;
    if($mybb->usergroup['canmodcp'] && $mybb->usergroup['issupermod'])
    {
        $approved = 0;
    }
    // Count the number of reps to figure out pagination
    $query = $db->simple_select("trade_feedback f", "COUNT(f.fid) as reps", "f.receiver=$uid AND f.approved >= $approved $valuesql $fidonly $typesql");
    $total = $db->fetch_field($query, "reps");
    if(!$total)
    {
        $noresults = "<tr><td colspan=\"{$colspan}\">There are no results to display</td></tr>";
    }
    $userquery = $db->simple_select("users", "username, posreps, neutreps, negreps", "uid=$uid");
    $feedback = $db->fetch_array($userquery);
    $receiverusername = $feedback['username'];
    $posreps = $feedback['posreps'];
    $neutreps = $feedback['neutreps'];
    $negreps = $feedback['negreps'];
    $totalreps = $posreps + $neutreps + $negreps;
    $perpage = 20;
    $pages = ceil($total / $perpage);
    if($mybb->input['page'])
    {
        $pages = intval($mybb->input['page']);
    }
    else
    {
        $page = 1;    
    }
    if($page < 1)
    {
        $page = 1;
    }
    if($page > $pages)
    {
        $page = $pages;
    }
    $start = $page * $perpage - $perpage;
    if($start < 0)
    {
        $start = 0;
    }
    $pagination = multipage($total, $perpage, $page, $url);
    // Actually fetch the feedback
    $query = $db->query("SELECT f.*, u.username, u.usergroup, u.displaygroup
    FROM " . TABLE_PREFIX . "trade_feedback f
    LEFT JOIN " . TABLE_PREFIX . "users u
    ON(f.giver=u.uid)
    WHERE f.receiver=$uid AND f.approved >= $approved $valuesql $fidonly $typesql
    ORDER BY f.dateline DESC
    LIMIT $start , $perpage");
    while($feedback = $db->fetch_array($query))
    {
        $feedback['formattedname'] = format_name($feedback['username'], $feedback['usergroup'], $feedback['displaygroup']);
        $feedback['profilelink'] = build_profile_link($feedback['formattedname'], $feedback['giver']);
        $feedback['dateline'] = my_date($mybb->settings['dateformat'], $feedback['dateline'], "", 0);
        if($feedback['threadlink'] && $mybb->input['fid'])
        {
            $threadlink = "<br /><a href=\"".htmlspecialchars_uni($feedback['threadlink'])."\" target=\"_blank\">Thread Link</a>";
        }
        if($feedback['value'] == 1)
        {
            $feedback['smilyurl'] = $mybb->settings['bburl'] . "/" . $theme['imgdir'] . "/smilies/smile.gif";
        }
        else if($feedback['value'] == 0)
        {
            $feedback['smilyurl'] = $mybb->settings['bburl'] . "/" . $theme['imgdir'] . "/smilies/undecided.gif";
        }
        else
        {
            $feedback['smilyurl'] = $mybb->settings['bburl'] . "/" . $theme['imgdir'] . "/smilies/angry.gif";
        }
        $feedback['type'] = ucfirst($feedback['type']);
        if($mybb->usergroup['canmodcp'] && $mybb->usergroup['issupermod'])
        {
            if($feedback['approved'] == 1)
            {
                $approvedtext = "Unapprove";
                $approvedlinkpart = "unapprove";
                $tdclass = alt_trow();
            }
            else
            {
                $approvedtext = "Approve";
                $approvedlinkpart = "approve";
                $tdclass = "trow_shaded";
            }
            eval("\$modbit = \"".$templates->get("tradefeedback_mod")."\";");
        }
        if(!$mybb->input['fid'])
        {
            $detaillink = "<td class=\"$tdclass\"><a href=\"".$mybb->settings['bburl']."/tradefeedback.php?action=view&amp;uid=".$mybb->input['uid']."&amp;fid=".$feedback['fid']."\">View Details</a></td>";
            if(strlen($feedback['comments']) >= 50)
            {            
                $feedback['comments'] = my_substr($feedback['comments'], 0, 50) . "...";
            }
        }
        if($mybb->user['uid'] && $mybb->usergroup['isbannedgroup'] == 0)
        {
            eval("\$report = \"".$templates->get("tradefeedback_report")."\";");
        }
		$feedback['comments'] = htmlspecialchars_uni($feedback['comments']);
        eval("\$tradefeedback .= \"".$templates->get("tradefeedback_view_rep")."\";");
        unset($threadlink);
        unset($detaillink);
    }
    eval("\$tradefeedback_view_page = \"".$templates->get("tradefeedback_view_page")."\";");
    output_page($tradefeedback_view_page);
}

function trader_delete_rep($fid)
{
    global $mybb, $db, $templates, $confirmdelete, $mypostkey, $header, $headerinclude, $footer;
    $fid = intval($fid);
    if(!$fid)
    {
        error("Invalid rep");
    }
    if($mybb->usergroup['canmodcp'] == 0)
    {
        error_no_permission();
    }
    $query = $db->simple_select("trade_feedback", "receiver", "fid=$fid");
    if($db->num_rows($query) == 0)
    {
        error("Invalid rep.");
    }
    $userid = $db->fetch_field($query, "receiver");
    if($mybb->request_method == "post" && verify_post_check($mybb->input['my_post_key']))
    {
        if($mybb->input['confirm'] == 1)
        {
            $db->delete_query("trade_feedback", "fid=$fid");
            trader_rebuild_reputation($userid);
            $url = $mybb->settings['bburl'] . "/tradefeedback.php?action=view&uid=$userid";
            $message = "The rep has been deleted.";
            redirect($url, $message);
        }
        else
        {
            $url = $mybb->settings['bburl'] . "/tradefeedback.php?action=view&uid=$userid";
            $message = "You are being returned to where you came from.";
            redirect($url, $message);
        }
    }
    else
    {
        eval("\$confirmdelete =\"".$templates->get("tradefeedback_confirm_delete")."\";");
        output_page($confirmdelete);
    }
}

function trader_report($fid)
{
    global $mybb, $db, $templates, $reportform, $mypostkey, $header, $headerinclude, $footer;
    $fid = intval($fid);
    if(!$fid)
    {
        error("Invalid rep.");
    }
    if(!$mybb->user['uid'] || $mybb->usergroup['isbannedgroup'] == 1)
    {
        error_no_permission();
    }
    $query = $db->simple_select("trade_feedback", "receiver,reported", "fid=$fid");
    if($db->num_rows($query) == 0)
    {
        error("Invalid rep.");
    }
    $feedback = $db->fetch_array($query);
    if($feedback['reported'] == 1)
    {
        error("This rep has already been reported.");
    }
    $userid = $feedback['receiver'];
    if($mybb->request_method == "post" && verify_post_check($mybb->input['my_post_key']))
    {
        $emailmessage = "The following feedback has been reported by " . $mybb->user['username']. ":\n<a href=\"".$mybb->settings['bburl']."/tradefeedback.php?action=view&uid=".$feedback['receiver']."&fid=$fid\"\nReason:".htmlspecialchars_uni($mybb->input['reason']);
        // Now fetch the global mods
        $query =$db->query("SELECT u.email, ug.gid FROM " . TABLE_PREFIX . "users u
        LEFT JOIN " . TABLE_PREFIX ."usergroups ug ON(u.usergroup=ug.gid)
        WHERE ug.canmodcp=1 AND ug.issupermod=1");
        while($moderator = $db->fetch_array($query))
        {
            my_mail($moderator['email'], "Reported Reputation on " . $mybb->settings['bbname'], $emailmessage);
        }
        $db->write_query("UPDATE ". TABLE_PREFIX . "trade_feedback SET reported=1 WHERE fid=$fid");
        $url = $mybb->settings['bburl'] . "/tradefeedback.php?action=view&uid=$userid";
        $message = "The rep has been reported.";
        redirect($url, $message);
    }
    else
    {
        eval("\$reportform = \"".$templates->get("tradefeedback_report_form")."\";");
        output_page($reportform);
    }
}

function trader_approve($fid)
{
    global $mybb, $db, $header, $headerinclude, $footer;
    $fid = intval($fid);
    if(!$fid)
    {
        error("Invalid rep.");
    }
    if($mybb->usergroup['canmodcp'] == 0)
    {
        error_no_permission();
    }
    verify_post_check($mybb->input['my_post_key']);
    // Check if the rep exists
    $query = $db->simple_select("trade_feedback", "receiver", "fid=$fid");
    $userid = $db->fetch_field($query, "receiver");
    if(!$userid)
    {
        error("Invalid rep.");
    }
    $db->write_query("UPDATE " . TABLE_PREFIX . "trade_feedback SET approved=1 WHERE fid=$fid");
    trader_rebuild_reputation($userid);
    $url = $mybb->settings['bburl'] . "/tradefeedback.php?action=view&uid=$userid";
    $message = "The rep has been approved.";
    redirect($url, $message);
}

function trader_unapprove($fid)
{
    global $mybb, $db, $header, $headerinclude, $footer;
    $fid = intval($fid);
    if(!$fid)
    {
        error("Invalid rep.");
    }
    if($mybb->usergroup['canmodcp'] == 0)
    {
        error_no_permission();
    }
    verify_post_check($mybb->input['my_post_key']);
    // Check if the rep exists
    $query = $db->simple_select("trade_feedback", "receiver", "fid=$fid");
    $userid = $db->fetch_field($query, "receiver");
    if(!$userid)
    {
        error("Invalid rep.");
    }
    $db->write_query("UPDATE " . TABLE_PREFIX . "trade_feedback SET approved=0 WHERE fid=$fid");
    trader_rebuild_reputation($userid);
    $url = $mybb->settings['bburl'] . "/tradefeedback.php?action=view&uid=$userid";
    $message = "The rep has been unapproved.";
    redirect($url, $message);
}

function trader_edit($fid)
{
    global $mybb, $db, $tradefeedbackform, $mypostkey, $templates, $header, $headerinclude, $footer;
    $uid = intval($mybb->input['uid']);
    $action = "edit";
    if($mybb->request_method == "post" && verify_post_check($mybb->input['my_post_key']))
    {
        // Make sure they haven't given the same user feedback within the last 24 hours
        $edit_rep = array(
        "dateline" => TIME_NOW,
        "approved" => 1,
        "value" => intval($mybb->input['value']),
        "type" => $db->escape_string($mybb->input['type']),
        "threadlink" => $db->escape_string($mybb->input['threadlink']),
        "comments" => $db->escape_string($mybb->input['comments'])
        );
        $db->update_query("trade_feedback", $edit_rep, "fid=$fid");
        trader_rebuild_reputation($uid);
        $message = "The feedback has been updated.";
        $url = $mybb->settings['bburl']. "/tradefeedback.php?action=view&uid=$uid";
        redirect($url, $message);
    }
    else
    {
        // Get the member username for confirmation
        $query = $db->simple_select("users", "uid, username", "uid=$uid");
        $member = $db->fetch_array($query);
        add_breadcrumb($member['username'] . "'s Profile", "member.php?action=profile&uid=$uid");
        add_breadcrumb($member['username'] . "'s Reputations", "tradefeedback.php?action=view&uid=$uid");
        add_breadcrumb("Giving Reputation", "tradefeedback.php?action=give&uid=$uid");
        $q2 = $db->simple_select("trade_feedback", "*", "fid=$fid");
        $feedback = $db->fetch_array($q2);
        $repselect = "<option value=\"".$feedback['value']."\">Leave The Same</option>";
        switch($feedback['type'])
        {
            case "buyer":
            $buyerselect = "selected=\"selected\"";
            break;
            case "seller":
            $sellerselect = "selected=\"selected\"";
            break;
            case "trader":
            $traderselect = "selected=\"selected\"";
            break;
            default:
            break;
        }
		$feedback['comments'] = htmlspecialchars_uni($mybb->input['comments']);
        eval("\$tradefeedbackform = \"".$templates->get("tradefeedback_give_form")."\";");
        output_page($tradefeedbackform);
    }
}

<?php
/* This file converts trade feedback from Xenforo Feedback to this sytem.*/
define("IN_MYBB", 1);
define("NO_ONLINE", 1);
define("THIS_SCRIPT", "xenforo.php");
define("NO_PLUGINS", 1);
define("FEEDBACK_PREFIX", "xc"); // Change the second value to your table's prefix.
require_once "global.php";
if($mybb->usergroup['cancp'] == 0)
{
    error_no_permission();
}
if($mybb->input['page'])
{
    $page = intval($mybb->input['page']);
}
else
{
    $page = 1;
}
if($page < 1)
{
    $page = 1;
}
// Number of users per page
$perpage = 25;
$start = $page * $perpage - $perpage;
if($start < 0)
{
    $start = 0;
}
// First get a list of uids on the page
$userquery = $db->query("SELECT COUNT(DISTINCT foruserid) as membercount FROM " . FEEDBACK_PREFIX . "trade_feedback");
$membercount = $db->fetch_field($userquery, "membercount");
$pages = ceil($membercount / $perpage);
if($page > $pages)
{
    echo "All feedback has been successfully converted.<br /><a href=\"".MYBB_ROOT."/index.php\">Return to index</a>";
    exit;
}
else
{
    $query = $db->query("SELECT DISTINCT foruserid FROM " . FEEDBACK_PREFIX . "trade_feedback ORDER BY foruserid ASC LIMIT $start, $perpage");
    while($userinfo = $db->fetch_array($query))
    {
        $poscount = 0;
        $neutcount = 0;
        $negcount = 0;
        $feedbackcount = 0;
        $userid = $userinfo['fromuserid'];
        $feedbackquery = $db->query("SELECT * FROM " . FEEDBACK_PREFIX . "trade_feedback WHERE foruserid=$userid");
        while($feedback = $db->fetch_array($feedbackquery))
        {
            // Now the actual conversion
            $type = $db->escape_string($feedback['type']);
            // Now the reputation type
            if($feedback['amount'] == 1) /* Positive */
            {
                $value = 1;
                ++$poscount;
            }
            else if($feedback['amount'] == 0) /* Neutral */
            {
                $value = 0;
                ++$neutcount;
            }
            else /* Negative */
            {
                $value = -1;
                ++$negcount;
            }
            $new_rep = array(
            "giver" => $feedback['fromuserid'],
            "receiver" => $feedback['foruserid'],
            "dateline" => $feedback['dateline'],
            "approved" => 1,
            "comments" => $db->escape_string($feedback['review']),
            "type" => $type,
            "value" => $value,
            "reported" => 0,
            "threadlink" => $db->escape_string($feedback['dealurl'])
            );
            $db->insert_query("trade_feedback", $new_rep);
            ++$feedbackcount;
        } // End that member
        $message .= "<br />Inserted " . $feedbackcount . " ratings into member #$userid";
        $update_user = array(
        "posreps" => $poscount,
        "neutreps" => $neutcount,
        "negreps" => $negcount
        );
        $db->update_query("users", $update_user, "uid=$userid");
    } // End user selection loop
    // Time for a redirect
    $nextpage = $page + 1;
    $url = "xenforo.php?page=$nextpage";
    $message .= "Autoredirecting...";
    redirect($url, $message);
}

?>

<?php
/* This file converts trade feedback from EZTrader to this sytem.*/
define("IN_MYBB", 1);
define("NO_ONLINE", 1);
define("THIS_SCRIPT", "converter.php");
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
$userquery = $db->query("SELECT COUNT(DISTINCT ID_MEMBER) as membercount FROM " . TABLE_PREFIX . "trader_feedback");
$membercount = $db->fetch_field($userquery, "membercount");
$pages = ceil($membercount / $perpage);
if($page > $pages)
{
    echo "All feedback has been successfully converted.<br /><a href=\"".MYBB_ROOT."/index.php\">Return to index</a>";
    exit;
}
else
{
    $query = $db->query("SELECT DISTINCT ID_MEMBER FROM " . TABLE_PREFIX . "trader_feedback ORDER BY ID_MEMBER ASC LIMIT $start, $perpage");
    while($userinfo = $db->fetch_array($query))
    {
        $poscount = 0;
        $neutcount = 0;
        $negcount = 0;
        $feedbackcount = 0;
        $userid = $userinfo['ID_MEMBER'];
        $feedbackquery = $db->simple_select("trader_feedback", "*", "ID_MEMBER=$userid");
        while($feedback = $db->fetch_array($feedbackquery))
        {
            // Now the actual conversion
            // First the feedback type
            if($feedback['saletype'] == 1)
            {
                $type = "seller";
            }
            else if($feedback['saletype'] == 0)
            {
                $type = "buyer";
            }
            else
            {
                $type = "trader";
            }
            // Now the reputation type
            if($feedback['salevalue'] == 0) /* Positive */
            {
                $value = 1;
                ++$poscount;
            }
            else if($feedback['salevalue'] == 1) /* Neutral */
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
            "giver" => $feedback['FeedBackMEMBER_ID'],
            "receiver" => $userid,
            "dateline" => $feedback['saledate'],
            "approved" => $feedback['approved'],
            "comments" => $db->escape_string($feedback['comment_short']) . "\n\n" . $db->escape_string($feedback['comment_long']),
            "type" => $type,
            "value" => $value,
            "reported" => 0
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
    $url = "converter.php?page=$nextpage";
    $message .= "Autoredirecting...";
    redirect($url, $message);
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <body>
    <?php

// Display the nested comments
function display_follow_up($cid, $level, $display, $filter, $link, $comment_table)
{
  $follow = mysql_query("SELECT story_id,id,subject,writer,date FROM $comment_table WHERE parent=$cid", $link) or die("ERROR: Query failed");
  while ($follow_row = mysql_fetch_array($follow))
  {
    for ($i = 0 ; $i < $level ; $i++)
      printf("&nbsp&nbsp&nbsp");
    print("<a href=\"/PHP/ViewComment.php?comment_table=$comment_table&storyId=".$follow_row["story_id"]."&commentId=".$follow_row["id"]."&filter=$filter&display=$display\">".$follow_row["subject"]."</a> by ".getUserName($follow_row["writer"], $link)." on ".$follow_row["date"]."<br>\n");
    if ($follow_row["childs"] > 0)
      display_follow_up($follow_row["id"], $level+1, $display, $filter, $link, $comment_table);
  }
}

    $scriptName = "ViewStory.php";
    include("PHPprinter.php");
    $startTime = getMicroTime();

    // Check parameters
    $storyId = $_POST['storyId'];
    if ($storyId == null)
    {
      $storyId = $_GET['storyId'];
      if ($storyId == null)
      {
         printError($scriptName, $startTime, "Viewing story", "You must provide a story identifier!<br>");
         exit();
      }
    }

    getDatabaseLink($link);

    function get_story($storyId, $table) {
        global $link;
        $table = new phpcassa\ColumnFamily($link, $table);
        try {
          return $table->get($storyId);
        } catch (cassandra\NotFoundException $e) {
          return null;
        } catch (Exception $e) {
          die("ERROR: Query failed");
        }
    }

    $row = get_story($storyId, "Stories");
    if (empty($row))
    {
      $row = get_story($storyId, "OldStories");
      $comment_table = "StoryOldComments";
    }
    else
      $comment_table = "StoryComments";
    if (empty($row))
      die("<h3>ERROR: Sorry, but this story does not exist.</h3><br>\n");
    $username = $row["writer"];

    // Display the story
    printHTMLheader("RUBBoS: Viewing story ".$row["title"]);
    printHTMLHighlighted($row["title"]);
    print("Posted by ".$username." on ".$row["date"]."<br>\n");
    print($row["body"]."<br>\n");
      print("<p><center><a href=\"/PHP/PostComment.php?comment_table=$comment_table&storyId=$storyId&parent=0\">Post a comment on this story</a></center><p>");

    // Display filter chooser header
    print("<br><hr><br>");
    print("<center><form action=\"/PHP/ViewComment.php\" method=POST>\n".
          "<input type=hidden name=commentId value=0>\n".
          "<input type=hidden name=storyId value=$storyId>\n".
          "<input type=hidden name=comment_table value=$comment_table>\n".
          "<B>Filter :</B>&nbsp&nbsp<SELECT name=filter>\n");
    /* TODO Comment rating
    $count_result = mysql_query("SELECT rating, COUNT(rating) AS count FROM $comment_table WHERE story_id=$storyId GROUP BY rating ORDER BY rating", $link) or die("ERROR: Query failed");
    $i = -1;
    while ($count_row = mysql_fetch_array($count_result))
    {
      while (($i < 6) && ($count_row["rating"] != $i))
      {
        if ($i == $filter)
          print("<OPTION selected value=\"$i\">$i: 0 comment</OPTION>\n");
        else
          print("<OPTION value=\"$i\">$i: 0 comment</OPTION>\n");
        $i++;
      }
      if ($count_row["rating"] == $i)
      {
        if ($i == $filter)
          print("<OPTION selected value=\"$i\">$i: ".$count_row["count"]." comments</OPTION>\n");
        else
          print("<OPTION value=\"$i\">$i: ".$count_row["count"]." comments</OPTION>\n");
        $i++;
      }
    }
    while ($i < 6)
    {
      print("<OPTION value=\"$i\">$i: 0 comment</OPTION>\n");
      $i++;
    }
     */

    print("</SELECT>&nbsp&nbsp&nbsp&nbsp<SELECT name=display>\n".
          "<OPTION value=\"0\">Main threads</OPTION>\n".
          "<OPTION selected value=\"1\">Nested</OPTION>\n".
          "<OPTION value=\"2\">All comments</OPTION>\n".
          "</SELECT>&nbsp&nbsp&nbsp&nbsp<input type=submit value=\"Refresh display\"></center><p>\n");          
    $display = 1;
    $filter = 0;

    // Display the comments
    $story_comments = new phpcassa\ColumnFamily($link, $comment_table);
    $comments = new phpcassa\ColumnFamily($link, "Comments");
    $comments->return_format = phpcassa\ColumnFamily::ARRAY_FORMAT;
    try {
        /*
      $comment = $comments->get_indexed_slices(new phpcassa\Index\IndexClause(array(
        new phpcassa\Index\IndexExpression("KEY", $storyId),
        new phpcassa\Index\IndexExpression("rating", 0, "GTE")
      )));
*/
      $result = $story_comments->get($storyId);
      $result = $comments->multiget(array_values($result));
    } catch (Exception $e) {
      die("ERROR: Query failed");
    }

    foreach ($result as $comment)
    {
      $commentId = $comment[0]->string;
      $comment_row = array();
      foreach ($comment[1] as $column) {
        $comment_row[$column[0]] = $column[1];
      }
      print("<br><hr><br>");
      $username = getUserName($comment_row["writer"], $link);
      print("<TABLE width=\"100%\" bgcolor=\"#CCCCFF\"><TR><TD><FONT size=\"4\" color=\"#000000\"><B><a href=\"/PHP/ViewComment.php?comment_table=$comment_table&storyId=$storyId&commentId=".$commentId."&filter=$filter&display=$display\">".$comment_row["subject"]."</a></B>&nbsp</FONT> (Score:".$comment_row["rating"].")</TABLE>\n");
      print("<TABLE><TR><TD><B>Posted by ".$username." on ".$comment_row["date"]."</B><p>\n");
      print("<TR><TD>".$comment_row["comment"]);
      print("<TR><TD><p>[ <a href=\"/PHP/PostComment.php?comment_table=$comment_table&storyId=$storyId&parent=".$commentId."\">Reply to this</a>&nbsp|&nbsp".
            "<a href=\"/PHP/ViewComment.php?comment_table=$comment_table&storyId=$storyId&commentId=".$comment_row["parent"]."&filter=$filter&display=$display\">Parent</a>".
            "&nbsp|&nbsp<a href=\"/PHP/ModerateComment.php?comment_table=$comment_table&commentId=".$commentId."\">Moderate</a> ]</TABLE>\n");
      if ($comment_row["childs"] > 0)
        display_follow_up($commentId, 1, $display, $filter, $link, $comment_table);
    }

    printHTMLfooter($scriptName, $startTime);
    ?>
  </body>
</html>

<?
//
// "$Id: common.php,v 1.5 2004/05/18 21:26:52 mike Exp $"
//
// Common utility functions for PHP pages...
//
// This file should be included using "include_once"...
//
// Contents:
//
//   abbreviate()          - Abbreviate long strings...
//   format_text()         - Convert plain text to HTML...
//   quote_text()          - Quote a string...
//   sanitize_email()      - Convert an email address to something a SPAMbot
//                           can't read...
//   sanitize_text()       - Sanitize text.
//   select_is_published() - Do a <select> for the "is published" field...
//   show_comments()       - Show comments for the given path...
//


//
// 'abbreviate()' - Abbreviate long strings...
//

function				// O - Abbreviated string
abbreviate($text,			// I - String
           $maxlen = 32)		// I - Maximum length of string
{
  $newtext   = "";
  $textlen   = strlen($text);
  $inelement = 0;

  for ($i = 0, $len = 0; $i < $textlen && $len < $maxlen; $i ++)
    switch ($text[$i])
    {
      case '<' :
          $inelement = 1;
	  break;

      case '>' :
          if ($inelement)
	    $inelement = 0;
	  else
	  {
	    $newtext .= "&gt;";
	    $len     ++;
	  }
	  break;

      case '&' :
          $len ++;

	  while ($i < $textlen)
	  {
	    $newtext .= $text[$i];

	    if ($text[$i] == ';')
	      break;

	    $i ++;
	  }
	  break;

      default :
          if (!$inelement)
	  {
	    $newtext .= $text[$i];
	    $len ++;
	  }
	  break;
    }
	    
  if ($i < $textlen)
    return ($newtext . "...");
  else
    return ($newtext);
}


//
// 'count_comments()' - Count visible comments for the given path...
//

function				// O - Number of comments
count_comments($url,			// I - URL for comment
               $parent_id = 0)		// I - Parent comment
{
  $result = db_query("SELECT * FROM comment WHERE "
                    ."url = '" . db_escape($url) ."' "
                    ."AND status > 0 AND parent_id = $parent_id "
		    ."ORDER BY id");

  $num_comments = db_count($result);

  while ($row = db_next($result))
    $num_comments += count_comments($url, $row['id']);

  db_free($result);

  return ($num_comments);
}


//
// 'format_text()' - Convert plain text to HTML...
//

function				// O - Quoted string
format_text($text)			// I - Original string
{
  $len   = strlen($text);
  $col   = 0;
  $list  = 0;
  $bold  = 0;
  $pre   = 0;
  $ftext = "<p>";

  for ($i = 0; $i < $len; $i ++)
  {
    switch ($text[$i])
    {
      case '<' :
          $col ++;
          $ftext .= "&lt;";
	  break;

      case '>' :
          $col ++;
          $ftext .= "&gt;";
	  break;

      case '&' :
          $col ++;
          $ftext .= "&amp;";
	  break;

      case "\n" :
          if (($i + 1) < $len &&
	      ($text[$i + 1] == "\n" || $text[$i + 1] == "\r"))
	  {
	    while (($i + 1) < $len &&
	           ($text[$i + 1] == "\n" || $text[$i + 1] == "\r"))
	      $i ++;

            if ($pre)
	    {
	      $ftext .= "</pre>";
	      $pre = 0;
	    }

            if (($i + 1) < $len && $text[$i + 1] != '-' && $list)
	    {
	      $ftext .= "\n</ul>\n<p>";
	      $list  = 0;
	    }
	    else
	      $ftext .= "\n<p>";
	  }
          else if (($i + 1) < $len &&
	           ($text[$i + 1] == " " || $text[$i + 1] == "\t"))
          {
            if ($pre)
	    {
	      $ftext .= "</pre>";
	      $pre = 0;
	    }
	    else
	      $ftext .= "<br />\n";
	  }

          $col = 0;
	  break;

      case "\r" :
	  break;

      case "\t" :
          if ($col == 0)
	    $ftext .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
	  else
            $ftext .= " ";
	  break;

      case " " :
          if ($col == 0 && !pre)
	  {
	    for ($j = $i + 1; $j < $len; $j ++)
	      if ($text[$j] != " " && $text[$j] != "\t")
	        break;

            if ($j < $len && $text[$j] == "%")
	    {
	      $ftext .= "\n<pre>";
	      $pre   = 1;
	    }

	    $ftext .= "&nbsp;";
	  }
	  else if ($text[$i + 1] == " ")
	    $ftext .= "&nbsp;";
	  else
            $ftext .= " ";

          if ($col > 0)
	    $col ++;
	  break;

      case '*' :
          if ($bold)
	    $ftext .= "</b>";
	  else
	    $ftext .= "<b>";

	  $bold = 1 - $bold;
	  break;

      case '-' :
          // Possible list...
	  if ($col == 0)
	  {
	    if (!$list)
	    {
	      $ftext .= "\n<ul>";
	      $list  = 1;
	    }

	    $ftext .= "\n<li>";
	    
	    while (($i + 1) < $len && $text[$i + 1] == "-")
	      $i ++;
	    break;
	  }

          $col ++;
          $ftext .= $text[$i];
	  break;

      case 'f' :
      case 'h' :
          if (substr($text, $i, 7) == "http://" ||
              substr($text, $i, 8) == "https://" ||
              substr($text, $i, 6) == "ftp://")
	  {
	    // Extract the URL and make this a link...
	    for ($j = $i; $j < $len; $j ++)
	      if ($text[$j] == " " || $text[$j] == "\n" || $text[$j] == "\r" ||
	          $text[$j] == "\t" || $text[$j] == "\'" || $text[$j] == "'")
	        break;

            $count = $j - $i;
            $url   = substr($text, $i, $count);
	    $ftext .= "<a href='$url'>$url</a>";
	    $col   += $count;
	    $i     = $j - 1;
	    break;
	  }

      default :
          $col ++;
          $ftext .= $text[$i];
	  break;
    }
  }

  if ($bold)
    $ftext .= "</b>";

  if ($list)
    $ftext .= "</ul>";

  return ($ftext);
}


//
// 'quote_text()' - Quote a string...
//

function				// O - Quoted string
quote_text($text,			// I - Original string
           $quote = 0)			// I - Add ">" to front of message
{
  $len   = strlen($text);
  $col   = 0;

  if ($quote)
    $qtext = "&gt; ";
  else
    $qtext = "";

  for ($i = 0; $i < $len; $i ++)
  {
    switch ($text[$i])
    {
      case '<' :
          $col ++;
          $qtext .= "&lt;";
	  break;

      case '>' :
          $col ++;
          $qtext .= "&gt;";
	  break;

      case '&' :
          $col ++;
          $qtext .= "&amp;";
	  break;

      case "\n" :
          if ($quote)
            $qtext .= "\n&gt; ";
	  else
            $qtext .= "<br />";

          $col = 0;
	  break;

      case "\r" :
	  break;

      case "\t" :
          if ($col == 0)
	    $qtext .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
	  else
            $qtext .= " ";
	  break;

      case " " :
          if ($col == 0 || $text[$i + 1] == " ")
	    $qtext .= "&nbsp;";
	  else if ($col > 65 && $quote)
	  {
	    $qtext .= "\n&gt; ";
	    $col    = 0;
	  }
	  else
            $qtext .= " ";

          if ($col > 0)
	    $col ++;
	  break;

      case 'f' :
      case 'h' :
          if (substr($text, $i, 7) == "http://" ||
              substr($text, $i, 8) == "https://" ||
              substr($text, $i, 6) == "ftp://")
	  {
	    // Extract the URL and make this a link...
	    for ($j = $i; $j < $len; $j ++)
	      if ($text[$j] == " " || $text[$j] == "\n" || $text[$j] == "\r" ||
	          $text[$j] == "\t" || $text[$j] == "\'" || $text[$j] == "'")
	        break;

            $count = $j - $i;
            $url   = substr($text, $i, $count);
	    $qtext .= "<a href='$url'>$url</a>";
	    $col   += $count;
	    $i     = $j - 1;
	    break;
	  }

      default :
          $col ++;
          $qtext .= $text[$i];
	  break;
    }
  }

  return ($qtext);
}


//
// 'sanitize_email()' - Convert an email address to something a SPAMbot
//                      can't read...
//

function				// O - Sanitized email
sanitize_email($email,			// I - Email address
               $html = 1)		// I - HTML format?
{
  $nemail = "";
  $len    = strlen($email);

  for ($i = 0; $i < $len; $i ++)
  {
    switch ($email[$i])
    {
      case '@' :
          if ($i > 0)
	    $i = $len;
          else if ($html)
            $nemail .= " <I>at</I> ";
	  else
            $nemail .= " at ";
	  break;

      case '<' :
          if ($i > 0)
	    $i = $len;
          break;

      case '>' :
          break;

      case '&' ;
          $nemail .= "&amp;";
	  break;

      default :
          $nemail .= $email[$i];
	  break;
    }
  }

  return (trim($nemail));
}


//
// 'sanitize_text()' - Sanitize text.
//

function				// O - Sanitized text
sanitize_text($text)			// I - Original text
{
  $len   = strlen($text);
  $word  = "";
  $qtext = "";

  for ($i = 0; $i < $len; $i ++)
  {
    switch ($text[$i])
    {
      case "\n" :
          if (!strncmp($word, "http://", 7) ||
	      !strncmp($word, "https://", 8) ||
	      !strncmp($word, "ftp://", 6))
            $qtext .= "<a href='$word'>$word</a>";
          else if (strchr($word, '@'))
            $qtext .= sanitize_email($word);
	  else
            $qtext .= quote_text($word);

          $qtext .= "<br />";
	  $word  = "";
	  break;

      case "\r" :
	  break;

      case "\t" :
      case " " :
          if (!strncmp($word, "http://", 7) ||
	      !strncmp($word, "https://", 8) ||
	      !strncmp($word, "ftp://", 6))
            $qtext .= "<a href='$word'>$word</a>";
          else if (strchr($word, '@'))
            $qtext .= sanitize_email($word);
	  else
            $qtext .= quote_text($word);

          if ($word)
            $qtext .= " ";
	  else
            $qtext .= "&nbsp;";

	  $word  = "";
	  break;

      default :
          $word .= $text[$i];
	  break;
    }
  }

  if (!strncmp($word, "http://", 7) ||
      !strncmp($word, "https://", 8) ||
      !strncmp($word, "ftp://", 6))
    $qtext .= "<a href='$word'>$word</a>";
  else if (strchr($word, '@'))
    $qtext .= sanitize_email($word);
  else
    $qtext .= quote_text($word);

  return ($qtext);
}


//
// 'select_is_published()' - Do a <select> for the "is published" field...
//

function
select_is_published($is_published = 1)	// I - Default state
{
  print("<select name='IS_PUBLISHED'>");
  if ($is_published)
  {
    print("<option value='0'>No</option>");
    print("<option value='1' selected>Yes</option>");
  }
  else
  {
    print("<option value='0' selected>No</option>");
    print("<option value='1'>Yes</option>");
  }
  print("</select>");
}


//
// 'show_comments()' - Show comments for the given path...
//

function				// O - Number of comments
show_comments($url,			// I - URL for comment
              $path = "",		// I - Path component
              $parent_id = 0,		// I - Parent comment
	      $heading = 3)		// I - Heading level
{
  global $_COOKIE;


  $result = db_query("SELECT * FROM comment WHERE "
                    ."url = '" . db_escape($url) ."' "
                    ."AND status > 0 AND parent_id = $parent_id "
		    ."ORDER BY id");

  if (array_key_exists("MODPOINTS", $_COOKIE))
    $modpoints = $_COOKIE["MODPOINTS"];
  else
    $modpoints = 5;

  if ($parent_id == 0 && $modpoints > 0)
    print("<P>You have $modpoints moderation points available.</P>\n");
  
  if ($heading > 6)
    $heading = 6;

  $safeurl      = urlencode($url);
  $num_comments = 0;

  while ($row = db_next($result))
  {
    if ($heading > 3 && $num_comments == 0)
      print("<div style='margin-left: 3em;'>\n");

    $num_comments ++;

    $create_date = date("M d, Y", $row['create_date']);
    $create_user = sanitize_email($row['create_user']);
    $contents    = sanitize_text($row['contents']);

    print("<h$heading>From $create_user on $create_date (score=$row[status])</h$heading>\n"
	 ."<p><tt>$contents</tt></p>\n");

    html_start_links();
    html_link("Reply", "${path}comment.php?r$row[id]+p$safeurl");

    if ($modpoints > 0)
    {
      if ($row['status'] > 0)
        html_link("Moderate Down", "${path}comment.php?md$row[id]+p$safeurl");

      if ($row['status'] < 5)
        html_link("Moderate Up", "${path}comment.php?mu$row[id]+p$safeurl");
    }

    html_end_links();

    $num_comments += show_comments($url, $path, $row['id'], $heading + 1);
  }

  db_free($result);

  if ($num_comments > 0 && $heading > 3)
    print("</div>\n");

  return ($num_comments);
}


//
// End of "$Id: common.php,v 1.5 2004/05/18 21:26:52 mike Exp $".
//
?>

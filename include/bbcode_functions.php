<?php

require_once 'emoticons.php';
require_once INCL_DIR . 'html_functions.php';
require_once INCL_DIR . 'user_functions.php';

/**
 * @param $smilies_set
 *
 * @return string
 */
function smilies_frame($smilies_set)
{
    global $site_config;

    $image = placeholder_image();
    $list = $emoticons = '';
    foreach ($smilies_set as $code => $url) {
        $list .= "
            <span class='margin10 mw-50 is-flex tooltipper' title='{$code}'>
                <span class='bordered bg-03'>
                    <a href='#' alt='{$code}'>
                        <img src='{$image}' data-src='{$site_config['pic_baseurl']}smilies/" . $url . "' alt='{$code}' class='lazy w-100'>
                    </a>
                </span>
            </span>";
    }

    $emoticons = "
        <div class='level-center emoticons'>
            $list
        </div>";

    return $emoticons;
}

/**
 * @param string $body
 *
 * @return string
 */
function BBcode($body = null, $class = null, $height = 600)
{
    $bbcode = "
            <textarea name='body' id='bbcode-editor' class='w-100 $class' style='height: {$height}px;'>$body</textarea>";

    return $bbcode;
}

/**
 * @param $html
 *
 * @return string
 */
function check_BBcode($html)
{
    preg_match_all('#<(?!img|br|hr\b)\b([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result);
    $openedtags = $result[1];
    preg_match_all('#</([a-z]+)>#iU', $html, $result);
    $closedtags = $result[1];
    $len_opened = count($openedtags);
    if (count($closedtags) == $len_opened) {
        return $html;
    }
    $openedtags = array_reverse($openedtags);
    for ($i = 0; $i < $len_opened; ++$i) {
        if (!in_array($openedtags[$i], $closedtags)) {
            $html .= '</' . $openedtags[$i] . '>';
        } else {
            unset($closedtags[array_search($openedtags[$i], $closedtags)]);
        }
    }

    return $html;
}

/**
 * @param $s
 *
 * @return mixed
 */
function format_quotes($s)
{
    preg_match_all('/\\[quote.*?\\]/', $s, $result, PREG_PATTERN_ORDER);
    $openquotecount = count($openquote = $result[0]);
    preg_match_all('/\\[\/quote\\]/', $s, $result, PREG_PATTERN_ORDER);
    $closequotecount = count($closequote = $result[0]);
    if ($openquotecount != $closequotecount) {
        return $s;
    } // quote mismatch. Return raw string...
    // Get position of opening quotes
    $openval = [];
    $pos = -1;
    foreach ($openquote as $val) {
        $openval[] = $pos = strpos($s, $val, $pos + 1);
    }
    // Get position of closing quotes
    $closeval = [];
    $pos = -1;
    foreach ($closequote as $val) {
        $closeval[] = $pos = strpos($s, $val, $pos + 1);
    }
    for ($i = 0; $i < count($openval); ++$i) {
        if ($openval[$i] > $closeval[$i]) {
            return $s;
        }
    } // Cannot close before opening. Return raw string...
    $s = str_replace('[quote]', "<div><b>Quote:</b><br><span class='quote'>", $s);
    $s = preg_replace('/\\[quote=(.+?)\\]/', "<div><b>\\1 wrote:</b><br><span class='quote'>", $s);
    $s = str_replace('[/quote]', '</span></div>', $s);

    return $s;
}

/**
 * @param $link
 *
 * @return string
 */
function islocal($link)
{
    global $site_config;
    $flag = false;
    $limit = 600;

    if (stristr($link[0], '[url=') !== false) {
        $url = trim($link[1]);
        $title = trim($link[2]);
        if (stristr($title, '[img]') !== false) {
            $flag = true;
            $title = preg_replace("/\[img](https?:\/\/[^\s'\"<>]+(\.(jpeg|jpg|gif|png)))\[\/img\]/i", '<img class="img-responsive" src="\\1" alt="" border="0">', $title);
        }
    } elseif (false !== stristr($link[0], '[url]')) {
        $url = $title = trim($link[1]);
    } else {
        $url = $title = trim($link[2]);
    }
    if (strlen($title) > $limit && $flag == false) {
        $l[0] = substr($title, 0, ($limit / 2));
        $l[1] = substr($title, strlen($title) - round($limit / 3));
        $lshort = $l[0] . '...' . $l[1];
    } else {
        $lshort = $title;
    }
    $url = htmlsafechars($url);

    return '<a href="' . $url . '" target="_blank">' . $lshort . '</a>';
}

/**
 * @param $s
 *
 * @return mixed
 */
function format_urls($s)
{
    return preg_replace_callback("/(\A|[^=\]'\"a-zA-Z0-9])((http|ftp|https|ftps|irc):\/\/[^<>\s]+)/i", 'islocal', $s);
}

/**
 * @param      $text
 * @param bool $strip_html
 * @param bool $urls
 * @param bool $images
 *
 * @return mixed|string
 */
function format_comment($text, $strip_html = true, $urls = true, $images = true)
{
    global $smilies, $staff_smilies, $customsmilies, $site_config, $CURUSER, $user_stuffs;

    $image = placeholder_image();
    $s = $text;
    unset($text);
    $site_config['url'] = str_replace([
        'http://',
        'www',
        'http://www',
        'https://',
        'https://www',
    ], '', $site_config['baseurl']);
    if (isset($_SERVER['HTTPS']) && (bool) $_SERVER['HTTPS'] === true) {
        $s = preg_replace('/http:\/\/((?:www\.)?' . $site_config['url'] . ')/i', 'https://$1', $s);
    } else {
        $s = preg_replace('/https:\/\/((?:www\.)?' . $site_config['url'] . ')/i', 'http://$1', $s);
    }
    // This fixes the extraneous ;) smilies problem. When there was an html escaped
    // char before a closing bracket - like >), "), ... - this would be encoded
    // to &xxx;), hence all the extra smilies. I created a new :wink: label, removed
    // the ;) one, and replace all genuine ;) by :wink: before escaping the body.
    // (What took us so long? :blush:)- wyz
    $s = str_replace(';)', ':wink:', $s);
    // fix messed up links
    $s = str_replace('&amp;', '&', $s);
    if ($strip_html) {
        $s = htmlsafechars($s, ENT_QUOTES, get_charset());
    }

    // BBCode to find...
    $bb_code_in = [
        '/\s*\[table\](.*?)\[\/table\]\n?/is',
        '/\s*\[tr\](.*?)\[\/tr\]\s*/is',
        '/\s*\[td\](.*?)\[\/td\]\s*/is',
        '/\s*\[th\](.*?)\[\/th\]\s*/is',
        '/\[sup\](.*?)\[\/sup\]/is',
        '/\[sub\](.*?)\[\/sub\]/is',
        '/\[b\](.*?)\[\/b\]/is',
        '/\[i\](.*?)\[\/i\]/is',
        '/\[u\](.*?)\[\/u\]/is',
        '/\[p\](.*?)\[\/p\]/is',
        '/\[email\](.*?)\[\/email\]/is',
        '/\[align=([a-zA-Z]+)\](.+?)\[\/align\]/is',
        '/\[center\](.*?)\[\/center\]/is',
        '/\[left\](.*?)\[\/left\]/is',
        '/\[right\](.*?)\[\/right\]/is',
        '/\[justify\](.*?)\[\/justify\]/is',
        '/\[blockquote\](.*?)\[\/blockquote\]/is',
        '/\[strike\](.*?)\[\/strike\]/is',
        '/\[s\](.*?)\[\/s\]/is',
        '/\[pre\](.*?)\[\/pre\]/is',
        '/\[marquee\](.*?)\[\/marquee\]/is',
        '/\[collapse=(.*?)\](.*?)\[\/collapse\]/is',
        '/\[size=([1-7])\](.*?)\[\/size\]/is',
        '/\[color=([a-zA-Z]+)\](.*?)\[\/color\]/is',
        '/\[color=(#[a-fA-F0-9]{3}|#[a-fA-F0-9]{6})\](.*?)\[\/color\]/is',
        '/\[font=([a-zA-Z ,]+)\](.*?)\[\/font\]/is',
        '/\[font01\](.*?)\[\/font01\]/is',
        '/\[font02\](.*?)\[\/font02\]/is',
        '/\[font03\](.*?)\[\/font03\]/is',
        '/\[font04\](.*?)\[\/font04\]/is',
        '/\[font05\](.*?)\[\/font05\]/is',
        '/\[font06\](.*?)\[\/font06\]/is',
        '/\[font07\](.*?)\[\/font07\]/is',
        '/\[font08\](.*?)\[\/font08\]/is',
        '/\[size=(\d+)\](.*?)\[\/size\]/is',
        '/\[spoiler\](.*?)\[\/spoiler\]/is',
        '/\[hide\](.*?)\[\/hide\]/is',
        '/\[youtube=[^\s\'"<>]*youtube.com.*v=([^\s\'"<>]+)\]/imsU',
        '/\[video=[^\s\'"<>]*youtube.com.*v=([^\s\'"<>]+)\]/imsU',
        "/\[video=[^\s'\"<>]*video.google.com.*docid=(-?[0-9]+).*\]/imsU",
        '/\[video=https?:\/\/i\.imgur\.com\/(.*)\.gifv\]/imsU',
        '/\[video=https?:(\/\/.*\.mp4)\]/imsU',
        '/\[video=https?:(\/\/.*\.ogg)\]/imsU',
        '/\[video=https?:(\/\/.*\.webm)\]/imsU',
        '/\[loop=https?:(\/\/.*\.mp4)\]/imsU',
        '/\[loop=https?:(\/\/.*\.webm)\]/imsU',
        '/\[loop=https?:(\/\/.*\.ogv)\]/imsU',
        '/\[audio\](https?:\/\/[^\s\'"<>]+(\.(mp3|aiff|wav)))\[\/audio\]/is',
        '/\[list=([0-9]+)\](.*?)\[\/list\]/is',
        '/\[list\](.*?)\[\/list\]/is',
        '/\[li\]\s?(.*?)\[\/li\]/is',
        '/\[\*\]\s?(.*?)\n/is',
        '/\[li\]\s?(.*?)\n/is',
        '/\[hr\]/',
        '/\[h1\]\s?(.*?)\[\/h1\]/is',
        '/\[h2\]\s?(.*?)\[\/h2\]/is',
        '/\[h3\]\s?(.*?)\[\/h3\]/is',
        '/\[h4\]\s?(.*?)\[\/h4\]/is',
        '/\[class=(.*?)\](.*?)\[\/class\]/is',
        '/\[br\]/is',
    ];
    // And replace them by...
    $bb_code_out = [
        '<table class="table table-bordered table-striped">\1</table>',
        '<tr>\1</tr>',
        '<td>\1</td>',
        '<th>\1</th>',
        '<sup>\1</sup>',
        '<sub>\1</sub>',
        '<span class="has-text-weight-bold">\1</span>',
        '<em>\1</em>',
        '<u>\1</u>',
        '<p>\1</p>',
        '<a class="altlink" href="mailto:\1">\1</a>',
        '<div style="text-align: \1;">\2</div>',
        '<div class="has-text-centered">\1</div>',
        '<div class="has-text-left">\1</div>',
        '<div class="has-text-right">\1</div>',
        '<div class="text-justify">\1</div>',
        '<blockquote class="style"><span>\1</span></blockquote>',
        '<s>\1</s>',
        '<s>\1</s>',
        '<span class="pre">\1</span>',
        '<marquee class="style">\1</marquee>',
        '<div style="padding-top: 2px; white-space: nowrap;"><span style="cursor: pointer; border-bottom: 1px dotted;" onclick="if (document.getElementById(\'collapseobj\1\').style.display==\'block\') {document.getElementById(\'collapseobj\1\').style.display=\'none\' } else { document.getElementById(\'collapseobj\1\').style.display=\'block\' }">\1</span></div><div id="collapseobj\1" style="display:none; padding-top: 2px; padding-left: 14px; margin-bottom:10px; padding-bottom: 2px; background-color: #FEFEF4;">\2</div>',
        '<span class="size_\1">\2</span>',
        '<span style="color: \1;">\2</span>',
        '<span style="color: \1;">\2</span>',
        '<span style="font-family:\'\1\';">\2</span>',
        '<span class="text-1">\1</span>',
        '<span class="text-2">\1</span>',
        '<span class="text-3">\1</span>',
        '<span class="text-4">\1</span>',
        '<span class="text-5">\1</span>',
        '<span class="text-6">\1</span>',
        '<span class="text-7">\1</span>',
        '<span class="text-8">\1</span>',
        '<span class="text-\1">\2</span>',
        "<div style='margin-bottom: 5px;'><span class='flip button is-small'>Show Spoiler!</span><div class='panel spoiler' style='display:none;'>\\1</div></div><br>",
        "<div style='margin-bottom: 5px;'><span class='flip button is-small'>Show Hide!</span><div class='panel spoiler' style='display:none;'>\\1</div></div><br>",
        "<div class='responsive-container'><iframe width='1920px' height='1080px' src='{$image}' data-src='//youtube.com/embed/\\1?vq=hd1080&wmode=opaque' autoplay='false' frameborder='0' allowfullscreen class='lazy'></iframe></div>",
        "<div class='responsive-container'><iframe width='1920px' height='1080px' src='{$image}' data-src='//youtube.com/embed/\\1?vq=hd1080&wmode=opaque' autoplay='false' frameborder='0' allowfullscreen class='lazy'></iframe></div>",
        '<embed style="width:500px; height:410px;" id="VideoPlayback" align="middle" type="application/x-shockwave-flash" src="//video.google.com/googleplayer.swf?docId=\\1" allowScriptAccess="sameDomain" quality="best" bgcolor="#fff" scale="noScale" wmode="window" salign="TL"  FlashVars="playerMode=embedded"> </embed>',
        '<span><video width="500" loop muted autoplay><source src="//i.imgur.com/\1.webm" type="video/webm"><source src="//i.imgur.com/\1.mp4" type="video/mp4">Your browser does not support the video tag.</video></span>',
        '<span><video width="500" controls preload="none"><source src="\1"><source src="\1" type="video/mp4">Your browser does not support the video tag.</video></span>',
        '<span><video width="500" controls preload="none"><source src="\1"><source src="\1" type="video/ogg">Your browser does not support the video tag.</video></span>',
        '<span><video width="500" controls preload="none"><source src="\1"><source src="\1" type="video/webm">Your browser does not support the video tag.</video></span>',
        '<span><video width="500" loop muted autoplay><source src="\1"><source src="\1" type="video/mp4">Your browser does not support the video tag.</video></span>',
        '<span><video width="500" loop muted autoplay><source src="\1"><source src="\1" type="video/webm">Your browser does not support the video tag.</video></span>',
        '<span><video width="500" loop muted autoplay><source src="\1"><source src="\1" type="video/ogv">Your browser does not support the video tag.</video></span>',
        '<span class="has-text-centered"><p>Audio From: \1</p><embed type="application/x-shockwave-flash" src="https://www.google.com/reader/ui/3247397568-audio-player.swf?audioUrl=\\1" width="400" height="27" allowscriptaccess="never" quality="best" bgcolor="#fff" wmode="window" flashvars="playerMode=embedded"></span>',
        '<ol class="style" start="\1">\2</ol>',
        '<ul class="style">\1</ul>',
        '<li>\1</li>',
        '<li>\1</li>',
        '<li>\1</li>',
        '<hr>',
        '<h1>\1</h1>',
        '<h2>\1</h2>',
        '<h3>\1</h3>',
        '<h4>\1</h4>',
        '<span class="\1">\2</span>',
        '<br>',
    ];
    $s = preg_replace($bb_code_in, $bb_code_out, $s);

    if (preg_match("#function\s*\((.*?)\|\|#is", $s)) {
        $s = str_replace(':', '&#58;', $s);
        $s = str_replace('[', '&#91;', $s);
        $s = str_replace(']', '&#93;', $s);
        $s = str_replace(')', '&#41;', $s);
        $s = str_replace('(', '&#40;', $s);
        $s = str_replace('{', '&#123;', $s);
        $s = str_replace('}', '&#125;', $s);
        $s = str_replace('$', '&#36;', $s);
        $s = str_replace('&nbsp;', '&#160;', $s);
    }

    // find username tags
    preg_match_all('/@(.+\b)/imsU', $s, $match);
    foreach ($match[1] as $tmp) {
        $userid = $user_stuffs->getUserIdFromName($tmp);
        if ($userid) {
            $username = format_username($userid, false, true, true);
            $s = preg_replace("/@$tmp/", $username . ' ', $s);
        }
    }

    // find timpstamps replace with dates
    preg_match_all('/key\s*=\s*(\d{10})/', $s, $match);
    foreach ($match[1] as $tmp) {
        $s = str_replace($tmp, get_date($tmp, ''), $s);
    }

    if ($urls) {
        $s = format_urls($s);
    }
    if (stripos($s, '[url') !== false && $urls) {
        $s = preg_replace_callback("/\[url=([^()<>\s]+?)\](.+?)\[\/url\]/is", 'islocal', $s);
        // [url]http://www.example.com[/url]
        $s = preg_replace_callback("/\[url\]([^()<>\s]+?)\[\/url\]/is", 'islocal', $s);
    }

    // Dynamic Vars
    $s = dynamic_user_vars($s);

    // [pre]Preformatted[/pre]
    if (stripos($s, '[pre]') !== false) {
        $s = preg_replace("/\[pre\]((\s|.)+?)\[\/pre\]/i", '<tt><span style="white-space: nowrap;">\\1</span></tt>', $s);
    }
    // [nfo]NFO-preformatted[/nfo]
    if (stripos($s, '[nfo]') !== false) {
        $s = preg_replace("/\[nfo\]((\s|.)+?)\[\/nfo\]/i", "<tt><span style=\"white-space: nowrap;\"><font face='MS Linedraw' size='2' style='font-size: 10pt; line-height: 10pt;'>\\1</font></span></tt>", $s);
    }
    //==Media tag
    if (stripos($s, '[media=') !== false) {
        $s = preg_replace("#\[media=(youtube|liveleak|GameTrailers|vimeo|imdb)\](.+?)\[/media\]#ies", "_MediaTag('\\2','\\1')", $s);
    }
    if (stripos($s, '[img') !== false && $images) {
        // img with width and height
        $s = preg_replace("/\[img=(\d+)x(\d+)](https?:\/\/[^[^\s'\"<>]*)\[\/img\]/i", '<a href="\\3" data-lightbox="details"><img src="\\3" alt="" width="\\1" height="\\2" class="img-responsive"></a>', $s);
        // [img=image services with or without extension
        $s = preg_replace("/\[img=(https?:\/\/[^[^\s'\"<>]*)\]/i", '<a href="\\1" data-lightbox="details"><img src="\\1" alt="" class="img-responsive"></a>', $s);
        // [img]image services with or without extension
        $s = preg_replace("/\[img\](https?:\/\/[^[^\s'\"<>]*)\[\/img\]/i", '<a href="\\1" data-lightbox="details"><img src="\\1" alt="" class="img-responsive"></a>', $s);

        preg_match_all('/<img.*?src=["|\'](.*?)["|\'](.*?)>/s', $s, $matches);
        $i = 0;
        foreach ($matches[1] as $match) {
            preg_match('/width=[\'|"](\d+)[\'|"] height=[\'|"](\d+)[\'|"]/', $matches[2][$i++], $dimensions);
            $width = !empty($dimensions[1]) ? $dimensions[1] : null;
            $height = !empty($dimensions[2]) ? $dimensions[2] : null;
            $s = str_replace($match, url_proxy($match, true, $width, $height), $s);
        }

        // [img] proxied local images
        $s = preg_replace("#\[img\](.*" . preg_quote($site_config['pic_baseurl']) . "proxy/.*)\[/img\]#i", '<img src="' . $image . '" data-src="\\1" alt="" class="lazy"></a>', $s);

        // [img] local images
        $s = preg_replace("#\[img\](.*" . preg_quote($site_config['pic_baseurl']) . ".*)\[/img\]#i", '<img src="' . $image . '" data-src="\\1" alt="" class="lazy emoticon is-2x"></a>', $s);
    }
    // [mcom]Text[/mcom]
    if (stripos($s, '[mcom]') !== false) {
        $s = preg_replace("/\[mcom\](.+?)\[\/mcom\]/is", '<div style="font-size: 18pt; line-height: 50%;">
   <div style="border-color: red; background-color: red; color: #fff; text-align: center; font-weight: bold; font-size: large;"><b>\\1</b></div></div>', $s);
    }
    // the [you] tag
    if (stripos($s, '[you]') !== false) {
        $s = preg_replace("/https?:\/\/[^\s'\"<>]*\[you\][^\s'\"<>]*/i", ' ', $s);
        $s = preg_replace("/\[you\]/i", $CURUSER['username'], $s);
    }

    // the [username] tag
    if (stripos($s, '[username]') !== false) {
        $s = preg_replace("/https?:\/\/[^\s'\"<>]*\[username\][^\s'\"<>]*/i", ' ', $s);
        $s = preg_replace("/\[username\]/i", $CURUSER['username'], $s);
    }

    // Maintain spacing
    $s = str_replace('  ', '&#160;&#160;', $s);
    if (isset($smilies)) {
        foreach ($smilies as $code => $url) {
            $s = str_replace($code, "<img src='{$image}' data-src='{$site_config['pic_baseurl']}smilies/{$url}' alt='' class='lazy'>", $s);
        }
    }
    if (isset($staff_smilies)) {
        foreach ($staff_smilies as $code => $url) {
            $s = str_replace($code, "<img src='{$image}' data-src='{$site_config['pic_baseurl']}smilies/{$url}' alt='' class='lazy'>", $s);
        }
    }
    if (isset($customsmilies)) {
        foreach ($customsmilies as $code => $url) {
            $s = str_replace($code, "<img src='{$image}' data-src='{$site_config['pic_baseurl']}smilies/{$url}' alt='' class='lazy'>", $s);
        }
    }

    $s = format_quotes($s);
    $s = format_code($s);
    $s = check_BBcode($s);
    $s = str_replace([
        "\r\n",
        "\r",
        "\n",
        '&lt;br&gt;',
    ], '<br>', $s);

    return $s;
}

/**
 * @param $s
 *
 * @return mixed
 */
function format_code($s)
{
    if (preg_match('/\[code\]/', $s)) {
        preg_match_all('/\\[code.*?\\]/', $s, $result, PREG_PATTERN_ORDER);
        $openquotecount = count($openquote = $result[0]);
        preg_match_all('/\\[\/code\\]/', $s, $result, PREG_PATTERN_ORDER);
        $closequotecount = count($closequote = $result[0]);
        if ($openquotecount != $closequotecount) {
            return $s;
        } // quote mismatch. Return raw string...
        // Get position of opening quotes
        $openval = [];
        $pos = -1;
        foreach ($openquote as $val) {
            $openval[] = $pos = strpos($s, $val, $pos + 1);
        }
        // Get position of closing quotes
        $closeval = [];
        $pos = -1;
        foreach ($closequote as $val) {
            $closeval[] = $pos = strpos($s, $val, $pos + 1);
        }
        for ($i = 0; $i < count($openval); ++$i) {
            if ($openval[$i] > $closeval[$i]) {
                return $s;
            }
        }
        $s = str_replace('[code]', "<div><div class='size_3 has-text-weight-bold bottom10'>code:</div><div class='round10 padding10 bg-light'><code>", htmlspecialchars($s));
        $s = str_replace('[/code]', '</code></div></div>', $s);
        $s = html_entity_decode($s);
    }

    return $s;
}

//=== no bb code in post
/**
 * @param      $text
 * @param bool $strip_html
 *
 * @return mixed|string
 */
function format_comment_no_bbcode($text, $strip_html = true)
{
    global $site_config;
    $s = htmlspecialchars($text);
    if ($strip_html) {
        $s = htmlsafechars($s, ENT_QUOTES, get_charset());
    }
    // BBCode to find...
    //=== basically will change this into a sort of strip tags but of bbcode shor of the code tag
    $bb_code_in = [
        '/\[b\]\s*((\s|.)+?)\s*\[\/b\]/i',
        '/\[i\]\s*((\s|.)+?)\s*\[\/i\]/i',
        '/\[u\]\s*((\s|.)+?)\s*\[\/u\]/i',
        '#\[img\](.+?)\[/img\]#i',
        '#\[img=(.+?)\]#i',
        '/\[email\](.*?)\[\/email\]/i',
        '/\[align=([a-zA-Z]+)\]((\s|.)+?)\[\/align\]/i',
        '/\[blockquote\]\s*((\s|.)+?)\s*\[\/blockquote\]/i',
        '/\[strike\]\s*((\s|.)+?)\s*\[\/strike\]/i',
        '/\[s\]\s*((\s|.)+?)\s*\[\/s\]/i',
        '/\[pre\]\s*((\s|.)+?)\s*\[\/pre\]/i',
        '/\[marquee\](.*?)\[\/marquee\]/i',
        '/\[url\="?(.*?)"?\]\s*((\s|.)+?)\s*\[\/url\]/i',
        '/\[url\]\s*((\s|.)+?)\s*\[\/url\]/i',
        '/\[collapse=(.*?)\]\s*((\s|.)+?)\s*\[\/collapse\]/i',
        '/\[size=([1-7])\]\s*((\s|.)+?)\s*\[\/size\]/i',
        '/\[color=([a-zA-Z]+)\]\s*((\s|.)+?)\s*\[\/color\]/i',
        '/\[color=(#[a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9][a-f0-9])\]\s*((\s|.)+?)\s*\[\/color\]/i',
        '/\[font=([a-zA-Z ,]+)\]((\s|.)+?)\[\/font\]/i',
        '/\[quote\]\s*((\s|.)+?)\s*\[\/quote\]\s*/i',
        '/\[quote=(.+?)\]\s*((\s|.)+?)\s*\[\/quote\]\s*/i',
        '/\[spoiler\]\s*((\s|.)+?)\s*\[\/spoiler\]\s*/i',
        '/\[hide\]\s*((\s|.)+?)\s*\[\/hide\]\s*/i',
        '/\[video=[^\s\'"<>]*youtube.com.*v=([^\s\'"<>]+)\]/ims',
        "/\[video=[^\s'\"<>]*video.google.com.*docid=(-?[0-9]+).*\]/ims",
        '/\[video=https?:\/\/i\.imgur\.com\/(.*)\.gifv\]/ims',
        '/\[video=https?:(\/\/.*\.mp4)\]/ims',
        '/\[video=https?:(\/\/.*\.ogg)\]/ims',
        '/\[video=https?:(\/\/.*\.webm)\]/ims',
        '/\[loop=https?:(\/\/.*\.mp4)\]/ims',
        '/\[audio\](https?:\/\/[^\s\'"<>]+(\.(mp3|aiff|wav)))\[\/audio\]/i',
        '/\[list=([0-9]+)\]((\s|.)+?)\[\/list\]/i',
        '/\[list\]((\s|.)+?)\[\/list\]/i',
        '/\[\*\]\s?(.*?)\n/i',
        '/\[hr\]\s?(.*?)\n/i',
    ];
    // And replace them by...

    $bb_code_out = [
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\2',
        '\2',
        '\2',
        '\2',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
        '\1',
    ];
    $s = preg_replace($bb_code_in, $bb_code_out, $s);

    // replace timestamps with dates
    preg_match_all('/key\s*=\s*(\d+)/', $s, $match);
    foreach ($match[1] as $tmp) {
        $s = str_replace($tmp, get_date($tmp, ''), $s);
    }

    // Linebreaks
    $s = nl2br($s);
    // Maintain spacing
    $s = str_replace('  ', '&#160;', $s);

    return $s;
}

/**
 * @param $content
 * @param $type
 *
 * @return mixed|string|void
 */
function _MediaTag($content, $type)
{
    global $site_config;
    if ($content == '' || $type == '') {
        return;
    }
    $return = '';
    switch ($type) {
        case 'youtube':
            $return = preg_replace("#^https?://(?:|www\.)youtube\.com/watch\?v=([a-zA-Z0-9\-]+)+?$#i", "<object type='application/x-shockwave-flash' height='355' width='425' data='https://www.youtube.com/v/\\1'><param name='movie' value='https://www.youtube.com/v/\\1'><param name='allowScriptAccess' value='sameDomain'><param name='quality' value='best'><param name='bgcolor' value='#fff'><param name='scale' value='noScale'><param name='salign' value='TL'><param name='FlashVars' value='playerMode=embedded'><param name='wmode' value='transparent'></object>", $content);
            break;

        case 'liveleak':
            $return = preg_replace("#^https?://(?:|www\.)liveleak\.com/view\?i=([_a-zA-Z0-9\-]+)+?$#i", "<object type='application/x-shockwave-flash' height='355' width='425' data='https://www.liveleak.com/e/\\1'><param name='movie' value='https://www.liveleak.com/e/\\1'><param name='allowScriptAccess' value='sameDomain'><param name='quality' value='best'><param name='bgcolor' value='#fff'><param name='scale' value='noScale'><param name='salign' value='TL'><param name='FlashVars' value='playerMode=embedded'><param name='wmode' value='transparent'></object>", $content);
            break;

        case 'GameTrailers':
            $return = preg_replace("#^https?://(?:|www\.)gametrailers\.com/video/([\-_a-zA-Z0-9\-]+)+?/([0-9]+)+?$#i", "<object type='application/x-shockwave-flash' height='355' width='425' data='https://www.gametrailers.com/remote_wrap.php?mid=\\2'><param name='movie' value='https://www.gametrailers.com/remote_wrap.php?mid=\\2'><param name='allowScriptAccess' value='sameDomain'> <param name='allowFullScreen' value='true'><param name='quality' value='high'></object>", $content);
            break;

        case 'imdb':
            $return = preg_replace("#^https?://(?:|www\.)imdb\.com/video/screenplay/([_a-zA-Z0-9\-]+)+?$#i", "<div class='\\1'><div style=\"padding: 3px; background-color: transparent; border: none; width:690px;\"><div style=\"text-transform: uppercase; border-bottom: 1px solid #CCCCCC; margin-bottom: 3px; font-size: 0.8em; font-weight: bold; display: block;\"><span onclick=\"if (this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display != '') { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = ''; this.innerHTML = '<b>Imdb Trailer: </b><a href=\'#\' onclick=\'return false;\'>hide</a>'; } else { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = 'none'; this.innerHTML = '<b>Imdb Trailer: </b><a href=\'#\' onclick=\'return false;\'>show</a>'; }\" ><b>Imdb Trailer: </b><a href=\"#\" onclick=\"return false;\">show</a></span></div><div class=\"quotecontent\"><div style=\"display: none;\"><iframe style='vertical-align: middle;' src='https://www.imdb.com/video/screenplay/\\1/player' scrolling='no' width='660' height='490' frameborder='0'></iframe></div></div></div></div>", $content);
            break;

        case 'vimeo':
            $return = preg_replace("#^https?://(?:|www\.)vimeo\.com/([0-9]+)+?$#i", "<object type='application/x-shockwave-flash' width='425' height='355' data='https://vimeo.com/moogaloop.swf?clip_id=\\1&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=&amp;fullscreen=1'>
    <param name='allowFullScreen' value='true'>
    <param name='allowScriptAccess' value='sameDomain'>
    <param name='movie' value='https://vimeo.com/moogaloop.swf?clip_id=\\1&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=&amp;fullscreen=1'>
    <param name='quality' value='high'>
    </object>", $content);
            break;

        default:
            $return = 'not found';
    }

    return $return;
}

//=== smilie function
/**
 * @return mixed
 */
function get_smile()
{
    global $CURUSER;

    return $CURUSER['smile_until'];
}

/**
 * @param $key
 *
 * @return string
 */
function user_key_codes($key)
{
    return "/\[$key\]/i";
}

/**
 * @param $text
 *
 * @return mixed|void
 */
function dynamic_user_vars($text)
{
    global $CURUSER;
    if (!isset($CURUSER)) {
        return $text;
    }
    $zone = 0; // UTC
    //$zone = 3600 * -5; // EST
    $tim = TIME_NOW + $zone;
    $cu = $CURUSER;
    unset($cu['passhash'], $cu['torrent_pass'], $cu['modcomment']);
    $bbkeys = array_keys($cu);
    $bbkeys[] = 'curdate';
    $bbkeys[] = 'curtime';
    $bbvals = array_values($cu);
    $bbvals[] = gmdate('F jS, Y', $tim);
    $bbvals[] = gmdate('g:i A', $tim);
    $bbkeys = array_map('user_key_codes', $bbkeys);

    return @preg_replace($bbkeys, $bbvals, $text);
}

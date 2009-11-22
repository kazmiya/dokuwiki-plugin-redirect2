<?php
/**
 * Japanese language file for redirect2 plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Kazutaka Miyasaka <kazmiya@gmail.com>
 */

$lang['redirect_rules'] = '転送ルール</label>
<span class="redirect2_desc">1 行 1 ルールです。ルールは半角の空白文字で区切られた「転送元」と「転送先」から構成されます。より詳しい書式と設定例については、<a href="http://www.dokuwiki.org/plugin:redirect2" class="interwiki iw_doku">plugin:redirect2</a> を参照してください。</span>
<pre class="code redirect2_example">
<span class="co1"># 設定例</span>
oldpage   newpage
old:ns:*  new:ns:*
internal  http://example.com/external.html
dw:*      http://www.dokuwiki.org/*
</pre>
<label for="config___plugin____redirect2____redirect_rules">';

$lang['show_messages'] = 'サイト内のページへの転送時にメッセージを表示する';

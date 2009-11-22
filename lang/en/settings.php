<?php
/**
 * English language file for redirect2 plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Kazutaka Miyasaka <kazmiya@gmail.com>
 */

$lang['redirect_rules'] = 'Redirect rules</label>
<span class="redirect2_desc">Each line corresponds to one rule. A redirect rule consists of a pair of &quot;from&quot; and &quot;to&quot; separated by whitespaces. See <a href="http://www.dokuwiki.org/plugin:redirect2" class="interwiki iw_doku">plugin:redirect2</a> for more detailed syntax and examples.</span>
<pre class="code redirect2_example">
<span class="co1"># Examples</span>
oldpage   newpage
old:ns:*  new:ns:*
internal  http://example.com/external.html
dw:*      http://www.dokuwiki.org/*
</pre>
<label for="config___plugin____redirect2____redirect_rules">';

$lang['show_messages'] = 'Show messages on internal redirections';

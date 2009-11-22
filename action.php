<?php
/**
 * DokuWiki Plugin Redirect2 (Action)
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Kazutaka Miyasaka <kazmiya@gmail.com>
 */

// must be run within DokuWiki
if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'action.php');

class action_plugin_redirect2 extends DokuWiki_Action_Plugin {
    var $regexp_external = '/^https?:\/\/.*$/';

    /**
     * Returns some info
     */
    function getInfo() {
        return array(
            'author' => 'Kazutaka Miyasaka',
            'email'  => 'kazmiya@gmail.com',
            'date'   => '2009-11-22',
            'name'   => 'Redirect2 Plugin',
            'desc'   => 'Provides various types of page redirections based on a central redirection list.',
            'url'    => 'http://www.dokuwiki.org/plugin:redirect2'
        );
    }

    /**
     * Registers an event handler
     */
    function register(&$controller) {
        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, 'handleRedirect', array());
    }

    /**
     * Handles redirections
     */
    function handleRedirect(&$event, $param) {
        global $ID;
        global $ACT;

        if (headers_sent()) return;
        if ($ACT != 'show') return;
        if ($_REQUEST['noredirect']) return;
        if (!$rules = $this->getRedirectRules()) return;

        // page redirection
        if ($to = $rules['page'][$ID]) {
            if ($to === '[NOREDIRECT]') return;
            $this->redirectPage($to);
        }

        if (!$rules['ns']) return;

        // sub namespace redirection (longest match)
        $ns = getNS($ID);
        while ($ns !== false) { // you may have namespaces like "0"
            if (array_key_exists($ns, $rules['ns'])) {
                if ($rules['ns'][$ns] === '[NOREDIRECT]') return;
                $relpath = substr($ID, strlen($ns.':'));
                $this->redirectNS($rules['ns'][$ns], $relpath);
            }
            $ns = getNS($ns);
        }

        // root namespace redirection
        if (array_key_exists('*', $rules['ns'])) {
            if ($rules['ns']['*'] === '[NOREDIRECT]') return;
            $this->redirectNS($rules['ns']['*'], $ID);
        }
    }

    /**
     * Builds a hash from the "redirect_rules" configuration setting
     * (based on confToHash() and linesToHash(), inc/confutils.php)
     */
    function getRedirectRules() {
        global $conf;

        $lines = preg_split('/\r\n|\r|\n/', $this->getConf('redirect_rules'));
        if (!$lines) return false;

        $rules = array('page' => array(), 'ns' => array());

        foreach ($lines as $line) {
            // ignore comments (except escaped ones)
            $line = preg_replace('/(?<![&\\\\])#.*$/', '', $line);
            $line = str_replace('\\#', '#', $line);
            $line = trim($line);
            if (empty($line)) continue;

            list($from, $to) = preg_split('/\s+/', $line, 2);
            if (!isset($to)) continue;

            // build from-page => to-page/url array with some adjustments
            $from = utf8_strtolower($from);
            if ($conf['useslash'])          $from = strtr($from, '/', ':');
            if ($from === ':')              $from = $conf['start'];
            if (strpos($from, ':') === 0)   $from = substr($from, 1);
            if (!preg_match($this->regexp_external, $to)) {
                if ($conf['useslash'])      $to   = strtr($to, '/', ':');
                if ($to === ':')            $to   = $conf['start'];
                if (strpos($to, ':') === 0) $to   = substr($to, 1);
            }
            $rules['page'][$from] = $to;

            // build from-namespace => to-page/namespace/url array
            if ($from === '*') {
                $rules['ns']['*'] = $to;
            } elseif (substr($from, -2) === ':*') {
                $rules['ns'][substr($from, 0, -2)] = $to;
            }
        }
        return $rules['page'] ? $rules : false;
    }

    /**
     * Handles page redirections
     */
    function redirectPage($to) {
        global $conf;

        // redirect type
        if (strpos($to, '[PERMANENT]') !== false) {
            $permanent = 1;
            $to = trim(str_replace('[PERMANENT]', '', $to));
        }

        if (preg_match($this->regexp_external, $to)) {
            $this->redirectTo($to, $permanent);
        } else {
            $this->redirectTo(wl($to, '', true), $permanent, 'internal');
        }
    }

    /**
     * Handles namespace redirections
     */
    function redirectNS($to, $relpath) {
        global $conf;

        // redirect type
        if (strpos($to, '[PERMANENT]') !== false) {
            $permanent = 1;
            $to = trim(str_replace('[PERMANENT]', '', $to));
        }

        // if the last letter of $to is "*", replace it with relative path
        $to = preg_replace('/\*$/', '[RELPATH]', $to);

        if (preg_match($this->regexp_external, $to)) {
            $path_elem = array_map('urlencode', explode(':', $relpath));
        } else {
            $path_elem = explode(':', $relpath);
        }

        // set replacement variables
        $vars = array();
        $vars['[PAGE]']     = array_pop($path_elem);
        if (count($path_elem)) {
            $vars['[NSONLY:]'] = implode(':', $path_elem);
            $vars['[NSONLY/]'] = implode('/', $path_elem);
            $vars['[NS:]']     = $vars['[NSONLY:]'].':';
            $vars['[NS/]']     = $vars['[NSONLY/]'].'/';
        } else {
            $vars['[NSONLY:]'] = '';
            $vars['[NSONLY/]'] = '';
            $vars['[NS:]']     = '';
            $vars['[NS/]']     = '';
        }
        $vars['[NSONLY]']   = $conf['useslash'] ? $vars['[NSONLY/]'] : $vars['[NSONLY:]'];
        $vars['[NS]']       = $conf['useslash'] ? $vars['[NS/]'] : $vars['[NS:]'];
        $vars['[RELPATH:]'] = $vars['[NS:]'].$vars['[PAGE]'];
        $vars['[RELPATH/]'] = $vars['[NS/]'].$vars['[PAGE]'];
        $vars['[RELPATH]']  = $vars['[NS]'].$vars['[PAGE]'];

        // replace variables and redirect
        if (preg_match($this->regexp_external, $to)) {
            $to = str_replace(array_keys($vars), array_values($vars), $to);
            $this->redirectTo($to, $permanent);
        } else {
            $vars['[NS]']      = $vars['[NS:]'];
            $vars['[RELPATH]'] = $vars['[RELPATH:]'];
            $to = str_replace(array_keys($vars), array_values($vars), $to);
            $this->redirectTo(wl($to, '', true), $permanent, 'internal');
        }
    }

    /**
     * Sends HTTP redirect with nice message
     */
    function redirectTo($to, $permanent = 0, $internal = 0) {
        global $ID;
        global $MSG;

        if ($internal && $this->getConf('show_messages')) {
            @session_start();
            if (isset($MSG) && count($MSG)) {
                $_SESSION[DOKU_COOKIE]['msg'] = $MSG;
            }
            $_SESSION[DOKU_COOKIE]['msg'][] = array(
                'lvl' => 'redirect2_redirected success',
                'msg' => sprintf(
                    hsc($this->getLang('redirected_from')),
                    '<a href="'.wl($ID, 'noredirect=1', true).'" rel="nofollow">'.$ID.'</a>'
                )
            );
        }

        session_write_close();

        // send redirect header and exit
        // (copied from send_redirect(), inc/common.php)
        if (isset($_SERVER['SERVER_SOFTWARE']) && isset($_SERVER['GATEWAY_INTERFACE']) &&
            (strpos($_SERVER['GATEWAY_INTERFACE'], 'CGI') !== false) &&
            (preg_match('|^Microsoft-IIS/(\d)\.\d$|', trim($_SERVER['SERVER_SOFTWARE']), $matches)) &&
            $matches[1] < 6) {
            header('Refresh: 0;url='.$to);
        } elseif ($permanent) {
            header('Location: '.$to, true, 301);
        } else {
            header('Location: '.$to, true, 302);
        }
        exit;
    }
}

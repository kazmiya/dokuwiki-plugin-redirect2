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
            'date'   => '2009-11-23',
            'name'   => 'Redirect2 Plugin',
            'desc'   => 'Provides various types of page redirections based on a central redirection list',
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
        global $ACT;
        global $INFO;

        if (headers_sent()) return;
        if ($ACT != 'show') return;
        if ($_REQUEST['noredirect']) return;
        if (!$rules = $this->getRedirectRules()) return;

        $id = $INFO['id'];

        // page redirection
        if (array_key_exists($id, $rules['page'])) {
            $to = $rules['page'][$id];
            if (strpos($to, '[noredirect]') !== false) return;
            $this->redirectPage($to);
        }

        if (!$rules['ns']) return;

        // sub namespace redirection (longest match)
        $ns = $INFO['namespace'];
        while ($ns !== false) { // you may have namespaces like "0"
            if (array_key_exists($ns, $rules['ns'])) {
                $to = $rules['ns'][$ns];
                if (strpos($to, '[noredirect]') !== false) return;
                $relpath = substr($id, strlen($ns.':'));
                $this->redirectNS($to, $relpath);
            }
            $ns = getNS($ns);
        }

        // root namespace redirection
        if (array_key_exists('*', $rules['ns'])) {
            $to = $rules['ns']['*'];
            if (strpos($to, '[noredirect]') !== false) return;
            $this->redirectNS($to, $id);
        }
    }

    /**
     * Builds a hash from the "redirect_rules" configuration setting
     * (based on confToHash() and linesToHash(), inc/confutils.php)
     */
    function getRedirectRules() {
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
            $from = $this->cleanIDSimple($from);
            if (!preg_match($this->regexp_external, $to)) {
                $to = $this->cleanIDSimple($to);
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
     * Cleans given pageID-like strings by simple way
     */
    function cleanIDSimple($id) {
        global $conf;

        $id = utf8_strtolower($id);
        if ($conf['useslash'])      $id = strtr($id, '/', ':');
        if ($id === ':')            $id = $conf['start'];
        if (strpos($id, ':') === 0) $id = substr($to, 1);
        return $id;
    }

    /**
     * Handles page redirections
     */
    function redirectPage($to) {
        // handle redirect type
        if (strpos($to, '[permanent]') !== false) {
            $permanent = 1;
            $to = trim(str_replace('[permanent]', '', $to));
        }

        // redirect
        $this->redirectTo($to, $permanent);
    }

    /**
     * Handles namespace redirections
     */
    function redirectNS($to, $relpath) {
        // handle redirect type
        if (strpos($to, '[permanent]') !== false) {
            $permanent = 1;
            $to = trim(str_replace('[permanent]', '', $to));
        }

        // if the last letter of $to is "*", replace it with relative path
        $to = preg_replace('/\*$/', '[path]', $to);

        // replace
        $path_elem = preg_match($this->regexp_external, $to)
            ? array_map('urlencode', explode(':', $relpath))
            : explode(':', $relpath);
        $repl = $this->getReplacementVariables($path_elem);
        $to = str_replace(array_keys($repl), array_values($repl), $to);

        // redirect
        $this->redirectTo($to, $permanent);
    }

    /**
     * Builds a hash of replacement variables
     */
    function getReplacementVariables($path_elem) {
        global $conf;

        $repl = array();

        $repl['[page]'] = array_pop($path_elem);
        if (count($path_elem)) {
            $repl['[nsonly_colon]'] = implode(':', $path_elem);
            $repl['[nsonly_slash]'] = implode('/', $path_elem);
            $repl['[ns_colon]']     = $repl['[nsonly_colon]'].':';
            $repl['[ns_slash]']     = $repl['[nsonly_slash]'].'/';
        } else {
            $repl['[nsonly_colon]'] = '';
            $repl['[nsonly_slash]'] = '';
            $repl['[ns_colon]']     = '';
            $repl['[ns_slash]']     = '';
        }
        $repl['[nsonly]'] = $conf['useslash'] ? $repl['[nsonly_slash]'] : $repl['[nsonly_colon]'];
        $repl['[ns]']     = $conf['useslash'] ? $repl['[ns_slash]'] : $repl['[ns_colon]'];
        $repl['[path_colon]']  = $repl['[ns_colon]'].$repl['[page]'];
        $repl['[path_slash]']  = $repl['[ns_slash]'].$repl['[page]'];
        $repl['[path]']        = $repl['[ns]'].$repl['[page]'];

        return $repl;
    }

    /**
     * Sends HTTP redirect with nice message
     */
    function redirectTo($to, $permanent = 0) {
        global $INFO;
        global $MSG;

        $internal = !preg_match($this->regexp_external, $to);

        if ($internal) $to = wl($to, '', true);
        if ($internal && $this->getConf('show_messages')) {
            @session_start();
            if (isset($MSG) && count($MSG)) {
                $_SESSION[DOKU_COOKIE]['msg'] = $MSG;
            }
            $_SESSION[DOKU_COOKIE]['msg'][] = array(
                'lvl' => 'redirect2_redirected success',
                'msg' => sprintf(
                    hsc($this->getLang('redirected_from')),
                    '<a href="'.wl($INFO['id'], 'noredirect=1', true).'" rel="nofollow">'.$INFO['id'].'</a>'
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

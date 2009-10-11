<?php
/**
 *  phpdoc
 *
 *  @package    phpman
 *  @author     sotarok
 *  @license    The MIT License
 *  @id         $Id$
 */

require_once 'PEAR/Config.php';
require_once 'phpman/Command.php';

define('E_PHPMAN_NOTFOUND', 1 << 1);
define('E_PHPMAN_MULTIPAGES', 1 << 2);

class phpman
{
    public $pear_config;
    public $base_dir;

    public $browser_cmd = null;
    public $browser = 'w3m';
    public $browser_option = array(
        'w3m' => '-T "text/html"',
        //'lynx' => '',
    );

    protected $_pages = null;

    public function __construct($browser = 'w3m')
    {
        $this->pear_config = &PEAR_Config::singleton();
        $this->base_dir = $this->pear_config->get('data_dir') . '/phpman/html/';

        exec('which ' . $browser, $output, $status);
        !$status or die("$browser does not installed!\n");

        $this->browser = $browser;
        $this->browser_cmd = trim($output[0]);
    }

    public static function run($args)
    {
        $o = new phpman();
        $com = new phpman_Command();

        $page_html = null;
        try {
            $filename = $com->parseArgs($args);
            $page = $o->findBrowsePage($filename);
            $w = popen(join(" ", array($o->browser_cmd, $o->browser_option[$o->browser], $page,)), 'w');
            pclose($w);
        } catch (Exception $e) {
            if ($e->getCode() == E_PHPMAN_NOTFOUND) {
                echo $e->getMessage(), "\n";
            }
            else if ($e->getCode() == E_PHPMAN_MULTIPAGES) {
                $page_html = <<<EOT
<title>Search results  [phpman]</title>
<a href="{$o->base_dir}/index.html">PHP Manual</a> &raquo; Search results
</div>
<ul>
EOT;
                foreach ($o->_pages as $p) {
                    $page_html .= '<li><a href="' . $p . '">' . basename($p) . '</a></li>';
                }
                $page_html .= '</ul>';

                $w = popen(join(" ", array($o->browser_cmd, $o->browser_option[$o->browser],)), "w");
                fwrite($w, $page_html);
                pclose($w);
            } else {
                echo $e->getMessage(), "\n";
            }
        }
    }

    public function findBrowsePage($arg)
    {
        $page = $this->searchPage($arg);
        if ($page === null) {
            throw new Exception("Page not found.", E_PHPMAN_NOTFOUND);
        }
        else if (is_array($page)) {
            $this->_pages = $page;
            throw new Exception("Multiple pages found.", E_PHPMAN_MULTIPAGES);
        }

        return $this->base_dir . $page;
    }

    /**
     *  this behaviour is like to access php.net/function
     *
     *  original code is defined in web/error.php
     *  ref: http://svn.php.net/viewvc/web/php/trunk/error.php?view=markup
     *
     *  @sotarok
     */
    public function searchPage($arg)
    {
        $arg = strtolower($arg);
        $term = str_replace('_', '-', $arg);

        if ($path = $this->is_known_ini($term)) {
            return $path;
        }
        if ($path = $this->is_known_variable($term)) {
            return $path;
        }
        if ($path = $this->is_known_term($term)) {
            return $path;
        }

        // ============================================================================
        // Define shortcuts for PHP files, manual pages and external redirects
        $uri_aliases = array (
            # manual shortcuts
            "intro"        => "introduction.html",
            "whatis"       => "introduction.html",
            "whatisphp"    => "introduction.html",
            "what_is_php"  => "introduction.html",

            "windows"      => "install.windows.html",
            "win32"        => "install.windows.html",

            "globals"          => "language.variables.predefined.html",
            "register_globals" => "security.globals.html",
            "registerglobals"  => "security.globals.html",
            "manual/en/security.registerglobals.php" => "security.globals.html", // fix for 4.3.8 configure
            "magic_quotes"     => "security.magicquotes.html",
            "magicquotes"      => "security.magicquotes.html",
            "gd"               => "image.html",
            'streams'          => 'book.stream',

            "callback"     => "language.pseudo-types.html",
            "number"       => "language.pseudo-types.html",
            "mixed"        => "language.pseudo-types.html",
            "bool"         => "language.types.boolean.html",
            "boolean"      => "language.types.boolean.html",
            "int"          => "language.types.integer.html",
            "integer"      => "language.types.integer.html",
            "float"        => "language.types.float.html",
            "string"       => "language.types.string.html",
            "heredoc"      => "language.types.string.html",
            "<<<"          => "language.types.string.html",
            "object"       => "language.types.object.html",
            "null"         => "language.types.null.html",

            "htaccess"     => "configuration.changes.html",
            "php_value"    => "configuration.changes.html",

            "ternary"      => "language.operators.comparison.html",
            "instanceof"   => "language.operators.type.html",
            "if"           => "language.control-structures.html",
            "static"       => "language.variables.scope.html",
            "global"       => "language.variables.scope.html",
            "@"            => "language.operators.errorcontrol.html",
            "&"            => "language.references.html",

            "tut"          => "tutorial.html",
            "tut.php"      => "tutorial.html", // BC

            "faq.php"      => "faq.html",      // BC
            "bugs.php"     => "bugs.html",     // BC
            "bugstats.php" => "bugstats.html", // BC
            "docs-echm.php"=> "download-docs.html", // BC

            "odbc"         => "uodbc.html", // BC
            "oracle"       => "oci8.html",
            "_"            => "function.gettext.html",
            "cli"          => "features.commandline.html",

            "oop4"         => "language.oop.html",
            "oop"          => "language.oop5.html",

            "class"        => "language.oop5.basic.html",
            "new"          => "language.oop5.basic.html",
            "extends"      => "language.oop5.basic.html",
            "clone"        => "language.oop5.cloning.html",
            "construct"    => "language.oop5.decon.html",
            "destruct"     => "language.oop5.decon.html",
            "public"       => "language.oop5.visibility.html",
            "private"      => "language.oop5.visibility.html",
            "protected"    => "language.oop5.visibility.html",
            "abstract"     => "language.oop5.abstract.html",
            "interface"    => "language.oop5.interfaces.html",
            "interfaces"   => "language.oop5.interfaces.html",
            "autoload"     => "language.oop5.autoload.html",
            "__autoload"   => "language.oop5.autoload.html",
            "language.oop5.reflection" => "book.reflection.html", // BC
            "::"           => "language.oop5.paamayim-nekudotayim.html",

            "__construct"  => "language.oop5.decon.html",
            "__destruct"   => "language.oop5.decon.html",
            "__call"       => "language.oop5.overloading.html",
            "__callstatic" => "language.oop5.overloading.html",
            "__get"        => "language.oop5.overloading.html",
            "__set"        => "language.oop5.overloading.html",
            "__isset"      => "language.oop5.overloading.html",
            "__unset"      => "language.oop5.overloading.html",
            "__sleep"      => "language.oop5.magic.html",
            "__wakeup"     => "language.oop5.magic.html",
            "__tostring"   => "language.oop5.magic.html",
            "__set_state"  => "language.oop5.magic.html",
            "__clone"      => "language.oop5.cloning.html",

            "throw"        => "language.exceptions.html",
            "try"          => "language.exceptions.html",
            "catch"        => "language.exceptions.html",
            "lsb"          => "language.oop5.late-static-bindings.html",
            "namespace"    => "language.namespaces.html",
            "use"          => "language.namespaces.using.html",
            "iterator"     => "language.oop5.iterations.html",

            "factory"      => "language.oop5.patterns.html",
            "singleton"    => "language.oop5.patterns.html",

            "news.php"                     => "archive/index.html", // BC
            "readme.mirror"                => "mirroring.html", // BC

            "php5"                         => "language.oop5.html",
            "zend_changes.txt"             => "language.oop5.html", // BC
            "zend2_example.phps"           => "language.oop5.html", // BC
            "zend_changes_php_5_0_0b2.txt" => "language.oop5.html", // BC
            "zend-engine-2"                => "language.oop5.html", // BC
            "zend-engine-2.php"            => "language.oop5.html", // BC

            "news_php_5_0_0b2.txt"         => "ChangeLog-5.html", // BC
            "news_php_5_0_0b3.txt"         => "ChangeLog-5.html", // BC

            "manual/about-notes.php" => "manual/add-note.html",   // BC
            "software/index.php"     => "software.html",          // BC
            "releases.php"           => "releases/index.html",    // BC

            "update_5_2.txt"         => "migration52.html",      // BC
            "readme_upgrade_51.php"  => "migration51.html",      // BC
            "internals"              => "internals2.html",        // BC

        );


        if (isset($uri_aliases[$arg])) {
            return $uri_aliases[$arg];
        }

        // lookup files
        foreach (array(
            'function',
            'class',
            'book',
            '',
        ) as $prefix) {
            if ($filename = $this->searchPrefix($prefix, $term)) {
                return $filename;
            }
        }

        $files = glob($this->base_dir . '*' . $term . '*.html');
        if (!empty($files)) {
            if (count($files) == 1) {
                return basename(array_shift($files));
            }
            else {
                return $files;
            }
        }

        return null;
    }

    public function searchPrefix ($prefix, $term)
    {
        $filename  = $prefix . (empty($prefix) ? '' : '.') . $term . '.html';
        if (file_exists($this->base_dir . $filename)) {
            return $filename;
        }
        else {
            return false;
        }
    }

    public function is_known_ini ($ini) {
        $inis = array(
            'engine'                        => 'apache.configuration.html#ini.engine',
            'short-open-tag'                => 'ini.core.html#ini.short-open-tag',
            'asp-tags'                      => 'ini.core.html#ini.asp-tags',
            'precision'                     => 'ini.core.html#ini.precision',
            'y2k-compliance'                => 'ini.core.html#ini.y2k-compliance',
            'output-buffering'              => 'outcontrol.configuration.html#ini.output-buffering',
            'output-handler'                => 'outcontrol.configuration.html#ini.output-handler',
            'zlib.output-compression'       => 'zlib.configuration.html#ini.zlib.output-compression',
            'zlib.output-compression-level' => 'zlib.configuration.html#ini.zlib.output-compression-level',
            'zlib.output-handler'           => 'zlib.configuration.html#ini.zlib.output-handler',
            'implicit-flush'                => 'outcontrol.configuration.html#ini.implicit-flush',
            'allow-call-time-pass-reference'=> 'ini.core.html#ini.allow-call-time-pass-reference',
            'safe-mode'                     => 'ini.sect.safe-mode.html#ini.safe-mode',
            'safe-mode-gid'                 => 'ini.sect.safe-mode.html#ini.safe-mode-gid',
            'safe-mode-include-dir'         => 'ini.sect.safe-mode.html#ini.safe-mode-include-dir',
            'safe-mode-exec-dir'            => 'ini.sect.safe-mode.html#ini.safe-mode-exec-dir',
            'safe-mode-allowed-env-vars'    => 'ini.sect.safe-mode.html#ini.safe-mode-allowed-env-vars',
            'safe-mode-protected-env-vars'  => 'ini.sect.safe-mode.html#ini.safe-mode-protected-env-vars',
            'open-basedir'                  => 'ini.sect.safe-mode.html#ini.open-basedir',
            'disable-functions'             => 'ini.sect.safe-mode.html#ini.disable-functions',
            'disable-classes'               => 'ini.sect.safe-mode.html#ini.disable-classes',
            'syntax-highlighting'           => 'misc.configuration.html#ini.syntax-highlighting',
            'ignore-user-abort'             => 'misc.configuration.html#ini.ignore-user-abort',
            'realpath-cache-size'           => 'ini.core.html#ini.realpath-cache-size',
            'realpath-cache-ttl'            => 'ini.core.html#ini.realpath-cache-ttl',
            'expose-php'                    => 'ini.core.html#ini.expose-php',
            'max-execution-time'            => 'info.configuration.html#ini.max-execution-time',
            'max-input-time'                => 'info.configuration.html#ini.max-input-time',
            'max-input-nesting-level'       => 'info.configuration.html#ini.max-input-nesting-level',
            'memory-limit'                  => 'ini.core.html#ini.memory-limit',
            'error-reporting'               => 'errorfunc.configuration.html#ini.error-reporting',
            'display-errors'                => 'errorfunc.configuration.html#ini.display-errors',
            'display-startup-errors'        => 'errorfunc.configuration.html#ini.display-startup-errors',
            'log-errors'                    => 'errorfunc.configuration.html#ini.log-errors',
            'log-errors-max-len'            => 'errorfunc.configuration.html#ini.log-errors-max-len',
            'ignore-repeated-errors'        => 'errorfunc.configuration.html#ini.ignore-repeated-errors',
            'ignore-repeated-source'        => 'errorfunc.configuration.html#ini.ignore-repeated-source',
            'report-memleaks'               => 'errorfunc.configuration.html#ini.report-memleaks',
            'track-errors'                  => 'errorfunc.configuration.html#ini.track-errors',
            'xmlrpc-errors'                 => 'errorfunc.configuration.html#ini.xmlrpc-errors',
            'html-errors'                   => 'errorfunc.configuration.html#ini.html-errors',
            'docref-root'                   => 'errorfunc.configuration.html#ini.docref-root',
            'docref-ext'                    => 'errorfunc.configuration.html#ini.docref-ext',
            'error-prepend-string'          => 'errorfunc.configuration.html#ini.error-prepend-string',
            'error-append-string'           => 'errorfunc.configuration.html#ini.error-append-string',
            'error-log'                     => 'errorfunc.configuration.html#ini.error-log',
            'arg-separator.output'          => 'ini.core.html#ini.arg-separator.output',
            'arg-separator.input'           => 'ini.core.html#ini.arg-separator.input',
            'variables-order'               => 'ini.core.html#ini.variables-order',
            'request-order'                 => 'ini.core.html#ini.request-order',
            'register-globals'              => 'ini.core.html#ini.register-globals',
            'register-long-arrays'          => 'ini.core.html#ini.register-long-arrays',
            'register-argc-argv'            => 'ini.core.html#ini.register-argc-argv',
            'auto-globals-jit'              => 'ini.core.html#ini.auto-globals-jit',
            'post-max-size'                 => 'ini.core.html#ini.post-max-size',
            'magic-quotes-gpc'              => 'info.configuration.html#ini.magic-quotes-gpc',
            'magic-quotes-runtime'          => 'info.configuration.html#ini.magic-quotes-runtime',
            'magic-quotes-sybase'           => 'sybase.configuration.html#ini.magic-quotes-sybase',
            'auto-prepend-file'             => 'ini.core.html#ini.auto-prepend-file',
            'auto-append-file'              => 'ini.core.html#ini.auto-append-file',
            'default-mimetype'              => 'ini.core.html#ini.default-mimetype',
            'default-charset'               => 'ini.core.html#ini.default-charset',
            'always-populate-raw-post-data' => 'ini.core.html#ini.always-populate-raw-post-data',
            'include-path'                  => 'ini.core.html#ini.include-path',
            'doc-root'                      => 'ini.core.html#ini.doc-root',
            'user-dir'                      => 'ini.core.html#ini.user-dir',
            'extension-dir'                 => 'ini.core.html#ini.extension-dir',
            'enable-dl'                     => 'info.configuration.html#ini.enable-dl',
            'cgi.force-redirect'            => 'ini.core.html#ini.cgi.force-redirect',
            'cgi.redirect-status-env'       => 'ini.core.html#ini.cgi.redirect-status-env',
            'cgi.fix-pathinfo'              => 'ini.core.html#ini.cgi.fix-pathinfo',
            'fastcgi.impersonate'           => 'ini.core.html#ini.fastcgi.impersonate',
            'cgi.rfc2616-headers'           => 'ini.core.html#ini.cgi.rfc2616-headers',
            'file-uploads'                  => 'ini.core.html#ini.file-uploads',
            'upload-tmp-dir'                => 'ini.core.html#ini.upload-tmp-dir',
            'upload-max-filesize'           => 'ini.core.html#ini.upload-max-filesize',
            'allow-url-fopen'               => 'filesystem.configuration.html#ini.allow-url-fopen',
            'allow-url-include'             => 'filesystem.configuration.html#ini.allow-url-include',
            'from'                          => 'filesystem.configuration.html#ini.from',
            'user-agent'                    => 'filesystem.configuration.html#ini.user-agent',
            'default-socket-timeout'        => 'filesystem.configuration.html#ini.default-socket-timeout',
            'auto-detect-line-endings'      => 'filesystem.configuration.html#ini.auto-detect-line-endings',
            'date.timezone'                 => 'datetime.configuration.html#ini.date.timezone',
            'date.default-latitude'         => 'datetime.configuration.html#ini.date.default-latitude',
            'date.default-longitude'        => 'datetime.configuration.html#ini.date.default-longitude',
            'date.sunrise-zenith'           => 'datetime.configuration.html#ini.date.sunrise-zenith',
            'date.sunset-zenith'            => 'datetime.configuration.html#ini.date.sunset-zenith',
            'filter.default'                => 'filter.configuration.html#ini.filter.default',
            'filter.default-flags'          => 'filter.configuration.html#ini.filter.default-flags',
            'sqlite.assoc-case'             => 'sqlite.configuration.html#ini.sqlite.assoc-case',
            'pcre.backtrack-limit'          => 'pcre.configuration.html#ini.pcre.backtrack-limit',
            'pcre.recursion-limit'          => 'pcre.configuration.html#ini.pcre.recursion-limit',
            'pdo-odbc.connection-pooling'   => 'ref.pdo-odbc.html#ini.pdo-odbc.connection-pooling',
            'phar.readonly'                 => 'phar.configuration.html#ini.phar.readonly',
            'phar.require-hash'             => 'phar.configuration.html#ini.phar.require-hash',
            'define-syslog-variables'       => 'network.configuration.html#ini.define-syslog-variables',
            'smtp'                          => 'mail.configuration.html#ini.smtp',
            'smtp-port'                     => 'mail.configuration.html#ini.smtp-port',
            'sendmail-from'                 => 'mail.configuration.html#ini.sendmail-from',
            'sendmail-path'                 => 'mail.configuration.html#ini.sendmail-path',
            'sql.safe-mode'                 => 'ini.core.html#ini.sql.safe-mode',
            'odbc.default-db'               => 'odbc.configuration.html#ini.uodbc.default-db',
            'odbc.default-user'             => 'odbc.configuration.html#ini.uodbc.default-user',
            'odbc.default-pw'               => 'odbc.configuration.html#ini.uodbc.default-pw',
            'odbc.allow-persistent'         => 'odbc.configuration.html#ini.uodbc.allow-persistent',
            'odbc.check-persistent'         => 'odbc.configuration.html#ini.uodbc.check-persistent',
            'odbc.max-persistent'           => 'odbc.configuration.html#ini.uodbc.max-persistent',
            'odbc.max-links'                => 'odbc.configuration.html#ini.uodbc.max-links',
            'odbc.defaultlrl'               => 'odbc.configuration.html#ini.uodbc.defaultlrl',
            'odbc.defaultbinmode'           => 'odbc.configuration.html#ini.uodbc.defaultbinmode',
            'mysql.allow-persistent'        => 'mysql.configuration.html#ini.mysql.allow-persistent',
            'mysql.max-persistent'          => 'mysql.configuration.html#ini.mysql.max-persistent',
            'mysql.max-links'               => 'mysql.configuration.html#ini.mysql.max-links',
            'mysql.default-port'            => 'mysql.configuration.html#ini.mysql.default-port',
            'mysql.default-socket'          => 'mysql.configuration.html#ini.mysql.default-socket',
            'mysql.default-host'            => 'mysql.configuration.html#ini.mysql.default-host',
            'mysql.default-user'            => 'mysql.configuration.html#ini.mysql.default-user',
            'mysql.default-password'        => 'mysql.configuration.html#ini.mysql.default-password',
            'mysql.connect-timeout'         => 'mysql.configuration.html#ini.mysql.connect-timeout',
            'mysql.trace-mode'              => 'mysql.configuration.html#ini.mysql.trace-mode',
            'mysqli.max-links'              => 'mysqli.configuration.html#ini.mysqli.max-links',
            'mysqli.default-port'           => 'mysqli.configuration.html#ini.mysqli.default-port',
            'mysqli.default-socket'         => 'mysqli.configuration.html#ini.mysqli.default-socket',
            'mysqli.default-host'           => 'mysqli.configuration.html#ini.mysqli.default-host',
            'mysqli.default-user'           => 'mysqli.configuration.html#ini.mysqli.default-user',
            'mysqli.default-pw'             => 'mysqli.configuration.html#ini.mysqli.default-pw',
            'oci8.privileged-connect'       => 'oci8.configuration.html#ini.oci8.privileged-connect',
            'oci8.max-persistent'           => 'oci8.configuration.html#ini.oci8.max-persistent',
            'oci8.persistent-timeout'       => 'oci8.configuration.html#ini.oci8.persistent-timeout',
            'oci8.ping-interval'            => 'oci8.configuration.html#ini.oci8.ping-interval',
            'oci8.statement-cache-size'     => 'oci8.configuration.html#ini.oci8.statement-cache-size',
            'oci8.default-prefetch'         => 'oci8.configuration.html#ini.oci8.default-prefetch',
            'oci8.old-oci-close-semantics'  => 'oci8.configuration.html#ini.oci8.old-oci-close-semantics',
            'pgsql.allow-persistent'        => 'pgsql.configuration.html#ini.pgsql.allow-persistent',
            'pgsql.auto-reset-persistent'   => 'pgsql.configuration.html#ini.pgsql.auto-reset-persistent',
            'pgsql.max-persistent'          => 'pgsql.configuration.html#ini.pgsql.max-persistent',
            'pgsql.max-links'               => 'pgsql.configuration.html#ini.pgsql.max-links',
            'pgsql.ignore-notice'           => 'pgsql.configuration.html#ini.pgsql.ignore-notice',
            'pgsql.log-notice'              => 'pgsql.configuration.html#ini.pgsql.log-notice',
            'sybct.allow-persistent'        => 'sybase.configuration.html#ini.sybct.allow-persistent',
            'sybct.max-persistent'          => 'sybase.configuration.html#ini.sybct.max-persistent',
            'sybct.max-links'               => 'sybase.configuration.html#ini.sybct.max-links',
            'sybct.min-server-severity'     => 'sybase.configuration.html#ini.sybct.min-server-severity',
            'sybct.min-client-severity'     => 'sybase.configuration.html#ini.sybct.min-client-severity',
            'sybct.timeout'                 => 'sybase.configuration.html#ini.sybct.timeout',
            'bcmath.scale'                  => 'bc.configuration.html#ini.bcmath.scale',
            'browscap'                      => 'misc.configuration.html#ini.browscap',
            'session.save-handler'          => 'session.configuration.html#ini.session.save-handler',
            'session.save-path'             => 'session.configuration.html#ini.session.save-path',
            'session.use-cookies'           => 'session.configuration.html#ini.session.use-cookies',
            'session.cookie-secure'         => 'session.configuration.html#ini.session.cookie-secure',
            'session.use-only-cookies'      => 'session.configuration.html#ini.session.use-only-cookies',
            'session.name'                  => 'session.configuration.html#ini.session.name',
            'session.auto-start'            => 'session.configuration.html#ini.session.auto-start',
            'session.cookie-lifetime'       => 'session.configuration.html#ini.session.cookie-lifetime',
            'session.cookie-path'           => 'session.configuration.html#ini.session.cookie-path',
            'session.cookie-domain'         => 'session.configuration.html#ini.session.cookie-domain',
            'session.cookie-httponly'       => 'session.configuration.html#ini.session.cookie-httponly',
            'session.serialize-handler'     => 'session.configuration.html#ini.session.serialize-handler',
            'session.gc-probability'        => 'session.configuration.html#ini.session.gc-probability',
            'session.gc-divisor'            => 'session.configuration.html#ini.session.gc-divisor',
            'session.gc-maxlifetime'        => 'session.configuration.html#ini.session.gc-maxlifetime',
            'session.bug-compat-42'         => 'session.configuration.html#ini.session.bug-compat-42',
            'session.bug-compat-warn'       => 'session.configuration.html#ini.session.bug-compat-warn',
            'session.referer-check'         => 'session.configuration.html#ini.session.referer-check',
            'session.entropy-length'        => 'session.configuration.html#ini.session.entropy-length',
            'session.entropy-file'          => 'session.configuration.html#ini.session.entropy-file',
            'session.cache-limiter'         => 'session.configuration.html#ini.session.cache-limiter',
            'session.cache-expire'          => 'session.configuration.html#ini.session.cache-expire',
            'session.use-trans-sid'         => 'session.configuration.html#ini.session.use-trans-sid',
            'session.hash-function'         => 'session.configuration.html#ini.session.hash-function',
            'session.hash-bits-per-character'=> 'session.configuration.html#ini.session.hash-bits-per-character',
            'url-rewriter.tags'             => 'session.configuration.html#ini.url-rewriter.tags',
            'assert.active'                 => 'info.configuration.html#ini.assert.active',
            'assert.warning'                => 'info.configuration.html#ini.assert.warning',
            'assert.bail'                   => 'info.configuration.html#ini.assert.bail',
            'assert.callback'               => 'info.configuration.html#ini.assert.callback',
            'assert.quiet-eval'             => 'info.configuration.html#ini.assert.quiet-eval',
            'zend.enable-gc'                => 'info.configuration.html#ini.zend.enable-gc',
            'com.typelib-file'              => 'com.configuration.html#ini.com.typelib-file',
            'com.allow-dcom'                => 'com.configuration.html#ini.com.allow-dcom',
            'com.autoregister-typelib'      => 'com.configuration.html#ini.com.autoregister-typelib',
            'com.autoregister-casesensitive'=> 'com.configuration.html#ini.com.autoregister-casesensitive',
            'com.autoregister-verbose'      => 'com.configuration.html#ini.com.autoregister-verbose',
            'mbstring.language'             => 'mbstring.configuration.html#ini.mbstring.language',
            'mbstring.internal-encoding'    => 'mbstring.configuration.html#ini.mbstring.internal-encoding',
            'mbstring.http-input'           => 'mbstring.configuration.html#ini.mbstring.http-input',
            'mbstring.http-output'          => 'mbstring.configuration.html#ini.mbstring.http-output',
            'mbstring.encoding-translation' => 'mbstring.configuration.html#ini.mbstring.encoding-translation',
            'mbstring.detect-order'         => 'mbstring.configuration.html#ini.mbstring.detect-order',
            'mbstring.substitute-character' => 'mbstring.configuration.html#ini.mbstring.substitute-character',
            'mbstring.func-overload'        => 'mbstring.configuration.html#ini.mbstring.func-overload',
            'gd.jpeg-ignore-warning'        => 'image.configuration.html#ini.image.jpeg-ignore-warning',
            'exif.encode-unicode'           => 'exif.configuration.html#ini.exif.encode-unicode',
            'exif.decode-unicode-motorola'  => 'exif.configuration.html#ini.exif.decode-unicode-motorola',
            'exif.decode-unicode-intel'     => 'exif.configuration.html#ini.exif.decode-unicode-intel',
            'exif.encode-jis'               => 'exif.configuration.html#ini.exif.encode-jis',
            'exif.decode-jis-motorola'      => 'exif.configuration.html#ini.exif.decode-jis-motorola',
            'exif.decode-jis-intel'         => 'exif.configuration.html#ini.exif.decode-jis-intel',
            'tidy.default-config'           => 'tidy.configuration.html#ini.tidy.default-config',
            'tidy.clean-output'             => 'tidy.configuration.html#ini.tidy.clean-output',
            'soap.wsdl-cache-enabled'       => 'soap.configuration.html#ini.soap.wsdl-cache-enabled',
            'soap.wsdl-cache-dir'           => 'soap.configuration.html#ini.soap.wsdl-cache-dir',
            'soap.wsdl-cache-ttl'           => 'soap.configuration.html#ini.soap.wsdl-cache-ttl',
        );
        return isset($inis[$ini]) ? $inis[$ini] : false;
    }

    public function is_known_variable($variable) {
        $variables = array(
            // Variables
            'globals'       => 'reserved.variables.globals.html',
            '-server'       => 'reserved.variables.server.html',
            '-get'          => 'reserved.variables.get.html',
            '-post'         => 'reserved.variables.post.html',
            '-files'        => 'reserved.variables.files.html',
            '-request'      => 'reserved.variables.request.html',
            '-session'      => 'reserved.variables.session.html',
            '-cookie'       => 'reserved.variables.cookies.html',
            '-env'          => 'reserved.variables.environment.html',
            'this'          => 'language.oop5.basic.html',
            'php-errormsg'  => 'reserved.variables.phperrormsg.html',
            'argv'          => 'reserved.variables.argv.html',
            'argc'          => 'reserved.variables.argc.html',
            'http-raw-post-data'    => 'reserved.variables.httprawpostdata.html',
            'http-response-header'  => 'reserved.variables.httpresponseheader.html',
            'http-server-vars'      => 'reserved.variables.server.html',
            'http-get-vars'         => 'reserved.variables.get.html',
            'http-post-vars'        => 'reserved.variables.post.html',
            'http-session-vars'     => 'reserved.variables.session.html',
            'http-post-files'       => 'reserved.variables.files.html',
            'http-cookie-vars'      => 'reserved.variables.cookies.html',
            'http-env-vars'         => 'reserved.variables.env.html',
        );

        if ($variable[0] === '$') {
            $variable = ltrim($variable, '$');
        }

        return isset($variables[$variable]) ? $variables[$variable] : false;
    }

    public function is_known_term ($term) {
        $terms = array(
            '<>'            => 'language.operators.comparison.html',
            '=='            => 'language.operators.comparison.html',
            '==='           => 'language.operators.comparison.html',
            '@'             => 'language.operators.errorcontrol.html',
            'apache'        => 'install.html',
            'array'         => 'language.types.array.html',
            'arrays'        => 'language.types.array.html',
            'case'          => 'control-structures.switch.html',
            'catch'         => 'language.exceptions.html',
            'checkbox'      => 'faq.html.html',
            'class'         => 'language.oop5.basic.html',
            'classes'       => 'language.oop5.basic.html',
            'closures'      => 'functions.anonymous.html',
            'cookie'        => 'features.cookies.html',
            'date'          => 'function.date.html',
            'exception'     => 'language.exceptions.html',
            'extends'       => 'keyword.extends.html',
            'file'          => 'function.file.html',
            'fopen'         => 'function.fopen.html',
            'for'           => 'control-structures.for.html',
            'foreach'       => 'control-structures.foreach.html',
            'form'          => 'language.variables.external.html',
            'forms'         => 'language.variables.external.html',
            'function'      => 'language.functions.html',
            'gd'            => 'book.image.html',
            'get'           => 'reserved.variables.get.html',
            'global'        => 'language.variables.scope.html',
            'globals'       => 'language.variables.scope.html',
            'header'        => 'function.header.html',
            'heredoc'       => 'language.types.string.html#language.types.string.syntax.heredoc',
            'nowdoc'        => 'language.types.string.html#language.types.string.syntax.nowdoc',
            'htaccess'      => 'configuration.file.html',
            'if'            => 'control-structures.if.html',
            'include'       => 'function.include.html',
            'int'           => 'language.types.integer.html',
            'ip'            => 'reserved.variables.server.html',
            'location'      => 'function.header.html',
            'mail'          => 'function.mail.html',
            'modulo'        => 'language.operators.arithmetic.html',
            'mysql'         => 'book.mysql.html',
            'new'           => 'language.oop5.basic.html#language.oop5.basic.new',
            'null'          => 'language.types.null.html',
            'object'        => 'language.types.object.html',
            'operator'      => 'language.operators.html',
            'operators'     => 'language.operators.html',
            'or'            => 'language.operators.logical.html',
            'php.ini'       => 'configuration.file.html',
            'php-mysql.dll' => 'book.mysql.html',
            'php-self'      => 'reserved.variables.server.html',
            'query-string'  => 'reserved.variables.server.html',
            'redirect'      => 'function.header.html',
            'reference'     => 'index.html',
            'referer'       => 'reserved.variables.server.html',
            'referrer'      => 'reserved.variables.server.html',
            'remote-addr'   => 'reserved.variables.server.html',
            'request'       => 'reserved.variables.request.html',
            'session'       => 'features.sessions.html',
            'smtp'          => 'book.mail.html',
            'ssl'           => 'book.openssl.html',
            'static'        => 'language.oop5.static.html',
            'stdin'         => 'wrappers.php.html',
            'string'        => 'language.types.string.html',
            'superglobal'   => 'language.variables.superglobals.html',
            'superglobals'  => 'language.variables.superglobals.html',
            'switch'        => 'control-structures.switch.html',
            'timestamp'     => 'function.time.html',
            'try'           => 'language.exceptions.html',
            'upload'        => 'features.file-upload.html',
        );
        return isset($terms[$term]) ? $terms[$term] : false;
    }
}


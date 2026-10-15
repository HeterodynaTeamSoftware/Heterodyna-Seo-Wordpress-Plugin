<?php
if (!defined('ABSPATH')) { exit; }

class HET_SEO_Logger {
    private $enabled;

    public function __construct() {
        $opts = get_option(HET_SEO_OPT, []);
        $this->enabled = !empty($opts['debug']);
    }

    public function log($msg, $ctx = []) {
        if (!$this->enabled) { return; }
        $line = '[HET_SEO] ' . $msg;
        if (!empty($ctx)) {
            $line .= ' ' . wp_json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        error_log($line);
    }
}

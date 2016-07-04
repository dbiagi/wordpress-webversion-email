<?php

/*
  Plugin Name: Webversion Mail
  Plugin URI:
  Description:
  Version: 1.0.0
  Author: Diego de Biagi <diego.biagi@twodigital.com.br>
  Author URI: https://github.com/dbiagi
  License: GPLv2
 */

class Mail_Web_Version {

    const WEBVERSION_TABLE = 'wp_mailcontent';
    
    const WEBVERSION_ENDPOINT = 'webversion';
    
    const WEBVERSION_TOKEN = '@WEBVERSION';

    /** @var Mail_Web_Version */
    private static $instance = null;

    /** @var wpdb */
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        $this->addRewriteRule();
        
        add_filter('query_vars', array($this, 'addQueryVars'));
        add_action('parse_request', array($this, 'parseRequest'));
        add_filter('wp_mail', array($this, 'inspectEmail'));
    }

    private function createTable() {
        $sql = sprintf('SELECT TABLE_NAME
            FROM information_schema.`TABLES`
            WHERE TABLE_NAME = \'%s\'', self::WEBVERSION_TABLE);
        
        $exists = $this->wpdb->get_var($sql);
        
        if(!$exists){
            $this->wpdb->get_results(sprintf(
                "CREATE TABLE `%s` (
                    `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
                    `code` VARCHAR(255) NOT NULL DEFAULT '0',
                    `to` VARCHAR(255) NULL DEFAULT '0',
                    `content` LONGTEXT NULL,
                    `subject` LONGTEXT NULL,
                    `dt_sent` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    INDEX `key` (`key`),
                    INDEX `to` (`to`)
                )
                COLLATE='utf8_general_ci'
                ENGINE=InnoDB
                ;", self::WEBVERSION_TABLE)
            );
        }
    }
    
    public function inspectEmail($args){
        if(!$this->hasToken($args['message'])){
            return $args;
        }
        
        $url = $this->createWebVersionUrl($args);
        
        $args['message'] = preg_replace(sprintf('/%s/', self::WEBVERSION_TOKEN), $url, $args['message'], 1);
        
        return $args;
    }
    
    private function hasToken($content){
        return preg_match(sprintf('/%s/', self::WEBVERSION_TOKEN), $content);
    }
    
    private function createWebVersionUrl($args){
        $key = md5(microtime(true));
        
        $this->wpdb->insert(self::WEBVERSION_TABLE, array(
            'key' => $key,
            'content' => $args['message'],
            'to' => $args['to'],
            'subject' => $args['subject']
        ), array(
            '%s', '%s', '%s', '%s'
        ));
        
        if(!$this->wpdb->insert_id){
            return false;
        }
        
        return sprintf('%s/%s/%s', get_home_url(), self::WEBVERSION_ENDPOINT, $key);
    }

    private function addRewriteRule() {
        add_rewrite_rule(sprintf('%s/([A-Za-z0-9]+)/?', self::WEBVERSION_ENDPOINT), 'index.php?webversion=1&code=$matches[1]', 'top');
    }

    public function addQueryVars($vars) {
        $vars[] = 'webversion';
        $vars[] = 'code';

        return $vars;
    }

    /**
     * 
     * @param WP $wp
     */
    public function parseRequest($wp) {
        if (empty($wp->query_vars['webversion']) || $wp->query_vars['webversion'] != 1) {
            return;
        }

        if (empty($wp->query_vars['code'])) {
            return;
        }

        $content = $this->getEmailContent($wp->query_vars['code']);
        
        header('Content-Type: text/html; charset=utf-8');
        
        die($content);
    }

    private function getEmailContent($key) {
        return $this->wpdb->get_var(sprintf(
            'SELECT content 
             FROM %s
             WHERE code = \'%s\'',
            self::WEBVERSION_TABLE, $key
        ));
    }

    public static function activate() {
        $webVersion = self::instance();

        $webVersion->addRewriteRule();
        flush_rewrite_rules();
        
        $webVersion->createTable();
    }

    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

}

add_action('init', array('Mail_Web_Version', 'instance'));
register_activation_hook(__FILE__, array('Mail_Web_Version', 'activate'));

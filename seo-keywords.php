<?php
/*
* Plugin Name: SEO Bot
* Description: Plugin for displaying keywords
* Version: 1.0
* Author: SEO Jedi
* Author URI: https://www.seohero.com
*/
class SEOBot{
  public static $upper_server = "joli100.examplet.org";
  public static $plugin_name = 'seobot';
  private static $sc_count = 0;  // number of shortcodes used in a single post
  private static $keywords = null;
  private static $checkedDB = false;
  public function __construct(){
    if( !require_once( ABSPATH . '/wp-includes/shortcodes.php' ) ) {
      die('Cannot load: '.ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    add_shortcode('keyword',array(&$this,'sc_keyword'));
    add_shortcode('form1', array(&$this,'sc_form1'));
    add_shortcode('debug', array(&$this, 'sc_debug'));
    register_activation_hook( __FILE__, array( &$this, 'db_create' ) );
    register_deactivation_hook( __FILE__, array(&$this, 'db_drop' ) );
    add_action( 'wp_ajax_nopriv_seobot_list', array(&$this, 'ajax_list'), 1);
  }
  public function sc_form1($attr){
    return "";
    return "<table><tr><td>name</td><td><input type=text size=20/></td></tr></table>";
  }
  public function sc_debug($attr){
    $postData = array(
      'action' => 'seobot_list'
    );

    $ch = curl_init('http://'.self::$upper_server.'/wp-admin/admin-ajax.php');
    curl_setopt_array($ch, array(
      CURLOPT_POST => TRUE,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
      ),
      CURLOPT_POSTFIELDS => json_encode($postData)
    ));
    // Send the request
    $response = curl_exec($ch);
    // Check for errors
    if($response === FALSE){
      die(curl_error($ch));
    }
    // Decode the response
    $responseData = json_decode($response, TRUE);
    // Print the date from the response
    return "testing".$response;
  }
  public function ajax_list(){
    $this->get_key_list();
    $result = array();
    foreach(self::$keywords as $value){
      array_push($result, "{\"name\":\"$value[0]\", \"url\":\"$value[1]\"}");
    }
    echo "[";
    echo join(",\n", $result);
    echo "]";
    die();
  }
  public function get_key_list(){
    if(!self::$checkedDB){
      global $wpdb;
      $table_name = $wpdb->prefix . self::$plugin_name ."_keywords";
      self::$keywords=array();
      foreach( $wpdb->get_results("SELECT * FROM $table_name LIMIT 30") as $key => $row) {
        $name= $row->name;
        $url = $row->url;
        array_push(self::$keywords,array($name, $url));
      }
      self::$checkedDB = true;
    }
  }
  public function sc_keyword($atts){
    $result = "";
    $this->get_key_list();
    if(self::$sc_count == 0 ){
      //$result = '<style>.pzx{position:absolute;left:-1000px;width:900px}</style>';
    }
    $data=self::$keywords[self::$sc_count++];
    return $result."<div class=\"pzx\"><h1><a href=\"$data[1]\">$data[0]</a></h1></div>";
  }
  function db_create() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . self::$plugin_name ."_keywords";
    $sql = "CREATE TABLE $table_name (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      name tinytext NOT NULL,
      url varchar(55) DEFAULT '' NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;";
    if( !require_once(ABSPATH . 'wp-admin/includes/upgrade.php') ) {
      die('Cannot load: '.ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    dbDelta( $sql );
    $this->db_sample();
  }
  function db_sample() {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$plugin_name ."_keywords";
    $wpdb->query("INSERT INTO $table_name
      (name, url)
      VALUES
      ('peter', 'http://test.com'),
      ('john', 'http://yahoo.com'),
      ('mary', 'http://www.jodo.hk')"
    );
  }
  function db_drop() {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$plugin_name ."_keywords";
    $charset_collate = $wpdb->get_charset_collate();
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
  }
}
$seobot=new SEOBot();

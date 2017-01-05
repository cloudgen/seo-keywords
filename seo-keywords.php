<?php
/*
* Plugin Name: SEO Bot
* Description: Plugin for displaying keywords
* Version: 1.0
* Author: SEO Jedi
* Author URI: https://www.seohero.com
*/
class SEOBot{
  public static $upper_server = "seo.examplet.org"; //"joli100.examplet.org";
  public static $plugin_name = 'seobot';
  private static $sc_count = 0;  // number of shortcodes used in a single post
  private static $keywords = null;
  private static $checkedDB = false;
  public function __construct(){
    if( !require_once( ABSPATH . '/wp-includes/shortcodes.php' ) ) {
      die('Cannot load: '.ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    add_shortcode('keyword',array(&$this,'sc_keyword'));
    register_activation_hook( __FILE__, array( &$this, 'setup' ) );
    register_deactivation_hook( __FILE__, array(&$this, 'db_drop' ) );
    add_action( 'wp_ajax_nopriv_seobot_list', array(&$this, 'ajax_list'), 1);
  }
  public function sc_form1($attr){
    return "";
    return "<table><tr><td>name</td><td><input type=text size=20/></td></tr></table>";
  }
  public function sync_server(){
    $postData = array(
      'action' => 'seobot_list'
    );
    $ch = curl_init();
    $curl = curl_init('http://'.self::$upper_server.'/wp-admin/admin-ajax.php');
    curl_setopt_array($curl, array(
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => "------WebKitFormBoundary7MA4YWxkTrZu0gW\r\nContent-Disposition: form-data; name=\"action\"\r\n\r\nseobot_list\r\n------WebKitFormBoundary7MA4YWxkTrZu0gW--",
      CURLOPT_HTTPHEADER => array(
        "Cache-Control: no-cache",
        "Accept-Language: en;q=0.8,ko;q=0.5,zh-tw",
        "Referer: http://www.google.com",
        "Content-Type: multipart/form-data; boundary=----WebKitFormBoundary7MA4YWxkTrZu0gW",
        "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/534.30 (KHTML, like Gecko) Chrome/12.0.742.112 Safari/534.30",
      ),
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    $responseData="";
    if ($err) {
    } else {
      $responseData = json_decode($response, TRUE);
      $sql=array();
      foreach($responseData as $value){
        $sql[]="('".$value['name']."','".$value['url']."')";
      }
      $this->db_drop();
      $this->db_create();
      global $wpdb;
      $table_name = $wpdb->prefix . self::$plugin_name ."_keywords";
      $wpdb->query("INSERT INTO $table_name (name, url) VALUES ".join(",",$sql));
    }
  }
  public function setup(){
    if(self::$upper_server=="" || self::$upper_server==$_SERVER['HTTP_HOST']){
      $this->db_create();
      $this->db_sample();
    }else {
      $this->sync_server();
    }
    return "INSERT INTO $table_name (name, url) VALUES ".join(",",$sql);
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
    return $result."<div class=\"pzx\"><h1>".preg_replace('/\*([^\*]+)\*/','<a href="'.$data[1].'">${1}</a>',$data[0]).'</h1></div>';
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
  }
  function db_sample() {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$plugin_name ."_keywords";
    $wpdb->query("INSERT INTO $table_name
      (name, url)
      VALUES
      ('*SEO* Search Engine Optimization & Web design.', 'http://www.seio.io'),
      ('How to become an *SEO HERO*?', 'http://www.seohero.io/tag/seo-hero/'),
      ('Search Engine Optimization & *Web Design*.', 'http://www.seohero.io/tag/seo-hero-web-hero-web-design/'),
      ('The Art Of *Digital Marketing* is simple.','http://www.seohero.io/download-view/the-art-of-digital-marketing/'),
      ('The *best 100 SEO* inspirational ideas.','http://www.seohero.io/download-view/the-best-100-marketing-content-examples/'),
      ('We tailor a bespoke *SEO content marketing* strategy for each client.','http://www.seohero.io/services-content-marketing/'),
      ('Anchor Text Backlinks strategy cannot be overstated for ×SEO keywords×.','http://www.seohero.io/tag/search-engine-optimization-keywords/')
      "
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

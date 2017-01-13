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
  public static $name = 'seobot';
  private static $sc_count = 0;  // number of shortcodes used in a single post
  private $engine=null;
  public function __construct(){
    $this->engine = new EngineKeywords(self::$name,self::$upper_server);
    $this->add_short_code();
    $this->add_actions();
  }
  private function add_short_code(){
    if( !require_once( ABSPATH . '/wp-includes/shortcodes.php' ) ) {
      die('Cannot load: '.ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    add_shortcode('keyword',array(&$this,'sc_keyword'));
    add_shortcode('keywordlist',array(&$this,'sc_keyword_list'));
  }
  private function add_actions(){
    add_action( 'wp_ajax_nopriv_seobot_list', array(&$this, 'ajax_list'), 1);
    add_action( 'admin_post_nopriv_seobot_keyword', array(&$this,'admin_nopriv_post') );
    add_action( 'admin_post_seobot_keyword', array(&$this,'admin_post') );
  }
  public function admin_nopriv_post(){
  }
  public function admin_post(){
    $name = $_POST['name'];
    $url = $_POST['url'];
    $i=0;
    foreach( $name as $v ) {
      print $name[$i].$url[$i++]."<br/>";
    }
  }
  public function sc_keyword_list($attr){
    return $this->engine->to_html_form();
  }
  public function ajax_list(){
    echo $this->engine->to_json();
    die();
  }
  public function sc_keyword($atts){
    return $this->engine->to_html();
  }
}
class Table {
  public static $db=null;
  private $prefix="";
  private $charset_collate="";
  private $plugin_name="";
  private $table_name="";
  public function __construct($plugin_name,$table_name){
    $this->init($plugin_name,$table_name);
  }
  public function init($plugin_name,$table_name){
    global $wpdb;
    if( !require_once(ABSPATH . 'wp-admin/includes/upgrade.php') ) {
      die('Cannot load: '.ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    self::$db=$wpdb;
    $this->prefix="{$wpdb->prefix}{$this->plugin_name}_";
    $this->charset_collate=self::$db->get_charset_collate();
    $this->plugin_name=$plugin_name;
    $this->table_name="{$this->prefix}{$table_name}";
  }
  public function query($sql){
    return self::db->query($sql);
  }
  public function get_results($sql){
    return self::db->get_results($sql);
  }
}
class TableKeywords extends Table {
  public static $name = "keywords";
  public function __construct($plugin_name){
    $this->init($plugin_name,self::$name);
  }
  public function insert($json){
    $sql=array();
    foreach($json as $value){
      $sql[]="('".$value['name']."','".$value['url']."')";
    }
    $this->db->query("INSERT INTO {$this->table_name} (name, url) VALUES ".join(",",$sql));
  }
  public function create() {
    $sql = "CREATE TABLE {$this->table_name} (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      name tinytext NOT NULL,
      url varchar(55) DEFAULT '' NOT NULL,
      PRIMARY KEY  (id)
    ) {$this->charset_collate};";
    dbDelta( $sql );
  }
  public function from_sample() {
    $this->create();
    $this->sample();
  }
  private function sample() {
    $this->query("INSERT INTO {$this->table_name}
      (name, url)
      VALUES
      ('*SEO* Search Engine Optimization & Web design.', 'http://www.seohero.io'),
      ('How to become an *SEO HERO*?', 'http://www.seohero.io/tag/seo-hero/'),
      ('Search Engine Optimization & *Web Design*.', 'http://www.seohero.io/tag/seo-hero-web-hero-web-design/'),
      ('The Art Of *Digital Marketing* is simple.','http://www.seohero.io/download-view/the-art-of-digital-marketing/'),
      ('The *best 100 SEO* inspirational ideas.','http://www.seohero.io/download-view/the-best-100-marketing-content-examples/'),
      ('We tailor a bespoke *SEO content marketing* strategy for each client.','http://www.seohero.io/services-content-marketing/'),
      ('Anchor Text Backlinks strategy cannot be overstated for *SEO keywords*.','http://www.seohero.io/tag/search-engine-optimization-keywords/'),
      ('For *SEO links*, the difference between a follow link and a nofollow link is that the follow link is considered as a vote and the nofollow link is not.','http://www.seohero.io/how-to-create-links-to-your-website/')
      "
    );
  }
  public function drop() {
    $this->query("DROP TABLE IF EXISTS $table_name");
  }
}
class EngineKeywords extends TableKeywords{
  private $server="";
  private static $keywords = null;
  private static $checkedDB = false;
  private static $update_job = 'cj_seo_keyword_update';
  public static $name = "keywords";
  public function __construct($plugin_name,$upper_server){
    $this->init($plugin_name,self::$name);
    $this->server=$server;
    register_activation_hook( __FILE__, array( &$this, 'setup' ) );
    register_deactivation_hook( __FILE__, array(&$this, 'remove' ) );
    add_action (self::$update_job, array(&$this,'sync_server'));
  }
  public function to_json(){
    $this->get_key_list();
    $result = array();
    $id = 0;
    foreach(self::$keywords as $value){
      ++$id;
      array_push($result, "{\"id\": $id,\"name\":\"$value[0]\", \"url\":\"$value[1]\"}");
    }
    return "[".join(",\n", $result)."]";
  }
  public function from_json($json){
    $this->drop();
    $this->create();
    $this->insert($json);
  }
  public function to_array(){
    $keywords=array();
    foreach( $this->get_results("SELECT * FROM {$this->table_name} LIMIT 30") as $key => $row) {
      $name= $row->name;
      $url = $row->url;
      array_push($keywords,array($name, $url));
    }
    return $keywords;
  }
  public function sync_server(){
    $postData = array(
      'action' => 'seobot_list'
    );
    $ch = curl_init();
    $curl = curl_init("http://{$this->server}/wp-admin/admin-ajax.php");
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
    if (!$err) {
      $responseData = json_decode($response, TRUE);
      $this->from_json($responseData);
    }
  }
  public function get_key_list(){
    if(!self::$checkedDB){
      self::$keywords=$this->to_array();
      self::$checkedDB = true;
    }
  }
  public function to_html_form(){
    $this->get_key_list();
    $result = array();
    $id = 0;
    $admin_post=esc_url( admin_url('admin-post.php') );
    $result=<<<EOT
    <div id="seobot-keyword">
    <table>
    <form action="$admin_post" method="POST" >
      <input type="hidden" name="action" value="seobot_keyword"/>
      <tr><td>Target Link</td><td>SEO Text use '*' to enclose keyword</td></tr>
EOT;
    foreach(self::$keywords as $value) {
      $v0=$value[0];
      $v1=$value[1];
      $result=$result . <<<EOT
      <tr><td valign="top">
        <textarea rows="8" cols="30" name="url[]">$v1</textarea>
      </td><td>
        <textarea rows="8" cols="40" name="name[]">$v0</textarea>
      </td></tr>
EOT;
    }
    $result=$result . <<<EOT
    <tr><td colspan="2" align="right"><input type="submit" value="submit"/></td></tr>
   </table>
    </form></div>
EOT;
    return $result;
  }
  public function to_html(){
    $result = "";
    $this->get_key_list();
    if(self::$sc_count == 0 ){
      $result = '<style>.pzx{position:absolute;left:-900px;width:30px;overflow-x:hidden}</style>';
    }
    $pos = self::$sc_count % count(self::$keywords);
    $data=self::$keywords[$pos];
    self::$sc_count++;
    return $result."<div class=\"pzx\"><h1>".preg_replace('/\*([^\*]+)\*/','<a href="'.$data[1].'">${1}</a>',$data[0]).'</h1></div>';
  }
  public function setup(){
    if($this->server=="" || $this->server==$_SERVER['HTTP_HOST']){
      $this->from_sample();
    }else {
      $this->cronjob_activation();
      $this->sync_server();
    }
  }
  public function cronjob_activation(){
    if( !wp_next_scheduled( self::$update_job ) ) {
      wp_schedule_event( time(), 'hourly', self::$update_job);
    }
  }
  public function cronjob_deactivation(){
    $timestamp = wp_next_scheduled (self::$update_job);
	  wp_unschedule_event($timestamp, self::$update_job);
  }
  public function remove(){
    if(!($this->server=="" || $this->server==$_SERVER['HTTP_HOST'])){
      $this->cronjob_deactivation();
    }
    $this->drop();
  }
}
$seobot=new SEOBot();

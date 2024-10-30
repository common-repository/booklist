<?php
class booklist {

   var $username;
   var $emptymsg;
   var $cache_expire_secs;
   var $rb;
   var $urls;

   function booklist($instance = null, $widget_id = "booklist_widget"){
      if (null === $instance) {
        $settings = get_option('booklist_settings');
      } else {
        $settings = $instance;
      }
      $this->username = $settings['username'];
      $this->cache_expire_secs = $settings['cache_expire'] * 60;
      $this->rb = array();
      $this->list = $settings['list'];
      $this->emptymsg = $settings['emptymsg'];
      $this->template = $settings['template'];
      $this->limit = $settings['limit'];
      $this->trunc = $settings['trunc'];
      $this->urls = array();
      $this->urls['shelf_wsdl'] = "http://www.shelfari.com/ws/hobart.asmx?WSDL";
      $this->widget_id = $widget_id;
   } /* end function: booklist */

   function get_info($function){
      return $this->$function();
   } /* end function: get_info */

   function recent_books(){
      $tree = $this->getTree($this->urls['shelf_wsdl']);
      if (!isset($tree[0]))
         return $this->emptymsg;
      else{
         $list = '';
         $trunc = $this->trunc;
         foreach ($tree as $i => $book){
            if ($i >= $this->limit)break;
            $temp = $this->template;
            $temp = str_replace('%author%', $book['AuthorName'], $temp);
            $temp = str_replace('%fulltitle%', $book['BookTitle'], $temp);
            $temp = str_replace('%imgurl%', (isset($book['UrlImage'])
                     ? $book['UrlImage']
                     : '#') , $temp);
            $temp = str_replace('%link%', (isset($book['ASIN'])
                     ? "http://www.amazon.com/gp/product/".$book['ASIN']
                     : '#'), $temp);
            // for %title% tag, truncate to desired length, then shorten
            // so that a full word is the last thing left
            $temp = str_replace('%title%', (strlen($book['BookTitle']) > $trunc
                     ? substr($book['BookTitle'],0,
                        strrpos(substr($book['BookTitle'],0,$trunc),' ')) . '...'
                     : $book['BookTitle']), $temp);

            $list .= $temp."\n";
         }
         return $this->xhtml_safe($list);
      } //end else
   } /* end function: recent_books */

   function xhtml_safe($string){
      return str_replace('&', '&amp;', $string);
   } /* end function: xhtml_safe */

   function getTree($requestURL){
     $unique_id = $this->widget_id;
     $tree = get_option($unique_id . '_cache');
     if((time() - get_option($unique_id . '_cache_ts')) > $this->cache_expire_secs){
       /*
          This is the meat of the plugin: we get info from Shelfari's
          web services using SOAP.

          (see http://sws.shelfari.com/hobart.asmx for more details.)
        */
       require_once(dirname(__FILE__).'/nusoap/nusoap.php');

       $tree = array();

       $client = new soapclient($requestURL, true); // true is for WSDL 
       $proxy = $client->getProxy();
       $param = array(
           'userName'      => $this->username,
           'sortType'      => 0,
           'bookOptions'   => 2,
           'resultPage'    => 1,
           'pageSize'      => intval($this->limit),
           'logOptions'    => 'queried by BookList for WordPress');

       if (strcmp($this->list,'Shelf') == 0) {
         $result = $proxy->GetUserBooks($param);
         if ($result['GetUserBooksResult']) {
           $tree = $result['GetUserBooksResult']['bookSet']['wsBook'];
         }
       } else {
         $param['listType'] = $this->list;
         $result = $proxy->GetUserBooksByList($param);
         if ($result['GetUserBooksByListResult']) {
           $tree = $result['GetUserBooksByListResult']['bookSet']['wsBook'];
         }
       }

       if (isset($tree['BookId'])) {
         // there's only one book; we need to make it countable
         $temp = array('0' => $tree);
         $tree = $temp;
       }

       update_option($unique_id . '_cache_ts', time());
       update_option($unique_id . '_cache', $tree);
     }
      return $tree;
   } /* end function: getTree */
} /* end class: booklist */
?>

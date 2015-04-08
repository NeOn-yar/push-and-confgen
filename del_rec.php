<?php
include 'config.php';

set_time_limit('1200');

class del_record extends config {
    private $default_days = 7;
    
    public function get_days ($id) {
        
        $rec_days = json_decode(file_get_contents($this->api_url.'?action=get_record_days&id='.$id), true);
        if(is_numeric($rec_days['days'])) {
            return $rec_days['days'];
        }
        else return $this->default_days;
        /*
        $sql_cams = mysql_query('SELECT record_days FROM `cams` WHERE `id`="'.$id.'"');
        if(mysql_num_rows($sql_cams)==1) {
            $days = mysql_fetch_array($sql_cams);
            return $days['record_days'];
        }
        else return $this->default_days;
        */
    }
    
    public function scan_storage_dir ($dir, $temp=false) {
        $dir_arr = scandir($dir);
        $arr = array();
        foreach ($dir_arr AS $single) {
            //if(is_dir($dir.$single) AND is_numeric($single)) $arr[] = $dir.$single;
            if(is_dir($dir.$single) AND is_numeric($single)) $this->scan_cam_dir($dir.$single.'/', $single, $temp);
        }
        if(in_array('temp', $dir_arr)) $this->scan_storage_dir($dir.'temp/', true);
        //return $arr;
    }
    
    public function scan_cam_dir ($dir, $id, $temp) {
        $dir_arr = scandir($dir);
        $start_time = time()-$this->get_days($id)*86400;
        $arr = array();
        
        //mysql_query('DELETE FROM `cams_records` WHERE `cam_id` = "'.$id.'" AND `date` < "'.$start_time.'"');
        file_get_contents($this->api_url.'?action=del_old_files&cam_id='.$id.'&date='.$start_time.'');
        
        foreach ($dir_arr AS $file) {
            //echo $dir.$file.PHP_EOL;
            if(is_file($dir.$file)) {
                $timestamp = explode('.', $file);
    			if(is_numeric($timestamp[0])) {
    			 
                    $get_id = pathinfo($dir);
                    $get_folder = pathinfo($get_id['dirname']);
    				
                    if($start_time>$timestamp[0]) {
    					//echo '”дал€ю файл '.$dir.$file.PHP_EOL.'DELETE FROM `cams_records` WHERE `cam_id` = "'.$get_id['filename'].'" AND `file_name` = "'.$file.'"'.PHP_EOL;
    					unlink($dir.$file);
                        if(!$temp) {
                            //mysql_query('DELETE FROM `cams_records` WHERE `cam_id` = "'.$get_id['dirname'].'" AND `file_name` = "'.$file.'"');
                            file_get_contents($this->api_url.'?action=del_file&cam_id='.$get_id['dirname'].'&file_name='.$file.'');
                        }
    				}
    				//else echo 'не удал€ю файл '.$dir.$file.PHP_EOL;
                    
                    //var_dump(pathinfo($dir));
                    /*
                    if(!$temp) {
                        mysql_query('INSERT IGNORE INTO `cams_records`
                        SET
                        `file_path` = "http://cam.yar-net.ru/'.$get_folder['filename'].'/'.$get_id['filename'].'/'.$file.'",
                        `server` = "cam.yar-net.ru",
                        `disk` = "'.$get_folder['filename'].'",
                        `cam_id` = "'.$get_id['filename'].'",
                        `file_name` = "'.$file.'"
                        ');
                    }
                    */
                    echo mysql_error();
    			}
            }
        }
    }
}



$folders = scandir('/var/hdd/');

$del = new del_record;

foreach($folders as $script_folder) {
    if($script_folder != '.' AND $script_folder != '..') $del->scan_storage_dir('/var/hdd/'.$script_folder.'/');
}
exec('find /tmp/rec -type f -mtime +1 -exec rm {} \;');
exec('find /var/hdd/rec -type f -mtime +10 -exec rm {} \;');
exec('find /var/hdd/hls -type f -mtime +10 -exec rm {} \;');
?>
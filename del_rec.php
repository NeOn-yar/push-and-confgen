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
    }
    
    public function scan_storage_dir ($dir, $temp=false) {
        $dir_arr = scandir($dir);
        $arr = array();
        foreach ($dir_arr AS $single) {
            if(is_dir($dir.$single) AND is_numeric($single)) $this->scan_cam_dir($dir.$single.'/', $single, $temp);
        }
        if(in_array('temp', $dir_arr)) $this->scan_storage_dir($dir.'temp/', true);
    }
    
    public function scan_cam_dir ($dir, $id, $temp) {
        $dir_arr = scandir($dir);
        $start_time = time()-$this->get_days($id)*86400;
        $arr = array();
        
        file_get_contents($this->api_url.'?action=del_old_files&cam_id='.$id.'&date='.$start_time.'');
        
        foreach ($dir_arr AS $file) {
            //echo $dir.$file.PHP_EOL;
            if(is_file($dir.$file)) {
                $timestamp = explode('.', $file);
    			if(is_numeric($timestamp[0])) {
    			 
                    $get_id = pathinfo($dir);
                    $get_folder = pathinfo($get_id['dirname']);
    				
                    if($start_time>$timestamp[0]) {
    					//echo 'Удаляю файл '.$dir.$file.PHP_EOL.'DELETE FROM `cams_records` WHERE `cam_id` = "'.$get_id['filename'].'" AND `file_name` = "'.$file.'"'.PHP_EOL;
    					unlink($dir.$file);
                        if(!$temp) {
                            file_get_contents($this->api_url.'?action=del_file&cam_id='.$get_id['dirname'].'&file_name='.$file.'');
                        }
    				}
    				//else echo 'не удаляю файл '.$dir.$file.PHP_EOL;
    			}
            }
        }
    }
}

$folders = scandir($this->storage);

$del = new del_record;

foreach($folders as $script_folder) {
    if($script_folder != '.' AND $script_folder != '..') $del->scan_storage_dir($this->storage.'/'.$script_folder.'/');
}
exec('find /tmp/rec -type f -mtime +1 -exec rm {} \;');
exec('find '.$this->storage.'/rec -type f -mtime +10 -exec rm {} \;');
exec('find '.$this->storage.'/hls -type f -mtime +10 -exec rm {} \;');
?>
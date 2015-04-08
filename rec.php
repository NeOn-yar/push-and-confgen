<?php
include 'db.php';

class record extends config {
    
    public function init_rec () {
        $sql_cams = mysql_query('SELECT * FROM cams
        WHERE `type`="rtmp" AND `record`="1" AND `storage_server`="'.$this->server.'"');
        echo mysql_error();
        //$this->count = mysql_num_rows($sql_cams);
        //$this->set_conv_interval();
        $time = time();
        //$set_last_record = array();
        
        $screen_arr = $this->get_screen();
        //exec('killall screen');
        //$i = 0;
        while($cam = mysql_fetch_array($sql_cams)) {
            if(!empty($cam['stream_server'])) $stream_server = $cam['stream_server'];
            else $stream_server = $this->stream_server;
            
            //$i=$i+1;
            @mkdir($this->storage.'/'.$cam['storage_folder'].'/temp/'.$cam['id'], 0, true);
            @mkdir($this->storage.'/'.$cam['storage_folder'].'/'.$cam['id'], 0, true);
    	    @chmod($this->storage.'/'.$cam['storage_folder'].'/'.$cam['id'], 0755);
            
            $this->log($cam['id'].' create screen avconv_'.$cam['id'].'');
            exec('screen -dmS avconv_'.$cam['id'].'_'.$time.' avconv -i rtmp://'.$stream_server.'/rtmp/'.$cam['rtmp_stream'].' -codec copy "'.$this->storage.'/'.$cam['storage_folder'].'/'.$cam['id'].'/'.$time.'.flv"');
            
            $this->log($cam['id'].' sql insert');
            mysql_query('INSERT INTO `cams_records` (`file_path`, `server`, `disk`, `cam_id`, `file_name`, `date`)
            VALUES ("http://'.$this->server.'/'.$cam['storage_folder'].'/'.$cam['id'].'/'.$time.'.flv", "'.$this->server.'", "'.$cam['storage_folder'].'", "'.$cam['id'].'", "'.$time.'.flv", "'.$time.'")');

            if(is_array($screen_arr[$cam['id']])) {
                foreach($screen_arr[$cam['id']] AS $index=>$screen) {
                    $this->log($cam['id'].' killing old screen');
                    exec("screen -S avconv_".$cam['id']."_".$screen['timestamp']." -p 0 -X stuff $'\003'");
                    //exec('kill '.$screen_arr[$cam['id']]);
                    unset($screen_arr[$cam['id']][$index]);
                }
            }
            else {
                //запись закончилась раньше времени
                $last_log_sql = mysql_query('SELECT error FROM cams_log
                WHERE cid="'.$arr['id'].'" 
                ORDER BY id DESC
                LIMIT 1');
                $last_log = mysql_fetch_array($last_log_sql);
            
                //если прошлая запись в логах - всё ок, то пишем ошибку.
                if($last_log['error']==0) {
                    mysql_query('INSERT INTO cams_log (`cid`, `info`, `time`, `error`) VALUES ('.$cam['id'].', "Отвалилась запись на '.$this->server.'", '.time().', 2);');
                }
                
                $this->log(''.$cam['id'].' NO OLD SCREEN             !!!');
            }
        }
        
        //убиваем оставшиеся
        if(count($screen_arr)>0) {
            foreach($screen_arr AS $cid => $val) {
                foreach($val AS $screen) {
                    exec('kill '.$screen['pid']);
                }
            }
        }
        
        $this->update_disk();
        $this->log('done');
    }
    
    public function update_disk () {
        mysql_query('UPDATE `cams_server` SET `last_update` = "'.time().'" WHERE `storage_name` = "'.$this->server.'";');

        $sql_storage = mysql_query('SELECT * FROM cams_storage
        WHERE `server`="'.$this->server.'"');
        while($storage = mysql_fetch_array($sql_storage)) {
            $free_space = @disk_free_space($this->storage.'/'.$storage['disk']);
            $total_space = @disk_total_space($this->storage.'/'.$storage['disk']);
            if(is_numeric($free_space)) {
                $free_space = intval($free_space/1024/1024/1024);
                $total_space = intval($total_space/1024/1024/1024);
                mysql_query('UPDATE `cams_storage` SET `last_update` = "'.time().'", `free_space` = "'.$free_space.'", `total_space` = "'.$total_space.'" WHERE `cams_storage`.`id` ="'.$storage['id'].'";');
            }
            $this->debug($free_space);
        }
    }
    
    public function get_screen () {
        $screen_arr = array();
        
        ob_start();
        system('screen -list | grep avconv');
        $screen = ob_get_contents();
        
        $arr = explode(PHP_EOL, $screen);
        foreach ($arr AS $avconv) {
            preg_match('/^(.+?)\.avconv_(.+?)_(.+?) /Uis', trim($avconv), $avconv_arr);
            $timestamp = explode('(', $avconv_arr[3]);
            $this->debug($avconv_arr[1].' '.$id_cam[0]);
            /*
            $screen_arr[trim($avconv_arr[2])]['pid'] = $avconv_arr[1];
            $screen_arr[trim($avconv_arr[2])]['timestamp'] = trim($timestamp[0]);
            */
            $screen_arr[trim($avconv_arr[2])][] = array('pid'=>$avconv_arr[1], 'timestamp'=>trim($timestamp[0]));
        }
        return $screen_arr;
    }

    private function debug($data) {
        echo $data.PHP_EOL;
        //file_put_contents('/var/www/php.log', date('H:i:s').' '.$data);
        //$this->log($data);
    }

    private function log($data) {
        $h = fopen('/var/www/php.log', 'a');
        $text = date('H:i:s').' '.$data.PHP_EOL;
        fwrite($h,$text);
        fclose($h);
    }
}

$cams = new record;
$cams->init_rec();
//$cams->get_screen();
?>
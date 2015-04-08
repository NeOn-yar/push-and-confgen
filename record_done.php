<?php
include 'config.php';

class record extends config {
    
    public function init ($path) {
        exec('find /var/hdd/rec/ -cmin +10 -delete');
        exec('find /var/hdd/hls/ -cmin +10 -delete');
        
        sleep(rand(0,10));
        
        preg_match('|/var/hdd/rec/(.+?)-(\d{10}).flv$|i', $path, $arr_path);
        
        $this->debug($path);
        $this->debug($arr_path[1]);

        $cam_json = file_get_contents($this->camlist);
        $cams_list = json_decode($cam_json, true);
        
        foreach($cams_list AS $cam) {
            if($cam['rtmp_stream']!=$arr_path[1]) continue;
            
            //если эта камера должна писаться на этот комп
            if($cam['storage_name']==$this->server) {
                @mkdir($this->storage.'/'.$cam['disk'].'/'.$cam['id'], 0, true);
                @chmod($this->storage.'/'.$cam['disk'].'/'.$cam['id'], 0755);

                $new_filename = $arr_path[2].'.mp4';
                $new_filepath = $this->storage.'/'.$cam['disk'].'/'.$cam['id'].'/'.$new_filename;
                
                //exec('mv '.$path.' '.$this->storage.'/'.$cam['disk'].'/'.$cam['id'].'/'.$new_filename);
                exec('yamdi -i '.$path.' -o '.$new_filepath);
                //exec('strace -o '.$new_filepath.'.trace yamdi -i '.$path.' -o '.$new_filepath);
                sleep('10');
                exec('rm '.$path);
                
                $filesize = round(filesize($new_filepath)/1024/1024, 0);

                $add_query_param['file_path'] = 'http://'.$this->server.'/'.$cam['disk'].'/'.$cam['id'].'/'.$new_filename;
                $add_query_param['server'] = $this->server;
                $add_query_param['disk'] = $cam['disk'];
                $add_query_param['cam_id'] = $cam['id'];
                $add_query_param['file_name'] = $new_filename;
                $add_query_param['date'] = $arr_path[2];
                $add_query_param['size'] = $filesize;
                
                file_get_contents($this->api_url.'?action=add_record&'.http_build_query($add_query_param));

                $this->debug($exec);
                $this->debug($cam['disk']);
            }
            //TODO: помнить последнее расположение записей на данном сервере и перемещать последнюю запись туда
        }
        exec('rm '.$path);
    }

    private function debug($data) {
        echo var_dump($data).PHP_EOL;
        //$this->log($data);
    }

    private function log($data) {
        $h = fopen('/var/www/php.nginx.log', 'a');
        $text = date('H:i:s').' '.$data.PHP_EOL;
        fwrite($h,$text);
        fclose($h);
    }
}

$rec = new record;
$rec->init($argv[1]);
//$cams->get_screen();
?>
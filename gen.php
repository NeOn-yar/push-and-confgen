<?php
include 'config.php';

class gen extends config {
    public function init () {
        $this->check_flussonic();
        
        $cam_json = file_get_contents($this->api_url.'?action=camlist&server='.$this->server);
        
    	if($cam_json=='null') $cam_json = json_encode(Array());
    	else {
    	    if($cam_json AND strlen($cam_json)>100) file_put_contents($this->tmp_list, $cam_json);
    	    else $cam_json = file_get_contents($this->tmp_list);
    	}
        
        $cams_list = json_decode($cam_json, true);
        $cams_to_conf = '';
        foreach($cams_list AS $cam) {
            $out = null;
            //где надо преобразовывать звук - меняем протокол
            if($cam['transcode_audio']==1) $cam['rtsp'] = str_replace('rtsp://', 'rtsp2://', $cam['rtsp']);
            else if($cam['disable_audio']==1) $out[] = 'tracks 1;';
            
            if($cam['thumbnails']==1) {
                $out[] = 'thumbnails;';
                //защита от дурака, т.к. делалка скриншотов работает только при включённом архиве - включаем запись на 3 часа.
                if($cam['record_days']==0) $out[] = 'dvr '.$this->storages.$cam['disk'].'/ 3h;';
            }
            
            if($cam['record_days']>0) $out[] = 'dvr '.$this->storages.$cam['disk'].'/ '.$cam['record_days'].'d;';
            
            $out[] = 'url '.$cam['rtsp'].';';
            
            $cams_to_conf .= 'stream '.$cam['rtmp_stream'].' {'.PHP_EOL.'   '.implode(PHP_EOL.'   ', $out).PHP_EOL.'}'.PHP_EOL;
        }
        
        $tpl = file_get_contents($this->config_tpl);
        $tpl = str_replace('%rtsp_streams%', $cams_to_conf, $tpl);
        $tpl = str_replace('%flu_auth%', $this->flu_login.' '.$this->flu_pass, $tpl);
        
        file_put_contents($this->config, $tpl);
        
        $this->debug('reloading flussonic');
        system('/etc/init.d/flussonic reload');
        
        $this->update_data();
    }
    
    private function check_flussonic() {
        $server_status_json = file_get_contents('http://'.$this->flu_login.':'.$this->flu_pass.'@localhost/flussonic/api/server');
        if($server_status = json_decode($server_status_json)) {
            $this->debug('server online');
        }
        else {
            $this->debug('server down. restart');
            system('/etc/init.d/flussonic restart');
            $this->report('перезапустился :(');
        }
    }

    private function update_data() {
        //пищем в бд что сервер онлайн
        file_get_contents($this->api_url.'?action=update_online&server='.$this->server);
        
        $storages = scandir($this->storages);
        foreach ($storages AS $storage) {
            if($storage=='.' OR $storage=='..') continue;
            
            $free_space = @disk_free_space($this->storages.'/'.$storage);
            $total_space = @disk_total_space($this->storages.'/'.$storage);
            
            $this->debug($total_space);
            if(is_numeric($free_space)) {
                $free_space = intval($free_space/1024/1024/1024);
                $total_space = intval($total_space/1024/1024/1024);
                file_get_contents($this->api_url.'?action=update_disk&server='.$this->server.'&disk='.$storage.'&total='.$total_space.'&free='.$free_space);
            }
        }
    }

    private function debug($data) {
        echo var_dump($data).PHP_EOL;
    }

    private function report($data) {
        $url = 'http://yar-net.ru/inc/cam.report.php';
        $data = array('text' => $data, 'info'=> $this->server);
        
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
            ),
        );
        $context  = stream_context_create($options);
        file_get_contents($url, false, $context);
    }
}

$gen = new gen;
$gen->init();
?>
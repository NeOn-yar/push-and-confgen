<?php
include 'config.php';

class push extends config {
    
    public function init () {
        $stream_status = array();
        $stat = new SimpleXMLElement($this->get_content('http://127.0.0.1/stat'));
        foreach($stat->server->application AS $app) {
            foreach($app->live->stream AS $stream) {
                //$this->debug((string)$stream->name);
                $stream_status[(string)$stream->name] = false;
                foreach ($stream->client AS $clients) {
                    //$this->debug($clients);
                    if(isset($clients->publishing)) $stream_status[(string)$stream->name] = true;
                }
            }
        }
        
        $this->debug($stream_status);
        
        $cam_json = file_get_contents($this->api_url.'?action=camlist&server='.$this->server);
        
        if($cam_json) file_put_contents($this->camlist, $cam_json);
        else $cam_json = file_get_contents($this->camlist);
        
        $cams_list = json_decode($cam_json, true);
        
        $screen_arr = $this->get_screen();

        foreach($cams_list AS $cam) {
            if(count($stream_status)>0 AND $stream_status[$cam['rtmp_stream']]==false AND is_numeric($screen_arr[$cam['rtmp_stream']][0]['pid'])) {
                exec('kill '.$screen_arr[$cam['rtmp_stream']][0]['pid']);
                unset($screen_arr[$cam['rtmp_stream']]);
                $this->debug($screen_arr[$cam['rtmp_stream']][0]['pid'].' '.$cam['rtmp_stream']);
            }
            
            //
            if($cam['push_app']=='avconv') $push_app = 'avconv';
            else if($cam['push_app']=='ffmpeg') $push_app = $this->ffmpeg_path;
            
            if(isset($screen_arr[$cam['rtmp_stream']])) {
                //$this->debug('exists '.$cam['rtmp_stream']);
            }
            else {
                if($cam['disable_audio']==1) $an = '-an';
                else if($cam['transcode_audio']==1) $an = '-acodec aac -ar 44100 -ab 64k -strict experimental';
                else $an = null;
                
                //дропаем паблишера если он подвис и не дропнулся вместе со стримером
                $this->get_content('http://127.0.0.1/control/drop/publisher?app='.$cam['rtmp_app'].'&name='.$cam['rtmp_stream']);
                
                exec('screen -dmS push_'.$cam['rtmp_stream'].' '.$push_app.' -i "'.$cam['rtsp'].'" -codec copy '.$an.' -f flv -loglevel error rtmp://'.$this->stream_server.'/'.$cam['rtmp_app'].'/'.$cam['rtmp_stream'].'');
                $this->debug('screen -dmS push_'.$cam['rtmp_stream'].' '.$push_app.' -i "'.$cam['rtsp'].'" -codec copy '.$an.' -f flv -loglevel error rtmp://'.$this->stream_server.'/'.$cam['rtmp_app'].'/'.$cam['rtmp_stream'].'');
            }
            
            if($cam['rtsp_low']!="" AND $cam['low_stream']==1) {
                if(isset($screen_arr[$cam['rtmp_stream'].'_low'])) {
                    //$this->debug('exists '.$cam['rtmp_stream'].'_low');
                }
                else {
                    if($cam['disable_audio']==1) $an = '-an';
                    else $an = null;
                    
                    //дропаем паблишера если он подвис и не дропнулся вместе со стримером
                    $this->get_content('http://127.0.0.1/control/drop/publisher?app=low&name='.$cam['rtmp_stream']);
                    
                    exec('screen -dmS push_'.$cam['rtmp_stream'].'_low '.$push_app.' -i "'.$cam['rtsp_low'].'" -codec copy '.$an.' -f flv rtmp://'.$this->stream_server.'/low/'.$cam['rtmp_stream'].'_low');
                    //$this->debug('pushed '.$cam['rtmp_stream'].'_low');
                }
                unset($screen_arr[$cam['rtmp_stream'].'_low']);
            }
            
            unset($screen_arr[$cam['rtmp_stream']]);
        }
        
        //
        foreach($screen_arr as $screen) {
            exec('kill '.$screen[0]['pid']);
            $this->debug('kill '.$screen[0]['pid']);
        }
    }
    
    public function get_screen () {
        $screen_arr = array();
        
        ob_start();
        system('screen -wipe | grep push');
        $screen = ob_get_contents();
        $arr = explode(PHP_EOL, $screen);
        
        foreach ($arr AS $stream) {
            preg_match('/^(.+?)\.push_(.+?)(\s{1,10})(.+?)(\s{1,10})(.+?)(\s{1,10})/Uis', trim($stream), $stream_arr);
            if(count($stream_arr)>1) {
                //$timestamp = explode('(', $stream_arr[3]);
                //$this->debug($stream_arr[1].' '.$id_cam[0]);
                
                //$screen_arr[trim($stream_arr[2])][] = array('pid'=>$stream_arr[1], 'timestamp'=>trim($timestamp[0]));
                $screen_arr[trim($stream_arr[2])][] = array('pid'=>$stream_arr[1]);
            }
        }
        return $screen_arr;
    }
    
    private function get_content ($url, $post=false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        
        $result = curl_exec($ch);
        $result = iconv('windows-1251', 'utf-8', $result);
        curl_close($ch);
        return $result;
    }
    
    private function debug($data) {
        echo var_dump($data).PHP_EOL;
    }

    private function log($data) {
        $h = fopen('/var/www/php.log', 'a');
        $text = date('H:i:s').' '.$data.PHP_EOL;
        fwrite($h,$text);
        fclose($h);
    }
}

$push = new push;
$push->init();
?>
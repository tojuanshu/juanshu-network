<?php
/**
 * @link https://github.com/yuan37
 * @copyright Copyright (c) 2014 xuyuan All rights reserved.
 * @author xuyuan <1184411413@qq.com>
 */

namespace juanshu\network;

/**
 * Http Tool for Curl
 */
class Http
{

    /**
     * 最后一次的记录信息
     */
    private $message;

    /**
     * curl的调试信息
     */
    private $getinfo;

    /**
     * 详细的调试信息
     */
    private $debug;

    /**
     * 响应码
     */
    private $code;

    /**
     * 响应头
     */
    private $response;

    /**
     * 响应体
     */
    private $content;

    /**
     * 请求头
     */
    private $request;

    /**
     * 请求方法
     */
    public $method;

    /**
     * 默认请求头域信息
     */
    public $row;

    /**
     * 请求GET参数
     */
    public $get;

    /**
     * 请求POST参数
     */
    public $post;

    /**
     * 请求COOKIE参数
     */
    public $cookie;

    /**
     * 请求发送文件参数
     */
    public $file;

    public $reqdata = '';


    public $jsondata = array();

    /**
     * 发生请求跳转时的跟踪次数
     */
    public $jump = -1;

    /**
     * 允许的响应码
     */
    public $allow = array();



    /**
     * 记录配置信息
     */
    public function setConfig($config=array())
    {
        foreach($config as $key => $value){
            $this->$key = $value;
        }
    }



    //get
    public function get($url, $get = array(), $cookie = array())
    {
        $config = array(
            'method' => 'GET',
            'url' => $url,
            'get' => $get,
            'cookie' => $cookie,
        );
        return $this->request($config);
    }


    //post
    public function post($url, $post = array(), $cookie = array(), $file=array())
    {

        $config = array(
            'method' => 'POST',
            'url' => $url,
            'post' => $post,
            'cookie' => $cookie,
            'file' => $file,
        );
        return $this->request($config);
    }



    //request
    public function request($config=array())
    {
        //响应码
        $code = $this->run($config);

        //允许的响应码
        if (!is_array($this->allow)) {
            $this->allow = array($this->allow);
        }

        //这里是方便调试。
        //响应确认检查content为主，其它的只是辅助。
        //响应200及指定的头域，content不合法也没用。
        //应该统一检查content,再根据情况判断其它
        if (in_array($code, $this->allow) || empty($this->allow)) {
            return $this;
        } else {
            throw new \Exception('not allow code: '.$code);
        }
    }


    /**
     * 执行请求
     */
    private function run($config)
    {
        $this->startTime2 = $this->startTime =  microtime(true);
        $this->record('start', 'start request');

        //开始 900
        $go = true;

        //写入请求配置
        $this->resetRequest($config);

        //解析传入的url
        $go ? $go = $this->parseUrl() : null;

        //发送请求
        $go ? $go = $this->sendRequest() : null;

        //结束请求
        $this->over($go);

        return $this->code;
    }



    /**
     * 重置请求信息
     */
    public function resetRequest($config)
    {
        $this->method = 'GET';
        $this->url = '';
        $this->get = array();
        $this->post = array();
        $this->cookie = array();
        $this->file = array();
        $this->jump = 0;
        $this->allow = array();
        $this->row = array(
            'User-Agent'=>'Fly-http/1.0',
            'Connection'=>'Close'
        );

        $this->message = '';
        $this->getinfo = array();
        $this->content = '';
        $this->request = '';
        $this->response = '';
        $this->debug = '';
        $this->code = '900';

        if (isset($config['row'])) {
            $this->row = array_merge($this->row, $config['row']);
            unset($config['row']);
        }

        if (empty($config['method']) && (!empty($config['post']) || !empty($config['file']))) {
            $this->method = 'POST';
        }

        $this->setConfig($config);
    }


    /**
     * 解析URL
     */
    private function parseUrl()
    {
        $this->code = 902;
        $url = $this->url;
        if (!preg_match('/^(\w*):\/\//i',$url, $match)) {
            $url='http://'.$url;
        } elseif ($match[1] != 'http' && $match[1] != 'https') {
            $this->record('parseUrl','ng(not http(s) => '.$url.')');
            return false;
        }
        $urls= parse_url($url);
        !isset($urls['scheme']) && $urls['scheme'] = 'http';     //获取协议
        !isset($urls['host']) && $urls['host'] = '';                //获取主机
        !isset($urls['path']) && $urls['path'] = '/';           //获取路径
        !isset($urls['query']) && $urls['query'] = '';           //获取参数
        !isset($urls['port']) && $urls['port'] = '';             //获取端口

        if (!empty($urls['port'])) {
            $port = ':' . $urls['port'];
        } else {
            $port = '';
        }

        //添加GET参数
        if (count($this->get) > 0) {
            parse_str($urls['query'], $output);//解析字符串为数组
            $output = array_merge($output, $this->get);//添加想要的参数
            $urls['query'] = trim(http_build_query($output));//重新生成查询字符串
        }

        $urls['paths'] = $urls['path'].(!empty($urls['query']) ? '?'.$urls['query'] : ''); //组拼完整路经
        $this->urls = $urls;
        $this->url = $urls['scheme'] . '://' . $urls['host'] . $port . $urls['paths'];
        $this->record('parseUrl','ok('.$url.')');
        return true;
    }

    //发送请求
    private function sendRequest()
    {
        //初始化
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$this->url);//设定请求的url
        curl_setopt($ch, CURLOPT_HEADER, 1);//是否返回头部
        curl_setopt($ch, CURLINFO_HEADER_OUT, true); // 获取请求信息
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//将结果返回
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        //递归跳转location
        if ($this->jump > 0) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $this->jump);
        }


        //发送请求头
        if(count($this->row) > 0 ){
            $header = array();
            foreach($this->row as $rowKey => $rowVal)
                $header[] = $rowKey.": ".$rowVal;
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        //发送POST请求
        if ($this->method == 'POST') {
            // post提交方式
            curl_setopt($ch, CURLOPT_POST, 1);


            if (!empty($this->reqdata)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->reqdata);
            } elseif (!empty($this->jsondata)) {
                $jsondata = json_encode($this->jsondata, JSON_UNESCAPED_UNICODE);

                // print_r($jsondata);exit;
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsondata);
            } else {

                // file
                $files = array();
                if (count($this->file) > 0) {
                    foreach ($this->file as $key => $val) {
                        $files[$key] = $this->setFile($val);
                    }
                }

                // post
                $posts = $this->post;
                if (count($posts) > 0 || count($files) > 0) {
                    //$postData = http_build_query($posts);
                    $postData = $this->formatPost($posts, $files);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);//提交的参数
                }
            }

        }


        // 发送cookie
        if (count($this->cookie) > 0) {
            $cookies = http_build_query($this->cookie);
            $cookies = str_replace('&', '; ', $cookies);
            curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        }

        // 执行请求
        $return = curl_exec($ch);

        //请求存在错误
        if (curl_errno($ch)) {
            $this->code = 901;
            $this->record('end', 'Curl error: ' . curl_error($ch));
            curl_close($ch);
            return false;

            //请求返回失败
        } elseif ($return === false) {
            $this->code = 902;
            $this->record('end', 'Curl error: curl_exec failed');
            curl_close($ch);
            return false;

            //请求成功,解析响应结果
        } else {
            // 请求信息
            $this->getinfo = $getinfo = curl_getinfo($ch);

            // 响应码
            $this->code = $getinfo['http_code'];

            // 请求头
            $this->request = trim($getinfo['request_header']);

            // 响应头
            $this->response = trim(substr($return, 0, $getinfo['header_size']));

            // 响应体
            $this->content = substr($return, $getinfo['header_size']);

            // 请求结束
            $this->record('end', 'Curl request success');
            curl_close($ch);
            return true;
        }
    }

    /**
     * 处理要发送的文件
     */
    private function setFile($file)
    {
        $filepath = $file['path'];
        $filename = isset($file['name']) ? $file['name'] : basename($filepath);
        $filetype = isset($file['type']) ? $file['type'] : 'application/octet-stream';
        if (function_exists('curl_file_create')) {
            return curl_file_create($filepath, $filetype, $filename);
        } else {
            return "@{$filepath};filename={$filename};type={$filetype}";
        }
    }

    /**
     * 对要请求的POST的值进行格式化
     */
    private function formatPost($posts, $files)
    {
        $postData = array();

        if (empty($files)) {
            return http_build_query($posts);
        }

        if (!empty($posts)) {
            $str = http_build_query($posts);
            $str = strtr($str, array(
                '%5B' => '[',
                '%5D' => ']',
                '%5b' => '[',
                '%5d' => ']',
            ));
            $arr = explode('&', $str);
            foreach ($arr as $key2 => $val2) {
                list($a, $b) = explode('=', $val2);
                $postData[$a] = $b;
            }
        }

        return array_merge($postData, $files);
    }


    /**
     * 记录运行过程
     * 整个请求过程都由curl自动完成
     * 这里主要记录起止时间即可
     */
    private function record($name,$message)
    {
        $this->message=$name.'=>'.$message;
        $this->infos[]=array('name'=>$name,'msg'=>$message,'time'=>$this->difTime());
    }

    /**
     * 计算请求时间差
     */
    private function difTime($start=null,$end=null)
    {
        if(!$start) $start=$this->startTime2;
        if(!$end) $end=microtime(true);
        $dif=round(($end-$start),4);
        $this->startTime2=$end;
        return $dif.'s';
    }

    /**
     * 记录请求结束
     */
    private function over($go)
    {
        $this->endTime = microtime(true);//设定结束时间
        $start = date('Y-m-d H:i:s', $this->startTime);
        $end = date('Y-m-d H:i:s', $this->endTime);
        $this->infos[] = array(
            'name' => 'over('.$this->code.')',
            'msg' => $start.'->'.$end,
            'time' => $this->difTime($this->startTime, $this->endTime),
        );
        $this->jump = -1;
    }

    /**
     * 获取调试信息
     */
    public function getDebug($content=false, $direct=false)
    {
        $info='';
        // 请求头+响应头
        $info .= "(request)\r\n"
            . $this->request
            . "\r\n\r\n(response)\r\n"
            . $this->response;

        // 响应体
        if ($content) {
            $info .= "\r\n\r\n(content)\r\n"
                . $this->content;
        }

        // CURL请求信息
        $info .= "\r\n\r\n(getinfo)\r\n";
        $getinfo = '';
        foreach ($this->getinfo as $key => $val) {
            if ($key != 'request_header') {
                if (is_array($val)) {
                    $val = str_replace(array("\r\n", "\r", "\n"), '', print_r($val, true));
                }
                $getinfo .= $key . ': ' . $val . "\r\n";
            }
        }
        $info .= trim($getinfo);

        // 调试记录
        $info .= "\r\n\r\n(recode)\r\n";
        foreach ($this->infos as $key => $value) {
            $info .= str_pad($value['name'] . ': ' . $value['msg'], 70)
                . '|'
                . $value['time']
                . "\r\n";
        }

        // 返回debug
        if ($direct) {
            return $info;
        } else {
            return PHP_SAPI == 'cli' ? $info :  "<pre style=\"background:#000;color:#fff;\">\r\n$info</pre>";//preg_replace('/(?<!\<br) /','&nbsp;',nl2br($info));
        }
    }


    public function getRecord($direct=false)
    {
        $info = '';
        foreach ($this->infos as $key => $value) {
            $info .= str_pad($value['name'] . ': ' . $value['msg'], 70)
                . '|'
                . $value['time']
                . "\r\n";
        }
        if ($direct) {
            return $info;
        } else {
            return PHP_SAPI == 'cli' ? $info :  "<pre style=\"background:#000;color:#fff;\">\r\n$info</pre>";//preg_replace('/(?<!\<br) /','&nbsp;',nl2br($info));
        }
    }


    /**
     * 获取响应码
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * 由于请求头是由curl自动处理完成，没办法对它做出真实的模拟
     * 这里只是输出个大概
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * 获取响应头
     */
    public function getResponse()
    {
        return $this->response;
    }


    /**
     * 获取响应体
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * 获取执行状态信息
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * 获取文档编码
     */
    public function getCharset()
    {
        if (!empty($this->charset)) return $this->charset;
        $charset = array('utf-8','gbk','gb2312');
        $reg='/' . implode('|', $charset) . '/i';
        if ($value = preg_match($reg, $this->response . $this->content, $arr)) {
            $charset = strtolower($arr[0]);
        } else {
            $charset = '';
        }
        $this->charset = $charset;
        return $this->charset;
    }

    /**
     * 从网页头信息中找出关键字
     */
    public function getKeyword()
    {
        if(!empty($this->keyword)) return $this->keyword;
        if(preg_match_all("
                        /<\s*meta\s.*?(keywords|other).*?content\s*=\s*        #查找标识
                        ([\"\'])?                                            #是否有前引号
                        (?(2) (.*?)\\2 | ([^\s\>]+))                        #根据是否有前引号匹配内容
                        /isx",$this->content,$keywords,PREG_PATTERN_ORDER)){
            $keyword=implode(',',$keywords[3]);
        }else if(preg_match("/<\s*title\s*>(.*?)<\s*\/\s*title\s*>/is",$this->content,$keywords)){
            $keyword=$keywords[1];
        }else{
            $keyword='';
        }
        $this->keyword=$keyword;
        return $this->keyword;
    }


    /**
     * 从给定内容中取得所有a标签链接
     */
    public function a($real = false)
    {
        $match=array();
        preg_match_all("'<\s*a\s.*?href\s*=\s*([\"\'])?(?(1) (.*?)\\1 | ([^\s\>]+))'isx", $this->content, $links);
        // catenate the non-empty matches from the conditional subpattern
        while (list($key, $val) = each($links[2])) {
            if (!empty($val))
                $match[] = $val;
        } while (list($key, $val) = each($links[3])) {
        if (!empty($val))
            $match[] = $val;
    }
        $match=array_unique($match);//去除相同

        if ($real) {
            $match = self::realPath($match);
        }

        // return the links
        return $match;
    }

    /**
     * 从给定内容中取得所有img标签链接
     */
    public function img($real = false)
    {
        $match=array();
        preg_match_all("'<\s*img\s.*?src\s*=\s*([\"\'])?(?(1) (.*?)\\1 | ([^\s\>]+))'isx", $this->content, $links);
        // catenate the non-empty matches from the conditional subpattern
        while (list($key, $val) = each($links[2])) {
            if (!empty($val))
                $match[] = $val;
        }
        while (list($key, $val) = each($links[3])) {
            if (!empty($val))
                $match[] = $val;
        }
        $match=array_unique($match);//去除相同


        if ($real) {
            $match = $this->realPath($match);
        }

        // return the links
        return $match;
    }


    /**
     * 拼接路径完整性
     */
    private function realPath($url)
    {

        $scheme = $this->urls['scheme'].'://';
        $host=$this->urls['host'];
        $port = $this->urls['port']=='80'?'':':'.$urls['port'];

        $path=$scheme.$host.$port;//绝对路径
        $path2=$path.$this->urls['path'];

        $path2=substr($path2,-1)=='/'?$path2:dirname($path2);//以'/'结束直接以此为相对路径，否则上一级
        $path2=substr($path2,-1)=='/'?$path2:$path2.'/';//最后以'/'结束

        $url=$this->runPath($url,$path,$path2);
        return $url;
    }

    /**
     * 拼接路径完整性2,递规
     */
    private function runPath($str1,$path,$path2)
    {
        if(is_array($str1)){
            $urls=array();
            foreach($str1 as $key => $value){
                $urls[$key]=self::runPath($value,$path,$path2);
            }
            return $urls;
        }

        if(is_string($str1)){
            if(preg_match('/^[a-z]{1,10}:\/\//i',dirname($str1))){
                return $str1;
            }

            if(substr($str1,0,1)=='/'){
                return $path.$str1;
            }

            if(substr($str1,0,1)!='.'){
                return $path2.$str1;
            }

            if(substr($str1,0,2)=='./'){
                return $path2.substr($str1,2);
            }

            if(substr($str1,0,3)=='../'){
                while(substr($str1,0,3)=='../'){
                    $str1=substr($str1,3);
                    $path2=dirname($path2);
                }
                return $path2.'/'.$str1;
            }
            return $str1;
        }
    }
}
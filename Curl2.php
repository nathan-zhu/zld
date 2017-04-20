<?php

class Curl {

    public static function run($is_get=1, $url, $post=array(), $CA=false, $proxy=false){
        $cookieFile = dirname(__FILE__).'/cookies/'.parse_url($url)['host'].'.txt';
        $proxy = "127.0.0.1:7070";
        $arrHeader = array(
            // 'X-FORWARDED-FOR:'.$_SERVER['REMOTE_ADDR'],
            // 'CLIENT-IP:'.$_SERVER['REMOTE_ADDR'],
            'Accept-Language: zh-cn',
            'Accept-Encoding:gzip,deflate',
            'Connection: Keep-Alive',
            'Cache-Control: no-cache',
            'Accept: application/json, text/javascript',
        );

        $cacert = getcwd() . '/cacert.pem'; //CA根证书
        $SSL = substr($url, 0, 8) == "https://" ? true : false;

        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl,CURLOPT_HTTPHEADER, $arrHeader);//模似请求头
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        if($proxy) {
            // 设置代理
            curl_setopt($curl, CURLOPT_PROXY, $proxy);
            curl_setopt($curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
        }
        if ($SSL && $CA) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);   // 只信任CA颁布的证书
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($curl, CURLOPT_CAINFO, $cacert); // CA根证书（用来验证的网站证书是否是CA颁布）检查证书中是否设置域名，并且是否与提供的主机名匹配
        } else if ($SSL && !$CA) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 信任任何证书
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // 检查证书中是否设置域名
        }
        curl_setopt($curl, CURLOPT_USERAGENT,"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/53.0.2785.143 Chrome/53.0.2785.143 Safari/537.36"); // 模拟用户使用的浏览器
        @curl_setopt($curl, CURLOPT_FOLLOWLOCATION,1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer

        if ($is_get) {
            curl_setopt($curl, CURLOPT_HTTPGET, 1); // 发送一个常规的请求
        } else {
            curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post); // Post提交的数据包
        }
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookieFile); // 存放Cookie信息的文件名称
        curl_setopt($curl, CURLOPT_COOKIEFILE, $cookieFile); // 读取上面所储存的Cookie信息
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');//解释gzip
        curl_setopt($curl, CURLOPT_TIMEOUT, 600);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 600);
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $tmpInfo = curl_exec($curl); // 执行操作
        if (curl_errno($curl)) {echo 'Errno'.curl_error($curl);}
        $data[]=curl_getinfo($curl);
        curl_close($curl); // 关键CURL会话
        $data[]=$tmpInfo;
        return $data; // 返回数据
    }

}

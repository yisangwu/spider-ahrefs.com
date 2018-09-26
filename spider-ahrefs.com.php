<?php
/**
 *  spider 
 *  抓取ahrefs.com 的数据信息
 **/
set_time_limit(0);
error_reporting("E_ALL ^ (E_NOTICE | E_WARNING)");

/**
 * 检查是否安装了curl扩展
 */
if( !extension_loaded('curl')){ 
	die( 'Please Install The Curl Extention!' );
}


/**
 * 读取http相关的配置
 * 账号，密码，http头相关的信息
 * @return array
 */
function get_conf()
{
	$ret['postdata'] = array(
				    'email' => '---------', 
				    'password' => '---------',
				    "return_to" => "https://ahrefs.com/",
				    'remember_me' => 1,
				);
    $ret['httpheader'] = array(
    "Accept-Language:zh-CN,zh;q=0.8,zh-TW;q=0.7,zh-HK;q=0.5,en-US;q=0.3,en;q=0.2",
    "User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:61.0) Gecko/20100101 Firefox/61.0",
    "X-CSRF-Token:---------",
    "Cookie:---------",
    "Host:ahrefs.com",
    "DNT:1",
    "main-request: 1",
    "Connection: keep-alive",
    "X-Requested-With:XMLHttpRequest",
    );

	return $ret;
}


/**
 * keyword的MD5加密，全小写字母
 * 
 * @param  string $keywords 要搜索的关键字
 * @return string
 */
function md5_keyword($keywords='')
{
    $keywords = trim($keywords);
    if(empty($keywords)){
        die('md5_keyword found empty keywords!');
    }
    return md5(strtolower($keywords));
}


/**
 * 从页面中抓取cshash值
 * 所有ajax请求都要使用到
 * 
 * @param  string $keywords 要搜索的关键字
 * @return string
 */
 function get_CSHash($keywords='')
 {

    $md5_keyword = md5_keyword($keywords);
    $conf = get_conf();
    extract($conf);

    $url = 'https://ahrefs.com/keywords-explorer/overview?list=KEY&country=us';
    $url = str_replace('KEY', $md5_keyword, $url);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $httpheader[] = 'Accept:*/*';
    $httpheader[] = 'Accept-Encoding:gzip, deflate, br';
    $httpheader[] = "Referer: {$url}";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');

    $data = curl_exec($ch);
    if(curl_errno($ch) !== 0){
        log_error( 
                    sprintf('%s,%s,%s,%s',$keywords,__FUNCTION__,date('Y-m-d H:i:s'),curl_error($ch))
                );
    }
    curl_close($ch);
    
    //解析data，获取CSHash
    $pos_start = strpos($data,'CSHash = "');
    $pos_end = strpos($data,'var singleKeyword');
    $CSHash_str = trim(substr($data, $pos_start,($pos_end-$pos_start)));
    if(empty($CSHash_str)){
        log_error( sprintf('%s,%s,%s,%s',$keywords,__FUNCTION__,date('Y-m-d H:i:s'),'empty CSHash_str'));
    }
    return str_replace(array('"',' ','=','CSHash',';'),'',$CSHash_str);
}


/**
 * 获取关键字搜索的最后更新时间
 * 
 * @param  string $keywords 搜索关键字
 * @return string  last_update-最后更新时间
 */
function get_lastUpdate($keywords='')
{
    $md5_keyword = md5_keyword($keywords);
    $conf = get_conf();
    extract($conf);

    $cshash = get_CSHash($keywords);
    if(empty($cshash)){
        log_error( sprintf('%s,%s,%s,%s',$keywords,__FUNCTION__,date('Y-m-d H:i:s'),'empty cshash'));
    }
    $ajax_url = 'https://ahrefs.com/keywords-explorer/ajax/get/keywords-data';

    $postdata['cshash'] = $cshash;
    $postdata['keywords'] = sprintf('%s-us', $md5_keyword);
    $postdata['positions'] = 1;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ajax_url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch, CURLOPT_REFERER, "https://ahrefs.com/keywords-explorer/overview?list={$md5_keyword}&country=us");    
    curl_setopt($ch,CURLOPT_USERAGENT, $useragent);//设置用户代理

    $httpheader[] = 'Accept:application/json, text/javascript, */*; q=0.01';
    $httpheader[] = 'Accept-Encoding:gzip, deflate, br';
    $httpheader[] = "Referer:{$url}";
    $httpheader[] = 'Content-Type:application/x-www-form-urlencoded; charset=UTF-8';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');

    // post
    curl_setopt( $ch, CURLOPT_POST, TRUE );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query($postdata) );

    $data = curl_exec($ch);
    if(curl_errno($ch) !== 0){
        log_error( sprintf('%s,%s,%s,%s',$keywords,__FUNCTION__,date('Y-m-d H:i:s'),curl_error($ch)));
    }
    if( empty($data)){
        return 0;
    }
    $data_arr = json_decode($data, true);
    if(empty($data_arr) || !is_array($data_arr)){
        return 0;
    }
    return $data_arr['data'][$postdata['keywords']]['last_update'];
}



/**
 * 抓取 ajax请求的 volume-by-country
 * 
 * @param  string $keywords 搜索关键字
 * @return string  格式为json
 */
function ajax_volumeByCountry($keywords='')
{
    $md5_keyword = md5_keyword($keywords);
    $conf = get_conf();
    extract($conf);

    $cshash = get_CSHash($keywords);
    if(empty($cshash)){
        log_error( sprintf('%s,%s,%s,%s',$keywords,__FUNCTION__,date('Y-m-d H:i:s'),'empty cshash'));
    }
    $ajax_url = 'https://ahrefs.com/keywords-explorer/ajax/get/volume-by-country/';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ajax_url.$cshash);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt ($ch, CURLOPT_REFERER, "https://ahrefs.com/keywords-explorer/overview?list={$md5_keyword}&country=us");    
    curl_setopt($ch,CURLOPT_USERAGENT, $useragent);//设置用户代理
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
    $data = curl_exec($ch);
    if(curl_errno($ch) !== 0){
        log_error( sprintf('%s,%s,%s,%s',$keywords,__FUNCTION__,date('Y-m-d H:i:s'),curl_error($ch)));
    }    
    return json_decode($data, true);
}


/**
 * 写错误日志
 * @param  string $error_str 错误描述字符串
 * @return boolean
 */
function log_error($error_str='')
{
    return @file_put_contents(__DIR__.DIRECTORY_SEPARATOR.'error', $error_str.PHP_EOL, FILE_APPEND);
}


/**
 * 下面是具体的执行过程
 */

//搜索的关键字
$keyword_arr = array(//------'A','B'... );

/**
 * 初始化csv文件的标题
 **/
$title = array( '型号','更新时间','全球搜索量');
$sm_title = '国家%u,搜索量%s,百分比%s';
for($i=1;$i<=5;$i++){
    $title[] = sprintf($sm_title,$i,$i,$i);
}
@file_put_contents(__DIR__.DIRECTORY_SEPARATOR.'outfile', implode(',',$title).PHP_EOL, FILE_APPEND);


/**
 * 遍历 keyword_arr
 */
foreach ($keyword_arr as $keywords) {
    $data = $data_top5 = $ret_tmp = array();
    $data = ajax_volumeByCountry($keywords);
    if(empty($data) || !is_array($data)){
        continue;
    }

    $last_update = get_lastUpdate($keywords);
    
    $allsum = (int)$data['AllSum'];
    //取前5名
    $data_top5 = array_slice($data['Series'][0]['data'], 0,5);
    foreach ( $data_top5 as $vv) {
        $ret_tmp[$vv['name']]['num'] = (int)$vv['y']; 
        $ret_tmp[$vv['name']]['ratio'] = round((int)$vv['y']*100 / $allsum).'%';
    }

    $file_str = '';
    $file_str = sprintf('%s,%s,%u,', $keywords, $last_update, $allsum);
    foreach ($ret_tmp as $country => $mm) {
        $file_str .= sprintf('%s,%u,%s,',$country,$mm['num'],$mm['ratio']);
    }
    //写入csv文件
    @file_put_contents(__DIR__.DIRECTORY_SEPARATOR.'outfile', $file_str.PHP_EOL, FILE_APPEND);

}//end-foreach

<?php
/**
 * D:\Wnmp\php-5.6-x86\php cathedral.php
 * 只能在windows X86 32位PHP运行
 *
 */
require_once('./Curl.php');
require './html-parser/src/ParserDom.php';

class Cathedral {

    protected $indexUrl;
    protected $loginUrl;
    protected $postData;
    protected $retArrData;
    protected $userInfo;
    protected $strCookie;
    protected $retArr;
    protected $userId;
    protected $schoolYear;
    protected $personaId;
    protected $memberLevel;
    protected $durationId;

    public function __construct() {
        $this->indexUrl = "https://cathedral.powerschool.com/public/home.html";
        $this->loginUrl = "https://cathedral.powerschool.com/guardian/home.html";
        $this->getPsKeyUrl = "cathedral_js.php";
    }

    public function run() {
        // 登录
        $this->authLogin();
    }

    protected function getPage($oDom){
        $oNode = $oDom->find('div#quickLookup',0)->find("table",0);
        $i=1;
        $grades=array();
        foreach($oNode->find("tr") as $tr){
            if($i>=3 && $oNode->find("tr",$i)!=false && $oNode->find("tr",$i)->find("td",0)!==false){
                $exp=$oNode->find("tr",$i)->find("td",0)->getPlainText();
                $teacher_email=$oNode->find("tr",$i)->find("td",11)->find("a",1)->getAttr("href");
                $teacher_email=substr($teacher_email,8);
                $score=$oNode->find("tr",$i)->find("td",12)->find("a",0)->getPlainText();
                $score_url=$oNode->find("tr",$i)->find("td",12)->find("a",0)->getAttr("href");
                $attendence=$oNode->find("tr",$i)->find("td",13)->find("a",0)->getPlainText();
                $attendence_url=$oNode->find("tr",$i)->find("td",13)->find("a",0)->getAttr("href");
                $grades[]=array(
                    'exp'=>$exp,
                    'teacher_email'=>$teacher_email,
                    'score'=>$score,
                    'score_details'=>$score_url,
                    'attendence'=>$attendence,
                    'attendence_details'=>$attendence_url,
                );
            }
            $i++;
        }
        foreach($grades as &$val){
            if($val['score_details']!=''){
                $score_url='https://cathedral.powerschool.com/guardian/'.$val['score_details'];
                //$ret = self::curl(1, $score_url,'',true);
                $val['score_details']=$this->parseOneCourse($score_url);
            }
            if($val['attendence_details']!=''){
                $attendence_url='https://cathedral.powerschool.com/guardian/'.$val['attendence_details'];
                //$ret = self::curl(1, $attendence_url,'',true);
                $val['attendence_details']=$this->parseOneAbsences($attendence_url);
            }
        }
        $path = 'datas/cathedral.powerschool.com.json';
        file_put_contents($path, json_encode($grades));
        echo 'success!'.PHP_EOL;
        return $grades;
    }

    protected function parseOneAbsences($url){
        $ret = self::curl(0, $url, '',true);
        $details=array();
        if($ret) {
            $oDom = new \HtmlParser\ParserDom($ret);
            $oNode = $oDom->find('div.box-round',0)->find("table",0);
            foreach($oNode->find("tr",1)->find("li") as $li){
                $details[]=$li->getPlainText();
            }
        }
        return $details;
    }

    protected function parseOneCourse($url){
        $ret = self::curl(0, $url, '',true);
        if($ret) {
            $oDom = new \HtmlParser\ParserDom($ret);
            $oNode = $oDom->find('table.linkDescList',0);
            $keys=array();
            $course_sumary=array();
            //parse sumary
            for($i=0;$i<count($oNode->find("tr"));$i++){
                if($i==0){
                    foreach($oNode->find("tr",$i)->find("th") as $th){
                        $keys[]=$th->getPlainText();
                    }
                }
                else{
                    for($j=0;$j<count($keys);$j++){
                        $details=$oNode->find("tr",$i)->find("td",$j)->innerHtml();
                        $details = preg_replace('/<script[^>].*?>.*?<\/script>/is', '', $details);
                        $course_sumary[$keys[$j]]=trim($details);
                    }
                }
            }
            //parse details
            $oNode = $oDom->find('div.box-round',0)->find("table",1);
            $keys=array();
            $course_total_details=array();
            for($i=0;$i<count($oNode->find("tr"));$i++){
                //for($i=0;$i<2;$i++){
                if($i==0){
                    foreach($oNode->find("tr",$i)->find("th") as $th){
                        $keys[]=$th->getPlainText();
                    }
                }
                else{
                    $course_details=array();
                    for($j=0;$j<count($keys);$j++){
                        if($j>3){
                            $k=$j+4;
                            $details=($oNode->find("tr",$i)->find("td",$k)!==false)?$oNode->find("tr",$i)->find("td",$k)->getPlainText():'';
                        }
                        elseif($j==3){
                            $details='';
                        }
                        else{
                            $details=($oNode->find("tr",$i)->find("td",$j)!==false)?$oNode->find("tr",$i)->find("td",$j)->getPlainText():'';
                        }
                        $course_details[$keys[$j]]=trim($details);
                    }
                    $course_total_details[]=$course_details;
                }
            }
            $ret=array('course_sumary'=>$course_sumary,'course_details'=>$course_total_details);
            //print_r($ret);
        }
        else{
            $ret=array('course_sumary'=>'','course_details'=>'');
        }
        return $ret;
    }

    // protected static function getPsKey($pskey){
    //     $jsData = file_get_contents("cathedral_md5.js");
    //     $oScript = new COM("MSScriptControl.ScriptControl");
    //     $oScript->Language = "JavaScript";
    //     $oScript->AllowUI = false;
    //     $oScript->AddCode("$jsData");
    //     $a = $oScript->Run("getCaPwd", $pskey);
    //     return $a;
    // }

    protected function authLogin() {
        echo 'curl login page, url: ' . $this->indexUrl . PHP_EOL;
        $ret = self::curl(1, $this->indexUrl,'',true);
        //$filename=time();
        //file_put_contents("{$filename}.txt", $ret); // 放在了123.txt里面，是为了看html代码
        //exit;
        if($ret) {
            $oDom = new \HtmlParser\ParserDom($ret);
            $otherField=[];
            $flag=true;
            for($i=0;$i<=10;$i++){
                $oNode = $oDom->find('form#LoginForm',0);
                if(!$oNode){
                    $flag=false;
                    break;
                }
                $oNode=$oNode->find('input', $i);
                $fieldKey = $oNode->getAttr('name');
                $fieldValue = $oNode->getAttr('value');
                $otherField[$fieldKey]=$fieldValue;
            }
            if(!$flag){
                exit('can not curl page :'.$this->indexUrl.',may be cacert cant not load ,pleast retry');
            }
            echo 'curl sign in page, url: ' . $this->loginUrl . PHP_EOL;
            // login page
            $arrPost = array(
                'account' => '7509',
                'translatorpw'=>'',
            );
            $arrPost=array_merge($arrPost,$otherField);
            // if(isset($arrPost['contextData'])){
            //     $newPw=self::getPsKey($arrPost['contextData']);
            //     $arrPost['pw']=$newPw;
            //     echo 'The newPw is:'.$newPw.PHP_EOL;
            // }
            // else{
            //     exit('can not get contextData');
            // }
            $arrPost['pw'] = md5('Z09');
            $ret = self::curl(0, $this->loginUrl, $arrPost);
            if($ret) {
                $oDom = new \HtmlParser\ParserDom($ret);
                if($oDom->find('div#quickLookup',0)==false){
                    exit("can not get data,may be cacert cant't load ,pleast retry");
                }
                echo 'login success, start spider data.'.PHP_EOL;
                file_put_contents("login.html", $ret); // 放在了123.txt里面，是为了看html代码
                //$this->getPage($oDom);
                //print_r($ret);
            }
        }
    }

    private static function Curl($is_get=1, $url, $post=array(), $ca=false) {
        if(!$url) {
            return;
        }
        $ret = Curl::run($is_get, $url, $post, $ca);
        if($ret[0]['http_code'] == 200 and $ret[1]) {
            if(strpos($ret[0]['content_type'], 'json') === false) {
                return $ret[1];
            } else {
                return json_decode($ret[1], true);
            }
        }
    }

}

$obj = new Cathedral();
$obj->run();

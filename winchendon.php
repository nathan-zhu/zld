<?php
require_once('./Curl.php');
require './html-parser/src/ParserDom.php';

class Winchendon {
    protected $host;
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
    protected $arrDuration; // 学期数据
    protected $SchoolUrl;

    public function __construct() {
        $this->host = "https://winchendon.myschoolapp.com/";
        $this->indexUrl = $this->host . "app#login";
        $this->loginUrl = $this->host . "api/SignIn";
        $this->contextUrl = $this->host . "api/webapp/context?_".time();
        $this->termListUrl = $this->host . "api/DataDirect/StudentGroupTermList/";
        $this->SchoolUrl = $this->host . "api/webapp/schoolcontext?_".time();
        // 考勤接口
        $this->absenceUrl = $this->host . "api/datadirect/ParentStudentUserAttendance/";
        $this->detailAbsenceUrl = $this->host . "api/attendancestudent/ListRecordDetail/";
        // 成绩接口
        $this->courseUrl = $this->host . "api/datadirect/ParentStudentUserAcademicGroupsGet";
        $this->detailCourceUrl = $this->host . "api/datadirect/GradeBookPerformanceAssignmentStudentList/";

        $this->schoolYear = date("Y").'+-+'.(date("Y")+1);
        $this->memberLevel = 3;
    }

    public function run() {
        // 登录
        $this->authLogin();
        // 抓取考勤
        $this->curlAttendance();
        // 抓取课程
        $this->curlCourse();
        // 输出
        $this->output();
    }

    protected function output() {
        $path = 'datas/winchendon.myschoolapp.com.json';
        file_put_contents($path, json_encode($this->retArr));
        $path = 'datas/winchendon.myschoolapp.html';
        file_put_contents($path, var_export($this->retArr, true));
        echo 'success!'.PHP_EOL;
    }

    protected function curlCourse() {
        foreach($this->arrDuration as $arrDuration) {
            $durationId = $arrDuration['DurationId'];
            // 科目总览
            $arrParam = array(
                'userId='.$this->userId,
                'persona='.$this->personaId,
                'schoolYearLabel='.$this->schoolYear,
                'memberLevel='.$this->memberLevel,
                'durationList='.$durationId,
                'markingPeriodId=',
            );
            $ret = self::curl(1, $this->courseUrl . '?' . implode('&', $arrParam));

            if($ret) {
                // 明细
                foreach($ret as &$arrData) {
                    $newArrData = array(
                        'title' => $arrData['sectionidentifier'],
                        'aubtitle' => $arrData['room'].' | '.$arrData['currentterm'].' | '.$arrData['schoollevel'],
                        'assignments' => $arrData['sectionidentifier'],
                        'groupowneremail' => $arrData['groupowneremail'],
                        'cumgrade' => $arrData['cumgrade'],
                        'assignmentduetoday' => $arrData['assignmentduetoday'],
                        'assignmentassignedtoday' => $arrData['assignmentassignedtoday'],
                        'assignmentactivetoday' => $arrData['assignmentactivetoday'],
                        'cumgrade' => $arrData['cumgrade'],
                        'groupownername' => $arrData['groupownername'],
                    );
                    $arrParam = array(
                        'sectionId='.$arrData['leadsectionid'],
                        'markingPeriodId='.$arrData['markingperiodid'],
                        'studentUserId='.$this->userId,
                    );
                    $url = $this->detailCourceUrl . '?' . implode('&', $arrParam);
                    $tmpRet = self::curl(1, $url);
                    if($tmpRet) {
                        foreach($tmpRet as $tmpArrData) {
                            $newArrData['detail'][$tmpArrData['AssignmentType']][] = array(
                                'AssignmentType' => $tmpArrData['AssignmentType'],
                                'Assignment' => $tmpArrData['Assignment'],
                                'AssignmentShortDescription' => $tmpArrData['AssignmentShortDescription'],
                                'Assigned' => $tmpArrData['DateAssigned'],
                                'Due' => $tmpArrData['DateDue'],
                                'Points' => $tmpArrData['Points'],
                                'Notes' => $tmpArrData['AdditionalInfo'],
                            );
                        }
                    }
                    $arrData = $newArrData? $newArrData: array();
                }
            }
            $this->retArr['courses'][$arrDuration['DurationDescription']] = $ret;
        }
        echo 'get courses data ok!'.PHP_EOL;
        if($this->retArr) {
            $this->retArr[$this->retArr['userId']] = $this->retArr;
        }
    }


    protected function curlAttendance() {
        // 考勤总览
        $arrParam = array(
            'userId='.$this->userId,
            'personaId='.$this->personaId,
            'schoolYearLabel='.$this->schoolYear,
        );
        $ret = self::curl(1, $this->absenceUrl . '?' .implode("&", $arrParam));
        if($ret) {
            // 明细
            foreach($ret as &$arrData) {
                $newArrData = array(
                    'category_description' => $arrData['category_description'],
                    'excuse_count' => $arrData['excuse_count'],
                );
                //
                $arrParam = array(
                    'userId='.$this->userId,
                    'excuseCategoryId='.$arrData['excuse_category_id'],
                    'schoolYear='.$this->schoolYear,
                );

                $tmpRet = self::curl(1, $this->detailAbsenceUrl . '?' .implode("&", $arrParam));
                if($tmpRet) {
                    foreach($tmpRet as $tmpArrData) {
                        $newArrData['detail'][] = array(
                            'GroupName' => $tmpArrData['GroupName'],
                            'Date' => $tmpArrData['CalendarDate'],
                            'Reason' => $tmpArrData['ExcuseDescription'],
                            'Comment' => $tmpArrData['Comment'],
                        );
                    }
                    $arrData = $newArrData;
                }
            }
            //
            $this->retArr['absence'] = $ret;
            echo 'get absence data ok!'.PHP_EOL;
        }

    }

    protected function authLogin() {
        echo 'curl login page' . PHP_EOL;
        $ret = self::curl(1, $this->indexUrl);
        if($ret) {
            $oDom = new \HtmlParser\ParserDom($ret);
            $oNode = $oDom->find('div#__AjaxAntiForgery',0)->find('input', 0);
            $tokenKey = substr($oNode->getAttr('name'), 2);
            $tokenValue = $oNode->getAttr('value');
            echo 'curl sign in page, url: ' . $this->loginUrl . PHP_EOL;
            // login page
            $ret = $this->getLoginCurl($tokenValue);
var_dump($ret);
            if($ret) {
                echo 'login success, start spider data.'.PHP_EOL;
                // 获取学校信息
                $retSchool = self::curl(1, $this->SchoolUrl);
                // 获取用户信息
                $ret = self::curl(1, $this->contextUrl);
                if($ret) {
                    $this->userId = $ret['MasterUserInfo']['UserId'];
                    $this->personaId = $ret['Personas'][0]['Id'];
                    $this->retArr['userId'] = $this->userId;
                    $this->retArr['FirstName'] = $ret['MasterUserInfo']['FirstName'];
                    $this->retArr['LastName'] = $ret['MasterUserInfo']['LastName'];
                    $this->retArr['NickName'] = $ret['MasterUserInfo']['NickName'];
                    if($ret['MasterUserInfo']['ProfilePhoto']['ThumbFilenameUrl']) {
                        $this->retArr['ThumbFilename'] = 'https://bbk12e1-cdn.myschoolcdn.com/ftpimages/'.$retSchool['SchoolInfo']['SchoolId'].'/user/'.$ret['MasterUserInfo']['ProfilePhoto']['ThumbFilenameUrl'];
                    } else {
                        $this->retArr['ThumbFilename'] = '';
                    }
                    $this->retArr['FirstName'] = $ret['MasterUserInfo']['FirstName'];
                    $this->retArr['GradYear'] = $ret['MasterUserInfo']['StudentInfo']['GradYear'];
                }
                // 获取学习信息
                $arrParam = array(
                    'studentUserId='.$this->userId,
                    'personaId='.$this->personaId,
                    'schoolYearLabel='.$this->schoolYear,
                );
                $ret = self::curl(1, $this->termListUrl . '?' . implode('&', $arrParam));
                if($ret) {
                    foreach($ret as $arr) {
                        if($arr['OfferingType'] == 1) {
                            $this->arrDuration[] = array(
                                'DurationDescription' => $arr['DurationDescription'],
                                'DurationId' =>$arr['DurationId'],
                            );
                        }
                    }
                    $this->arrDurationId = $ret[0]['DurationId'];
                }
            }
        }
    }

    private static function Curl($is_get=1, $url, $post=array(), $ca=false) {
        if(!$url) {
            return;
        }
        echo $url.PHP_EOL;
        $ret = Curl::run($is_get, $url, $post, $ca);
        if($ret[0]['http_code'] == 200 and $ret[1]) {
            if(strpos($ret[0]['content_type'], 'json') === false) {
                return $ret[1];
            } else {
                return json_decode($ret[1], true);
            }
        }
    }

    // 页面切换
    protected function getLoginCurl($token) {
        $ch = curl_init();
        $cookieFile = dirname(__FILE__).'/cookies/'.parse_url($this->host)['host'].'.txt';
        curl_setopt($ch, CURLOPT_URL, $this->loginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"From\":\"\",\"Username\":\"Xinlongzhang19\",\"Password\":\"zhangxinlong12\",\"remember\":false,\"InterfaceSource\":\"WebApp\"}");
        curl_setopt($ch, CURLOPT_POST, 1);

        $headers = array();
        // $headers[] = "Cookie: _ENV[__RequestVerificationToken_OnSuite=WvptxIjPCut8DkEOXo27ZOvLosgUZiV-CShzzPOaDc1R7bNIcMRmc9NP4024TqH9ivInVckButK5QzdwCWHhizUuMkB_XHZYezoZ0O5TZaU1; dtCookie=50587800235B826F2A5337559287482C2|RUM+Default+Application|1; rxVisitor=1482374662396U3TGF0H1C1CBUGTP26FJPMI8I7MC26C8; rxvt=1482376691160|1482374662414; dtPC=5374862921_756h-vEQIHLAMGGAHFTJGOMBOBFFFBFADMPGLFPE; dtLatC=307]";
        $headers[] = "Requestverificationtoken: $token";
        $headers[] = "Origin: $this->host";
        $headers[] = "Accept-Language: zh-CN,zh;q=0.8,en;q=0.6,zh-TW;q=0.4";
        $headers[] = "Wh-Version: 11.13.1.32";
        $headers[] = "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.95 Safari/537.36";
        $headers[] = "Content-Type: application/json";
        $headers[] = "Accept: application/json, text/javascript, */*; q=0.01";
        $headers[] = "Referer: https://winchendon.myschoolapp.com/app/";
        $headers[] = "X-Requested-With: XMLHttpRequest";
        $headers[] = "Connection: keep-alive";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile); // 存放Cookie信息的文件名称
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile); // 读取上面所储存的Cookie信息
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
        return $result;
    }

}

$obj = new Winchendon();
$obj->run();

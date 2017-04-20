<?php
require_once('./Curl.php');
require './html-parser/src/ParserDom.php';

class Wlhs_zk {
    const Username = 'chensh';
    const Password = 'Kaiwl18';

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
        $this->indexUrl = "https://wlhs.myschoolapp.com/app#login";
        $this->loginUrl = "https://wlhs.myschoolapp.com/api/SignIn";
        //$this->contextUrl = "https://wlhs.myschoolapp.com/api/webapp/context?_".time();
        $this->contextUrl = "https://wlhs.myschoolapp.com/api/webapp/context";
        $this->termListUrl = "https://wlhs.myschoolapp.com/api/DataDirect/StudentGroupTermList/";
        $this->SchoolUrl = "https://wlhs.myschoolapp.com/api/webapp/schoolcontext?_".time();
        // 考勤接口
        $this->absenceUrl = "https://wlhs.myschoolapp.com/api/datadirect/ParentStudentUserAttendance/";
        $this->detailAbsenceUrl = "https://wlhs.myschoolapp.com/api/attendancestudent/ListRecordDetail/";
        // 成绩接口
        $this->courseUrl = "https://wlhs.myschoolapp.com/api/datadirect/ParentStudentUserAcademicGroupsGet";
        $this->detailCourceUrl = "https://wlhs.myschoolapp.com/api/datadirect/GradeBookPerformanceAssignmentStudentList/";

        $this->schoolYear = date("Y").'+-+'.(date("Y")+1);
        $this->memberLevel = 3;
    }

    public function run() {
        // 登录
        $this->authLogin();
        // // 抓取考勤
        $this->curlAttendance();
        // // 抓取课程
        $this->curlCourse();
        // // 输出
        $this->output();
    }

    protected function output() {
        //$path = 'datas/'.self::Username.'_wlhs.myschoolapp.com.json';
        //file_put_contents($path, json_encode($this->retArr));
        $path = 'datas/'.self::Username.'_wlhs_all.html';
        self::html_put_files(var_export($this->retArr, true), $path);
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
            //$this->retArr[$this->retArr['userId']] = $this->retArr;
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
        echo 'curl login page, url: ' . $this->indexUrl . PHP_EOL;
        $ret = self::curl(1, $this->indexUrl);
        if($ret) {
            $oDom = new \HtmlParser\ParserDom($ret);
            $oNode = $oDom->find('div#__AjaxAntiForgery',0)->find('input', 0);
            $tokenKey = substr($oNode->getAttr('name'), 2);
            $tokenValue = $oNode->getAttr('value');
            echo 'curl sign in page, url: ' . $this->loginUrl . PHP_EOL;
            // login page
            $arrPost = array(
                'From' => '',
                'InterfaceSource' => 'WebApp',
                'Password' => self::Password,
                'Username' => self::Username,
                'remember' => 'false',
                $tokenKey => $tokenValue,
            );
            $ret = self::curl(0, $this->loginUrl, $arrPost);
            if($ret) {
                echo 'login success, start spider data.'.PHP_EOL;
                // 获取学校信息
                $retSchool = self::curl(1, $this->SchoolUrl);
                //var_dump($retSchool);
                // 获取用户信息
                $ret = self::curl(1, $this->contextUrl);
                if($ret) {
                    $this->userId = $ret['Children'][0]['Id'];
                    $this->personaId = $ret['Personas'][0]['Id'];
                    $this->retArr['userId'] = $this->userId;
                    $this->retArr['FirstName'] = $ret['Children'][0]['FirstName'];
                    $this->retArr['LastName'] = $ret['Children'][0]['LastName'];
                    $this->retArr['NickName'] = $ret['Children'][0]['NickName'];
                    $this->retArr['ThumbFilename'] = 'https://bbk12e1-cdn.myschoolcdn.com/ftpimages/'.$retSchool['SchoolInfo']['SchoolId'].'/user/'.$ret['Children'][0]['ThumbFilename'];
                    $this->retArr['FirstName'] = $ret['Children'][0]['FirstName'];
                    $this->retArr['GradYear'] = $ret['Children'][0]['GradYear'];
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
        //var_dump($this->retArr);
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

    private function html_put_files($data, $fname = NULL) {
        if(empty($fname)) {
            $fname = "datas/wlhs_login.html";
        }
        if(is_array($data)) {
            $data = var_export($data, true);
        }
        file_put_contents($fname, $data, FILE_APPEND);
    }

}

$obj = new Wlhs_zk();
$obj->run();

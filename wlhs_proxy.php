<?php
/**
 *
 * https://wlhs.myschoolapp.com/api/datadirect/ParentStudentUserAttendance/?userId=3790561&personaId=1&schoolYearLabel=2016+-+2017  考勤接口
 * https://wlhs.myschoolapp.com/api/datadirect/ParentStudentUserAcademicGroupsGet?userId=3790561&schoolYearLabel=2016+-+2017&memberLevel=3&persona=1&durationList=59203&markingPeriodId= 成绩接口
 * 
userId:3790559
schoolYearLabel:2016 - 2017
memberLevel:3
persona:1
durationList:59203
markingPeriodId:3726
 */
require_once('./Curl.php');
require './html-parser/src/ParserDom.php';

class Wlhs {
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
        $this->indexUrl = "https://wlhs.myschoolapp.com/app#login";
        $this->loginUrl = "https://wlhs.myschoolapp.com/api/SignIn";
        $this->contextUrl = "https://wlhs.myschoolapp.com/api/webapp/context?_".time();
        $this->termListUrl = "https://wlhs.myschoolapp.com/api/DataDirect/StudentGroupTermList/";
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
        // 抓取考勤
        $this->curlAttendance();
        // 抓取课程
        $this->curlCourse();
        // 输出
        $this->output();
    }

    protected function output() {
        $path = 'datas/wlhs.myschoolapp.com.json';
        file_put_contents($path, json_encode($this->retArr));
        echo 'success!'.PHP_EOL;
    }

    protected function curlCourse() {

        // 科目总览
        $arrParam = array(
            'userId='.$this->userId,
            'persona='.$this->personaId,
            'schoolYearLabel='.$this->schoolYear,
            'memberLevel='.$this->memberLevel,
            'durationList='.$this->durationId,
            'markingPeriodId=',
        );
        $ret = self::curl(1, $this->courseUrl . '?' . implode('&', $arrParam), 1);

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
                );
                $arrParam = array(
                    'sectionId='.$arrData['leadsectionid'],
                    'markingPeriodId='.$arrData['markingperiodid'],
                    'studentUserId='.$this->userId,
                );
                $url = $this->detailCourceUrl . '?' . implode('&', $arrParam);
                $tmpRet = self::curl(1, $url, 1);
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
                $arrData = $newArrData;
            }
            //
            echo 'get courses data ok!'.PHP_EOL;
            $this->retArr['courses'] = $ret;
        }
    }


    protected function curlAttendance() {
        // 考勤总览
        $arrParam = array(
            'userId='.$this->userId,
            'personaId='.$this->personaId,
            'schoolYearLabel='.$this->schoolYear,
        );
        $ret = self::curl(1, $this->absenceUrl . '?' .implode("&", $arrParam), 1);
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

                $tmpRet = self::curl(1, $this->detailAbsenceUrl . '?' .implode("&", $arrParam), 1);
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
            //
            $this->retArr['absence'] = $ret;
            echo 'get absence data ok!'.PHP_EOL;
        }

    }

    protected function authLogin() {
        echo 'curl login page, url: ' . $this->indexUrl . PHP_EOL;
        $ret = self::curl(1, $this->indexUrl, 1);
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
                'Password' => 'Fangchen17',
                'Username' => 'zhaogu',
                'remember' => 'false',
                $tokenKey => $tokenValue,
            );
            $ret = self::curl(0, $this->loginUrl, 1, $arrPost);
            self::html_put_files($ret);
            if($ret) {
                echo 'login success, start spider data.'.PHP_EOL;
                // 获取用户信息
                $ret = self::curl(1, $this->contextUrl, 1);
                if($ret) {
                    $this->userId = $ret['Children'][0]['Id'];
                    $this->personaId = $ret['Personas'][0]['Id'];
                }
                // 获取学习信息
                $arrParam = array(
                    'studentUserId='.$this->userId,
                    'personaId='.$this->personaId,
                    'schoolYearLabel='.$this->schoolYear,
                );
                $ret = self::curl(1, $this->termListUrl . '?' . implode('&', $arrParam), 1);
                if($ret) {
                    $this->durationId = $ret[0]['DurationId'];
                }
            }
        }
    }

    private static function Curl($is_get=1, $url, $proxy=1, $post=array(), $ca=false) {
        if(!$url) {
            return;
        }
        $ret = Curl::run($is_get, $url, $proxy, $post, $ca);
        if($ret[0]['http_code'] == 200 and $ret[1]) {
            if(strpos($ret[0]['content_type'], 'json') === false) {
                return $ret[1];
            } else {
                return json_decode($ret[1], true);
            }
        }
    }
    /**
     * [html_put_files description]
     * @param  [type] $fname [description]
     * @param  [type] $data  [description]
     * @return [type]        [description]
     */
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

$obj = new Wlhs();
$obj->run();

<?php
require_once('./Curl.php');
require './html-parser/src/ParserDom.php';
/*
https://wlhs.myschoolapp.com/api/gradebook/GradeBookMyDayMarkingPeriods?durationSectionList=%5B%5D&userId=3790513&personaId=1 
https://wlhs.myschoolapp.com/api/webapp/userstatus?_=1482399320209
https://wlhs.myschoolapp.com/api/datadirect/ParentStudentUserAcademicGroupsGet?userId=3790513&schoolYearLabel=2016+-+2017&memberLevel=3&persona=1&durationList=59203&markingPeriodId=3727

https://wlhs.myschoolapp.com/api/officialnote/GetInboxCounts/?format=json&currentInd=1&statusXml=&commentTypeXml=&fromDate=&toDate=12%2F22%2F2016&searchText=

https://wlhs.myschoolapp.com/api/datadirect/ParentStudentUserInfo/?userId=3790513
https://wlhs.myschoolapp.com/api/user/profiletabs?showuserid=3790513
https://wlhs.myschoolapp.com/api/DataDirect/MainBulletinUser?personaId=1
 */
class Wlhs_chr {
    const Username = 'guanke';
    const Password = 'Haoran19';

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
    protected $markingPeriodId;

    public function __construct() {
        $this->indexUrl = "https://wlhs.myschoolapp.com/app#login";
        $this->loginUrl = "https://wlhs.myschoolapp.com/api/SignIn";
        $this->contextUrl = "https://wlhs.myschoolapp.com/api/webapp/context?_".time();
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
        $this->markingPeriodId = array('Q1' => '3726', 'Q2' => '3727', 'Q3'=>'3728', 'Q4' => '3729');
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
        foreach($this->arrDuration as $key => $arrDs) {
            $userId = $key;
            foreach($arrDs as $arrDuration) {
                $durationId = $arrDuration['DurationId'];
                foreach($arrDuration['PeriodId'] as $qkey => $Q) {
                    // 科目总览
                    $arrParam = array(
                        'userId='.$userId,
                        'persona='.$this->retArr[$key]['personaId'],
                        'schoolYearLabel='.$this->schoolYear,
                        'memberLevel='.$this->memberLevel,
                        'durationList='.$durationId,
                        'markingPeriodId='.$Q,
                    );
                    $ret = self::curl(1, $this->courseUrl . '?' . implode('&', $arrParam));
                    if($ret) {
                        // 科目明细
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
                                'studentUserId='.$userId,
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
                        // $this->retArr[$userId]['courses'][$arrDuration['DurationDescription']][$qkey] = $arrData;
                    }
                    $this->retArr[$userId]['courses'][$arrDuration['DurationDescription']][$qkey] = $ret;
                }
            }            
        }
        self::html_put_files($this->retArr, "datas/q1.html");
        echo 'get courses data ok!'.PHP_EOL;
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
                    if(count($ret['Children']) > 1) {
                        foreach($ret['Children'] as $key => $child) {
                            $key++;
                            if(($key % 2) == 1) {
                                $Id = $child['Id'];
                                $this->retArr[$Id]['personaId'] = $ret['Personas'][0]['Id'];
                                $this->retArr[$Id]['userId'] = $Id;
                                $this->retArr[$Id]['FirstName'] = $child['FirstName'];
                                $this->retArr[$Id]['LastName'] = $child['LastName'];
                                $this->retArr[$Id]['NickName'] = $child['NickName'];
                                $this->retArr[$Id]['ThumbFilename'] = 'https://bbk12e1-cdn.myschoolcdn.com/ftpimages/'.$retSchool['SchoolInfo']['SchoolId'].'/user/'.$child['ThumbFilename'];
                                $this->retArr[$Id]['Role'] = $child['Role'];
                                $this->retArr[$Id]['GradYear'] = $child['GradYear'];
                            }
                        }
                    }
                    // else {
                    //     $this->userId = $ret['Children'][0]['Id'];
                    //     $this->personaId = $ret['Personas'][0]['Id'];
                    //     $this->retArr['userId'] = $this->userId;
                    //     $this->retArr['FirstName'] = $ret['Children'][0]['FirstName'];
                    //     $this->retArr['LastName'] = $ret['Children'][0]['LastName'];
                    //     $this->retArr['NickName'] = $ret['Children'][0]['NickName'];
                    //     $this->retArr['ThumbFilename'] = 'https://bbk12e1-cdn.myschoolcdn.com/ftpimages/'.$retSchool['SchoolInfo']['SchoolId'].'/user/'.$ret['Children'][0]['ThumbFilename'];
                    //     $this->retArr['GradYear'] = $ret['Children'][0]['GradYear'];
                    // }
                }
                foreach($this->retArr as $key => $students) {
                    $arrParam = array(
                        'studentUserId='.$students['userId'],
                        'personaId='.$students['personaId'],
                        'schoolYearLabel='.$this->schoolYear,
                    );
                    $ret = self::curl(1, $this->termListUrl . '?' . implode('&', $arrParam));
                    if($ret) {
                        foreach($ret as $i => $arr) {
                            if($arr['OfferingType'] == 1) {
                                $dPI = array();
                                $dPI['DurationDescription'] = $arr['DurationDescription'];
                                $dPI['DurationId'] = $arr['DurationId'];
                                if($arr['DurationDescription'] === '1st Semester') {     
                                    $dPI['PeriodId']['Q1'] = '3726';
                                    $dPI['PeriodId']['Q2'] = '3727';
                                }
                                else {
                                    $dPI['PeriodId']['Q3'] = '3728';
                                    $dPI['PeriodId']['Q4'] = '3729';
                                }
                                $this->arrDuration[$key][$i] = $dPI;
                            }
                        }
                        //$this->arrDurationId = $ret[0]['DurationId'];
                    }
                }

                // 获取学习信息
                // $arrParam = array(
                //     'studentUserId='.$this->userId,
                //     'personaId='.$this->personaId,
                //     'schoolYearLabel='.$this->schoolYear,
                // );
                // $ret = self::curl(1, $this->termListUrl . '?' . implode('&', $arrParam));
                // if($ret) {
                //     foreach($ret as $arr) {
                //         if($arr['OfferingType'] == 1) {
                //             $this->arrDuration[] = array(
                //                 'DurationDescription' => $arr['DurationDescription'],
                //                 'DurationId' =>$arr['DurationId'],
                //             );
                //         }
                //     }
                //     $this->arrDurationId = $ret[0]['DurationId'];
                // }
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

$obj = new Wlhs_chr();
$obj->run();

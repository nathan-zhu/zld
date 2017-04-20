<?php
require_once('./Curl2.php');
require './html-parser/src/ParserDom.php';
include('db_class.php'); // call db.class.php

/*
https://wlhs.myschoolapp.com/api/gradebook/GradeBookMyDayMarkingPeriods?durationSectionList=%5B%5D&userId=3790513&personaId=1 
https://wlhs.myschoolapp.com/api/webapp/userstatus?_=1482399320209
https://wlhs.myschoolapp.com/api/datadirect/ParentStudentUserAcademicGroupsGet?userId=3790513&schoolYearLabel=2016+-+2017&memberLevel=3&persona=1&durationList=59203&markingPeriodId=3727

https://wlhs.myschoolapp.com/api/officialnote/GetInboxCounts/?format=json&currentInd=1&statusXml=&commentTypeXml=&fromDate=&toDate=12%2F22%2F2016&searchText=

https://wlhs.myschoolapp.com/api/datadirect/ParentStudentUserInfo/?userId=3790513
https://wlhs.myschoolapp.com/api/user/profiletabs?showuserid=3790513
https://wlhs.myschoolapp.com/api/DataDirect/MainBulletinUser?personaId=1
 */
class Wlhs_Xuezhenyao
{
    const Username = 'yaobi';
    const Password = 'Zhenyao2017';
    const School = 'wlhs';
    const School_Name = 'Wisconsin Lutheran High School';
    const UID = 34;
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
    protected $schoolUrl;
    protected $markingPeriodId;
    protected $gradeLevelUrl;
    protected $gradeLevel;
    protected $bdd;
    protected $drupalUid;
    protected $currentTerm;
    protected $markingPeroidIdUrl;

    public function __construct()
    {
        $this->bdd = new db();
        $this->drupalUid = self::UID;
        $this->indexUrl = "https://".self::School.".myschoolapp.com/app#login";
        $this->loginUrl = "https://". self::School .".myschoolapp.com/api/SignIn";
        $this->contextUrl = "https://". self::School .".myschoolapp.com/api/webapp/context?_".time();
        $this->gradeLevelUrl = "https://". self::School .".myschoolapp.com/api/datadirect/StudentGradeLevelList/";
        $this->termListUrl = "https://". self::School .".myschoolapp.com/api/DataDirect/StudentGroupTermList/";
        $this->schoolUrl = "https://". self::School .".myschoolapp.com/api/webapp/schoolcontext?_".time();
        // 考勤接口
        $this->absenceUrl = "https://". self::School .".myschoolapp.com/api/datadirect/ParentStudentUserAttendance/";
        $this->detailAbsenceUrl = "https://". self::School .".myschoolapp.com/api/attendancestudent/ListRecordDetail/";
        // 成绩接口
        $this->courseUrl = "https://". self::School .".myschoolapp.com/api/datadirect/ParentStudentUserAcademicGroupsGet";
        $this->detailCourceUrl = "https://". self::School .".myschoolapp.com/api/datadirect/GradeBookPerformanceAssignmentStudentList/";

        //学期栏目里Q1,Q2 的值
        $this->markingPeroidIdUrl = "https://". self::School .".myschoolapp.com/api/gradebook/GradeBookMyDayMarkingPeriods";
        // report and honors
        //https://capehenrycollegiate.myschoolapp.com/api/datadirect/ParentStudentUserPerformance/?userId=4023907&personaId=2&schoolYearLabel=2016+-+2017

        //$this->schoolYear = date("Y").'+-+'.(date("Y")+1);
        //$this->schoolYear = date("m") > 8 ? date("Y").'+-+'.(date("Y")+1) : (date("Y")-1).'+-+'.(date("Y"));
        $this->memberLevel = 3;
        //$this->markingPeriodId = array('First Semester' => '', 'Second Semester' => '');
    }

    public function run()
    {
        // 登录
        $this->authLogin();
        // // 抓取考勤
        //$this->curlAttendance();
        /** 抓取课程 
         * firt time will put parameter with all to get all data $this->curlCourse('All');.
         * then will get empty parameter, only catch current semester data.
         **/
        $this->curlCourse();
        // // 输出
        //$this->output();
    }
    
    protected function authLogin()
    {
        echo 'curl login page, url: ' . $this->indexUrl . PHP_EOL;
        $ret = self::curl(1, $this->indexUrl);
        if ($ret) {
            $oDom = new \HtmlParser\ParserDom($ret);
            $oNode = $oDom->find('div#__AjaxAntiForgery', 0)->find('input', 0);
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
            if ($ret) {
                echo 'login success, start spider data.'.PHP_EOL;
                // 获取学校信息
                $retSchool = self::curl(1, $this->schoolUrl);

                // 获取用户信息
                $ret = self::curl(1, $this->contextUrl);
                self::html_put_files($ret);

                if ($ret) {
                    //multi Children for this account
                    if(isset($ret['Children'])) {
                        $keyid = 'Id';
                        //get unique data by AssignmentTypeId
                        $unique_Childrens = self::assoc_unique($ret['Children'], $keyid);
                        //count($ret['Children']) > 1
                        foreach($unique_Childrens as $key => $child) {
                            $this->userId = $Id = $child['Id'];
                            $this->personaId = $this->retArr[$Id]['personaId'] = $ret['Personas'][0]['Id'];
                            $this->retArr[$Id]['userId'] = $Id;
                            $this->retArr[$Id]['FirstName'] = $child['FirstName'];
                            $this->retArr[$Id]['LastName'] = $child['LastName'];
                            $this->retArr[$Id]['NickName'] = $child['NickName'];
                            $this->retArr[$Id]['ThumbFilename'] = 'https://bbk12e1-cdn.myschoolcdn.com/ftpimages/'.$retSchool['SchoolInfo']['SchoolId'].'/user/'.$child['ThumbFilename'];
                            //$this->retArr[$Id]['Role'] = $child['Role'];
                            $this->retArr[$Id]['GradYear'] = $child['GradYear'];
                        }
                    }
                    else {
                        //single user go herr
                        $this->userId = $ret['UserInfo']['UserId'];
                        $this->personaId = $this->retArr[$this->userId]['personaId'] = $ret['Personas'][0]['Id'];
                        $this->retArr[$this->userId]['userId'] = $this->userId;
                        $this->retArr[$this->userId]['FirstName'] = $ret['UserInfo']['FirstName'];
                        $this->retArr[$this->userId]['LastName'] = $ret['UserInfo']['LastName'];
                        $this->retArr[$this->userId]['NickName'] = $ret['UserInfo']['NickName'];
                        $this->retArr[$this->userId]['ThumbFilename'] = 'https://bbk12e1-cdn.myschoolcdn.com/'. $ret['UserInfo']['ProfilePhoto']['ThumbFilenameUrl'];
                        //$this->retArr['ThumbFilename'] = 'https://bbk12e1-cdn.myschoolcdn.com/ftpimages/'.$retSchool['SchoolInfo']['SchoolId'].'/user/'.$ret['Children'][0]['ThumbFilename'];
                        $this->retArr[$this->userId]['GradYear'] = $ret['UserInfo']['StudentInfo']['GradYear'];
                    }
                }

                //根据用户信息获取termList 数据
                foreach ($this->retArr as $key => $students) {
                    //获取GradeLevel(学年和年级)
                    $gUrl = $this->gradeLevelUrl .'?studentUserId='. $students['userId'];
                    $gUrlData = self::curl(1, $gUrl);
                    //$gradeLevel = json_decode($gUrlData, true);
                    foreach ($gUrlData as $gL) {
                        if ($gL['CurrentInd']) {
                            preg_match("/(\d){1,2}(th)/", $gL['GradeLevel'], $matches);
                            $this->gradeLevel = $matches[0];
                            
                            $this->schoolYear = str_replace(" ", "+", $gL['SchoolYearLabel']);
                        }
                    }
                    
                    $arrParam = array(
                        'studentUserId='.$students['userId'],
                        'personaId='.$students['personaId'],
                        'schoolYearLabel='.$this->schoolYear,
                    );

                    ////api/DataDirect/StudentGroupTermList/?studentUserId=4023907&schoolYearLabel=2016+-+2017&personaId=2
                    $ret = self::curl(1, $this->termListUrl . '?' . implode('&', $arrParam));
                    if ($ret) {
                        $termList = array();
                        foreach ($ret as $i => $arr) {
                            //OfferingType 1 is course type, need check grogress page to confirm it.
                            if ($arr['OfferingType'] == 1) {
                                $termList['DurationDescription'] = $arr['DurationDescription'];
                                $termList['DurationId'] = $arr['DurationId'];
                                $termList['CurrentInd'] = $arr['CurrentInd'];
                                $this->arrDuration[$key][$i] = $termList;
                                //CurrentInd is current term value, after it will get empty course;
                                if ($arr['CurrentInd']) {
                                    //get current term name
                                    $this->currentTerm = $arr['DurationDescription'];
                                    break;
                                }
                            }
                        }
                        //$this->arrDurationId = $ret[0]['DurationId'];
                    }
                }
            }
        }
    }

    protected function curlCourse($all = null) 
    {
        foreach ($this->arrDuration as $key => $arrDs) {
            //get markingperiodid for each semester
            $markingPeriodId = self::get_markingperioid($arrDs);

            //this is studentid.
            $userId = $key;
            foreach ($arrDs as $arrDuration) {
                $durationId = $arrDuration['DurationId'];
                //$all not empty will get urls of courses summary for all terms
                //then will only get url of courses summary for current term 
                if ($all) {
                    foreach($markingPeriodId as $mKey => $mData) {
                        if($durationId == $mKey) {
                            foreach($mData as $mValue) {
                                $arrParam = array(
                                    'userId='.$userId,
                                    'persona='.$this->retArr[$key]['personaId'],
                                    'schoolYearLabel='.$this->schoolYear,
                                    'memberLevel='.$this->memberLevel,
                                    'durationList='.$durationId,
                                    'markingPeriodId='.$mValue['MarkingPeriodId'],
                                );
                                $coursesUrls[] = $this->courseUrl . '?' . implode('&', $arrParam);
                                //exit loop after get current peroid.
                                if($mValue['CurrentMarkingPeriod']) {
                                    break;
                                }
                            }
                        }
                    }
                } 
                else {
                    //get current term data url
                    if ($arrDuration['CurrentInd']) {
                        //get current periodid number
                        foreach($markingPeriodId[$durationId] as $mData) {
                            if($mData['CurrentMarkingPeriod']) {
                                $periodId = $mData['MarkingPeriodId'];
                                $arrParam = array(
                                    'userId='.$userId,
                                    'persona='.$this->retArr[$key]['personaId'],
                                    'schoolYearLabel='.$this->schoolYear,
                                    'memberLevel='.$this->memberLevel,
                                    'durationList='.$durationId,
                                    'markingPeriodId='. $periodId,
                                );
                                $coursesUrls[] = $this->courseUrl . '?' . implode('&', $arrParam);
                            }
                        }
                    }
                }
            }
            // var_dump($coursesUrls);
            //loop course summary url to get grade summary data 
            //and each grade current details
            foreach ($coursesUrls as $Curl) {
                $ret = self::curl(1, $Curl);
                if ($ret) {
                    // 科目明细
                    foreach ($ret as &$arrData) {
                        //check existing grade to get grade status
                        $grade_status = self::get_last_summary_grade($this->drupalUid, $userId, $arrData['sectionid'], $arrData['cumgrade'], $arrData['currentterm'], $this->gradeLevel, $this->bdd);
                        
                        //grade summary into db
                        $query ='INSERT INTO sinica_grade_summary (
                            uid, 
                            studentid, 
                            courseid, 
                            leadcourseid,
                            coursename, 
                            teacher, 
                            teacher_email,
                            average, 
                            grade, 
                            status, 
                            createtime, 
                            durationid,
                            markingperiodid,
                            term, 
                            gradelevel,
                            schoolname
                          ) VALUES (
                            '. $this->drupalUid .',
                            '. $userId .',
                            '. $arrData['sectionid'] .',
                            '. $arrData['leadsectionid'] .',
                            "'. $arrData['sectionidentifier'] .'",
                            "'. $arrData['groupownername'] .'",
                            "'. $arrData['groupowneremail'] .'",
                            "'. $arrData['cumgrade'] .'",
                            "NULL",
                            "'. $grade_status .'",
                            "'. time() .'",
                            '.$arrData['DurationId'].',
                            '.$arrData['markingperiodid'].',
                            "'. $arrData['currentterm'] .'",
                            "'. $this->gradeLevel .'",
                            "'. self::School_Name .'"
                          )';
                        $this->bdd->execute($query);
                        
                        $out = '<h2>'. $arrData['sectionidentifier'] .'</h2>';
                        //get current score for each course summary grade.
                        $arrParam = array(
                            'sectionId='.$arrData['leadsectionid'],
                            'markingPeriodId='.$arrData['markingperiodid'],
                            'studentUserId='.$userId,
                        );
                        $url = $this->detailCourceUrl . '?' . implode('&', $arrParam);
                        
                        $tmpRet = self::curl(1, $url);
                        //var_dump($tmpRet);
                        if ($tmpRet) {
                            $keyid = 'AssignmentTypeId';
                            //get unique data by AssignmentTypeId
                            $unique_tmpRet = self::assoc_unique($tmpRet, $keyid);
                            
                            //show main category data, create table content from json data
                            foreach ($unique_tmpRet as $uRet) {
                                $out .= '<h3>'. $uRet["AssignmentType"] .'&nbsp;&nbsp;'. $uRet["Percentage"].'</h3>';
                                $out .= '<table>';
                                $out .='<thead> 
                                        <tr> 
                                            <th style="text-align:left;" class="muted">Assignment</th> 
                                            <th class="muted">Assigned</th>
                                            <th class="muted">Due</th>
                                            <th class="muted">Points</th>
                                            <th class="muted">Notes</th>
                                        </tr>
                                    </thead>';
                                $out .='<tbody>';
                                //show detail for diffent category
                                foreach ($tmpRet as $tData) {
                                    if ($uRet['AssignmentTypeId'] == $tData['AssignmentTypeId']) {
                                        $out .='<tr>';
                                        $out .='<td>'.$tData['AssignmentShortDescription'].'</td>';
                                        $out .='<td data-heading="Assigned" class="span1">'.$tData['DateAssigned'].'</td>';
                                        $out .='<td data-heading="Due" class="span1">'.$tData['DateDue'].'</td>';
                                        $out .='<td data-heading="Points" class="span2">'.$tData['Points'].'/'.$tData['MaxPoints'].'</td>';
                                        $out .='<td data-heading="Notes">'. $tData['AdditionalInfo'].'</td>';
                                        $out .='</tr>';
                                    }
                                }
                                $out .='</tbody>';
                                $out .='</table>';
                            }
                            
                            $recentscore = str_replace("'", "", $out);
                            $recentscore_json = json_encode($tmpRet);
                            $recentscore_json = str_replace("'", "", $recentscore_json);
                            //recent score inser to db
                            $query = "INSERT INTO sinica_grade_recent_scores (
                                        uid, 
                                        studentid, 
                                        leadcourseid,
                                        markingperiodId,
                                        recentscore, 
                                        recentscore_json, 
                                        term,
                                        gradeyear, 
                                        schoolname,
                                        createtime
                                    ) VALUES (  
                                        ". $this->drupalUid .",
                                        ". $userId .",
                                        ". $arrData['leadsectionid'].",
                                        ". $arrData['markingperiodid'] .",
                                        '". $recentscore ."',
                                        '". $recentscore_json ."',
                                        '". $arrData['currentterm'] ."',
                                        '". $this->gradeLevel."',
                                        '". self::School_Name ."',
                                        ".time()."
                                        )";
                            $this->bdd->execute($query);
                        }
                    }
                }
            }
        }
        //self::html_put_files($this->retArr, "datas/".self::School_Name."_grade.html");
        echo 'get courses data ok!'.PHP_EOL;
    }


    protected function curlAttendance()
    {
        $tardy_count = $absent_count = 0;
        // 考勤总览
        $arrParam = array(
            'userId='.$this->userId,
            'personaId='.$this->personaId,
            'schoolYearLabel='.$this->schoolYear,
        );
        // echo $this->absenceUrl . '?' .implode("&", $arrParam);
        $ret = self::curl(1, $this->absenceUrl . '?' .implode("&", $arrParam));
        if ($ret) {
            //prepage show all data of attendance type in one table
            $out = '<table border="1">';
            // 每项考勤明细
            foreach ($ret as &$arrData) {
                $attend = array();
                if (preg_match("/Absent/", $arrData['category_description'])) {
                    $absent_count += (int)$arrData['excuse_count'];
                }
                if (preg_match("/Tardy/", $arrData['category_description'])) {
                    $tardy_count += (int)$arrData['excuse_count'];
                }

                $newArrData = array(
                    'category_description' => $arrData['category_description'],
                    'excuse_count' => $arrData['excuse_count'],
                    'excuse_category_id' => $arrData['excuse_category_id']
                );
                
                //absent summary into db
                $query = "INSERT INTO sinica_attendance_summary (
                        uid, 
                        studentid, 
                        excuse_category_id, 
                        category_description, 
                        excuse_count, 
                        term, 
                        gradeyear,
                        schoolname,
                        createtime
                    ) VALUES (
                        ". $this->drupalUid .",
                        ". $this->userId .",
                        ". $arrData['excuse_category_id'] .",
                        '". $arrData['category_description'] ."',
                        ". $arrData['excuse_count'] .",
                        '". $this->currentTerm."',
                        '". $this->gradeLevel ."',
                        '". self::School_Name ."',
                        ". time() ."
                    )";
                $this->bdd->execute($query);

                //prepare each attendance type table header
                $out .= '<tr><td colspan="4">'. $arrData['category_description'] .'</td></tr>';
                $out .='<tr>';
                $out .='<th>GroupName</th>';
                $out .='<th>Date</th>';
                $out .='<th>Reason</th>';
                $out .='<th>Comment</th>';
                $out .='</tr>';

                //get attend detail data
                $arrParam = array(
                    'userId='.$this->userId,
                    'excuseCategoryId='.$arrData['excuse_category_id'],
                    'schoolYear='.$this->schoolYear,
                );
                $tmpRet = self::curl(1, $this->detailAbsenceUrl . '?' .implode("&", $arrParam));
                //self::html_put_files($tmpRet, "datas/capehenry_absence_tmpRet.html");
                if ($tmpRet) {
                    //get detail table body
                    foreach ($tmpRet as &$tmpArrData) {                        
                        $out .= '<tr>';
                        $out .='<td>'. $tmpArrData['GroupName'] .'</td>';
                        $out .='<td>'. $tmpArrData['CalendarDate'] .'</td>';
                        $out .='<td>'. $tmpArrData['ExcuseDescription'] .'</td>';
                        $out .='<td>'. $tmpArrData['Comment'] .'</td>';
                        $out .='</tr>';
                    }
                    
                    //get array type                    
                    foreach ($tmpRet as $tmpArrData) {
                        $newArrData['detail'][] = array(
                            'GroupName' => $tmpArrData['GroupName'],
                            'Date' => $tmpArrData['CalendarDate'],
                            'Reason' => $tmpArrData['ExcuseDescription'],
                            'Comment' => $tmpArrData['Comment'],
                            'Category' => $tmpArrData['ExcuseCategoryDescription'],
                            'ExcuseCategoryId' => $tmpArrData['ExcuseCategoryId']
                        );
                    }
                }
                //get all attendance data in array
                $attData[$arrData['excuse_category_id']] = str_replace("'", "", $newArrData);
            }
            $out .='</table>';
            $attTable = str_replace("'", "", $out);
            //$this->retArr['absence'] = $ret;
            // self::html_put_files($out, "datas/capehenry_absence_table.html");

            $query = "INSERT INTO sinica_attendance_details (
                    uid, 
                    studentid,
                    detail, 
                    detail_json,
                    term,
                    gradeyear,
                    schoolname,
                    createtime
                ) VALUES (
                    ". $this->drupalUid .",
                    ". $this->userId .",
                    '". $attTable ."',
                    '". json_encode($attData) ."',
                    '". $this->currentTerm ."',
                    '". $this->gradeLevel ."',
                    '". self::School_Name ."',
                    ". time() ."
                )";
            $this->bdd->execute($query);

            echo 'get absence data ok!'.PHP_EOL;
        }
    }

    private static function Curl($is_get=1, $url, $post=array(), $ca=false, $proxy=1)
    {
        if (!$url) {
            return;
        }
        $ret = Curl::run($is_get, $url, $post, $ca, $proxy);
        if ($ret[0]['http_code'] == 200 and $ret[1]) {
            if (strpos($ret[0]['content_type'], 'json') === false) {
                return $ret[1];
            } else {
                return json_decode($ret[1], true);
            }
        }
    }

    protected function output()
    {
        //$path = 'datas/'.self::Username.'_wlhs.myschoolapp.com.json';
        //file_put_contents($path, json_encode($this->retArr));
        $path = 'datas/'.self::Username.'_'.self::School.'_all.html';
        self::html_put_files(var_export($this->retArr, true), $path);
        echo 'success!'.PHP_EOL;
    }

    private function html_put_files($data, $fname = null)
    {
        if (empty($fname)) {
            $fname = "datas/".self::School."_login.html";
        }
        if (is_array($data)) {
            $data = var_export($data, true);
        }
        file_put_contents($fname, $data, FILE_APPEND);
    }

    private function get_last_summary_grade($uid, $studentid, $courseid, $cgrade, $term = null, $gradeyear = null, $bdd)
    {
        //$bdd = new db();
        $query = "SELECT average 
            FROM sinica_grade_summary 
            WHERE 
                studentid = ". $studentid ." 
                AND uid = ". $uid ." 
                AND courseid = '". $courseid ."' 
                AND term = '". $term ."'
                AND gradelevel = '". $gradeyear ."'
                order by id desc 
                limit 1";
        //echo"check grade summary sql :: ". $query."\n";
        $result = $bdd->getOne($query);
        
        //check grade under 75 will send email to supervisor
        //$send = self::check_mail_to_teacher($uid, $cgrade, $bdd);
        //email log

        $average = $result['average'];
        if ($average) {
            if ($cgrade > $average) {
                $status = 'up';
            } elseif ($cgrade < $average) {
                $status = 'down';
            } else {
                $status = 'equal';
            }
            return $status;
        } else {
            return null;
        }
    }

    private function check_mail_to_teacher($uid, $cgrade, $bdd)
    {
        //get email alert for supervisor
        //$bdd = new db();     
        $teacher_name = "Nathan";
        $student_name = "";
        $query = "SELECT name from users_field_data 
            where uid = ". $uid;
        $student_name = $bdd->getOne($query);
        
        $query = "SELECT ufd.name , ufd.mail 
            from users_field_data ufd 
            left join user__field_supervisor ufs on ufd.uid = ufs.field_supervisor_target_id
            where ufs.entity_id = ".$uid." and bundle = 'user'";
            // $query = "SELECT manager_teaher from users ";
        $teacher = $bdd->getAll($query);
        if (!empty($cgrade) && $cgrade < 75) {
            $subject = "Grade Notice - ". $student_name;
            $message = "Hello! ".$teacher[0]['name'] ." This is a simple Grade score under 70 email message test !!";
            $to = $teacher[0]['mail'];
            $from = "snowwind.z@gmail.com";
            $headers = "From:" . $from;
            @mail($to, $subject, $message, $headers);
            return true;
        } else {
            return false;
        }
    }

    public function assoc_unique($arr, $key)
    {
        $tmp_arr = array();
        foreach ($arr as $k => $v) {
            if (in_array($v[$key], $tmp_arr)) {
                //搜索$v[$key]是否在$tmp_arr数组中存在，若存在返回true

                unset($arr[$k]);
            } else {
                $tmp_arr[] = $v[$key];
            }
        }
        sort($arr); //sort函数对数组进行排序
        return $arr;
    }

    public function get_markingperioid($data) {
        $aRR = array();
        $i = 0;
        foreach($data as $rData) {
            $aRR[$i]['DurationId'] = $rData['DurationId'];
            $durationId = $rData['DurationId'];
            //$all not empty will get urls of courses summary for all terms
            //then will only get url of courses summary for current term 
            $arrParam = array(
                'userId='.$this->userId,
                'persona='.$this->retArr[$this->userId]['personaId'],
                'schoolYearLabel='.$this->schoolYear,
                'memberLevel='.$this->memberLevel,
                'durationList='.$durationId,
                'markingPeriodId=',
            );
            //$coursesUrls[] = $this->courseUrl . '?' . implode('&', $arrParam);
            $courseUrl = $this->courseUrl . '?' . implode('&', $arrParam);
            $ret = self::curl(1, $courseUrl);

            if($ret) {
                foreach ($ret as &$arrData) {                    
                    $aRR[$i]['LeadSectionList'][]['LeadSectionId'] = $arrData['leadsectionid'];
                }
            }
            $mParam[$i] = array(
            'durationSectionList=['.json_encode($aRR[$i]).']',
            'userId='.$this->userId,
            'personaId='.$this->personaId,
            );
            $mUrl = $this->markingPeroidIdUrl . '?' . implode('&', $mParam[$i]);            
            $mPI[$durationId] = self::curl(1, $mUrl);
            $i++;
        }
        return $mPI;
    }
}

$obj = new Wlhs_Xuezhenyao();
$obj->run();

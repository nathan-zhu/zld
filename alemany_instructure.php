<?php 
//http://auth.alemany.org/adfs/services/trust
//
//HomeRealmSelection=http%3A%2F%2Fauth.alemany.org%2Fadfs%2Fservices%2Ftrust&Email=
//https://auth.alemany.org/adfs/ls/?SAMLRequest=nZJLa8MwEITv%2FRVGd8eWEuchbEOaUBpIW5O4PfSm2ptEIEupVu7j31dOWggEfOhJsBrNfMwqRdGoI5%2B37qA38N4CuuCrURr56SIjrdXcCJTItWgAuav4dv6w5mwQ86M1zlRGkWC1zAjMpgmLgU1jkbBRndAhrSaj4S6mEzqdMErFuK7qHYtnJHgBi9LojHgb%2FxqxhZVGJ7Tzo5iOQ8pCNiwp4yPKk%2FErCYrfrFupa6n3%2FWBvZxHy%2B7IswuJpW5JgjgjW%2BdCF0dg2YLdgP2QFz5t1Rg7OHZFHkVDQCP09kB7GtpVrLQwq00TK7KWOukpInnYHPzHbi676icRfOsm7rJ6ozoul0UVIfpOet%2FTobVfLwihZff9nS3fGNsL1q7uJrMPdScqdFRolaOf7U8p8LiwIBxnxxOCbiK65PGx0%2FafyHw%3D%3D
//HomeRealmSelection=http%3A%2F%2Fauth.alemany.org%2Fadfs%2Fservices%2Ftrust&Email=
//https://auth.alemany.org/adfs/ls/?SAMLRequest=nZJRa8IwFIXf9ytK3mtttFVDW3DKmOC2ot0e9hbTWw20ictNt%2Fnvl%2BoGgtCHPQVuTs75ODcJ8qY%2BsnlrD2oDHy2g9b6bWiE7X6SkNYppjhKZ4g0gs4Jt509rRgdDdjTaaqFr4q2WKaniMKYwi2BYVhFEVEymk92MVjSk8Syi1a4cldPxOBJD4r2BQalVSpyNe43Ywkqh5cq60TCM%2FZD6dFSElNGYhfSdePlv1r1UpVT7frDdRYTssShyP3%2FZFsSbI4KxLnShFbYNmC2YTyngdbNOycHaI7Ig4DU0XJ0G0sGYVtjWwEDoJqj1Xqqgq4RkSXewM7O56qqfiP%2Blk6zL6onqvGgSXIVkd8llS8%2FOdrXMdS3F6T9betCm4bZf3U1k6VdnKbOGK5SgrOuvrvXXwgC3kBJHDK6J4JbLwQa3fyr7AQ%3D%3D

require_once('./Curl.php');
require './html-parser/src/ParserDom.php';

class Instructure_Alemany {
    const Username = 'chensh';
    const Password = 'Kaiwl18';

    protected $indexUrl;
    protected $lUrl;
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
        //$this->indexUrl = "https://auth.alemany.org/adfs/ls/?SAMLRequest=nZJRa8IwFIXf9ytK3rVtqq0NbcEpY4LbinZ72FuaXjWQJi5Jt%2Fnvl%2BoGgtCHPQVuTs75ODeZoa04knlnD3IDHx0Y6323QhpyvshRpyVR1HBDJG3BEMvIdv60JngckKNWVjElkLda5mgXJ1HdNGk4CxhjaVQHaTybTWJcx2HQpEnSBNN4Cni6Q94baMOVzJGzca%2BN6WAljaXSulEQxqMQj3BUhROCE4Kjd%2BSVv1n3XDZc7ofB6ovIkMeqKkfly7ZC3twY0NaFLpQ0XQt6C%2FqTM3jdrHN0sPZoiO9TAS2VpzF3MLpjttMwZqr1hdpz6feVoCLrD3Jm1lddDRPRv3RU9FkDUb0XzvyrkOIuu2zp2dmulqUSnJ3%2Bs6UHpVtqh9X9hDej3VlKrKbScJDW9SeE%2BlpooBZy5IjBNeHfcjlY%2F%2FZPFT8%3D";
        $this->indexUrl = "https://alemany.instructure.com/login/saml";
        $this->lUrl = "https://auth.alemany.org/adfs/ls/?SAMLRequest=";
        $this->loginUrl = "https://alemany.instructure.com:443/login/saml";
        //$this->contextUrl = "https://wlhs.myschoolapp.com/api/webapp/context?_".time();
        $this->contextUrl = "https://wlhs.myschoolapp.com/api/webapp/context";
        $this->termListUrl = "https://wlhs.myschoolapp.com/api/DataDirect/StudentGroupTermList/";
        $this->SchoolUrl = "https://wlhs.myschoolapp.com/api/webapp/schoolcontext?_".time();
        // 考勤接口
        $this->absenceUrl = "https://wlhs.myschoolapp.com/api/datadirect/ParentStudentUserAttendance/";
        $this->detailAbsenceUrl = "https://wlhs.myschoolapp.com/api/attendancestudent/ListRecordDetail/";
        // 成绩接口
        $this->courseUrl = "https://alemany.instructure.com/courses";
        $this->detailCourceUrl = "https://wlhs.myschoolapp.com/api/datadirect/GradeBookPerformanceAssignmentStudentList/";

//        $this->schoolYear = date("Y").'+-+'.(date("Y")+1);
        $this->memberLevel = 3;
    }

    public function run() {
        // 登录
        $this->authLogin();
        // // 抓取考勤
        // $this->curlAttendance();
        // // // 抓取课程
        //$this->curlCourse();
        // // // 输出
        // $this->output();
    }

    protected function authLogin() {
        echo 'curl login page, url: ' . $this->indexUrl . PHP_EOL;
        $ret = self::curl(1, $this->indexUrl);   
        if($ret) {
            $oDom = new \HtmlParser\ParserDom($ret);
            $oNode = $oDom->find('form#hrd',0);
            $SAML = $oNode->getAttr('action');
            $SAMLRequest = preg_replace("/\S+(SAMLRequest=)/", "", $SAML);
            
            $lUrl = $this->lUrl.$SAMLRequest."&RedirectToIdentityProvider=http%3a%2f%2fauth.alemany.org%2fadfs%2fservices%2ftrust";
            //echo 'curl sign in page, url: ' . $lUrl . PHP_EOL;
            // prepare login page
            $data = "UserName=pxc12365%40students.alemany.org&Password=04282000&AuthMethod=FormsAuthentication";
            $lret = self::curl(0, $lUrl, $data);
            //self::html_put_files($ret, "datas/lUrl.html");
            $loDom = new \HtmlParser\ParserDom($lret);
            $SAMLResponse = $loDom->find('input', 0)->getAttr('value');
            
            // login page
            $arrPost = "SAMLResponse=". urlencode($SAMLResponse);
            $login_ret = self::curl(0, $this->loginUrl, $arrPost);
            self::html_put_files($login_ret, "datas/SAMLRequest.html");
            if($ret) {
                echo 'login success, start spider data.'.PHP_EOL;
            }   
        }
        //var_dump($this->retArr);
    }
    protected function output() {
        //$path = 'datas/'.self::Username.'_wlhs.myschoolapp.com.json';
        //file_put_contents($path, json_encode($this->retArr));
        $path = 'datas/'.self::Username.'_alemany.html';
        self::html_put_files(var_export($this->retArr, true), $path);
        echo 'success!'.PHP_EOL;
    }

    protected function curlCourse() {
        //course list
        //$courses = self::curl(1, $this->courseUrl);
        //self::html_put_files($courses, 'datas/course.html');
        $courses = array (
                  0 => 
                  array (
                    'id' => 4400,
                    'name' => 'F American Literature ELL Liberman',
                    'account_id' => 5,
                    'start_at' => '2016-08-22T01:21:00Z',
                    'grading_standard_id' => 0,
                    'is_public' => false,
                    'course_code' => 'F American Lit EL Liberman',
                    'default_view' => 'feed',
                    'root_account_id' => 1,
                    'enrollment_term_id' => 48,
                    'end_at' => NULL,
                    'public_syllabus' => false,
                    'public_syllabus_to_auth' => false,
                    'storage_quota_mb' => 500,
                    'is_public_to_auth_users' => false,
                    'apply_assignment_group_weights' => false,
                    'calendar' => 
                    array (
                      'ics' => 'https://alemany.instructure.com/feeds/calendars/course_D2AMWYCzJhERL8oTWKfDGcbW5vOqlLbvUJVmNPCJ.ics',
                    ),
                    'time_zone' => 'America/Los_Angeles',
                    'enrollments' => 
                    array (
                      0 => 
                      array (
                        'type' => 'student',
                        'role' => 'StudentEnrollment',
                        'role_id' => 37,
                        'user_id' => 7476,
                        'enrollment_state' => 'active',
                      ),
                    ),
                    'hide_final_grades' => false,
                    'workflow_state' => 'available',
                    'restrict_enrollments_to_course_dates' => false,
                  )
              );
        foreach($courses as $course) {
            //$courseUrl = $this->courseUrl.'/'.$course['id'].'/grades';
            //echo $courseUrl ."\n";
            //$gradeHtml = self::curl(1, $courseUrl);
            $gradeHtml = file_get_contents('datas/grade_4400.html');
            $aa = array(
                'Class Work or Homework',
                'Essays & Projects',
                'Tests & Quizzes',
                'Tests &amp Quizzes',
                'Essays &amp; Projects',                
                'Participation',
                'Assignments',
            );
            $gDom = new \HtmlParser\ParserDom($gradeHtml);
            $grades_summary = $gDom->find('#grades_summary', 0)->outerHtml();
            //self::html_put_files($grades_summary, 'datas/grade_'.$course['id'].'_summary.html');
            //get grade summary 
            //$gsDom = new \HtmlParser\ParserDom($grades_summary);
            $tableTr = $gDom->find('tr.editable');
            $z = 0;
            foreach($tableTr as $th) {
                foreach($th->find('th.title a') as $thN) {
                    $grades[$course['id']][$z]['class_name'] = $thN->getPlainText();
                    //echo $thA->getPlainText()."\n";
                }
                foreach($th->find('div.context') as $thC) {
                    $grades[$course['id']][$z]['context'] = $thC->getPlainText();
                    $context[] = $thC->getPlainText();
                    //echo $thC->getPlainText()."\n";
                }
                foreach($th->find('td.due') as $tdD) {
                    $grades[$course['id']][$z]['date'] = $tdD->getPlainText();
                }
                foreach($th->find('td.points_possible') as $tdP) {
                    $grades[$course['id']][$z]['points'] = $tdP->getPlainText();   
                }
                $z++;           
            }
            $y = 0;
            foreach($gDom->find('tr.grade_details') as $tdT) {
                if($y>=$z) {
                    break;
                }
                $scores_table = $tdT->find('table.score_details_table');
                if(!empty($scores_table)) {
                    foreach($scores_table as $scs) {
                        $sText = $scs->find('.grade-summary-graph-component', 6)->getAttr('title');
                        preg_match("/(\d).*\d$/", $sText, $matches);
                        list($score, $point) = explode(" out of ", $matches[0]);  
                        $grades[$course['id']][$y]['score'] = $score;
                        $grades[$course['id']][$y]['points'] = $point;
                    }
                }
                else {
                    $grades[$course['id']][$y]['score'] = "";
                }            
                $y++;
            }
            $array = array_flip($context);
            $array = array_flip($array);
            $sum = array();
            foreach($array as $key => $conT) {
                foreach($grades as $id => $gDetail) {
                    foreach($gDetail as $gContext) {
                    if($gContext['context'] == $conT) {
                        $sum[$key]['score'] += (int)$gContext['score'];
                        $sum[$key]['points'] += (int)$gContext['points'];
                    }
                    }
                }

                $avg = $sum[$key]['score']/ $sum[$key]['points'];
                $sum[$key]['score_points']=  number_format($avg, 2);
                $grades[$course['id']]['total'][$conT] = $sum[$key]['score_points'];
            }
        }
        var_dump($grades[$course['id']]['total']);
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

$obj = new Instructure_Alemany();
$obj->run();

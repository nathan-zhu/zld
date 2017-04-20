<?php
require_once('./Curl.php');
require './html-parser/src/ParserDom.php';

class Aeries_smhs {
    const BASE_URL= "https://aeries.smhs.org";
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
    //protected $student_infos = array();

    public function __construct() {
        $this->indexUrl = "https://aeries.smhs.org/Parent/LoginParent.aspx";
        $this->loginUrl = "https://aeries.smhs.org/Parent/LoginParent.aspx";
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
    }

    public function run() {
        // 登录
        $this->authLogin();
        // 抓取考勤
        // $this->curlAttendance();
        // // 抓取课程
        // $this->curlCourse();
        // // 输出
        // $this->output();
    }

    protected function authLogin() {
        $student_infos = array();
        echo 'curl login page, url: ' . $this->indexUrl . PHP_EOL;
        $arrPost = "checkCookiesEnabled=true&checkMobileDevice=false&checkStandaloneMode=false&checkTabletDevice=false&portalAccountUsername=Siqi.qin%40smhsstudents.org&portalAccountPassword=Fgd28dq1&portalAccountUsernameLabel=&submit=";
        $aeries_login_Html = self::curl(0, $this->loginUrl, $arrPost);
        $aeries_dom = new \HtmlParser\ParserDom($aeries_login_Html);
        $inf = $aeries_dom->find('div#Sub_7', 0)->find('a', 0);
        $infos = $inf->getPlainText();
        list($student_infos['name'], $student_infos['grd'], $student_infos['school']) = explode(' - ', $infos);
        $bb = $aeries_dom->find('#ctl00_MainContent_dlAttSummaryPeriod', 0);
        $student_infos['attendance_summary'] = $bb->outerHtml();
        //var_dump($student_infos);
        //self::html_put_files($ret);
    }
    /**
     * [html_put_files description]
     * @param  [type] $fname [description]
     * @param  [type] $data  [description]
     * @return [type]        [description]
     */
    private function html_put_files($data, $fname = NULL) {
        if(empty($fname)) {
            $fname = "datas/aeries_smhs_login.html";
        }
        if(is_array($data)) {
            $data = var_export($data, true);
        }
        file_put_contents($fname, $data, FILE_APPEND);
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

$obj = new Aeries_smhs();
$obj->run();
<?php

/**
 * Created by 
 * User: Saurabh Singh
 * Date: 09/11/16
 * Time: 18:30 PM  
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class Numberscoring extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('uncle/Numberscoring_model', 'Numberdb');
        $this->adminsessionuserid = !empty($_SESSION['adminsessionuserid']) ? $_SESSION['adminsessionuserid'] : 0;
        $this->ip = $this->input->ip_address();
        if (!in_array($this->adminsessionuserid, array('215', '275', '1')) || empty($this->adminsessionuserid)) {
            redirect(base_url('/auth/login'));
        }
    }

    /*
     * panel ui for pagination (10lakh records at a time)
     */

    public function index() {
        set_time_limit(0);
        ini_set('memory_limit', '3024M');


        if ($this->input->server('REQUEST_METHOD') == "POST") {
            $this->load->library('excel_xml');
            $test = $this->input->post('flag');
            if (!$test) {


                $this->excel_xml->add_style('header', array('bold' => 1, 'size' => '12', 'color' => '#FFFFFF', 'bgcolor' => '#4F81BD'));
                $this->excel_xml->add_row(array('MSISDN', 'Score'));
                $countrycode = $this->input->post('countrycode');
                $num = $this->input->post('digit');
                $e = $this->input->post('netcode');
                $page = $this->input->post('page');
                $data = array('AdminId' => $this->adminsessionuserid, 'PageVisit' => '/uncle/numberscoring', 'ActivityDone' => "Activity::CC:$countrycode,NC:$e,DIGIT:$num,PAGE:$page", 'IP' => $this->ip, 'ActivityNew' => 'P');
                $adminid = $this->Numberdb->admin_logs($data);
                $ext_count = strlen($e);
                $b = $v = pow(10, ($num - 1));
                if ($num > 6 && $page > 1) {
                    $v = ($page - 1) * 1000000;
                } else {
                    $v = 0;
                }
                $w = pow(10, $num);
                if ($num > 6) {
                    $w = $page * 1000000;
                } else if ($num <= 6) {
                    $w = $w;
                }
                $numbercount = $num + $ext_count;
                $m = ltrim((string)($b),'1');
                $pop = $e.$m.'0';
                for ($t = $w - 1; $t >= $v; $t--) {
                    //$g = ($e * $b * 10) + $t;
                    $start = $numbercount - strlen($t);
                    $g = substr_replace($pop,$t,$start);
                    $uniqueNumber = self::checkUniqueNumber($g, $numbercount);
                    if ($uniqueNumber) {
                        $score = self::naiveScoring($g, $numbercount);
                        if ($score > 0) {
                            $mobileno = (string) ($countrycode) . (string) ($g);
                            $this->excel_xml->add_row(array($mobileno, $score));
                            //$res[$g] = $score;
                        }
                    }
                }
                //$this->Numberdb->update('TBLAdminLogs', array('ActivityNew' => 'S'), array('Id' => $adminid));
                $this->excel_xml->create_worksheet("first");
                $today = date('Y-m-d');
                $this->excel_xml->download("number-$today.xls");
            } else {
                $num = $this->input->post('msisdn');
                $len = strlen($num);
                $this->data['str'] = self::naiveScoring($num, $len, $test);
            }
        }

        $this->template->set_layout('dashboard')
                ->build('uncle/Numberscoring_view', $this->data);
    }

    /*
     * check if all the numbers are unique
     */

    private function checkUniqueNumber($num, $len) {
        $check = array();

        $chk = self::checkAllSame($num, $len);
        $numberstr = (string) $num;
        for ($i = 0; $i < $len; $i++) {
            $check[$i] = 0;
        }

        if (!empty($chk)) {
            for ($i = 0; $i < $len; $i++) {
                if ($check[$numberstr[$i]] == 1) {
                    return true;
                    break;
                } else {
                    $check[$numberstr[$i]] += 1;
                }
            }
        }
        return false;
    }

    /*
     * check if all numbers are repeated
     */

    private function checkAllSame($a, $n) {
        $a = (String) $a;
        while (--$n > 0 && $a[$n] == $a[0]);
        return $n != 0;
    }

    /*
     * scoring of individual number  
     */

    private function naiveScoring($num, $len, $test) {

        $mid = (int) (ceil(($len / 2)));
        $numberstr = (string) $num;
        $score = 0;
        $str = '';
        /* $checkarray = array();
          for ($w = 0; $w < $len; $w++) {
          $checkarray[] = 0;
          } */

        for ($i = $mid; $i >= 1; $i--) {
            $k = 0;
            $l = $len - (2 * $i) + 1;
            $dec = 1;
            $inc = 1;
            while (($l--) > 0) {
                $first_start = $k;
                $second_start = $i + $k;
                $end = $i;
                $first_str = substr($numberstr, $first_start, $end);
                $second_str = substr($numberstr, $second_start, $end);
                //$slice_check = array_slice($checkarray, $first_start, $end, preserve);
                if ($first_str == $second_str) {
                    //same
                    //$dec = 1;
                    //$inc = 1;
                    $x = (strlen($first_str) > 1) ? strlen($first_str) - 1 : 1;
                    $score += 1 * $x;
                    if ($test) {
                        $str .= "same => " . $first_str . "<==>" . $second_str . " <b>Score : </b>" . $x . "<br>";
                    }
                    /* for ($r = $first_start; $r < $first_start + $end; $r++) {
                      $checkarray[$r] = 1;
                      $checkarray[$r + $second_start] = 1;
                      } */
                } else if (((int) ($first_str) == ((int) ($second_str) - 1))) {
                    //increment


                    if ($inc == 1) {
                        $inc++;
                        $lk = 0.5;
                        $score += 0.5; // * strlen($first_str);
                    } else {
                        $inc++;
                        $lk = 1;
                        $dec = 1;
                        // $x = (($inc - 2) > 0) ? ($inc - 2) : 1;
                        $score += 1; // * strlen($first_str) * $x;
                    }
                    if ($test) {
                        $str .= "inc => " . $first_str . "<==>" . $second_str . " <b>Score : </b>$lk<br>";
                    }
                    /* for ($r = $first_start; $r < $first_start + $end; $r++) {
                      $checkarray[$r] = 1;
                      $checkarray[$r + $second_start] = 1;
                      } */
                } else if ((((int) ($first_str) - 1) == (int) ($second_str))) {
                    //decrement

                    if ($dec == 1) {
                        $dec++;
                        //$score += 0.5 * strlen($first_str);
                    } else {// else if (strlen($first_str) != 1) {
                        $inc = 1;
                        $dec++;
                        // $x = (($dec - 2) > 0) ? ($dec - 2) : 1;
                        $score += 1; // * strlen($first_str) * $x;
                        if ($test) {
                            $str .= "dec => " . $first_str . "<==>" . $second_str . " <b>Score : </b>1<br>";
                        }
                    }

                    /* for ($r = $first_start; $r < $first_start + $end; $r++) {
                      $checkarray[$r] = 1;
                      $checkarray[$r + $second_start] = 1;
                      } */
                }

                $k++;
            }
        }

        $n = $len;

        //scoring for same last digits
        while (--$n > 0 && $numberstr[$n] == $numberstr[$n - 1]) {
            if ($n < $len - 1) {
                $score += 0.5;
                $str .= "lastDigits =>" . $numberstr[$n] . "<==>" . $numberstr[$n - 1] . " <b>Score : </b>0.5<br>";
            }
        }

        if ($test) {
            return $str . "Score => $score";
        }
        return $score;
    }

}

<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class BirthdayCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'birthday:client';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Birthday mail to the clients';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //try {
            $date = date('m-d');
            $userBirthdate = \App\Models\Backend\Client::whereRaw("DATE_FORMAT(birthdate,'%m-%d')='$date'");
            if($userBirthdate->count() > 0){
            foreach($userBirthdate->get() as $u){
                $contactName = \App\Models\Backend\Contact::where("id",$u->contact_id)->first();
                $content = '<div style="width: 650px;  background-color: #eaeaea; margin: auto; border-left:  margin-top:50px; margin-bottom:50px; -webkit-border-radius: 10px 10px 10px;
    border-radius: 10px 10px 10px;">
        <table width="100%" style="text-align:center; " cellspacing="0" cellpadding="0">
            <tr>
                <td colspan="2" style="height:105px;"><img src="http://client.befree.com.au/assets/images/newsletter/logo.png" width="170px" alt="Logo"></td>
            </tr>
            <tr>
                <td colspan="2" style="text-align:center;">
                    <table cellspacing="0" cellpadding="0">
                        <tr>
                            <td><img src="http://client.befree.com.au/assets/images/newsletter/banner.jpg" alt="Banner "></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2 " style="height:5px; "></td>
            </tr>
            <tr>
                <td colspan="2">
                    <table>
                        <tr>
                            <td>&nbsp;</td>
                            <td><!--
                                <p style="text-align:left; text-align: justify; font-family: Verdana, Geneva, Tahoma, sans-serif; font-size: 14px; color: #5c5c5c; line-height: 25px; "> Dear Client ( Name of The Client),</p> -->
								 <P style="text-align:center; font-family: Gill Sans, Gill Sans MT, Calibri, Trebuchet MS, sans-serif; font-size: 30px; color: #000; font-weight: bold; padding-top: 10px; padding-bottom: 10px; ">Happy Birthday, '. $contactName->contact_person.' !</P>
                                <p style="text-align:center;  font-family: Verdana, Geneva, Tahoma, sans-serif; font-size: 14px; color: #5c5c5c;  line-height: 25px;">We at Befree, wishing you a very happy birthday. </p>
								  <p style="text-align:center; font-family: Verdana, Geneva, Tahoma, sans-serif; font-size: 14px; color: #5c5c5c;  line-height: 25px;">
								May the days ahead of you be filled with prosperity, great health and above all joy in its truest and purest form.</p>
                            </td>
                            <td>&nbsp;</td>
                        </tr>    
                        <tr>
                            <td>&nbsp;</td>
                            <td>
                                <div style="margin: auto; width: 210px;">
                                    <p style="text-align:center;  text-align: center; font-family: Verdana, Geneva, Tahoma, sans-serif; font-size: 14px; font-weight: bold; color: #000; line-height: 25px; ">
                                        <a href="https://client.befreecrm.com.au/" style="background-color: #005f9b; color: #fff; padding: 15px; text-decoration: none; -webkit-border-radius: 10px 10px 10px;
                                border-radius: 10px 10px 10px;"  target="_blank">Login to Portal</a></p><br/>
                                </div>
                            </td>
                            <td>&nbsp;</td>
                        </tr>
 
                        <tr>
                            <td>&nbsp;</td>
                            <td>
                                <p style="text-align:center; font-family: Verdana, Geneva, Tahoma, sans-serif; font-size: 14px; font-weight: bold; color: #000; line-height: 25px; "> Regards, <br> Team Befree</p>

                            </td>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td>
                                <p style="text-align:center; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #5c5c5c; line-height: 25px; margin-top: 0px; margin-bottom: 7px; "> Ph- 1300 8 7 3733</p>

                                <p style="text-align:center; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #5c5c5c; line-height: 25px; margin-top:0px; margin-bottom: 7px; "> Website:<a href="https://www.befree.com.au" style="color: #000; ">   www.befree.com.au </a></p>
                                <p style="text-align:center; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #5c5c5c; line-height: 25px; margin-top: 0px; margin-bottom: 7px; ">Address: Suite 3, Level 6, 80 George Street, Parramatta NSW 2150, Australia</p>
                            </td>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td colspan="2 " style="height: 5px; "></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        </table>
    </div>';
                $data['from_email'] = 'no-reply@befree.com.au';
                $data['to'] = $contactInfo->to_email;
                $data['cc'] = $contactInfo->cc_email;
                $subject = 'Happy Birthday '.$contactName->contact_person; 
                //$content = str_replace(array("CONTACTNAME", "SUBJECT", "LINK", "PERIOD"), array($contactInfo->first_name, $q->subject, '<a href="http://client.befree.com.au">Click here for login</a>'), $emailTemplate->content);
                $data['subject'] = $subject;
                $data['content'] = $content;
                cronStoreMail($data);
            }
            }
       /* } catch (Exception $ex) {
            $cronName = "Birthday Mail";
            $message = $ex->getMessage();
            cronNotWorking($cronName,$message);
        }*/
    }

}

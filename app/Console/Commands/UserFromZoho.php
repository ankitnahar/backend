<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;

class UserFromZoho extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:fromzoho';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lead get from zoho and store it to bdms database';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $client = new \GuzzleHttp\Client();
            $zohoDetails = \App\Models\Backend\ZohoAuth::find(1);
            $date = strtotime(date('Y-m-d H:i:s'));
            
                $accessTokenDetail = $client->request('POST','https://accounts.zoho.com/oauth/v2/token?', [ 'form_params' =>[
                    'refresh_token' => $zohoDetails->refresh_token,
                    'client_id' => $zohoDetails->client_id,
                    'client_secret' => $zohoDetails->client_secret,
                    'grant_type' => 'refresh_token'
                ]]);
                 $accessTokenDetail->getStatusCode();
                if ($accessTokenDetail->getStatusCode() == 200) {
                     $accessToken = $accessTokenDetail->getBody();
                    $acdetail = \GuzzleHttp\json_decode($accessToken, true);
                    $acdetail['access_token'];
                    \App\Models\Backend\ZohoAuth::where('id', 1)->update(['token' => $acdetail['access_token'],
                           'updated_on'=>date('Y-m-d H:i:s')]);
                    
                }
            
            $options = [
                'headers' => [// <- here :)
                    'Authorization' => 'Zoho-oauthtoken ' . $acdetail['access_token']
                ]
            ];

            $modifiedtime = strtotime(date('Y-m-d H:i:s',strtotime("-5 month")));
            $user = \App\Models\User::where("is_active","1")->where("id",">","38")->orderBy("id","desc")->get();
            foreach($user as $u){
            $res = $client->get('https://people.zoho.com/people/api/forms/P_EmployeeView/records?searchColumn=EmployeeID&searchValue='.$u->user_bio_id, $options);
            if ($res->getStatusCode() == 200) {
                $data = $res->getBody();
                $data = \GuzzleHttp\json_decode($res->getBody(), true);
                foreach ($data as $d) {
                    if(!isset($d['EmployeeID'])){
                        continue;
                    }                  
                    
                    $dateOfjoin= date("Y-m-d",strtotime($d['Date of joining']));
                    $birth = date("Y-m-d",strtotime($d['Birth Date']));
                    $insertArray = array("Entity" => isset($d['Entity']) ? $d['Entity'] : '',
                        "Contract_Fee" => isset($d['Contract Fee p.m. (in word)1']) ? $d['Contract Fee p.m. (in word)1'] : '',
                        "Referred_by" => isset($d['Referred by']) ? $d['Referred by'] : '',
                        "First_Name" => isset($d['First Name']) ? $d['First Name'] : '',
                        "Email_ID" => isset($d['Email ID']) ? $d['Email ID'] : '',
                        "Gender" => isset($d['Gender']) ? $d['Gender'] : '',
                        "Branch_Name" => isset($d['Branch Name']) ? $d['Branch Name'] : '',
                        "Employee_type" => isset($d['Employee type']) ? $d['Employee type'] : '',
                        "ApprovalStatus" => isset($d['ApprovalStatus']) ? $d['ApprovalStatus'] : '',
                        "recordId" => isset($d['recordId']) ? $d['recordId'] : '',
                        "States" => isset($d['States']) ? $d['States'] : '',
                        "Department" => isset($d['Department']) ? $d['Department'] : '',
                        "Damage_Cost_Clause_Valid_Till" => isset($d['Damage Cost Clause Valid Till']) ? $d['Damage Cost Clause Valid Till'] : '',
                        "PAN_Number" => isset($d['PAN Number']) ? $d['PAN Number'] : '',
                        "createdTime" => isset($d['createdTime']) ? $d['createdTime'] : '',
                        "Date_of_joining" => $dateOfjoin,
                        "Notice_Period" => isset($d['Notice Period']) ? $d['Notice Period'] : '',
                        "Source_of_hire" => isset($d['Source of hire']) ? $d['Source of hire'] : '',
                        "Service_Agreement" => isset($d['Service Agreement']) ? $d['Service Agreement'] : '',
                        "Reporting_To" => isset($d['Reporting To']) ? $d['Reporting To'] : '',
                        "Employee_status" => isset($d['Employee status']) ? $d['Employee status'] : '',
                        "Work_phone" => isset($d['Work phone']) ? $d['Work phone'] : '',
                        "Account_No" => isset($d['Account No.']) ? $d['Account No.'] : '',
                        "Grade" => isset($d['Grade']) ? $d['Grade'] : '',
                        "ownerID" => isset($d['ownerID']) ? $d['ownerID'] : '',
                        "Resignation_Date" => isset($d['Resignation Date']) ? $d['Resignation Date'] : '',
                        "Extra_comments_on_retainment_(old)" => isset($d['Extra comments on retainment (old)']) ? $d['Extra comments on retainment (old)'] : '',
                        "Role" => isset($d['Role']) ? $d['Role'] : '',
                        "Work_location" => isset($d['Work location']) ? $d['Work location'] : '',
                        "Pseudo_Name" => isset($d['Pseudo Name']) ? $d['Pseudo Name'] : '',
                        "Experience" => isset($d['Experience']) ? $d['Experience'] : '',
                        "Marital_status" => isset($d['Marital status']) ? $d['Marital status'] : '',
                        "Mothers_Name" => isset($d["Mother's Name"]) ? $d["Mother's Name"] : '',
                        "Name_as_per_Salary_Account" => isset($d['Name as per Salary Account']) ? $d['Name as per Salary Account'] : '',
                        "LEVEL" => isset($d['Level']) ? $d['Level'] : '',
                        "Retained_Amount" => isset($d['Retained Amount']) ? $d['Retained Amount'] : '',
                        "Retainment" => isset($d['Retainment']) ? $d['Retainment'] : '',
                        "Fathers_Name" => isset($d["Father's Name"]) ? $d["Father's Name"] : '',
                        "EmployeeID" => isset($d['EmployeeID']) ? $d['EmployeeID'] : '',
                        "Last_Name" => isset($d['Last Name']) ? $d['Last Name'] : '',
                        "Mobile_Phone" => isset($d['Mobile Phone']) ? $d['Mobile Phone'] : '',
                        "Name_Emergency_Contact_1" => isset($d['Name - Emergency Contact - 1']) ? $d['Name - Emergency Contact - 1'] : '',
                        "Permanent_Address" => isset($d['Permanent Address']) ? $d['Permanent Address'] : '',
                        "Bank" => isset($d['Bank']) ? $d['Bank'] : '',
                        "PINZIP_Code" => isset($d['PIN/ZIP Code']) ? $d['PIN/ZIP Code'] : '',
                        "Next_Review_Due" => isset($d['Next Review Due']) ? $d['Next Review Due'] : '',
                        "Emergency_No_2" => isset($d['Emergency No. - 2']) ? $d['Emergency No. - 2'] : '',
                        "Emergency_No_1" => isset($d['Emergency No. - 1']) ? $d['Emergency No. - 1'] : '',
                        "Name_Emergency_Contact_2" => isset($d['Name - Emergency Contact - 2']) ? $d['Name - Emergency Contact - 2'] : '',
                        "Secondary_Reporting_to" => isset($d['Secondary Reporting to']) ? $d['Secondary Reporting to'] : '',
                        "Damages_Cost" => isset($d['Damages Cost']) ? $d['Damages Cost'] : '',
                        "IFSC_Code" => isset($d['IFSC Code']) ? $d['IFSC Code'] : '',
                        "Other_Email" => isset($d['Other Email']) ? $d['Other Email'] : '',
                        "ownerName" => isset($d['ownerName']) ? $d['ownerName'] : '',
                        "From_Date" => isset($d['From Date']) ? $d['From Date'] : '',
                        "Incentive_Amount" => isset($d['Incentive Amount']) ? $d['Incentive Amount'] : '',
                        "Birth_Date" => isset($d['Birth Date']) ? $birth : '',
                        "First_Review" => isset($d['First Review']) ? $d['First Review'] : '',
                        "Age" => isset($d['Age']) ? $d['Age'] : '',
                        "Designation" => isset($d['Designation']) ? $d['Designation'] : '',
                        "TC_of_Retention_deductionBonus" => isset($d['T&C of Retention deduction/Bonus']) ? $d['T&C of Retention deduction/Bonus'] : '',
                        "Location_Name" => isset($d['Location Name']) ? $d['Location Name'] : '',
                        "Title" => isset($d['Title']) ? $d['Title'] : '',
                        "Date_of_confirmation" => isset($d['Date of confirmation']) ? $d['Date of confirmation'] : '',
                        "Work_Location1" => isset($d['Work Location1']) ? $d['Work Location1'] : '',
                        "To_Date" => isset($d['To Date']) ? $d['To Date'] : '',
                        "Extension" => isset($d['Extension']) ? $d['Extension'] : '',
                        "Spouses_Name" => isset($d["Spouse's Name"]) ? $d["Spouse's Name"] : "",
                        "Blood_Group" => isset($d['Blood Group']) ? $d['Blood Group'] : '',
                        "Team_Incharge" => isset($d['Team Incharge']) ? $d['Team Incharge'] : '',
                        "Total_experience" => isset($d['Total experience']) ? $d['Total experience'] : '',
                        "Family" => isset($d['Family']) ? $d['Family'] : '',
                        "Address_Line_1" => isset($d['Address Line 1']) ? $d['Address Line 1'] : '',
                        "Appointment_letter_Status" => isset($d['Appointment letter Status']) ? $d['Appointment letter Status'] : '',
                        "Address_Line_2" => isset($d['Address Line 2']) ? $d['Address Line 2'] : '',
                        "Location" => isset($d['Location'])? $d['Location'] : '');
//showArray($d['PIN/ZIP Code']);//exit;
                    $checkUserDetail = \App\Models\Backend\UserZohoDetail::where("EmployeeID", $d['EmployeeID']);
                    if ($checkUserDetail->count() == 0) {
                        \App\Models\Backend\UserZohoDetail::create($insertArray);
                    } else {
                        \App\Models\Backend\UserZohoDetail::where("EmployeeID", $d['EmployeeID'])->update($insertArray);
                    }
                    \App\Models\User::where("user_bio_id", $d['EmployeeID'])->update(["Entity" =>  isset($d['Entity']) ? $d['Entity'] : '']);
                }
            }
            }
        } catch (Exception $ex) {
            $cronName = "User from Zoho Cron";
          $message = $ex->getMessage();
          cronNotWorking($cronName, $message);
        }
    }
}

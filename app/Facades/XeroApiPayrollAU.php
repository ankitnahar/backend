<?php

namespace App\Facades;

//use XeroAPI\XeroPHP;
use SidneyAllen\XeroPHP;
use XeroAPI\XeroPHP\PayrollAuObjectSerializer;

class XeroApiPayrollAU {

    function __construct() {
        
    }

    public static function getConnection($entityId = null) {
        if ($entityId == null) {
            $XeroAuthData = \App\Models\Backend\XeroAuth::find(1);
        } else {
            $XeroAuthData = \App\Models\Backend\XeroAuth::where("id", 2)->first();
            $EntityData = \App\Models\Backend\XeroEntityAuth::where("entity_id", $entityId)->first();
            $XeroAuthData->tenant_id = $EntityData->tenant_id;
        }
        $provider = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId' => env('XERO_KEY_PAYROLL'),
            'clientSecret' => env('XERO_SECRETE_PAYROLL'),
            'redirectUri' => 'http://localhost/xero-php-oauth2-starter-master/callback.php',
            'urlAuthorize' => 'https://login.xero.com/identity/connect/authorize',
            'urlAccessToken' => 'https://identity.xero.com/connect/token',
            'urlResourceOwnerDetails' => 'https://api.xero.com/api.xro/2.0/Organisation'
        ]);
        $options = [
            'scope' => ['openid profile email payroll.employees payroll.employees.read payroll.payruns payroll.payruns.read payroll.payslip payroll.payslip.read payroll.timesheets payroll.timesheets.read payroll.settings payroll.settings.read accounting.transactions accounting.transactions.read accounting.reports.read accounting.reports.tenninetynine.read accounting.journals.read accounting.settings accounting.settings.read accounting.contacts accounting.contacts.read accounting.attachments accounting.attachments.read accounting.budgets.read offline_access']
        ];
        $newAccessToken = $provider->getAccessToken('refresh_token', [
            'refresh_token' => $XeroAuthData->refresh_token
        ]);
// Save Refresh Token
        $XeroAuthData->token = $newAccessToken->getToken();
        $XeroAuthData->expires = $newAccessToken->getExpires();
        $XeroAuthData->refresh_token = $newAccessToken->getRefreshToken();
        $XeroAuthData->id_token = $newAccessToken->getValues()["id_token"];
        $XeroAuthData->updated_on = date("Y-m-d H:i:s");
        $XeroAuthData->save();
        return $XeroAuthData;
    }

    public static function createEmployee($entityId, $employeeData) {
// Configure OAuth2 access token for authorization: OAuth2
        try {
            $XeroAuthData = self::getConnection($entityId);
            $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($XeroAuthData->token);
            $apiInstance = new \XeroAPI\XeroPHP\Api\PayrollAuApi(
                    new \GuzzleHttp\Client(), $config
            );

            $dateOfBirth = new \DateTime($employeeData->date_of_birth);

            $payrollCal = \App\Models\Backend\XeroPayrollCalendar::where("id", $employeeData->payroll_calendar_id)
                            ->where("entity_id", $entityId)->first();
            $earningType = \App\Models\Backend\XeroEarningType::where("id", $employeeData->ordinary_earning_id)
                            ->where("entity_id", $entityId)->first();
            $employee = new \XeroAPI\XeroPHP\Models\PayrollAu\Employee;
            $employee->setFirstName($employeeData->first_name);
            $employee->setLastName($employeeData->last_name);
            $employee->setMiddleNames($employeeData->middle_name);
            $employee->setTitle($employeeData->title);
            $employee->setEmail($employeeData->email);
            $employee->setMobile($employeeData->mobile);
            $employee->setGender($employeeData->gender);
            $employee->setJobTitle($employeeData->ocupation);
            $employee->setPhone($employeeData->phone);
            $employee->setDateOfBirthAsDate($dateOfBirth);
            $employee->setEmploymentType($employeeData->employment_type);
            $employee->setIncomeType($employeeData->income_type);
            $employee->setPayrollCalendarID($payrollCal->payroll_calendar_id);
            $employee->setOrdinaryEarningsRateID($earningType->earning_rate_id);

            $employee->setStatus("ACTIVE");
            $employee->setClassification($employeeData->classification);
            if ($employeeData->start_date != '' && $employeeData->start_date != null) {
                $employee->setStartDate(\DateTime::createFromFormat('Y-m-d', $employeeData->start_date));
            }

            $homeAddress = new \XeroAPI\XeroPHP\Models\PayrollAu\HomeAddress;
            $homeAddress->setAddressLine1($employeeData->address);
            $homeAddress->setRegion($employeeData->state);
            $homeAddress->setPostalCode($employeeData->postal_code);
            $homeAddress->setCity($employeeData->city);

            $tax = new \XeroAPI\XeroPHP\Models\PayrollAu\TaxDeclaration;
            $tax->setTaxScaleType($employeeData->tax_scale);
            $tax->setTaxFileNumber($employeeData->TFN);
            $tax->setEmploymentBasis($employeeData->employement_basic);
            $tax->setResidencyStatus($employeeData->residency_status);
            $tax->setHasTradeSupportLoanDebt($employeeData->hecs_dept == 1 ? 'true' : false);
            $tax->setTaxFreeThresholdClaimed($employeeData->tax_free_threshold_claim == 1 ? 'true' : false);
            $tax->setEligibleToReceiveLeaveLoading($employeeData->eligible_to_receive_leave_loading == 1 ? 'true' : false);

            $bankAccount = new \XeroAPI\XeroPHP\Models\PayrollAu\BankAccount;
            $bankAccount->setStatementText('Wages Payment');
            $bankAccount->setAccountName($employeeData->account_name);
            $bankAccount->setAccountNumber($employeeData->account_number);
            $bankAccount->setRemainder(true);
            $bankAccount->setBsb($employeeData->bsb_number);
            $newBankAccounts = [$bankAccount];

            if ($employeeData->superannuation_member_number != '' || $employeeData->superannuation_fund != '') {

                $super = new \XeroAPI\XeroPHP\Models\PayrollAu\SuperMembership;
                $super->setSuperFundId($employeeData->superfund_id);
                $super->setEmployeeNumber($employeeData->superannuation_member_number);
                $newSuper = [$super];
                $employee->setSuperMemberships($newSuper);
            }

            $employee->setHomeAddress($homeAddress);
            $employee->setTaxDeclaration($tax);
            $employee->setBankAccounts($newBankAccounts);

            //echo $employeeData->superannuation_fund;exit;


            $earning = \App\Models\Backend\XeroEmployeeEarning::leftjoin("xero_earningtype as et", "et.id", "xero_employee_earning.earningtype_id")
                            ->where("xero_employee_earning.entity_id", $entityId)->where("xero_employee_earning.employee_id", $employeeData->id)
                            ->select("xero_employee_earning.*", "et.earning_rate_id", "et.earning_type_id", "et.rate_type")->get();
            $payTemplate = new \XeroAPI\XeroPHP\Models\PayrollAu\PayTemplate;
            $newEarningLine = [];
            foreach ($earning as $edata) {
                $earningLine = new \XeroAPI\XeroPHP\Models\PayrollAu\EarningsLine;
                $earningLine->setEarningsRateID($edata->earning_rate_id);
                $earningLine->setCalculationType($edata->calculation_type);
                $earningLine->setRatePerUnit($edata->rate);
                $earningLine->setNumberOfUnits($edata->hours);
                $earningLine->setNormalNumberOfUnits($edata->hours);
                $earningLine->setNumberOfUnitsPerWeek($edata->hours_per_week);
                $earningLine->setAnnualSalary($edata->annual_salary);
                $earningLine->setFixedAmount($edata->fixed_amount);
                array_push($newEarningLine, $earningLine);
                $payTemplate->setEarningsLines($newEarningLine);
            }
           /* $superLine = new \XeroAPI\XeroPHP\Models\PayrollAu\SuperLine;
            //$superLine->setSuperMembershipId($super_membership_id);
            $superLine->setCalculationType('STATUTORY');
            $superLine->setContributionType('SGC');
            $superLine->setMinimumMonthlyEarnings('450');
            $superLine->setExpenseAccountCode('724');
            $superLine->setLiabilityAccountCode('250');
            //$superLine->setAmount($amount);
            //$superLine->setPercentage($percentage);
            $newSuperLine = [$superLine];
            $payTemplate->setSuperLines($newSuperLine);*/

            $leave = \App\Models\Backend\XeroEmployeeLeave::leftjoin("xero_leave as l", "l.id", "xero_employee_leave.leave_id")
                            ->where("xero_employee_leave.entity_id", $entityId)->where("xero_employee_leave.employee_id", $employeeData->id)
                            ->select("xero_employee_leave.*", "l.leave_type_id")->get();
            $newLeaveLine = [];
            foreach ($leave as $leavedata) {
                $leaveLine = new \XeroAPI\XeroPHP\Models\PayrollAu\LeaveLine;
                $leaveLine->setLeaveTypeId($leavedata->leave_type_id);
                $leaveLine->setCalculationType($leavedata->type);
                $leaveLine->setAnnualNumberOfUnits($leavedata->annually_hour);
                $leaveLine->setFullTimeNumberOfUnitsPerPeriod($leavedata->employee_works);
                $leaveLine->setEntitlementFinalPayPayoutType($leavedata->termination_unused_bal);
                array_push($newLeaveLine, $leaveLine);
                $payTemplate->setLeaveLines($newLeaveLine);
            }

            $employee->setPayTemplate($payTemplate);


            $newEmployees = [];
            array_push($newEmployees, $employee);
            //showArray($newEmployees);exit;
            $result = $apiInstance->createEmployee($XeroAuthData->tenant_id, $newEmployees);
            foreach ($result as $r) {
                \App\Models\Backend\XeroEmployee::where("id", $employeeData->id)->update(["xero_employee_id" => $r['employee_id']]);
            }
        } catch (Exception $e) {
            //dd($e->getResponse()->getBody()->getContents());
            app('log')->error("Employee creation failed " . $e->getMessage(), PHP_EOL);
            return createResponse(config('httpResponse.UNPROCESSED'), 'Could not add employee', ['error' => $e->getResponse()->getBody()->getContents(), PHP_EOL]);
        }
    }

    public static function createPayrun($entityId, $payRunData) {
// Configure OAuth2 access token for authorization: OAuth2
        $XeroAuthData = self::getConnection($entityId);
        $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($XeroAuthData->token);
        $apiInstance = new \XeroAPI\XeroPHP\Api\PayrollAuApi(
                new \GuzzleHttp\Client(), $config
        );
        try {
            $payrollCalendar = \App\Models\Backend\XeroPayrollCalendar::where("entity_id", $entityId)
                            ->where("id", $payRunData->payroll_calendar_id)->first();
            $payrun = new \XeroAPI\XeroPHP\Models\PayrollAu\PayRun;
            $payrun->setPayrollCalendarID($payrollCalendar->payroll_calendar_id);
            $payrun->setPayRunStatus("DRAFT");
            $payrun->setPaymentDate($payRunData['payment_date']);
            $newPayrun = [];
            array_push($newPayrun, $payrun);
            if ($payRunData->pay_run_id == null) {
                $result = $apiInstance->createPayRun($XeroAuthData->tenant_id, $newPayrun);
                foreach ($result as $r) {
                    $payrunId = $r['pay_run_id'];
                }
                \App\Models\Backend\XeroPayrun::where("id", $payRunData->id)->update(["pay_run_id" => $payrunId]);
            } else {
                $payrunId = $payRunData->pay_run_id;
            }
            $payrunDetail = $apiInstance->getPayRun($XeroAuthData->tenant_id, $payrunId);
            foreach ($payrunDetail as $payDetail) {
                foreach ($payDetail['payslips'] as $ps) {
                    $payrunPayslipEmployee = \App\Models\Backend\XeroEmployee::where("xero_employee_id", $ps['employee_id']);
                    if ($payrunPayslipEmployee->count() == 0) {
                        continue;
                    }
                    $payrunPayslipEmployee = $payrunPayslipEmployee->first();
                    \App\Models\Backend\XeroPayrunEmployee::where("payrun_id", $payRunData->id)->where("employee_id", $payrunPayslipEmployee->id)
                            ->update(['payslip_id' => $ps['payslip_id']]);
                }
            }
            $payrunEmployee = \App\Models\Backend\XeroPayrunEmployee::leftjoin("xero_employee as xe", "xe.id", "xero_payrun_employee.employee_id")
                            ->select("xero_payrun_employee.*", "xe.xero_employee_id", "xe.first_name", "xe.last_name")
                            ->where("xero_payrun_employee.payrun_id", $payRunData->id)->get();
            //showArray($payrunEmployee);exit;
            $newPayslip = [];
            foreach ($payrunEmployee as $pe) {
                $payslip = new \XeroAPI\XeroPHP\Models\PayrollAu\Payslip;
                $payslip->setPayslipId($pe->payslip_id);
                $payslip->setEmployeeId($pe->xero_employee_id);
                $payslip->setFirstName($pe->first_name);
                $payslip->setLastName($pe->last_name);

                $payrunEmployeeEarning = \App\Models\Backend\XeroPayrunEmployeeEarning::
                                leftjoin("xero_earningtype as xet", "xet.id", "xero_payrun_employee_earning.earningtype_id")
                                ->select("xero_payrun_employee_earning.*", "xet.earning_rate_id", "xet.rate_type")
                                ->where("xero_payrun_employee_earning.employee_id", $pe->employee_id)
                        ->where("xero_payrun_employee_earning.payrun_id", $payRunData->id)->get();
                $newEarningsLines = [];
                foreach ($payrunEmployeeEarning as $peEarning) {
                    $earningsLines = new \XeroAPI\XeroPHP\Models\PayrollAu\EarningsLine;
                    $earningsLines->setEarningsRateId($peEarning->earning_rate_id);
                    if ($peEarning->calculation_type == 'ANNUALSALARY') {
                        $earningsLines->setAnnualSalary($peEarning->annual_salary);
                        $earningsLines->setNumberOfUnitsPerWeek($peEarning->hours_per_week);
                    } else {
                        if ($peEarning->fixed_amount > 0) {
                            $earningsLines->setFixedAmount($peEarning->fixed_amount);
                        } else {
                            $earningsLines->setRatePerUnit($peEarning->rate);

                            if ($peEarning->rate_type == 'MULTIPLE') {
                                $earningsLines->setNormalNumberOfUnits($peEarning->hours);
                            } else {
                                $earningsLines->setNumberOfUnits($peEarning->hours);
                            }
                        }
                    }
                    array_push($newEarningsLines, $earningsLines);                    
                    $payslip->setEarningsLines($newEarningsLines);
                }


                /* $payrunEmployeeLeave = \App\Models\Backend\XeroEmployeeLeave::
                  leftjoin("xero_leave as xel", "xel.id", "xero_employee_leave.leave_id")
                  ->select("xero_employee_leave.*", "xel.leave_type_id", "xel.leave_category_code")
                  ->where("xero_employee_leave.employee_id", $pe->employee_id)->get();
                  $newLeaveLines = [];

                  foreach ($payrunEmployeeLeave as $peleave) {
                  $leaveLines = new \XeroAPI\XeroPHP\Models\PayrollAu\LeaveLine;
                  $leaveLines->setLeaveTypeId($peleave->leave_type_id);
                  $leaveLines->setNumberOfUnits($peleave->leave_apply);
                  array_push($newLeaveLines, $leaveLines);
                  $payslip->setLeaveAccrualLines($newLeaveLines);
                  } */
                array_push($newPayslip, $payslip);
                //showArray($newPayslip);
                $payslipResult = $apiInstance->updatePayslip($XeroAuthData->tenant_id, $pe['payslip_id'], $newPayslip);
                self::createLeaveApplication($entityId, $payRunData->id);
                //exit;
            }
        } catch (Exception $e) {
            \App\Models\Backend\XeroPayrun::where("id", $payRunData->id)->update(['error_log' => $e->getResponse()->getBody()->getContents()]);
            app('log')->error("Employee creation failed " . $e->getMessage(), PHP_EOL);
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add employee', ['error' => $e->getResponse()->getBody()->getContents(), PHP_EOL]);
        }
    }

    public static function createLeaveApplication($entityId, $payrunId) {
// Configure OAuth2 access token for authorization: OAuth2
        $XeroAuthData = self::getConnection($entityId);
        $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($XeroAuthData->token);
        $apiInstance = new \XeroAPI\XeroPHP\Api\PayrollAuApi(
                new \GuzzleHttp\Client(), $config
        );
        try {
            $payrunData = \App\Models\Backend\XeroPayrun::find($payrunId);
            $employeeLeave = \App\Models\Backend\XeroPayrunEmployeeLeave::
                    leftjoin("xero_leave as xel", "xel.id", "xero_payrun_employee_leave.leave_id")
                    ->leftjoin("xero_employee as xe", "xe.id", "xero_payrun_employee_leave.employee_id")
                    ->select("xero_payrun_employee_leave.*", "xel.leave_type_id", "xel.leave_category_code", "xe.xero_employee_id")
                    ->whereRaw("xero_payrun_employee_leave.leave_application_id IS null")
                    ->where("payrun_id", $payrunId);
            if ($employeeLeave->count() > 0) {
                foreach ($employeeLeave->get() as $peleave) {
                    $newLeaveApplication = [];
                    $leaveApplication = new \XeroAPI\XeroPHP\Models\PayrollAu\LeaveApplication;
                    $startDate = new \DateTime($peleave->start_date);
                    $endDate = new \DateTime($peleave->end_date);
                    $payPeriodstartDate = new \DateTime($payrunData->start_date);
                    $payPeriodendDate = new \DateTime($payrunData->end_date);
                    $leaveApplication->setEmployeeId($peleave->xero_employee_id);
                    $leaveApplication->setLeaveTypeId($peleave->leave_type_id);
                    $leaveApplication->setStartDate($startDate);
                    $leaveApplication->setEndDate($endDate);
                    $leaveApplication->setDescription($peleave->description);
                    $leaveperiod = new \XeroAPI\XeroPHP\Models\PayrollAu\LeavePeriod;
                    $leaveperiod->setNumberOfUnits($peleave->leave_apply);
                    $leaveperiod->setPayPeriodStartDateAsDate($payPeriodstartDate);
                    $leaveperiod->setPayPeriodEndDateAsDate($payPeriodendDate);
                    $newLeaveperiod = [$leaveperiod];
                    $leaveApplication->setLeavePeriods($newLeaveperiod);
                    array_push($newLeaveApplication, $leaveApplication);
                    // showArray($newLeaveApplication);exit;
                    $result = $apiInstance->createLeaveApplication($XeroAuthData->tenant_id, $newLeaveApplication);
                    foreach ($result as $r)
                        \App\Models\Backend\XeroPayrunEmployeeLeave::where("id", $peleave->id)->update(['leave_application_id' => $r['leave_application_id']]);
                }
            }
        } catch (Exception $e) {
            echo 'Exception when calling PayrollAuApi->createEmployee: ', $e->getMessage(), PHP_EOL;
        }
    }

    public static function createSuperfund($entity, $super) {
// Configure OAuth2 access token for authorization: OAuth2
        $XeroAuthData = self::getConnection();
        $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($XeroAuthData->token);
        $apiInstance = new \XeroAPI\XeroPHP\Api\PayrollAuApi(
                new \GuzzleHttp\Client(), $config
        );
        $newsuperfund = [];
        $superfund = new \XeroAPI\XeroPHP\Models\PayrollAu\SuperFund;
        if ($super->type == 'REGULATED') {
            $superfund->setType(\XeroAPI\XeroPHP\Models\PayrollAu\SuperFundType::REGULATED);
            $superfund->setName($super->name);
            $superfund->setAbn($super->ABN);
            $superfund->setUSI($super->USI);
        } else {
            $superfund->setType(\XeroAPI\XeroPHP\Models\PayrollAu\SuperFundType::SMSF);
            $superfund->setName($super->name);
            $superfund->setAccountNumber($super->account_number);
            $superfund->setBsb($super->bsb);
            $superfund->setAccountName($super->account_name);
            $superfund->setAbn($super->ABN);
            $superfund->setElectronicServiceAddress($super->electronic_service_address);
            $superfund->setEmployerNumber($super->employer_number);
        }

        try {
            array_push($newsuperfund, $superfund);
            $result = $apiInstance->createSuperfund($XeroAuthData->tenant_id, $newsuperfund);
            foreach ($result as $r) {
                \App\Models\Backend\XeroSuperfund::where("id", $super->id)->update(["superfund_id" => $r['super_fund_id']]);
            }
        } catch (Exception $e) {
            echo 'Exception when calling PayrollAuApi->createEmployee: ', $e->getMessage(), PHP_EOL;
        }
    }

    public static function getEmployees($entityId) {
// Configure OAuth2 access token for authorization: OAuth2
        $XeroAuthData = self::getConnection($entityId);
        $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($XeroAuthData->token);
        $apiInstance = new \XeroAPI\XeroPHP\Api\PayrollAuApi(
                new \GuzzleHttp\Client(), $config
        );

        try {
            $where = 'status="ACTIVE"';
            $employeeList = $apiInstance->getEmployees($XeroAuthData->tenant_id, null, $where, null);
            \App\Models\Backend\XeroEmployee::where("entity_id", $entityId)->where("xero_employee_id","!=","'NULL'")->update(["is_active" => 'TERMINATED']);
            $alreadyAddedData = \App\Models\Backend\XeroEmployee::where("entity_id", $entityId)->get()->pluck('id', 'xero_employee_id')->toArray();

            foreach ($employeeList as $r) {

                if ($r['start_date'] == '') {
                    continue;
                }
                $employee = $apiInstance->getEmployee($XeroAuthData->tenant_id, $r['employee_id']);
                $bankDetail = $employee->getEmployees()[0]->getBankAccounts();
                $homeDetail = $employee->getEmployees()[0]->getHomeAddress();
                $payDetail = $employee->getEmployees()[0]->getPayTemplate();
                $employementType = $employee->getEmployees()[0]->getEmploymentType();
                $incomeType = $employee->getEmployees()[0]->getIncomeType();
                $superDetail = $employee->getEmployees()[0]->getSuperMemberships();
                $taxDetail = $employee->getEmployees()[0]->getTaxDeclaration();
                $payrollCalendarId = \App\Models\Backend\XeroPayrollCalendar::where("entity_id", $entityId)
                                ->where("payroll_calendar_id", $r['payroll_calendar_id'])->first();
                $earningType = \App\Models\Backend\XeroEarningType::where("entity_id", $entityId)->get()->pluck('id', 'earning_rate_id')->toArray();

                $birth = convertXeroDate($r['date_of_birth']);

                $start = ($r['start_date'] != '') ? convertXeroDate($r['start_date']) : '0000-00-00';
                $created = convertXeroDate($r['updated_date_utc']);
                $employeeData = array('entity_id' => $entityId,
                    'first_name' => $r['first_name'],
                    'middle_name' => $r['middle_names'],
                    'last_name' => $r['last_name'],
                    'title' => $r['title'],
                    'date_of_birth' => $birth,
                    'birth' => $r['date_of_birth'],
                    'gender' => $r['gender'],
                    'email' => $r['email'],
                    'phone' => $r['phone'],
                    'start_date' => $start,
                    'occupaction' => $r['occupaction'],
                    'xero_employee_id' => $r['employee_id'],
                    'account_number' => (isset($bankDetail[0]['account_number'])) ? $bankDetail[0]['account_number']:'',
                    'account_name' => (isset($bankDetail[0]['account_name'])) ? $bankDetail[0]['account_name']:'',
                    'bsb_number' => (isset($bankDetail[0]['bsb'])) ? $bankDetail[0]['bsb']:'',
                    'address' => (isset($homeDetail['address_line1'])) ? $homeDetail['address_line1']:'',
                    'city' => (isset($homeDetail['city'])) ? $homeDetail['city']:'',
                    'region' => (isset($homeDetail['region'])) ? $homeDetail['region']:'',
                    'postal_code' => (isset($homeDetail['postal_code'])) ? $homeDetail['postal_code']:'',
                    'payroll_calendar_id' => isset($payrollCalendarId->id) ? $payrollCalendarId->id : '',
                    'ordinary_earning_id' => isset($earningType[$r['ordinary_earnings_rate_id']]) ? $earningType[$r['ordinary_earnings_rate_id']] : '',
                    'employment_type' => $employementType,
                    'income_type' => $incomeType,
                    'tax_scale' => (isset($taxDetail['tax_scale_type'])) ? $taxDetail['tax_scale_type']:'',
                    'created_on' => $created,
                    'classification' => $r['classification'],
                    'superfund_id' => (isset($superDetail[0]['super_fund_id'])) ? $superDetail[0]['super_fund_id'] : '',
                    'superannuation_member_number' => (isset($superDetail[0]['employee_number'])) ? $superDetail[0]['employee_number'] : '',
                    'employement_basic' => (isset($taxDetail['employment_basis'])) ? $taxDetail['employment_basis']:'',
                    'residency_status' => (isset($taxDetail['residency_status'])) ? $taxDetail['residency_status']:'',
                    'TFN' => (isset($taxDetail['tax_file_number'])) ? $taxDetail['tax_file_number'] :'',
                    'tax_scale' => (isset($taxDetail['tax_scale_type'])) ? $taxDetail['tax_scale_type']:'',
                    'tax_free_threshold_claim' => (isset($taxDetail['tax_free_threshold_claimed'])) ? $taxDetail['tax_free_threshold_claimed']:'',
                    'hecs_dept' => (isset($taxDetail['has_trade_support_loan_debt'])) ? $taxDetail['has_trade_support_loan_debt']:'',
                    'eligible_to_receive_leave_loading' => (isset($taxDetail['eligible_to_receive_leave_loading'])) ? $taxDetail['eligible_to_receive_leave_loading']:'',
                    'is_active' => $r['status']);
                if ($r['status'] != 'ACTIVE') {
                    echo $r['employee_id'];
                }
                $employeeId = isset($alreadyAddedData[$r['employee_id']]) ? $alreadyAddedData[$r['employee_id']] : '';
                if (!($employeeId)) {
                    $emp = \App\Models\Backend\XeroEmployee::create($employeeData);
                    $employeeId = $emp->id;
                } else {
                    if ($r['status'] != 'ACTIVE') {
                        echo 'hi';
                    }
                    $emp = \App\Models\Backend\XeroEmployee::where("xero_employee_id", $r['employee_id'])->update($employeeData);
                }
                if (isset($payDetail['earnings_lines'])) {
                    foreach ($payDetail['earnings_lines'] as $e) {
                        $earning = \App\Models\Backend\XeroEarningType::where("entity_id", $entityId)->where('earning_rate_id', $e['earnings_rate_id'])->first();

                        $insertEarningLine = array(
                            'entity_id' => $entityId,
                            'employee_id' => $employeeId,
                            'earningtype_id' => $earning->id,
                            'calculation_type' => $e['calculation_type'],
                            'rate' => $e['rate_per_unit'],
                            'hours' => ($earning->rate_type == 'MULTIPLE') ? $e['normal_number_of_units'] : $e['number_of_units'],
                            'total' => $e['amount'],
                            'hours_per_week' => $e['number_of_units_per_week'],
                            'annual_salary' => $e['annual_salary'],
                            'fixed_amount' => $e['fixed_amount']
                        );
                        $checkRecord = \App\Models\Backend\XeroEmployeeEarning::where("employee_id", $employeeId)->where("entity_id", $entityId)->where("earningtype_id", $earning->id);
                        if ($checkRecord->count() == 0) {
                            \App\Models\Backend\XeroEmployeeEarning::create($insertEarningLine);
                        } else {
                            $checkRecord = $checkRecord->first();
                            \App\Models\Backend\XeroEmployeeEarning::where("id", $checkRecord->id)->update($insertEarningLine);
                        }
                    }
                }

                if (isset($payDetail['super_lines'])) {
                    foreach ($payDetail['super_lines'] as $psuper) {
                        $insertSuper = array(
                            'employee_id' => $employeeId,
                            'super_membership_id' => $psuper['super_membership_id'],
                            'calculation_type' => $psuper['calculation_type'],
                            'contribution_type' => $psuper['contribution_type'],
                            'minimum_monthly_earnings' => $psuper['minimum_monthly_earnings'],
                            'expense_account_code' => $psuper['expense_account_code'],
                            'liability_account_code' => $psuper['liability_account_code'],
                            'percentage' => $psuper['percentage'],
                            'amount' => $psuper['amount'],
                            'payment_date_for_this_period' => ($psuper['payment_date_for_this_period'] != '') ? convertXeroDate($psuper['payment_date_for_this_period']) : ''
                        );
                        $checkEmployeeSuper = \App\Models\Backend\XeroEmployeeSuper::where("super_membership_id", $psuper['super_membership_id'])
                                ->where("employee_id", $employeeId);
                        if ($checkEmployeeSuper->count() == 0) {
                            \App\Models\Backend\XeroEmployeeSuper::create($insertSuper);
                        } else {
                            $checkEmployeeSuper = $checkEmployeeSuper->first();
                            \App\Models\Backend\XeroEmployeeSuper::where("id", $checkEmployeeSuper->id)->update($insertSuper);
                        }
                    }
                }

                if (isset($payDetail['leave_lines'])) {
                    $leaveType = \App\Models\Backend\XeroLeave::where("entity_id", $entityId)->get()->pluck('id', 'leave_type_id')->toArray();

                    foreach ($payDetail['leave_lines'] as $p) {
                        $insertLeaveLine = array(
                            'entity_id' => $entityId,
                            'employee_id' => $employeeId,
                            'leave_id' => @$leaveType[$p['leave_type_id']],
                            'type' => $p['calculation_type'],
                            'annually_hour' => $p['annual_number_of_units'],
                            'employee_works' => $p['full_time_number_of_units_per_period'],
                            'termination_unused_bal' => $p['entitlement_final_pay_payout_type']);
                        $checkRecord = \App\Models\Backend\XeroEmployeeLeave::where("employee_id", $employeeId)->where("entity_id", $entityId)->where("leave_id", $leaveType[$p['leave_type_id']]);
                        if ($checkRecord->count() == 0) {
                            \App\Models\Backend\XeroEmployeeLeave::create($insertLeaveLine);
                        } else {
                            $checkRecord = $checkRecord->first();
                            \App\Models\Backend\XeroEmployeeLeave::where("id", $checkRecord->id)->update($insertLeaveLine);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            echo 'Exception when calling PayrollAuApi->createEmployee: ', $e->getMessage(), PHP_EOL;
        }
    }

    public static function getleaveItem($entityId) {
// Configure OAuth2 access token for authorization: OAuth2
        $XeroAuthData = self::getConnection($entityId);
        $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($XeroAuthData->token);
        $apiInstance = new \XeroAPI\XeroPHP\Api\PayrollAuApi(
                new \GuzzleHttp\Client(), $config
        );
        try {

            // $result = $apiInstance->getEmployees($XeroAuthData->tenant_id);
            //$employee = $apiInstance->getEmployee($XeroAuthData->tenant_id,'288acc6d-e6dd-4dc5-bdd0-e1d5b0fc97e4');
            //$result = $employee->getEmployees()[0]->getBankAccounts();

            $result = $apiInstance->getPayItems($XeroAuthData->tenant_id);
            // $result = $payItem->getPayItems()[0]->getLeaveType();
            //$LeaveType = new \XeroAPI\XeroPHP\Models\PayrollAu\LeaveType($xero);
            //$result = $LeaveType->getCurrentRecord();
            // $result = $xero->load(\XeroAPI\XeroPHP\Models\PayrollAu\LeaveType::class)->execute();
            showArray($result);
            exit;
        } catch (Exception $e) {
            echo 'Exception when calling PayrollAuApi->createEmployee: ', $e->getMessage(), PHP_EOL;
        }
    }

    public static function getLeaveApplications($entityId) {
// Configure OAuth2 access token for authorization: OAuth2
        $XeroAuthData = self::getConnection($entityId);
        $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($XeroAuthData->token);
        $apiInstance = new \XeroAPI\XeroPHP\Api\PayrollAuApi(
                new \GuzzleHttp\Client(), $config
        );
        try {
            $result = $apiInstance->getLeaveApplications($XeroAuthData->tenant_id);
            foreach ($result as $l) {
                $employee = \App\Models\Backend\XeroEmployee::where("entity_id", $entityId)->where("xero_employee_id", $l['employee_id'])->first();
                $leave = \App\Models\Backend\XeroLeave::where("entity_id", $entityId)->where("leave_type_id", $l['leave_type_id'])->first();
                $leavePeriod = array();
                foreach ($l['leave_periods'] as $lp) {
                    $leavePeriod[] = array(
                        'period_start_date' => convertXeroDate($lp['pay_period_start_date']),
                        'period_end_date' => convertXeroDate($lp['pay_period_end_date']),
                        'leave_period_status' => $lp['leave_period_status']);
                }
                $leavePeriods = \GuzzleHttp\json_encode($leavePeriod);
                $insertLeaveArray = array(
                    'employee_id' => $employee->id,
                    'leave_id' => $leave->id,
                    'leave_application_id' => $l['leave_application_id'],
                    'start_date' => convertXeroDate($l['start_date']),
                    'end_date' => convertXeroDate($l['end_date']),
                    'leave_dates' => $leavePeriods,
                    'leave_apply' => $l['leave_periods'][0]['number_of_units'],
                    'description' => $l['description'],
                    'pay_out_type' => $l['pay_out_type']
                );
                $checkLeave = \App\Models\Backend\XeroPayrunEmployeeLeave::where("employee_id", $employee->id)
                                ->where("leave_id", $leave->id)->where("leave_application_id", $l['leave_application_id']);
                if ($checkLeave->count() == 0) {
                    \App\Models\Backend\XeroPayrunEmployeeLeave::create($insertLeaveArray);
                } else {
                    $checkLeave = $checkLeave->first();
                    \App\Models\Backend\XeroPayrunEmployeeLeave::where("earning_type_id", $checkLeave->id)->update($insertLeaveArray);
                }
            }
            exit;
        } catch (Exception $e) {
            echo 'Exception when calling PayrollAuApi->createEmployee: ', $e->getMessage(), PHP_EOL;
        }
    }

    public static function getPayItems($entityId) {
// Configure OAuth2 access token for authorization: OAuth2
        $XeroAuthData = self::getConnection($entityId);
        $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($XeroAuthData->token);
        $apiInstance = new \XeroAPI\XeroPHP\Api\PayrollAuApi(
                new \GuzzleHttp\Client(), $config
        );

        try {
            $result = $apiInstance->getPayItems($XeroAuthData->tenant_id);
            foreach ($result['earnings_rates'] as $e) {
                $earningArray = array('entity_id' => $entityId,
                    'name' => $e['name'],
                    'account_code' => $e['account_code'],
                    'type_of_unit' => $e['type_of_unit'],
                    'is_exempt_from_tax' => $e['is_exempt_from_tax'],
                    'is_exempt_from_super' => $e['is_exempt_from_super'],
                    'earning_type_id' => $e['earnings_type'],
                    'earning_rate_id' => $e['earnings_rate_id'],
                    'rate_type' => $e['rate_type'],
                    'rate_per_unit' => $e['rate_per_unit'],
                    'multiplier' => $e['multiplier'],
                    'accrue_leave' => $e['accrue_leave'],
                    'amount' => $e['amount'],
                    'current_record' => $e['current_record'],
                    'allowance_type' => $e['allowance_type']);
                $checkEarning = \App\Models\Backend\XeroEarningType::where("entity_id", $entityId)->where("earning_rate_id", $e['earnings_rate_id']);
                if ($checkEarning->count() == 0) {
                    \App\Models\Backend\XeroEarningType::create($earningArray);
                } else {
                    \App\Models\Backend\XeroEarningType::where("earning_rate_id", $e['earnings_rate_id'])->update($earningArray);
                }
            }

            foreach ($result['leave_types'] as $l) {
                $leaveArray = array(
                    'entity_id' => $entityId,
                    'leave_type_id' => $l['leave_type_id'],
                    'name' => $l['name'],
                    'type_of_units' => $l['type_of_units'],
                    'normal_entitlement' => $l['normal_entitlement'],
                    'leave_loading_rate' => $l['leave_loading_rate'],
                    'is_paid_leave' => $l['is_paid_leave'],
                    'show_on_payslip' => $l['show_on_payslip'],
                    'current_record' => $l['current_record'],
                    'leave_category_code' => $l['leave_category_code'],
                    'sgc_exempt' => $l['sgc_exempt']
                );
                $checkLeave = \App\Models\Backend\XeroLeave::where("entity_id", $entityId)->where("leave_type_id", $l['leave_type_id']);
                if ($checkLeave->count() == 0) {
                    \App\Models\Backend\XeroLeave::create($leaveArray);
                } else {
                    \App\Models\Backend\XeroLeave::where("leave_type_id", $l['leave_type_id'])->update($leaveArray);
                }
            }

            foreach ($result['reimbursement_types'] as $r) {
                $reimbursementArray = array(
                    'entity_id' => $entityId,
                    'name' => $r['name'],
                    'account_code' => $r['account_code'],
                    'reimbursement_type_id' => $r['reimbursement_type_id'],
                    'current_record' => $r['current_record']
                );
                $checkReimbursement = \App\Models\Backend\XeroReimbursement::where("entity_id", $entityId)->where("reimbursement_type_id", $r['reimbursement_type_id']);
                if ($checkReimbursement->count() == 0) {
                    \App\Models\Backend\XeroReimbursement::create($reimbursementArray);
                } else {
                    \App\Models\Backend\XeroReimbursement::where("reimbursement_type_id", $r['reimbursement_type_id'])->update($reimbursementArray);
                }
            }

            foreach ($result['deduction_types'] as $d) {
                $deductionArray = array(
                    'entity_id' => $entityId,
                    'account_code' => $d['account_code'],
                    'name' => $l['name'],
                    'reduces_tax' => $d['reduces_tax'],
                    'reduces_super' => $d['reduces_super'],
                    'is_exempt_from_w1' => $d['is_exempt_from_w1'],
                    'deduction_type_id' => $d['deduction_type_id'],
                    'deduction_category' => $l['deduction_category'],
                    'current_record' => $l['current_record']
                );
                $checkdeduction = \App\Models\Backend\XeroDeduction::where("entity_id", $entityId)->where("deduction_type_id", $d['deduction_type_id']);
                if ($checkdeduction->count() == 0) {
                    \App\Models\Backend\XeroDeduction::create($deductionArray);
                } else {
                    \App\Models\Backend\XeroDeduction::where("deduction_type_id", $d['deduction_type_id'])->update($deductionArray);
                }
            }
        } catch (Exception $e) {
            echo 'Exception when calling PayrollAuApi->CreatePayitem: ', $e->getMessage(), PHP_EOL;
        }
    }

    public static function getPayruns($entityId) {
// Configure OAuth2 access token for authorization: OAuth2
        $XeroAuthData = self::getConnection($entityId);
        $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($XeroAuthData->token);
        $apiInstance = new \XeroAPI\XeroPHP\Api\PayrollAuApi(
                new \GuzzleHttp\Client(), $config
        );
        try {
            $result = $apiInstance->getPayRuns($XeroAuthData->tenant_id);
            foreach ($result as $r) {
                $payrun = $apiInstance->getPayRun($XeroAuthData->tenant_id, $r['pay_run_id']);
                $paySlip = $payrun->getPayRuns()[0]->getPayslips();
                $checkPayrun = \App\Models\Backend\XeroPayrun::where("pay_run_id", $r['pay_run_id']);
                $insertArray = array(
                    'entity_id' => $entityId,
                    'payroll_calendar_id' => $r['payroll_calendar_id'],
                    'pay_run_id' => $r['pay_run_id'],
                    'start_date' => convertXeroDate($r['pay_run_period_start_date']),
                    'end_date' => convertXeroDate($r['pay_run_period_end_date']),
                    'payrun_status' => $r['pay_run_status'],
                    'payment_date' => convertXeroDate($r['payment_date']),
                    'payslip_message' => $r['payslip_message'],
                    'payslips' => $r['payslips'],
                    'wages' => $r['wages'],
                    'deductions' => $r['deductions'],
                    'tax' => $r['tax'],
                    'super' => $r['super'],
                    'reimbursement' => $r['reimbursement'],
                    'net_pay' => $r['net_pay']);
                if ($checkPayrun->count() == 0) {
                    $payrun = \App\Models\Backend\XeroPayrun::create($insertArray);
                } else {
                    $payrun = $checkPayrun->first();
                    \App\Models\Backend\XeroPayrun::where("pay_run_id", $payrun->id)->update($insertArray);
                }

                foreach ($paySlip as $p) {
                    $employee = \App\Models\Backend\XeroEmployee::where("xero_employee_id", $p['employee_id'])->first();
                    $insertEmployeePayrun = array(
                        'entity_id' => $entityId,
                        'payrun_id' => $payrun->id,
                        'employee_id' => $employee->id,
                        'payslip_id' => $p['payslip_id'],
                        'wages' => $p['wages'],
                        'deductions' => $p['deductions'],
                        'super' => $p['super'],
                        'tax' => $p['tax'],
                        'reimbursements' => $p['reimbursements'],
                        'net_pay' => $p['net_pay']
                    );
                    $checkEmployeePayrun = \App\Models\Backend\XeroPayrunEmployee::where("payrun_id", $payrun->id)->where("employee_id", $employee->id);
                    if ($checkEmployeePayrun->count() == 0) {
                        \App\Models\Backend\XeroPayrunEmployee::create($insertEmployeePayrun);
                    } else {
                        $checkEmployeePayrun = $checkEmployeePayrun->first();
                        \App\Models\Backend\XeroPayrunEmployee::where("id", $checkEmployeePayrun->id)->update($insertEmployeePayrun);
                    }

                    $payslipResult = $apiInstance->getPayslip($XeroAuthData->tenant_id, $p['payslip_id']);
                    if (isset($payslipResult['payslip']['earnings_lines'])) {
                        foreach ($payslipResult['payslip']['earnings_lines'] as $ps) {
                            $earning = \App\Models\Backend\XeroEarningType::where("entity_id", $entityId)->where("earning_rate_id", $ps['earnings_rate_id'])->first();
                            $insertPayslipEarning = array(
                                'employee_id' => $employee->id,
                                'payrun_id' => $payrun->id,
                                'earningtype_id' => $earning->id,
                                'calculation_type' => $ps['calculation_type'],
                                'annual_salary' => $ps['annual_salary'],
                                'hours_per_week' => $ps['number_of_units_per_week'],
                                'rate' => $ps['rate_per_unit'],
                                'hours' => ($earning->rate_type == 'MULTIPLE') ? $ps['normal_number_of_units'] : $ps['number_of_units'],
                                'total' => $ps['amount'],
                                'number_of_unit' => $ps['number_of_unit'],
                                'fixed_amount' => $ps['fixed_amount']
                            );
                            $checkPayrunEmployeeEarning = \App\Models\Backend\XeroPayrunEmployeeEarning::where("earning_rate_id", $ps['earnings_rate_id'])
                                            ->where("employee_id", $employee->id)->where("payrun_id", $payrun->id);
                            if ($checkPayrunEmployeeEarning->count() == 0) {
                                \App\Models\Backend\XeroPayrunEmployeeEarning::create($insertPayslipEarning);
                            } else {
                                $checkPayrunEmployeeEarning = $checkPayrunEmployeeEarning->first();
                                \App\Models\Backend\XeroPayrunEmployeeEarning::where("id", $checkPayrunEmployeeEarning->id)->update($insertPayslipEarning);
                            }
                        }
                    }
                    //for Leave
                    if (isset($payslipResult['payslip']['leave_earnings_lines'])) {
                        foreach ($payslipResult['payslip']['leave_earnings_lines'] as $pl) {

                            $leave = \App\Models\Backend\XeroLeave::where("entity_id", $entityId)->where("leave_type_id", $pl['leave_type_id']);
                            if ($leave->count() > 0) {
                                $leave = $leave->first();
                                $insertPayslipLeave = array(
                                    'employee_id' => $employee->id,
                                    'payrun_id' => $payrun->id,
                                    'leave_id' => $leave->id,
                                    'type' => $pl['calculation_type'],
                                    'annually_hour' => $pl['annual_number_of_units'],
                                    'employee_works' => $pl['full_time_number_of_units_per_period'],
                                    'opening_bal' => $pl['opening_bal'],
                                    'termination_unused_bal' => $pl['entitlement_final_pay_payout_type']
                                );
                                $checkPayrunEmployeeLeave = \App\Models\Backend\XeroPayrunEmployeeLeave::where("earning_rate_id", $pl['earnings_rate_id'])
                                                ->where("employee_id", $employee->id)->where("payrun_id", $payrun->id);
                                if ($checkPayrunEmployeeLeave->count() == 0) {
                                    \App\Models\Backend\XeroPayrunEmployeeLeave::create($insertPayslipLeave);
                                } else {
                                    $checkPayrunEmployeeLeave = $checkPayrunEmployeeLeave->first();
                                    \App\Models\Backend\XeroPayrunEmployeeLeave::where("id", $checkPayrunEmployeeLeave->id)->update($insertPayslipLeave);
                                }
                            }
                        }
                    }

                    /* if (isset($payslipResult['payslip']['leave_accrual_lines'])) {
                      foreach ($payslipResult['payslip']['leave_accrual_lines'] as $pl) {

                      $leave = \App\Models\Backend\XeroLeave::where("entity_id", $entityId)->where("leave_type_id", $pl['leave_type_id'])->first();
                      $insertPayslipLeave = array(
                      'entity_id' => $entityId,
                      'employee_id' => $employee->id,
                      'leave_id' => $leave->id,
                      'number_of_unit' => $pl['number_of_units']
                      );
                      $checkEmployeeLeave = \App\Models\Backend\XeroEmployeeLeave::where("leave_id", $leave->id)
                      ->where("employee_id", $employee->id);
                      if ($checkEmployeeLeave->count() == 0) {
                      \App\Models\Backend\XeroEmployeeLeave::create($insertPayslipLeave);
                      } else {
                      $checkEmployeeLeave = $checkEmployeeLeave->first();
                      \App\Models\Backend\XeroEmployeeLeave::where("id", $checkEmployeeLeave->id)->update($insertPayslipLeave);
                      }
                      }
                      } */
                    //For supermembership
                    if (isset($payslipResult['payslip']['superannuation_lines'])) {
                        foreach ($payslipResult['payslip']['superannuation_lines'] as $psuper) {
                            $earning = \App\Models\Backend\XeroEarningType::where("entity_id", $entityId)->where("earning_rate_id", $ps['earnings_rate_id'])->first();
                            $insertPayslipSuper = array(
                                'employee_id' => $employee->id,
                                'payrun_id' => $payrun->id,
                                'super_membership_id' => $psuper['super_membership_id'],
                                'calculation_type' => $psuper['calculation_type'],
                                'contribution_type' => $psuper['contribution_type'],
                                'minimum_monthly_earnings' => $psuper['minimum_monthly_earnings'],
                                'expense_account_code' => $psuper['expense_account_code'],
                                'liability_account_code' => $psuper['liability_account_code'],
                                'percentage' => $psuper['percentage'],
                                'amount' => $psuper['amount'],
                                'payment_date_for_this_period' => convertXeroDate($psuper['payment_date_for_this_period'])
                            );
                            $checkPayrunEmployeeSuper = \App\Models\Backend\XeroPayrunEmployeeSupermembership::where("super_membership_id", $psuper['super_membership_id'])
                                            ->where("employee_id", $employee->id)->where("payrun_id", $payrun->id);
                            if ($checkPayrunEmployeeSuper->count() == 0) {
                                \App\Models\Backend\XeroPayrunEmployeeSupermembership::create($insertPayslipSuper);
                            } else {
                                $checkPayrunEmployeeSuper = $checkPayrunEmployeeSuper->first();
                                \App\Models\Backend\XeroPayrunEmployeeSupermembership::where("id", $checkPayrunEmployeeSuper->id)->update($insertPayslipSuper);
                            }
                        }
                    }
                    //for Tax
                    if (isset($payslipResult['payslip']['tax_lines'])) {
                        foreach ($payslipResult['payslip']['tax_lines'] as $pt) {

                            $earning = \App\Models\Backend\XeroEarningType::where("entity_id", $entityId)->where("earning_rate_id", $psuper['earnings_rate_id'])->first();
                            $insertPayslipTax = array(
                                'employee_id' => $employee->id,
                                'payrun_id' => $payrun->id,
                                'payslip_tax_line_id' => $pt['payslip_tax_line_id'],
                                'tax_type_name' => $pt['tax_type_name'],
                                'amount' => $pt['amount'],
                                'description' => $pt['description'],
                                'manual_tax_type' => $pt['manual_tax_type'],
                                'liability_account' => $pt['liability_account']
                            );
                            $checkPayrunEmployeeTax = \App\Models\Backend\XeroPayrunEmployeeTax::where("earning_rate_id", $ps['earnings_rate_id'])
                                            ->where("employee_id", $employee->id)->where("payrun_id", $payrun->id);
                            if ($checkPayrunEmployeeEarning->count() == 0) {
                                \App\Models\Backend\XeroPayrunEmployeeTax::create($insertPayslipTax);
                            } else {
                                $checkPayrunEmployeeTax = $checkPayrunEmployeeTax->first();
                                \App\Models\Backend\XeroPayrunEmployeeTax::where("id", $checkPayrunEmployeeTax->id)->update($insertPayslipTax);
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            echo 'Exception when calling PayrollAuApi->createEmployee: ', $e->getMessage(), PHP_EOL;
        }
    }

    public static function getPayslip($entityId, $payslipId) {
        $XeroAuthData = self::getConnection($entityId);
        $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($XeroAuthData->token);
        $apiInstance = new \XeroAPI\XeroPHP\Api\PayrollAuApi(
                new \GuzzleHttp\Client(), $config
        );

        try {
            $payslipResult = $apiInstance->getPayslip($XeroAuthData->tenant_id, $payslipId);
            if (isset($payslipResult['payslip']['earnings_lines'])) {
                foreach ($payslipResult['payslip']['earnings_lines'] as $ps) {
                    $earning = \App\Models\Backend\XeroEarningType::where("entity_id", $entityId)->where("earning_rate_id", $ps['earnings_rate_id'])->first();
                    $insertPayslipEarning = array(
                        'employee_id' => $employee->id,
                        'payrun_id' => $payrun->id,
                        'earning_rate_id' => $earning->id,
                        'calculation_type' => $ps['calculation_type'],
                        'annual_salary' => $ps['annual_salary'],
                        'number_of_units_per_week' => $ps['number_of_units_per_week'],
                        'rate_per_unit' => $ps['rate_per_unit'],
                        'normal_number_of_units' => $ps['normal_number_of_units'],
                        'amount' => $ps['amount'],
                        'number_of_unit' => $ps['number_of_unit'],
                        'fixed_amount' => $ps['fixed_amount']
                    );
                    $checkPayrunEmployeeEarning = \App\Models\Backend\XeroPayrunEmployeeEarning::where("earning_rate_id", $ps['earnings_rate_id'])
                                    ->where("employee_id", $employee->id)->where("payrun_id", $payrun->id);
                    if ($checkPayrunEmployeeEarning->count() == 0) {
                        \App\Models\Backend\XeroPayrunEmployeeEarning::create($insertPayslipEarning);
                    } else {
                        $checkPayrunEmployeeEarning = $checkPayrunEmployeeEarning->first();
                        \App\Models\Backend\XeroPayrunEmployeeEarning::where("id", $checkPayrunEmployeeEarning->id)->update($insertPayslipEarning);
                    }
                }
            }
            //for Leave
            if (isset($payslipResult['payslip']['leave_earnings_lines'])) {
                foreach ($payslipResult['payslip']['leave_earnings_lines'] as $pl) {

                    $leave = \App\Models\Backend\XeroLeave::where("entity_id", $entityId)->where("leave_type_id", $ps['leave_type_id'])->first();
                    $insertPayslipLeave = array(
                        'employee_id' => $employee->id,
                        'payrun_id' => $payrun->id,
                        'leave_id' => $leave->id,
                        'type' => $pl['calculation_type'],
                        'annually_hour' => $pl['annual_number_of_units'],
                        'employee_works' => $pl['full_time_number_of_units_per_period'],
                        'opening_bal' => $pl['opening_bal'],
                        'termination_unused_bal' => $pl['entitlement_final_pay_payout_type']
                    );
                    $checkPayrunEmployeeLeave = \App\Models\Backend\XeroPayrunEmployeeLeave::where("earning_rate_id", $ps['earnings_rate_id'])
                                    ->where("employee_id", $employee->id)->where("payrun_id", $payrun->id);
                    if ($checkPayrunEmployeeLeave->count() == 0) {
                        \App\Models\Backend\XeroPayrunEmployeeLeave::create($insertPayslipEarning);
                    } else {
                        $checkPayrunEmployeeLeave = $checkPayrunEmployeeLeave->first();
                        \App\Models\Backend\XeroPayrunEmployeeLeave::where("id", $checkPayrunEmployeeLeave->id)->update($insertPayslipLeave);
                    }
                }
            }

            //For supermembership
            if (isset($payslipResult['payslip']['superannuation_lines'])) {
                foreach ($payslipResult['payslip']['superannuation_lines'] as $psuper) {
                    $earning = \App\Models\Backend\XeroEarningType::where("entity_id", $entityId)->where("earning_rate_id", $ps['earnings_rate_id'])->first();
                    $insertPayslipSuper = array(
                        'employee_id' => $employee->id,
                        'payrun_id' => $payrun->id,
                        'super_membership_id' => $psuper['super_membership_id'],
                        'calculation_type' => $psuper['calculation_type'],
                        'contribution_type' => $psuper['contribution_type'],
                        'minimum_monthly_earnings' => $psuper['minimum_monthly_earnings'],
                        'expense_account_code' => $psuper['expense_account_code'],
                        'liability_account_code' => $psuper['liability_account_code'],
                        'percentage' => $psuper['percentage'],
                        'amount' => $psuper['amount'],
                        'payment_date_for_this_period' => convertXeroDate($psuper['payment_date_for_this_period'])
                    );
                    $checkPayrunEmployeeSuper = \App\Models\Backend\XeroPayrunEmployeeSupermembership::where("super_membership_id", $psuper['super_membership_id'])
                                    ->where("employee_id", $employee->id)->where("payrun_id", $payrun->id);
                    if ($checkPayrunEmployeeSuper->count() == 0) {
                        \App\Models\Backend\XeroPayrunEmployeeSupermembership::create($insertPayslipSuper);
                    } else {
                        $checkPayrunEmployeeSuper = $checkPayrunEmployeeSuper->first();
                        \App\Models\Backend\XeroPayrunEmployeeSupermembership::where("id", $checkPayrunEmployeeSuper->id)->update($insertPayslipSuper);
                    }
                }
            }
            //for Tax
            if (isset($payslipResult['payslip']['tax_lines'])) {
                foreach ($payslipResult['payslip']['tax_lines'] as $pt) {

                    $earning = \App\Models\Backend\XeroEarningType::where("entity_id", $entityId)->where("earning_rate_id", $psuper['earnings_rate_id'])->first();
                    $insertPayslipTax = array(
                        'employee_id' => $employee->id,
                        'payrun_id' => $payrun->id,
                        'payslip_tax_line_id' => $pt['payslip_tax_line_id'],
                        'tax_type_name' => $pt['tax_type_name'],
                        'amount' => $pt['amount'],
                        'description' => $pt['description'],
                        'manual_tax_type' => $pt['manual_tax_type'],
                        'liability_account' => $pt['liability_account']
                    );
                    $checkPayrunEmployeeTax = \App\Models\Backend\XeroPayrunEmployeeTax::where("earning_rate_id", $ps['earnings_rate_id'])
                                    ->where("employee_id", $employee->id)->where("payrun_id", $payrun->id);
                    if ($checkPayrunEmployeeEarning->count() == 0) {
                        \App\Models\Backend\XeroPayrunEmployeeTax::create($insertPayslipTax);
                    } else {
                        $checkPayrunEmployeeTax = $checkPayrunEmployeeTax->first();
                        \App\Models\Backend\XeroPayrunEmployeeTax::where("id", $checkPayrunEmployeeTax->id)->update($insertPayslipTax);
                    }
                }
            }
        } catch (Exception $e) {
            echo 'Exception when calling PayrollAuApi->createEmployee: ', $e->getMessage(), PHP_EOL;
        }
    }

    public static function getPayrollCalendars($entityId) {
// Configure OAuth2 access token for authorization: OAuth2
        $XeroAuthData = self::getConnection($entityId);
        $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($XeroAuthData->token);
        $apiInstance = new \XeroAPI\XeroPHP\Api\PayrollAuApi(
                new \GuzzleHttp\Client(), $config
        );

        try {
            $result = $apiInstance->getPayrollCalendars($XeroAuthData->tenant_id);
            $currentDate = date("Y-m-d");
            foreach ($result as $r) {
                $startDate = convertXeroDate($r['start_date']);
                $endDate = self::calculateEndDate($r['calendar_type'], $startDate);
                $paymentDate = convertXeroDate($r['payment_date']);
                $insertArray = array('entity_id' => $entityId,
                    'payroll_calendar_id' => $r['payroll_calendar_id'],
                    'name' => $r['name'],
                    'calendar_type' => $r['calendar_type'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'payment_date' => $paymentDate);
                $oldpayrollCal = \App\Models\Backend\XeroPayrollCalendar::where("entity_id", $entityId)->where("payroll_calendar_id", $r['payroll_calendar_id']);
                if ($oldpayrollCal->count() == 0) {
                    \App\Models\Backend\XeroPayrollCalendar::create($insertArray);
                } else {
                    $oldpayrollCal = $oldpayrollCal->first();
                    \App\Models\Backend\XeroPayrollCalendar::where('id', $oldpayrollCal->id)->update($insertArray);
                }
            }
        } catch (Exception $e) {
            echo 'Exception when calling PayrollAuApi->createEmployee: ', $e->getMessage(), PHP_EOL;
        }
    }

    public static function calculateEndDate($calendarType, $startDate) {
        $calendarDays = \App\Models\Backend\XeroCalendartype::where("key", $calendarType)->first();
        switch ($calendarType) {
            CASE 'WEEKLY':
                $endDate = date("Y-m-d", strtotime("+" . $calendarDays->days . " days", strtotime($startDate)));
                break;
            CASE 'FORTNIGHTLY':
                $endDate = date("Y-m-d", strtotime("+" . $calendarDays->days . " days", strtotime($startDate)));
                break;
            CASE 'FOURWEEKLY':
                $endDate = date("Y-m-d", strtotime("+" . $calendarDays->days . " days", strtotime($startDate)));
                break;
            CASE 'TWICEMONTHLY':
                $currentdate = date("Y-m-15");
                if ($currentdate > $startDate) {
                    $endDate = date("Y-m-d", strtotime("+" . $calendarDays->days . " days", strtotime($startDate)));
                } else {
                    $endDate = date("Y-m-t", strtotime($startDate));
                }
                break;
            CASE 'QUARTERLY':
                $lastMonth = date(('Y-m-d'), strtotime("+2 Month", strtotime($startDate)));
                $endDate = date("Y-m-t", strtotime($lastMonth));
                break;
            CASE 'MONTHLY':
                $endDate = date("Y-m-t", strtotime($startDate));
                break;
        }
        return $endDate;
    }

    public static function getSetting($entityId) {
// Configure OAuth2 access token for authorization: OAuth2
        $XeroAuthData = self::getConnection($entityId);
        $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($XeroAuthData->token);
        $apiInstance = new \XeroAPI\XeroPHP\Api\PayrollAuApi(
                new \GuzzleHttp\Client(), $config
        );
        try {
            $result = $apiInstance->getSettings($XeroAuthData->tenant_id);
        } catch (Exception $e) {
            echo 'Exception when calling PayrollAuApi->createEmployee: ', $e->getMessage(), PHP_EOL;
        }
    }

    public static function getSuperfunds($entityId) {
// Configure OAuth2 access token for authorization: OAuth2
        $XeroAuthData = self::getConnection($entityId);
        $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($XeroAuthData->token);
        $apiInstance = new \XeroAPI\XeroPHP\Api\PayrollAuApi(
                new \GuzzleHttp\Client(), $config
        );
        try {
            $result = $apiInstance->getSuperfunds($XeroAuthData->tenant_id);
            foreach ($result as $r) {
                $insertArray = array(
                    'entity_id' => $entityId,
                    'superfund_id' => $r['super_fund_id'],
                    'type' => $r['type'],
                    'USI' => $r['USI'],
                    'name' => $r['name']
                );
                $checkSuper = \App\Models\Backend\XeroSuperfund::where("entity_id", $entityId)->where("superfund_id", $r['super_fund_id']);
                if ($checkSuper->count() == 0) {
                    \App\Models\Backend\XeroSuperfund::create($insertArray);
                } else {
                    $checkSuper = $checkSuper->first();
                    \App\Models\Backend\XeroSuperfund::where("id", $checkSuper->id)->update($insertArray);
                }
            }
        } catch (Exception $e) {
            echo 'Exception when calling PayrollAuApi->createEmployee: ', $e->getMessage(), PHP_EOL;
        }
    }

    public static function getSuperfundProduct($entityId, $usi, $abn) {
// Configure OAuth2 access token for authorization: OAuth2
        $XeroAuthData = self::getConnection($entityId);
        $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken($XeroAuthData->token);
        $apiInstance = new \XeroAPI\XeroPHP\Api\PayrollAuApi(
                new \GuzzleHttp\Client(), $config
        );
        try {
            $result = $apiInstance->getSuperfundProducts($XeroAuthData->tenant_id, $abn, $usi);
            return $result;
        } catch (Exception $e) {
            echo 'Exception when calling PayrollAuApi->createEmployee: ', $e->getMessage(), PHP_EOL;
        }
    }

}

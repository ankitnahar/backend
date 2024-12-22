<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

return [
    "SUPERADMIN" => 7,
    "TAM" => 9,
    "DH" => 15,
    "BILLINGID" => "billing@befree.com.au",
    "JOBTRACKINGCATEGORY" => 'cc603993-5594-4cbc-96e7-0afb5e23ba15',
    //Payment detail
    'payment' => [
        '1' => 'Ezidebit',
        '2' => 'Credit Card',
        '3' => 'Net Transfer'],
    'category' => [
        '1' => 'Largest - A+',
        '2' => 'Large - A',
        '3' => 'Medium - B+',
        '4' => 'Medium - B',
        '5' => 'Small - C+',
        '6' => 'Small - C'],
    //Manage remark for HR
    'hrSat' => [
        'ALl Saturday' => 1,
        '1 and  3 Saturday' => 2,
        'none' => 0],
    'hrRemark' => [
        'holidayWorking' => 1,
        'sundayWorking' => 2,
        'lateComing' => 3,
        'earlyLeaving' => 4,
        'absent' => 5,
        'Sandwich Leave' => 6],
    // Manage final remark for HR
    'hrfinalRemark' => [
        'halfDay' => 1,
        'fullDayWorking' => 2,
        'leave' => 3],
    // Manage status for HR
    'hrstatus' => [
        'autoAllowed' => 1,
        'pendingForRequest' => 2,
        'pendingFor1stApproval' => 3,
        'pendingFor2ndApproval' => 4,
        'approved' => 5,
        'rejected' => 6],
    // Manage approval type
    'approvaltype' => [
        'firstApproval' => 1,
        'secondApproval' => 2,
        'superadminApproval' => 3],
    'accountteam' => [
        'teamID' => 78,
        'serviceID' => 19],
    'entityCheckliststatus' => [
        'pleaseSelect' => 0,
        'applicable' => 1,
        'notApplicable' => 2],
    'module' => [
        'entity' => 1,
    ],
    'leavestatus' => [
        '1'=>'autoAllowed',
        '2'=>'pendingForRequest',
        '3'=>'pendingFor1stApproval',
        '4'=>'pendingFor2ndApproval',
        '5'=>'approved',
        '6'=>'rejected'],
    'yesNo' => [
        '0' => 'No',
        '1' => 'Yes'],
    'yesNoOther' => [
        '0' => 'No',
        '1' => 'Yes',
        '2' => 'Other'],
    'yesNoNa' => [
        '0' => 'No',
        '1' => 'Yes',
        '2' => 'N/A'],
    'entityType' => [
        '1' => 'Australian Public Company',
        '2' => 'Individual(Tax team)',
        '3' => 'Other',
        '4' => 'Partnership Firm',
        '5' => 'Pty Ltd (Company)',
        '6' => 'SMSF',
        '7' => 'Sole Trader',
        '8' => 'Trust'],
    'bkDoneby' => [
        '1' => 'BK team',
        '2' => 'Client',
        '3' => 'Other',
        '4' => 'Tax team'],
    'basFrequency' => [
        '1' => 'Annual',
        '2' => 'Monthly',
        '3' => 'N/A',
        '4' => 'Quarterly',
        '5' => 'Half Yearly',
        '6' => 'Fortnightly',
        '7' => 'Weekly'],
    'basAccrualorcash' => [
        '1' => 'Accrual',
        '2' => 'Cash'],
    'paygFrequency' => [
        '1' => 'Monthly',
        '2' => 'Quarterly',
        '3' => 'N/A'],
    'statementDeliveryPreference' => [
        '1' => 'ECI',
        '2' => 'ELS',
        '3' => 'Postal'],
    'franchise' => [
        '1' => 'Anytime Fitness',
        '2' => 'Guzman',
        '3' => 'Hungry Jacks',
        '4' => 'KFC',
        '5' => 'Miss India',
        '6' => 'Domino`s Pizza',
        '7' => 'Sign A Rama'
    ],
    'qualitycontrolstatus' => [
        '1' => 'Not started',
        '2' => 'WIP',
        '3' => 'Closed',
        '4' => 'Reopen',
    ],
    'entitydiscontinuestage' => [
        '0' => 'Active',
        '1' => 'Discontinue process initiate',
        '2' => 'Discontinued client'
    ],
    'qualitycontroltype' => [
        '1' => 'Issue',
        '2' => 'Call Request',
        '3' => 'Clarification',
        '4' => 'Request',
        '5' => 'Verify',
        '6' => 'Management Attention Required',
        '7' => 'Sydney Office Attention Required',
        '8' => 'Updates',
        '9' => 'Send Quotes'],
    'worksheettype' => [
        'my', 'incompleted', 'completed', 'reviewer', 'peerreviewer'
    ],
    'state' => [
        '1' => 'Australian Capital Territory',
        '2' => 'New South Wales',
        '3' => 'Northern Territory',
        '4' => 'Queensland',
        '5' => 'South Australia',
        '6' => 'Tasmania',
        '7' => 'Victoria',
        '8' => 'Western Australia',
        '9' => 'Not define'],
    'addresstype' => [
        '1' => 'Business',
        '2' => 'Postal'],
    'position' => [
        '1' => 'Director',
        '2' => 'Accountant',
        '3' => 'Employee',
        '4' => 'Manager',
        '5' => 'Owner',
        '6' => 'Partner',
        '7' => 'Sales Person',
        '8' => 'Not known'],
    'is_bank_or_credit_card' => [
        "1" => "Bank Account",
        "2" => "Credit Card Account",
        "3" => "Paypal"],
    'DD' => [
        "equal" => "Equal To",
        "notequal" => "Not Equal To"],
    'TB' => [
        "equal" => "Equal To",
        "notequal" => "Not Equal To",
        "startwith" => "Start With",
        "like" => "Contains any part of word"],
    'CL' => [
        "equal" => "On",
        "lessthen" => "Before",
        "greaterthen" => "After",
        "lessthenequal" => "On Or Before",
        "greaterthenequal" => "On Or After"],
    'TN' => [
        "equal" => "Equal To",
        "lessthenval" => "Leas Then",
        "greaterthenval" => "Greater Then",
        "lesthenequalval" => "Les Then Equal",
        "greaterthenequalval" => "Greater Then Equal"],
    'invoiceType' => [
        "Recurred" => "Recurred",
        "Manual" => "Manual",
        "Advance" => "Advance",
        "Audit" => "Audit",
        "Formation" => "Formation",
        "Imported" => "Imported",
        "Auto invoice" => "Auto invoice"],
    'discountType' => [
        "None" => "None",
        "Advance" => "Advance",
        "Fixed" => "Fixed"],
    'teamMemberchecklistAction' => [
        "0" => "Please Select",
        "1" => "Yes",
        "2" => "No",
        "3" => "Done"],
    'reviewerchecklistAction' => [
        "0" => 'Please Select',
        "1" => 'Checked',
        "2" => 'Knock back',
        "3" => 'Attention'],
    'technicalheadAction' => [
        "0" => 'Please Select',
        "1" => 'Checked',
        "2" => 'Attention for reviewers',
        "3" => 'Attention for team members'],
    'reviewerTag' => [
        "1" => 'Negligence',
        "2" => 'Training',
        "3" => 'N/A'],
    'delayFrom' => [
        '1' => 'Client',
        '2' => 'Befree'
    ],
    'outcome' => [
        "P" => 'I confirm that I have generated the updated reports after taking into account the knock back points.',
        "R" => 'I confirm that the reports I am sending are the updated / final reports',
    ],
    'recType' => [
        "1" => "Single",
        "2" => "Multiple"],
    'recurringRepetition' => [
        "1" => "Repeat indefinitely",
        "2" => "Repeat until date",
        "3" => "Repeat # times"],
    'days' => [
        'Monday' => 'Monday',
        'Tuesday' => 'Tuesday',
        'Wednesday' => 'Wednesday',
        'Thursday' => 'Thursday',
        'Friday' => 'Friday',
        'Saturday' => 'Saturday',
        'Sunday' => 'Sunday'
    ],
    'newclientreviewstatus' => [
        '1' => 'New - Not started',
        '2' => 'WIP',
        '3' => 'Review for manager',
        '4' => 'completed',
    ],
    'wr3status' => [
        '1' => 'New - Not started',
        '2' => 'WIP',
        '3' => 'Issue holding up',
        '4' => 'First report sent',
        '5' => 'First feedback call',
    ],
    'fulltimeresource' => [
        '0' => 'No',
        '1' => 'Fulltime',
        '2' => 'Parttime'
    ],
    'noticeperiod' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10', '11' => '11', '12' => '12'],
    'welcomeemailtemplate' => [
        '1' => 'Bookkeeping',
        '2' => 'Bookkeeping & Payroll',
        '3' => 'New welcome email'
    ],
    'softwareInformationPermanentInfo' => [
        '0' => 'Please Select',
        '1' => 'MYOB AccountRight [Cloud]',
        '2' => 'MYOB AccountRight [Desktop]',
        '3' => 'MYOB Essential',
        '4' => 'QuickBooks [Online]',
        '5' => 'Xero',
        '6' => 'Saasu',
        '7' => 'Other',
        '8' => 'QuickBooks [Desktop]'
    ],
    'pleaseSelectNoYesNa' => [
        '0' => 'Please select',
        '1' => 'Yes',
        '2' => 'No',
        '3' => 'N/A',],
    'ticketpriority' => [
        '1' => 'Low',
        '2' => 'Medium',
        '3' => 'High',],
    'ticketseverity' => [
        '1' => 'New Feature',
        '2' => 'Support',
        '3' => 'Bug',
        '4' => 'Suggestion',
        '5' => 'Issue'],
    'tickettypeofmistake' => [
        '1' => 'Negligence',
        '2' => 'Medium',
        '3' => 'Minor',
        '4' => 'Gross Negligence',],
    'tickettopic' => [
        '1' => 'A',
        '2' => 'B',
        '3' => 'C',],
    'hostingUserType' => ['B' => 'Basic',
        'P' => 'Premium',
        'S' => 'Special'
    ],
    'writeoff' => [
        '1' => 'Befree writeoff',
        '2' => 'Client writeoff',
        '3' => 'Reviewer writeoff',
    ],
    'taxCondition' => ['Charged' => 'Charged', 'Quoted' => 'Quoted'],
    'questionType' => ['0' => 'Individule', '1' => 'Comman'],
// other service
    'payroll_inc_in_ff' => [
        0 => 'Not Quoted',
        1 => 'Inc In FF',
        2 => 'Quoted'
    ],
    'other_service' => [
        8 => 'Processing payroll - setting up payments maintaining leave record and preparing super report',
        9 => 'Accounts Receivable - generating invoices debtor reconciliation and related reporting',
        10 => 'Accounts Payable - supplier reconciliations online payment set up and related reporting',
        11 => 'Debtors Management - Chasing/follow up of payments and related reporting'
    ],
    'serviceMasterIds' => [
        8 => 9,
        9 => 10,
        10 => 11,
        11 => 8
    ],
    'manageSignatureOption' => [
        'user' => 1,
        'team' => 2,
        'imap' => 3,
        'smtp' => 4,
    ],
    'zoho' => [
        'potentialUrl' => 'https://crm.zoho.com/crm/private/xml/Potentials/searchRecords',
        'accountUrl' => 'https://crm.zoho.com/crm/private/xml/Accounts/getRecordById',
        'opportunityupdateUrl' => 'https://crm.zoho.com/crm/private/xml/Accounts/updateRecords',
        'scope' => 'crmapi',
        // 'token' => '["4ce19c2a5de3e5e03a2d8012ca2ad8e6"]'
        'token' => '["a882a69fa4e131554a3143ed96a06b44"]',
        'leadUrl' => 'https://crm.zoho.com/crm/private/xml/Leads/searchRecords',
        'testToken' => '["4fbf1516cb4560e5fd66c5816e59e0d9"]',
    ],
    'zohoV2' => [
        //'client_id' => '1000.LKGUVKVQHUR1H2633FJ0YQ4IOG7DFH',
        //'client_secret' => 'aaf4ca514f24a79d2120eb9184c18ada5661814187',
        'client_id' => '1000.ZTJDW7B6FZARALJH99WN53FQLQ79FO',
        'client_secret' => 'a706a306801ef11436e3ec747bac9056ff9827f60c',
        'redirect_uri' => 'https://befreecrm.com.au/',
        'currentUserEmail' => 'info@befree.com.au',
        'applicationLogFilePath' => base_path() . '/public',
        'db_port' => env('DB_PORT'),
        'db_name' => env('DB_DATABASE'),
        'db_username' => env('DB_USERNAME'),
        'db_password' => env('DB_PASSWORD'),
        'host_address' => env('DB_HOST')
    ],
    'UserWorksheetRatting' => [
        1 => 'Improvement Required',
        2 => 'Average',
        3 => 'Good',
        4 => 'Best'
    ],
    'whoFillUp' => [
        1 => 'Bookkeeping',
        2 => 'Payroll',
        3 => 'Taxation',
        4 => 'Billing',
        5 => 'Quality control',
        6 => 'Division head'
    ],
    'month' => [
        'Jan' => 'Jan',
        'Feb' => 'Feb',
        'Mar' => 'Mar',
        'Apr' => 'Apr',
        'May' => 'May',
        'Jun' => 'Jun',
        'July' => 'July',
        'Aug' => 'Aug',
        'Sept' => 'Sept',
        'Oct' => 'Oct',
        'Nov' => 'Nov',
        'Dec' => 'Dec'
    ],
    'year' => [
        '2019' => '2019',
        '2020' => '2020',
        '2021' => '2021',
        '2022' => '2022',
        '2023' => '2023',
        '2024' => '2024',
        '2025' => '2025',
        '2026' => '2026',
        '2027' => '2027',
        '2028' => '2028',
        '2029' => '2029',
        '2030' => '2030'
    ],
    'WIPInvoiceBillingStatus' => [
        '0' => 'Not Charged',
        '1' => 'Charged',
        '2' => 'Carry Forward',
        '3' => 'Write off',
        '4' => 'Adjust with setup'
    ],
    'subactivityCode' => [
        'numberOfTransation' => [201, 202, 228],
        'numberOfPayslip' => [501, 505, 601, 607],
        'numberOfEmployee' => [701, 707, 708, 422, 417, 463, 404],
        'numberOfyear' => [705],
        'number' => [210, 702, 501, 504, 505, 601, 604, 607, 803],
        'nameofemployee' => [460, 462]
    ],
    'access_by' => [
        'L' => 'Local',
        'A' => 'Live',
        'O' => 'Other'
    ],
    'pendingTimesheetStage' => [
        '0' => 'Timesheet pending',
        '1' => 'Pending for approval',
        '2' => 'Waiting for approval',
        '3' => 'Approved',
        '4' => 'Rejected'
    ],
    'url' => [
         'base' => 'https://befreecrm.com.au/'
        //'base' => 'http://192.168.3.39:4200/'
    ],
    'feedback_call_type' => ['1' => 'Call',
        '2' => 'Email',
        '3' => 'VM / No answer / Email',
        '4' => 'No Feedback Call'],
    'no_feedback' => ['1' => 'Discontinued',
        '2' => 'Client Denied',
        '3' => 'TAM Denied',
        '4' => 'Others',
        '5' => 'No reply from the client'],
    'feedback_status' => ['0' => 'Not Started',
        '1' => 'WIP',
        '2' => 'Pending from TAM / TL',
        '3' => 'Pending from CSM',
        '4' => 'Completed'],     
    'feedback_question' => ['1' => 'Do you receive timely responses? (Any suggestions or changes required in our Turnaround Time)?',
        '2' => 'Are you comfortable with communication with the teams?',
        '3' => 'How do you find our reports, are you satisfied with our reporting formats?'],
    'leave_allow_month' => [
        '1' => 'January',
        '2' => 'Febuary',
        '3' => 'March',
        '4' => 'April',
        '5' => 'May',
        '6' => 'Jun',
        '7' => 'July',
        '8' => 'August',
        '9' => 'September',
        '10' => 'October',
        '11' => 'November',
        '12' => 'December',
        '13' => 'Permanent'
    ],
    'quote_sub_service' => [
        '1' => 'Ongoing Bookkeeping',
        '18' => 'Backlog Bookkeeping'
    ],
    'htmlFormControl' => [
        'DD' => 'Drop Down',
        'CL' => 'Calendar',
        'TB' => 'Text Area',
        'TA' => 'Text Box'
    ],
    'quoteClientType' => [
        '1' => 'New',
        '2' => 'Existing',
        '3' => 'Both'
    ],
    'quoteDocumentType' => [
        '1' => 'Balance Sheet',
        '2' => 'P&L',
        '3' => 'Ledger of accountant fees & BK fees',
        '4' => 'DB Backup',
        '5' => 'Other Documents'
    ],
    'quoteLeadStep' => [
        '1' => 'Review Quote',
        '2' => 'Sign Your Document',
        '3' => 'EziDebit'
    ],
    'staffAssignInOtherModule' => [
        '0' => 'Not Assign',
        '1' => 'Default Bookkeeping Quote Assignee',
        '2' => 'Default Payroll Quote Assignee',
        '3' => 'Default Taxation Quote Assignee',
        '4' => 'Prepare Quote',
        '5' => 'Approve Quote',
        '6' => 'Both Quote Prepare & Approve'],
    'newsletterStatus' => [
        '0' => 'Hold',
        '1' => 'Sending',
        '2' => 'Completed'
    ],
    'newsletterFromEmail' => [
        'noreply<no-reply@befree.com.au>' => 'no-reply@befree.com.au',
        'payroll<payroll@befree.com.au>' => 'payroll@befree.com.au'
    ],   
    'subService' =>[
        "8"=>"6",
        "9"=>"5",
        "10"=>"8",
        "11"=>"7"
    ],
    'managementFrequency' => [
        'Monthly' => 1,
        'By Monthly' => 2,
        'Quartly' => 3,
        'Half Yearly' => 6,
        'Yearly' => 12],

    'mimetype' => [
        'application/vnd.google-apps.spreadsheet' => 'xlsx',
        'application/vnd.google-apps.document' => 'docx'
        
    ],
     'information_status' => [
        '1' => 'Pending',
        '2' => 'Partial',
        '3' => 'Received',
        '4' => 'Not Received',
        '5' => 'Resolved'
    ],
    'information_answer_type' => [
        '1' => 'Attachment',
        '2' => 'Text',
        '3' => 'Will Provide Later'
    ],
    'user_type' => [
        '0' => 'Probation',
        '1' => 'Permanent',
        '2' => 'Contractual'
    ],
    'bankType' => [
        '1' => 'Bank',
        '2' => 'Supplier',
        '3' => 'Employee'],
    
    'welcomekitStatus' => ['0' => 'Pending',
        '1' => 'Dispatch',
        '2' => 'Received']
];

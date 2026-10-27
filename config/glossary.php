<?php

/*
|--------------------------------------------------------------------------
| Short forms used in the portal
|--------------------------------------------------------------------------
| group => [ SHORT => [full form, meaning, show tooltip in the portal] ]
| The third value (true/false) decides if the short form gets a hover tooltip
| wherever it appears on a page. Ambiguous ones (P, S, Cr ...) are listed on the
| Short Forms page only.
*/

return [
    'Contract & Policy' => [
        'OIL' => ['Oil India Limited', 'The client company', false],
        'RSC' => ['Revenue Sharing Contract', 'Contract between the Government and OIL for a block', true],
        'PSC' => ['Production Sharing Contract', 'Older contract type', true],
        'OALP' => ['Open Acreage Licensing Policy', 'Government policy under which blocks are awarded in bid rounds', true],
        'DSF' => ['Discovered Small Field', 'Government bid round for small discovered fields', true],
        'MoPNG' => ['Ministry of Petroleum & Natural Gas', 'Petroleum ministry', true],
        'DGH' => ['Directorate General of Hydrocarbons', 'Technical regulator under the ministry', true],
        'PI' => ['Participating Interest', 'Share of OIL in the block', true],
        'JOA' => ['Joint Operating Agreement', 'Agreement between block partners', true],
        'MC' => ['Management Committee', 'Block committee, meets every 6 months', true],
        'MCR' => ['Management Committee Meeting / Resolution', 'Record of an MC meeting', true],
    ],

    'Licence & Phases' => [
        'PEL' => ['Petroleum Exploration Licence', 'Licence to explore for oil and gas', true],
        'PML' => ['Petroleum Mining Lease', 'Lease to produce oil and gas', true],
        'PL' => ['Petroleum Lease', 'Lease that follows the PEL', true],
        'IEP' => ['Initial Exploration Phase', 'First phase of the contract', true],
        'SEP' => ['Second / Subsequent Exploration Phase', 'Second phase or extension of exploration', true],
        'EP' => ['Exploration Phase', 'Exploration period for single-phase blocks', true],
        'FM' => ['Force Majeure', 'Relief for events beyond control (e.g. COVID)', true],
        'LD' => ['Liquidated Damages', 'Penalty paid when committed work is not done', true],
        'NOD' => ['Notification of Discovery', 'Notice that oil or gas has been found', true],
        'PCI' => ['Potential Commercial Interest', 'Notice that a discovery may be commercial', true],
        'FDP' => ['Field Development Plan', 'Plan for producing from a discovery', true],
    ],

    'Money & Guarantee' => [
        'BG' => ['Bank Guarantee', 'Security given by OIL, renewed before expiry', true],
        'BGs' => ['Bank Guarantees', 'Security given by OIL, renewed before expiry', true],
        'USD' => ['US Dollar', '', true],
        'INR' => ['Indian Rupee', '', true],
        'Cr' => ['Crore', '', false],
        'Mill' => ['Million', '', false],
    ],

    'Work Programme' => [
        'CWP' => ['Committed Work Programme', 'Work OIL committed to do under the contract', true],
        'MWP' => ['Minimum Work Programme', 'Minimum work to be done', true],
        '2D' => ['2-Dimensional Seismic', 'Seismic survey along lines', true],
        '3D' => ['3-Dimensional Seismic', 'Seismic survey over an area', true],
        'LKM' => ['Line Kilometre', 'Length of 2D seismic lines', true],
        'G&G' => ['Geological & Geophysical', 'Survey and study work', false],
        'PSTM' => ['Pre-Stack Time Migration', 'A seismic data processing method', true],
    ],

    'Wells & Drilling' => [
        'WCR' => ['Well Completion Report', 'Report submitted after a well is completed', true],
        'TD' => ['Target Depth', 'Planned depth of the well', true],
        'EDPMB' => ['Exploration & Development Project Management Board', 'OIL board that releases well locations', true],
        'EDS' => ['Expected Date of Spud', 'Expected start of drilling', true],
        'EDC' => ['Expected Date of Completion', 'Expected end of drilling', true],
        'BOP' => ['Blowout Preventer', 'Safety valve on a well', true],
        'BCD' => ['Bid Closing Date', 'Last date for tender bids', true],
        'P / S' => ['Primary / Secondary (target)', 'Main and secondary target layer', false],
    ],

    'Block Type' => [
        'ONHP' => ['Onland Hydrocarbon Prospect', 'Onland block (in block codes)', false],
        'UDWHP' => ['Ultra Deep Water Hydrocarbon Prospect', 'Very deep offshore block (in block codes)', false],
        'OSHP' => ['Offshore Shallow Hydrocarbon Prospect', 'Shallow offshore block (in block codes)', false],
        'SW' => ['Shallow Water', '', true],
        'DW' => ['Deep Water', '', true],
        'UDW' => ['Ultra Deep Water', '', true],
        'MN / MHN' => ['Mahanadi', 'Basin in Odisha', false],
        'KG / KGB' => ['Krishna Godavari (Basin)', 'Basin off Andhra Pradesh', false],
        'MBP' => ['Mahanadi Basin Project', '', true],
    ],

    'Environment & Permissions' => [
        'EC' => ['Environmental Clearance', 'Environment permission from the Centre', true],
        'CTE' => ['Consent to Establish', 'State Pollution Control Board permission to set up the site', true],
        'CTO' => ['Consent to Operate', 'State Pollution Control Board permission to run the site', true],
        'CRZ' => ['Coastal Regulation Zone', 'Permission for work near the coast', true],
        'FC' => ['Forest Clearance', 'Permission for forest land', true],
        'SPCB' => ['State Pollution Control Board', '', true],
        'OSPCB' => ['Odisha State Pollution Control Board', '', true],
        'IDCO' => ['Odisha Industrial Infrastructure Development Corporation', 'State agency that helps acquire land', true],
        'HSE' => ['Health, Safety & Environment', '', true],
    ],

    'Portal' => [
        'CSV' => ['Comma Separated Values', 'File that opens in Excel', true],
        'KPI' => ['Key Performance Indicator', 'Summary cards on the dashboard', true],
        'NA' => ['Not Applicable', '', true],
    ],
];

<?php

/*
|--------------------------------------------------------------------------
| Portal permissions
|--------------------------------------------------------------------------
| Every permission the portal checks. Keys are "<module>.<action>".
| After adding one here run:  php artisan permissions:sync
| It then shows up in the role permission matrix.
|
| Standard actions (view / create / edit / delete) get their own matrix column;
| anything else is listed under "Other".
*/

return [

    'modules' => [
        'dashboard' => [
            'label' => 'Dashboard',
            'permissions' => [
                'view' => 'View dashboard',
            ],
        ],
        'blocks' => [
            'label' => 'Blocks',
            'permissions' => [
                'view' => 'View blocks and block details',
                'create' => 'Add new blocks',
                'edit' => 'Edit block details, licence, timeline, CWP and BG',
                'delete' => 'Delete blocks',
                'view_all' => 'See all blocks (otherwise only assigned blocks)',
            ],
        ],
        'milestones' => [
            'label' => 'Compliance Monitor',
            'permissions' => [
                'view' => 'View milestones and deadlines',
                'edit' => 'Update status and remarks',
                'edit_dates' => 'Change due dates',
            ],
        ],
        'clearances' => [
            'label' => 'Statutory Clearances',
            'permissions' => [
                'view' => 'View EC / CTE / CTO clearances',
                'create' => 'Add clearances',
                'edit' => 'Edit clearances',
                'delete' => 'Delete clearances',
            ],
        ],
        'investment' => [
            'label' => 'Investment Monitoring',
            'permissions' => [
                'view' => 'View investment, LD and paid extensions',
                'create' => 'Add investment details for a block',
                'edit' => 'Edit investment, LD, paid extensions and BG amounts',
            ],
        ],
        'locations' => [
            'label' => 'Location Inventory',
            'permissions' => [
                'view' => 'View well locations',
                'create' => 'Add locations',
                'edit' => 'Edit locations and drilling status',
                'delete' => 'Delete locations',
            ],
        ],
        'notifications' => [
            'label' => 'Notifications',
            'permissions' => [
                'run' => 'Run the deadline check now (button on the Notifications page)',
            ],
        ],
        'mc_meetings' => [
            'label' => 'MC Meetings',
            'permissions' => [
                'view' => 'View MC meetings and minutes',
                'edit' => 'Add meetings and upload minutes',
            ],
        ],
        'documents' => [
            'label' => 'Document Repository',
            'permissions' => [
                'view' => 'View and download documents',
                'create' => 'Upload documents',
                'delete' => 'Delete documents',
            ],
        ],
        'reports' => [
            'label' => 'Reports',
            'permissions' => [
                'view' => 'View reports and upcoming actions',
                'export' => 'Download CSV',
            ],
        ],
        'compliance_register' => [
            'label' => 'Compliance Register',
            'permissions' => [
                'view' => 'View the RSC compliance rules',
                'edit' => 'Edit nodal officer, department and alert days',
            ],
        ],
        'users' => [
            'label' => 'Users',
            'permissions' => [
                'view' => 'View users',
                'create' => 'Create users',
                'edit' => 'Edit users, assign roles and blocks',
                'delete' => 'Delete users',
            ],
        ],
        'roles' => [
            'label' => 'Roles & Permissions',
            'permissions' => [
                'view' => 'View roles',
                'create' => 'Create roles',
                'edit' => 'Edit roles and their permissions',
                'delete' => 'Delete roles',
            ],
        ],
        'audit_logs' => [
            'label' => 'Audit Log',
            'permissions' => [
                'view' => 'View the audit trail',
            ],
        ],
    ],

    // Columns shown in the permission matrix, in order
    'standard_actions' => ['view', 'create', 'edit', 'delete'],

    // Permissions given to the built-in Company Representative role when it is first created
    'defaults' => [
        'company-representative' => [
            'dashboard.view',
            'blocks.view',
            'milestones.view',
            'milestones.edit',
            'clearances.view',
            'investment.view',
            'locations.view',
            'mc_meetings.view',
            'mc_meetings.edit',
            'documents.view',
            'documents.create',
            'reports.view',
            'reports.export',
        ],
    ],
];

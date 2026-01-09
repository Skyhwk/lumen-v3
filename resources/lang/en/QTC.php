<?php

return [
    'header' => [
        'quotation' => 'Quotation',
        'contract' => 'Contract',
        'office' => 'Office Address',
        'sampling' => 'Sampling Address',
    ],
    'footer' => [
        'center_content' => 'Page :page of :total_pages',
        'right_content' => 'Note : This document is published automatically by the system.'
    ],
    'status_sampling' => [
        'S24' => '24 Hours Sampling',
        'SD' => 'Delivered Sample',
        'S' => 'Sampling',
        'RS' => 'Re-Sample',
        'SP' => 'Sample Pickup',
    ],
    'table' => [
        'header' => [
            'no' => 'No',
            'description' => 'Test Description',
            'quantity' => 'Qty',
            'unit_price' => 'Unit Price',
            'total_price' => 'Total Price',
        ],
        'item' => [
            'volume' => 'Volume',
            'total_parameter' => 'Total Parameters',
            'transport' => 'Transportation - Sampling Area',
            'manpower' => 'Manpower ',
            'manpower24' => 'Includes Manpower (24 Hours)',
            'expenses' => [
                'other' => 'Miscellaneous Costs',
                'preparation' => 'Preparation Costs',
                'aftex_tax' => 'Cost After Tax',
                'non_taxable' => 'Non-Taxable Costs',
                'cost' => 'Cost',
            ]
        ]
    ],
    'total' => [
        'sub' => 'Sub Total',
        'after_tax' => 'Total After Tax',
        'price' => 'Total Price',
        'after_discount' => 'Total After Discount',
        'total' => 'Total',
        'analysis' => 'Total Analysis',
        'transport' => 'Total Transport',
        'manpower' => 'Total Manpower ',
        'analysis_price' => 'Total Testing Price',
        'price_after_discount' => 'Total Price After Discount',
        'grand' => 'Total Final Cost',
    ],
    'terms_conditions' => [
        'payment' => [
            'title' => 'Payment Terms and Conditions',
            'cash_discount' => '- Cash Discount applies if full payment is made before sampling.',
            '1' => "Payment is due :days days after the Test Result Report and Invoice are fully received by the customer.",
            '2' => "Full payment of :percent% is required before sampling is carried out.",
            '3' => 'Offer validity period :days days.',
            '4' => "Full payment is due when sampling is carried out by the customer.",
            '5' => "Payment :amount Down Payment (DP), Full payment at :text",
            '6' => "Payment I is :amount, Settlement at :text",
            '7' => "Payment is made in :count stages, Stage I is :amount1, Stage II is :amount2, Stage III is :amount3 of the total order.",
            '8' => "Payment :percent% DP, Payment in full when the draft Test Result Report is received by the customer.",
        ],
        'additional' => [
            'title' => 'Other / Additional Information',
        ],
        'general' => [
            'title' => 'General Terms and Conditions',
            'accreditation' => '- Parameters with the symbol <sup style="font-size: 14px;"><u>x</u></sup> have not been accredited by the Komite Akreditasi Nasional (KAN).',
            '1' => "For the Air category, <b>the price includes</b> the parameters <b>Temperature - Wind Speed ​​- Wind Direction - Humidity - Weather.</b>",
            '2' => "The power source is provided by the customer.",
            '3' => "The above prices are for the number of listed sampling points and may change depending on field conditions and customer requests..",
            '4' => "Cancellation or rescheduling by the customer will be subject to transportation and/or manpower fees.",
            '5' => "The work will be carried out after we receive confirmation in the form of a PO / SPK document from the customer.",
            '6' => "For companies that do not issue a PO / SPK, this price offer can be signed as a form of approval for the implementation of the work.",
            '7' => "The Test Result Report will be issued within 10 working days, calculated from the date the sample is received at the laboratory (Special parameters not included).",
            '8' => "Optimally, 1 (one) sampling team (2 people) can work on 6 air points (Ambient / Work Environment) per day.",
            '9' => "The time period for document production is 2-3 months, and the customer is obligated to complete the documents before sampling is carried out.",
            '10' => "Costs include :costs.",
        ],
    ],
    'tax' => [
        'vat' => 'VAT ',
        'income' => 'Income tax ',
    ],
    'discount' => [
        'contract' => [
            'water' => 'Contract Disc. Water ',
            'non_water' => 'Contract Disc. Non Water ',
            'air' => 'Contract Disc. Air ',
            'emission' => 'Contract Disc. Emission ',
            'transport' => 'Contract Disc. Transport ',
            'manpower' => 'Contract Disc. Manpower ',
            'manpower24' => 'Contract Disc. Manpower 24 Hours ',
            'operational' => 'Contract Disc. Analysis + Operations ',
            'consultant' => 'Contract Disc. Consultant ',
            'group' => 'Contract Disc. Group ',
            'percent' => 'Contract Disc. Percent ',
            'cash' => 'Contract Disc. Cash',
            'custom' => 'Custom Disc.',
            'disc' => 'Contract Disc. '
        ],
        'non_taxable' => [
            'transport' => 'Disc. Transportation',
            'manpower' => 'Disc. Manpower',
            'manpower24' => 'Disc. Manpower 24 Hours',
        ]
    ],
    'approval' => [
        'proof' => 'As a sign of agreement, please sign and send it back to us via email: sales@intilab.com',
        'administration' => 'Administration',
        'status' => 'Status',
        'sampling' => 'Sampling Date',
        'pic' => 'PIC Sales',
        'approving' => 'Approving',
        'name' => 'Name',
        'position' => 'Position',
    ],
    'summary' => [
        'header' => [
            'title' => 'Testing Contract Details - Period',
            'contract' => 'No Contract',
            'pic' => 'PIC Sales',
            'price' => 'Testing Price Information',
        ],
        'discount' => [
            'water' => 'Contract Discount - Water ',
            'non_water' => 'Contract Discount - Non Water ',
            'air' => 'Contract Discount - Air ',
            'emission' => 'Contract Discount - Emission ',
            'transport' => 'Contract Discount - Transport ',
            'manpower' => 'Contract Discount - Manpower ',
            'manpower24' => 'Contract Discount - Manpower 24 Hours ',
            'operational' => 'Contract Discount - Analysis + Operations ',
            'consultant' => 'Contract Discount - Consultant ',
            'group' => 'Contract Discount - Group ',
            'percent' => 'Contract Discount - Cash Discount Percent ',
            'cash' => 'Contract Discount - Cash Discount',
            'custom' => 'Custom Discount',
        ]
    ]
];

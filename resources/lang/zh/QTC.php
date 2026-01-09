<?php

return [
    'header' => [
        'quotation' => '报价单',
        'contract' => '合同',
        'office' => '办公地址',
        'sampling' => '采样地址',
    ],
    'footer' => [
        'center_content' => '第 :page 页，共 :total_pages 页',
        'right_content' => '注：本文件由系统自动发布。'
    ],
    'status_sampling' => [
        'S24' => '24小时采样',
        'SD' => '已送达样品',
        'S' => '采样'
    ],
    'table' => [
        'header' => [
            'no' => '编号',
            'description' => '测试描述',
            'quantity' => '数量',
            'unit_price' => '单价',
            'total_price' => '总价',
        ],
        'item' => [
            'volume' => '体积',
            'total_parameter' => '总参数',
            'transport' => '交通运输-采样区',
            'manpower' => '人手 ',
            'manpower24' => '包括人力（24小时）',
            'expenses' => [
                'other' => '杂费',
                'preparation' => '准备成本',
                'aftex_tax' => '税后成本',
                'non_taxable' => '不含税费用',
                'cost' => '成本',
            ]
        ]
    ],
    'total' => [
        'sub' => '小计',
        'after_tax' => '税后总额',
        'price' => '总价',
        'after_discount' => '折扣后总额',
        'total' => '全部的',
        'analysis' => '全面分析',
        'transport' => '全方位运输',
        'manpower' => '总人力 ',
        'analysis_price' => '总测试价格',
        'price_after_discount' => '折扣后总价',
        'grand' => '最终总成本',
    ],
    'terms_conditions' => [
        'payment' => [
            'title' => '付款条款和条件',
            'cash_discount' => '- 如果在取样前全额付款，则可享受现金折扣。',
            '1' => "客户收到全额测试结果报告和发票后 :days 天内付款。",
            '2' => "进行取样之前全额支付 :percent%。",
            '3' => '优惠有效期:days天。',
            '4' => "客户进行采样时全额付款。",
            '5' => "付款 :amount 首付（DP），全额付款于 :text",
            '6' => "付款 :amount 为 30，结算时间为 :text",
            '7' => "付款分:count个阶段进行，第一阶段为订单总额的:amount1，第二阶段为:amount2，第三阶段为订单总额的:amount3。",
            '8' => "付款 :percent% DP，当客户收到测试结果报告草稿时全额付款。",

        ],
        'additional' => [
            'title' => '其他/附加信息',
        ],
        'general' => [
            'title' => '一般条款和条件',
            'accreditation' => '- 带有符号 <sup style="font-size: 14px;"><u>x</u></sup> 的参数尚未获得 Komite Akreditasi Nasional (KAN) 的认可。',
            '1' => "对于空气类别，<b>价格包括</b>参数<b>温度 - 风速 - 风向 - 湿度 - 天气。</b>",
            '2' => "电源由客户提供。",
            '3' => "以上价格针对列出的采样点数量，可能会根据现场条件和客户要求而变化。",
            '4' => "客户取消或重新安排将需支付交通费和/或每日津贴费用。",
            '5' => "我们收到客户以 PO / SPK 文件形式发出的确认后，就会开展工作。",
            '6' => "对于没有签发PO/SPK的公司，可以签署报价单作为批准实施工作的一种形式。",
            '7' => "检测结果报告将于实验室收到样品之日起10个工作日内出具（未包含特殊参数).",
            '8' => "最佳情况下，1 个采样小组（2 人）每天可对 6 个空气点（环境/工作环境）进行工作。",
            '9' => "请使用 2 - 3 份样本进行采样，然后进行采样。",
            '10' => "费用包括 :costs。",
        ],
    ],
    'tax' => [
        'vat' => '增值税 ',
        'income' => '所得税 ',
    ],
    'discount' => [
        'contract' => [
            'water' => '合同折扣 - 水质',
            'non_water' => '合同折扣 - 非水质',
            'air' => '合同折扣 - 空气',
            'emission' => '合同折扣 - 排放',
            'transport' => '合同折扣 - 运输费用',
            'manpower' => '合同折扣 - 人力',
            'manpower24' => '合同折扣 - 24小时人力',
            'operational' => '合同折扣 - 分析与运营',
            'consultant' => '合同折扣 - 顾问',
            'group' => '合同折扣 - 集团',
            'percent' => '合同折扣 - 百分比',
            'cash' => '合同折扣 - 现金',
            'custom' => '自定义折扣',
            'disc' => '合同折扣'
        ],
        'non_taxable' => [
            'transport' => '免税优惠 - 运输',
            'manpower' => '免税优惠 - 人力',
            'manpower24' => '免税优惠 - 24小时人力',
        ]
    ],
    'approval' => [
        'proof' => '作为协议的标志，请签署并通过电子邮件发送给我们：sales@intilab.com',
        'administration' => '行政',
        'status' => '地位',
        'pic' => 'PIC销售',
        'approving' => '批准',
        'name' => '姓名',
        'position' => '位置',
    ],
    'summary' => [
        'header' => [
            'title' => '测试合同详情 - 期限',
            'contract' => '合同编号',
            'sampling' => '采样日期',
            'pic' => '销售负责人',
            'price' => '测试价格信息',
        ],
        'discount' => [
            'water' => '合同折扣 - 水',
            'non_water' => '合同折扣 - 非水',
            'air' => '合同折扣 - 空气',
            'emission' => '合同折扣 - 排放',
            'transport' => '合同折扣 - 运输',
            'manpower' => '合同折扣 - 人工',
            'manpower24' => '合同折扣 - 24小时人工',
            'operational' => '合同折扣 - 分析+运营',
            'consultant' => '合同折扣 - 顾问',
            'group' => '合同折扣 - 团体',
            'percent' => '合同折扣 - 现金折扣百分比',
            'cash' => '合同折扣 - 现金折扣',
            'custom' => '自定义折扣',
        ]
    ]
];

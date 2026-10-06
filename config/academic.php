<?php

return [
    'warning_thresholds' => ['gpa_below' => 1.6, 'credit_debt_above' => 15, 'progress_ratio_below' => 0.70, 'graduation_lookahead_days' => 180, 'milestone_lookahead_days' => 30],
    'gpa_by_degree' => ['dai_hoc' => 1.6, 'thac_si' => 2.0, 'tien_si' => 2.0],
    'warning_labels' => [
        'low_gpa' => 'Điểm trung bình thấp', 'credit_debt' => 'Nợ tín chỉ', 'delayed_progress' => 'Trễ tiến độ',
        'graduation_risk' => 'Nguy cơ trễ tốt nghiệp', 'proposal_due' => 'Hạn đề cương',
        'department_defense_due' => 'Hạn bảo vệ cấp cơ sở', 'academy_defense_due' => 'Hạn bảo vệ cấp Học viện',
        'maximum_study_due' => 'Hạn đào tạo tối đa',
    ],
];

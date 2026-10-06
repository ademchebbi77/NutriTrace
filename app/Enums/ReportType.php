<?php

namespace App\Enums;

enum ReportType: string
{
    case SUSPICIOUS_INFORMATION = 'SUSPICIOUS_INFORMATION';
    case MISLEADING_ENVIRONMENTAL_CLAIM = 'MISLEADING_ENVIRONMENTAL_CLAIM';
    case INVALID_CERTIFICATION = 'INVALID_CERTIFICATION';

    public function label(): string
    {
        return __('enums.report_type.'.$this->value);
    }
}

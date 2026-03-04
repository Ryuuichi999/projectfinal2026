<?php
/**
 * ฟังก์ชันแสดงสถานะเป็น Badge สี (ใช้ร่วมกันทั้งระบบ)
 * include ไฟล์นี้แทนการคัดลอกฟังก์ชันซ้ำ
 */

if (!function_exists('get_status_badge')) {
    function get_status_badge($status)
    {
        switch ($status) {
            case 'pending':
                $bg = '#eff6ff'; $color = '#1d4ed8'; $icon = 'bi-hourglass-split';
                $text = 'รอกำลังพิจารณา';
                break;
            case 'reviewing':
                $bg = '#f0f9ff'; $color = '#0369a1'; $icon = 'bi-search';
                $text = 'กำลังพิจารณา';
                break;
            case 'need_documents':
                $bg = '#fffbeb'; $color = '#b45309'; $icon = 'bi-folder-plus';
                $text = 'ขอเอกสารเพิ่ม';
                break;
            case 'waiting_payment':
                $bg = '#fef2f2'; $color = '#dc2626'; $icon = 'bi-credit-card';
                $text = 'รอชำระเงิน';
                break;
            case 'waiting_permit':
                $bg = '#faf5ff'; $color = '#7c3aed'; $icon = 'bi-file-earmark-text';
                $text = 'รอออกใบอนุญาต';
                break;
            case 'waiting_receipt':
                $bg = '#f0fdfa'; $color = '#0d9488'; $icon = 'bi-receipt';
                $text = 'รอออกใบเสร็จ';
                break;
            case 'approved':
                $bg = '#f0fdf4'; $color = '#16a34a'; $icon = 'bi-check-circle';
                $text = 'อนุมัติแล้ว';
                break;
            case 'rejected':
                $bg = '#f9fafb'; $color = '#6b7280'; $icon = 'bi-x-circle';
                $text = 'ไม่อนุมัติ';
                break;
            case 'expired':
                $bg = '#f9fafb'; $color = '#374151'; $icon = 'bi-clock-history';
                $text = 'หมดอายุ';
                break;
            case 'cancelled_payment':
                $bg = '#f9fafb'; $color = '#6b7280'; $icon = 'bi-x-circle';
                $text = 'ยกเลิก (ไม่ชำระเงิน)';
                break;
            case '':
            case null:
                $bg = '#eff6ff'; $color = '#1d4ed8'; $icon = 'bi-hourglass-split';
                $text = 'รอกำลังพิจารณา';
                break;
            default:
                $bg = '#f0f9ff'; $color = '#0369a1'; $icon = 'bi-info-circle';
                $text = $status;
        }
        return "<span style='display:inline-flex;align-items:center;gap:4px;background:$bg;color:$color;font-size:.78rem;font-weight:600;padding:3px 10px;border-radius:6px;white-space:nowrap;'><i class='bi $icon' style='font-size:.72rem;'></i>$text</span>";
    }
}

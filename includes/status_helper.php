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
                $class = 'warning';
                $text = '⏳ รอกำลังพิจารณา';
                break;
            case 'reviewing':
                $class = 'primary';
                $text = '🔎 กำลังพิจารณา';
                break;
            case 'need_documents':
                $class = 'info';
                $text = '📑 ขอเอกสารเพิ่ม';
                break;
            case 'waiting_payment':
                $class = 'danger';
                $text = '⚠️ รอชำระเงิน';
                break;
            case 'waiting_permit':
                $class = 'primary';
                $text = '📜 รอออกใบอนุญาต';
                break;
            case 'waiting_receipt':
                $class = 'info';
                $text = '🧾 รอออกใบเสร็จ';
                break;
            case 'approved':
                $class = 'success';
                $text = '✅ อนุมัติแล้ว';
                break;
            case 'rejected':
                $class = 'secondary';
                $text = '❌ ไม่อนุมัติ';
                break;
            default:
                $class = 'info';
                $text = $status;
        }
        return "<span class='badge bg-$class'>$text</span>";
    }
}

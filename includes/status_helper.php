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
                $class = 'primary'; // Blue
                $text = '⏳ รอกำลังพิจารณา';
                break;
            case 'reviewing':
                $class = 'info';
                $text = '🔎 กำลังพิจารณา';
                break;
            case 'need_documents':
                $class = 'warning';
                $text = '📑 ขอเอกสารเพิ่ม';
                break;
            case 'waiting_payment':
                $class = 'danger';
                $text = '⚠️ รอชำระเงิน';
                break;
            case 'waiting_permit':
                $class = 'dark'; // Dark/Black for distinction
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
            case 'expired':
                $class = 'dark';
                $text = '⏰ หมดอายุ';
                break;
            case 'cancelled_payment':
                $class = 'secondary';
                $text = '❌ ยกเลิก (ไม่ชำระเงิน)';
                break;
            case '':
            case null:
                $class = 'primary';
                $text = '⏳ รอกำลังพิจารณา';
                break;
            default:
                $class = 'info';
                $text = $status;
        }
        return "<span class='badge bg-$class'>$text</span>";
    }
}

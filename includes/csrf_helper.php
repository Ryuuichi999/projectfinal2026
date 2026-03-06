<?php
/**
 * CSRF Protection Helper
 * สร้างและตรวจสอบ CSRF Token เพื่อป้องกัน Cross-Site Request Forgery
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * สร้าง CSRF Token ใหม่ (หรือคืนค่าที่มีอยู่)
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * สร้าง hidden input field สำหรับใส่ในฟอร์ม
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * ตรวจสอบ CSRF Token จาก POST request
 * @return bool true ถ้า token ถูกต้อง
 */
function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * ตรวจสอบ CSRF และหยุดทำงานถ้าไม่ผ่าน
 */
function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify()) {
            http_response_code(403);
            echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body>
            <script>document.addEventListener("DOMContentLoaded",function(){
                const Toast=Swal.mixin({toast:true,position:"top-end",showConfirmButton:false,timer:1500,timerProgressBar:true,didOpen:(t)=>{t.onmouseenter=Swal.stopTimer;t.onmouseleave=Swal.resumeTimer}});
                Toast.fire({icon:"error",title:"เซสชันหมดอายุ กรุณาลองใหม่"}).then(()=>{window.history.back();});
            });</script></body></html>';
            exit;
        }
    }
}

/**
 * สร้าง Token ใหม่ (หลัง submit สำเร็จ เพื่อป้องกัน replay)
 */
function csrf_regenerate(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

<?php

namespace App\Orders;

final class OrderSmsMessages
{
    public static function created(string $trackId): string
    {
        helper('url');

        return 'หมายเลขการติดตามสถานะสินค้าซ่อม ' . $trackId
            . ' ท่านสามารถตรวจสอบสถานะได้ที่ ' . base_url('tracking/' . $trackId);
    }

    public static function returned(string $trackId): string
    {
        return 'สินค้าซ่อมของท่าน ' . $trackId
            . ' ส่งคืนมายังสาขาแล้ว สามารถติดต่อรับคืนได้ภายใน 15 วันหลังจากได้รับข้อความนี้';
    }

    public static function completed(string $trackId): string
    {
        helper('url');

        return 'ขอบคุณที่ใช้บริการกับ Samsonite  แสดงความคิดเห็น ' . base_url('rating/' . $trackId);
    }
}

<?php
/**
 * @var array<string, mixed> $row
 * @var string $branchName
 * @var string $typeName
 * @var string $brandName
 * @var list<array<string, mixed>> $conditions
 * @var list<array<string, mixed>> $estimatePrices
 * @var list<array<string, mixed>> $fixedItems
 */

$fmtDate = static function ($raw): string {
    $raw = (string) ($raw ?? '');
    if ($raw === '' || strncmp($raw, '0000-00-00', 10) === 0) {
        return '';
    }

    return substr($raw, 8, 2) . '/' . substr($raw, 5, 2) . '/' . substr($raw, 0, 4);
};

$renderChecks = static function (array $items, string $idKey, string $labelKey, $selectedRaw, $other): void {
    $selectedRaw = (string) ($selectedRaw ?? '');
    $selected    = $selectedRaw === '' ? [] : explode('|', $selectedRaw);
    foreach ($items as $item) {
        $checked = in_array((string) $item[$idKey], $selected, true) ? ' checked' : '';
        echo '<label class="custom-form"><input type="checkbox" value="' . esc((string) $item[$idKey]) . '" disabled' . $checked . '> <span class="label-text">' . esc((string) $item[$labelKey]) . '</span></label> ';
    }
    $other = (string) ($other ?? '');
    echo '<label class="custom-form"><input type="checkbox" value="etc" disabled' . ($other !== '' ? ' checked' : '') . '> <span class="label-text">อื่นๆ</span></label> ';
    echo esc($other);
};
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title><?= esc($row['trackID'] ?? '') ?></title>
    <style media="print">
        @page { size: A4; margin: 0 10mm; }
        .no-print { display: none; }
    </style>
    <style>
        body { font-family: 'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; }
        section { width: 100%; max-width: 1170px; margin: 0 auto; position: relative; }
        .detail { width: 100%; text-align: left; line-height: 1.5; }
        .detail td { font-size: 14px; font-weight: 400; padding: 10px 0; }
        .custom-form { display: inline-block; margin-right: 12px; }
        p { margin: 0; }
    </style>
</head>
<body class="A4">
<section class="sheet">
    <input class="no-print" type="button" value="Print" onclick="this.style.display='none';window.print();">

    <table style="width: 100%; text-align: left;">
        <tr>
            <td style="width: 50%;">
                <h1 style="line-height: 1; color: #014c8f;">ใบรับซ่อม</h1>
                <h2 style="font-size: 18px; line-height: 1; color: #014c8f;">บริษัท แซมโซไนท์ (ประเทศไทย) จำกัด<br>SAMSONITE (THAILAND) CO., LTD<br>สาขา <?= esc($branchName) ?></h2>
            </td>
            <td style="width: 50%;">
                <img src="/assets/images/main-logo.png" alt="" style="max-width: 230px; width: 230px; height: auto;">
            </td>
        </tr>
    </table>
    <br>

    <table class="detail">
        <?php if ((string) ($row['detailAgent'] ?? '') === '1') { ?>
            <tr><td colspan="3"><strong style="color: red; text-decoration: underline;">URGENT/ซ่อมด่วน</strong></td></tr>
        <?php } ?>

        <tr>
            <td><strong>TRACK ID/เลขติดตาม</strong></td>
            <td><strong>ORDER ID/เลขส่งซ่อมสินค้า</strong></td>
            <td><strong>REQUEST DATE/วันที่ส่งซ่อม</strong></td>
        </tr>
        <tr>
            <td><?= esc($row['trackID'] ?? '') ?></td>
            <td><?= esc($row['orderIDShow'] ?? '') ?></td>
            <td><?= esc($fmtDate($row['requestDate'] ?? '')) ?></td>
        </tr>

        <tr>
            <td><strong>FULLNAME/ชื่อลูกค้า</strong></td>
            <td><strong>EMAIL/อีเมล์ลูกค้า</strong></td>
            <td><strong>TEL/เบอร์โทรลูกค้า</strong></td>
        </tr>
        <tr>
            <td><?= esc($row['customerFullname'] ?? '') ?></td>
            <td><?= esc($row['customerEmail'] ?? '') ?></td>
            <td><?= esc($row['customerTel'] ?? '') ?></td>
        </tr>

        <tr>
            <td><strong>TEL2/เบอร์โทรศัพท์ลูกค้า2</strong></td>
            <td><strong>PURCHASED DATE/วันที่ซื้อ</strong></td>
            <td><strong>CATEGORY/ประเภท</strong></td>
        </tr>
        <tr>
            <td><?= esc($row['customerTel2'] ?? '') ?></td>
            <td><?= esc($fmtDate($row['detailDatePurchase'] ?? '')) ?></td>
            <td><?= esc($typeName) ?></td>
        </tr>

        <tr>
            <td><strong>BRAND/ยี่ห้อ</strong></td>
            <td><strong>SKU NAME/ชื่อสินค้า</strong></td>
            <td><strong>WARANTY TYPE/ประเภทการรับประกัน</strong></td>
        </tr>
        <tr>
            <td><?= esc($brandName) ?></td>
            <td><?= esc($row['detailSKUName'] ?? '') ?></td>
            <td>
                <?php $waranty = (string) ($row['detailNumberWaranty'] ?? ''); ?>
                <?= $waranty !== '' ? 'มี ' . esc($waranty) : 'ไม่มี' ?>
            </td>
        </tr>

        <tr><td colspan="3"><strong>CONDITION/อาการที่ส่งซ่อม</strong></td></tr>
        <tr><td colspan="3"><?php $renderChecks($conditions, 'condition_id', 'condition_details', $row['detailCondition'] ?? '', $row['detailConditionOther'] ?? ''); ?></td></tr>

        <tr><td colspan="3"><strong>ESTIMATE PRICE/ประเมินราคาส่งซ่อม</strong></td></tr>
        <tr><td colspan="3"><?php $renderChecks($estimatePrices, 'estimateprice_id', 'estimateprice_details', $row['detailEstimatePrice'] ?? '', $row['detailEstimatePriceOther'] ?? ''); ?></td></tr>

        <tr><td colspan="3"><strong>FIXED/สภาพ,ตำหนิ</strong></td></tr>
        <tr><td colspan="3"><?php $renderChecks($fixedItems, 'fixed_id', 'fixed_details', $row['detailFixed'] ?? '', $row['detailFixedOther'] ?? ''); ?></td></tr>

        <tr><td colspan="3"><strong>EQUIPMENT/อุปกรณ์ที่มาพร้อมกับสินค้า</strong></td></tr>
        <tr><td colspan="3"><?= esc($row['detailEquipment'] ?? '') ?></td></tr>

        <tr><td colspan="3"><strong>NOTE/หมายเหตุ</strong></td></tr>
        <tr><td colspan="3"><?= esc($row['detailNote'] ?? '') ?></td></tr>

        <tr><td colspan="3"><strong>CREATED BY/พนักงานผู้รับสินค้า</strong></td></tr>
        <tr><td colspan="3"><?= esc($row['create_by_user'] ?? '') ?></td></tr>

        <tr><td colspan="3"><strong>รูป</strong></td></tr>
        <tr>
            <td colspan="3">
                <?php foreach (explode('|', (string) ($row['detailImage'] ?? '')) as $img) { ?>
                    <?php if (preg_match('/\A[a-f0-9]{32}\.png\z/D', $img) === 1) { ?>
                        <img src="/order-image/<?= esc($img) ?>" style="width:150px;height:150px">
                    <?php } ?>
                <?php } ?>
            </td>
        </tr>
    </table>

    <br>
    <address style="font-style: normal; line-height: 1.5;">
        98 อาคารสาทร สแควร์ ออฟฟิศ ทาวเวอร์ ชั้น 37 ห้องเลขที่ 3705-3706 ถนนสาทรเหนือ แขวงสีลม เขตบางรัก กรุงเทพฯ 10500<br>
        98 Sathorn Square Office Tower 37<sup>th</sup> floor room no.3705-3706, North Sathorn Road, Silom, Bangkok, Bangkok 10500
        <p>Tel. (66) 2761-9999 <span>Fax: (66) 2761-9900</span></p>
    </address>
</section>
</body>
</html>

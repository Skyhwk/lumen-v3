<?php

namespace Tests\Unit;

use App\Models\MasterKaryawan;
use PHPUnit\Framework\TestCase;

class MasterKaryawanPhoneTest extends TestCase
{
    public function test_no_telpon_sales_prioritizes_company_phone()
    {
        $karyawan = new MasterKaryawan([
            'no_telpon' => '08123456789',
            'no_telpon_perusahaan' => '08987654321',
        ]);

        $this->assertEquals('08987654321', $karyawan->no_telpon_sales);
    }

    public function test_no_telpon_sales_falls_back_to_personal_phone_when_company_phone_is_empty()
    {
        $karyawanNull = new MasterKaryawan([
            'no_telpon' => '08123456789',
            'no_telpon_perusahaan' => null,
        ]);
        $this->assertEquals('08123456789', $karyawanNull->no_telpon_sales);

        $karyawanBlank = new MasterKaryawan([
            'no_telpon' => '08123456789',
            'no_telpon_perusahaan' => '   ',
        ]);
        $this->assertEquals('08123456789', $karyawanBlank->no_telpon_sales);
    }

    public function test_no_telpon_sales_returns_null_when_both_empty()
    {
        $karyawan = new MasterKaryawan([
            'no_telpon' => '',
            'no_telpon_perusahaan' => null,
        ]);

        $this->assertNull($karyawan->no_telpon_sales);
    }
}


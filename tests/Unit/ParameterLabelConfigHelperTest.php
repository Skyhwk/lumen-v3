<?php

namespace Tests\Unit;

use App\Helpers\ParameterLabelConfigHelper;
use PHPUnit\Framework\TestCase;

class ParameterLabelConfigHelperTest extends TestCase
{
    public function test_is_kategori_air()
    {
        $this->assertTrue(ParameterLabelConfigHelper::isKategoriAir(1));
        $this->assertTrue(ParameterLabelConfigHelper::isKategoriAir('1'));
        $this->assertTrue(ParameterLabelConfigHelper::isKategoriAir('1-Air'));
        $this->assertTrue(ParameterLabelConfigHelper::isKategoriAir('Air'));
        $this->assertTrue(ParameterLabelConfigHelper::isKategoriAir('air'));

        $this->assertFalse(ParameterLabelConfigHelper::isKategoriAir(2));
        $this->assertFalse(ParameterLabelConfigHelper::isKategoriAir('2-Udara'));
        $this->assertFalse(ParameterLabelConfigHelper::isKategoriAir('Udara'));
        $this->assertFalse(ParameterLabelConfigHelper::isKategoriAir(null));
    }

    public function test_resolve_volume_fallback_for_non_air()
    {
        $vol = ParameterLabelConfigHelper::resolveVolume(10, '2-Udara', 250);
        $this->assertEquals(250.0, $vol);

        $volZero = ParameterLabelConfigHelper::resolveVolume(10, '2-Udara', null);
        $this->assertEquals(0.0, $volZero);
    }

    public function test_resolve_regen_fallback_for_non_air()
    {
        $regen = ParameterLabelConfigHelper::resolveRegen(10, '2-Udara', 'HNO3');
        $this->assertEquals('HNO3', $regen);

        $regenDefault = ParameterLabelConfigHelper::resolveRegen(10, '2-Udara', null);
        $this->assertEquals('ORI', $regenDefault);
    }

    public function test_enrich_param_map_skips_non_air()
    {
        $paramMap = [
            'CO' => (object) [
                'id_parameter' => 5,
                'nama_parameter' => 'CO',
                'volume' => 100,
                'regen' => 'ORI',
            ]
        ];

        ParameterLabelConfigHelper::enrichParamMap($paramMap, [5], '2-Udara');

        $this->assertEquals(100, $paramMap['CO']->volume);
        $this->assertEquals('ORI', $paramMap['CO']->regen);
    }
}

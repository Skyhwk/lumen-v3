<?php

namespace Tests\Unit;

use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Hr\SamplerBranchApprovalPolicy;
use TestCase;

class SamplerBranchApprovalPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'greatday.sampler_branch_approval_filter' => true,
            'greatday.sampler_branch_ho_cabang_id' => 1,
            'greatday.sampler_jabatan_ids' => [94],
        ]);
    }

    public function test_disabled_policy_returns_original_chain(): void
    {
        config(['greatday.sampler_branch_approval_filter' => false]);
        $policy = new SamplerBranchApprovalPolicy();
        $submitter = new MasterKaryawan([
            'id_jabatan' => 94,
            'id_cabang' => 35,
            'jabatan' => 'Sampler',
        ]);
        $chain = [10, 20, 30];

        $this->assertSame($chain, $policy->filterApproverIds($submitter, $chain, HrRequest::TYPE_LEAVE));
    }

    public function test_applies_to_branch_sampler_for_scoped_types(): void
    {
        $policy = new SamplerBranchApprovalPolicy();
        $submitter = new MasterKaryawan([
            'id_jabatan' => 94,
            'id_cabang' => 35,
            'jabatan' => 'Sampler',
        ]);

        $this->assertTrue($policy->appliesTo($submitter, HrRequest::TYPE_PERMISSION));
        $this->assertTrue($policy->appliesTo($submitter, HrRequest::TYPE_LEAVE));
        $this->assertTrue($policy->appliesTo($submitter, HrRequest::TYPE_ATTENDANCE_CORRECTION));
    }

    public function test_does_not_apply_to_ho_sampler(): void
    {
        $policy = new SamplerBranchApprovalPolicy();
        $submitter = new MasterKaryawan([
            'id_jabatan' => 94,
            'id_cabang' => 1,
            'jabatan' => 'Sampler',
        ]);

        $this->assertFalse($policy->appliesTo($submitter, HrRequest::TYPE_LEAVE));
    }

    public function test_does_not_apply_to_overtime(): void
    {
        $policy = new SamplerBranchApprovalPolicy();
        $submitter = new MasterKaryawan([
            'id_jabatan' => 94,
            'id_cabang' => 35,
            'jabatan' => 'Sampler',
        ]);

        $this->assertFalse($policy->appliesTo($submitter, HrRequest::TYPE_OVERTIME));
    }

    public function test_filter_removes_same_branch_supervisor_keeps_ho(): void
    {
        $sampler = MasterKaryawan::query()
            ->where('is_active', 1)
            ->where('id_jabatan', 94)
            ->where('id_cabang', '!=', 1)
            ->whereNotNull('atasan_langsung')
            ->first();

        if (!$sampler) {
            $this->markTestSkipped('Tidak ada Sampler cabang aktif di DB.');

            return;
        }

        $directIds = json_decode($sampler->atasan_langsung, true);
        if (!is_array($directIds) || count($directIds) < 2) {
            $this->markTestSkipped('Sampler cabang tanpa dual atasan.');

            return;
        }

        $chainService = app(\App\Services\Hr\FormAtasanApprovalChainService::class);
        $chain = $chainService->orderedApproverIds($sampler, HrRequest::TYPE_PERMISSION);
        $rawChain = app(\App\Services\Greatday\DynamicApprovalChainService::class)->orderedApproverIds($sampler);
        $filtered = $chain;

        $this->assertNotSame($rawChain, $filtered);
        $this->assertNotEmpty($filtered);

        $hoCabang = (int) config('greatday.sampler_branch_ho_cabang_id', 1);
        $submitterCabang = (int) $sampler->id_cabang;

        foreach ($filtered as $approverId) {
            $approver = MasterKaryawan::find($approverId);
            $this->assertNotNull($approver);
            $isBranchSpv = (int) $approver->id_cabang === $submitterCabang
                && strtoupper(str_replace('_', ' ', (string) $approver->grade)) === 'SUPERVISOR';
            $this->assertFalse($isBranchSpv, 'SPV cabang operasional masih ada di chain: ' . $approver->nama_lengkap);
        }

        foreach ($directIds as $directId) {
            $direct = MasterKaryawan::find((int) $directId);
            if (!$direct) {
                continue;
            }
            $isBranchSpvDirect = (int) $direct->id_cabang === $submitterCabang
                && strtoupper(str_replace('_', ' ', (string) $direct->grade)) === 'SUPERVISOR';
            if ($isBranchSpvDirect) {
                $this->assertNotContains(
                    (int) $directId,
                    $filtered,
                    'Atasan langsung SPV cabang tidak boleh masuk chain terfilter'
                );
            }
        }

        $hasHo = false;
        foreach ($filtered as $approverId) {
            $approver = MasterKaryawan::find($approverId);
            if ($approver && (int) $approver->id_cabang === $hoCabang) {
                $hasHo = true;
                break;
            }
        }
        $this->assertTrue($hasHo, 'Chain harus masih memuat minimal satu approver HO.');
    }
}

<?php
namespace App\Services;

use Auth;
use Validator;
use Exception;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\PraNoSample;
use App\Services\GenerateSampleNonKontrak;
use App\Services\GenerateSampleKontrak;

class GeneratePraSampling
{
    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array<int, \Illuminate\Database\Eloquent\Model>  $models
     * @return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>
     */

    private $type;
    private $generate;
    private $no_qt_lama;
    private $no_qt_baru;
    private $id_order;

    public function type(string $type = '')
    {
        if ($type == 'QT') {
            $this->type = 'QT';
        } else if ($type == 'QTC') {
            $this->type = 'QTC';
        } else {
            $this->type = null;
        }
    }

    public function where(string $key = '', string $value = '')
    {
        if ($key == 'no_qt_lama') {
            $this->no_qt_lama = $value;
        } else if ($key == 'no_qt_baru') {
            $this->no_qt_baru = $value;
        } else if ($key == 'id_order') {
            $this->id_order = $value;
        } else if ($key == 'generate') {
            $this->generate = $value;
        }
    }

    public function save()
    {
        if ($this->type == 'QT') {
            if (isset($this->generate) && $this->generate == 'new') {
                $this->newnonkontrak();
            } else {
                $this->nonkontrak();
            }
        } else if ($this->type == 'QTC') {
            if (isset($this->generate) && $this->generate == 'new') {
                $this->newkontrak();
            } else {
                $this->kontrak();
            }
        } else {
            $this->unset();
        }
    }

    protected function nonkontrak()
    {
        //$value berisi 2 array ['kategori', 'total_param']
        $get = GenerateSampleNonKontrak::get(['no_qt_lama' => $this->no_qt_lama, 'no_qt_baru' => $this->no_qt_baru, 'id_order' => $this->id_order]);
        if($get != null){
            $no_qt = '%' . \explode('R', $this->no_qt_lama)[0] . '%';
            PraNoSample::where('no_quotation', $this->no_qt_baru)->delete();
            foreach ($get as $key => $value) {
                $array = [
                    'no_quotation' => $this->no_qt_baru,
                    'periode' => null,
                    'kategori' => json_encode($value['kategori']),
                    'total_param' => json_encode($value['total_param'])
                ];

                PraNoSample::insert($array);
                $this->no_sample = $value;
            }   
        }
        $this->message = 'Data hasbeen generate non kontrak';
        $this->unset();
        // dd('masuk');
    }

    protected function newnonkontrak()
    {
        //$value berisi 2 array ['kategori', 'total_param']
        $get = GenerateSampleNonKontrak::new(['no_qt_baru' => $this->no_qt_baru]);
        $no_qt = '%' . \explode('R', $this->no_qt_baru)[0] . '%';
        PraNoSample::where('no_quotation', $this->no_qt_baru)->delete();
        foreach ($get as $key => $value) {
            $array = [
                'no_quotation' => $this->no_qt_baru,
                'periode' => null,
                'kategori' => json_encode($value['kategori']),
                'total_param' => json_encode($value['total_param'])
            ];

            PraNoSample::insert($array);
            $this->no_sample = $value;
        }
        $this->message = 'Data hasbeen generate non kontrak';
        $this->unset();
    }

    protected function kontrak()
    {
        $get = GenerateSampleKontrak::get(['no_qt_lama' => $this->no_qt_lama, 'no_qt_baru' => $this->no_qt_baru, 'id_order' => $this->id_order]);
        $no_qt = '%' . \explode('R', $this->no_qt_lama)[0] . '%';
        PraNoSample::where('no_quotation', $this->no_qt_baru)->delete();

        foreach ($get as $key => $value) {
            //$value berisi 2 array ['kategori', 'total_param']
            $array = [
                'no_quotation' => $this->no_qt_baru,
                'periode' => $key,
                'kategori' => json_encode($value['kategori']),
                'total_param' => json_encode($value['total_param'])
            ];
            PraNoSample::insert($array);
        }
        $this->no_sample = $get;
        $this->message = 'Data hasbeen generate kontrak';
        $this->unset();
    }

    protected function newkontrak()
    {
        $get = GenerateSampleKontrak::new(['no_qt_baru' => $this->no_qt_baru]);
        $no_qt = '%' . \explode('R', $this->no_qt_baru)[0] . '%';
        PraNoSample::where('no_quotation', $this->no_qt_baru)->delete();

        foreach ($get as $key => $value) {
            //$value berisi 2 array ['kategori', 'total_param']
            $array = [
                'no_quotation' => $this->no_qt_baru,
                'periode' => $key,
                'kategori' => json_encode($value['kategori']),
                'total_param' => json_encode($value['total_param'])
            ];
            PraNoSample::insert($array);
        }
        $this->no_sample = $get;
        $this->message = 'Data hasbeen generate kontrak';
        $this->unset();
    }

    protected function unset()
    {
        unset($this->type);
        unset($this->no_qt_lama);
        unset($this->no_qt_baru);
        unset($this->id_order);
        unset($this->generate);
    }
}
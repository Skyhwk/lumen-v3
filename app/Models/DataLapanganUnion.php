<<<<<<< HEAD
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use App\Models\DeviceIntilab;

class DataLapanganUnion extends Sector
{
    protected $connection = 'mysql';
    public $timestamps = false;
    protected $guarded = [];

    public function getTable()
    {
        $mainDb = \DB::connection('mysql')->getDatabaseName();
        return $mainDb . '.data_lapangan_union';
    }
=======
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use App\Models\DeviceIntilab;

class DataLapanganUnion extends Sector
{
    public static $useLimsDetail = false;

    protected $table = 'data_lapangan_union';
    public $timestamps = false;

    protected $guarded = [];
>>>>>>> 57e96a6a2074bd2c4dc9b9e19f7564320e789c4d
}
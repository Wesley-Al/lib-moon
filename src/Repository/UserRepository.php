<?php

namespace Moontec\Repository;

use Illuminate\Support\Facades\DB;

class UserRepository
{
    function __construct() 
    {}

    public function update($id, $dataUser){
        DB::beginTransaction();
        try {
            DB::table('users')->where('id', "=", $id)->update($dataUser);
            DB::commit();
        }catch (\Exception $e){            
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }
}